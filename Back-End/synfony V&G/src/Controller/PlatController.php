<?php

namespace App\Controller;

use App\Entity\Plat;
use App\Repository\AllergeneRepository;
use App\Repository\PlatRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/plat', name: 'app_api_plat_')]
class PlatController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private SerializerInterface $serializer,
        private PlatRepository $repository,
        private AllergeneRepository $allergeneRepository,
    ) {
    }

    /**
     * Construit une représentation "plate" et sûre du plat (évite les
     * références circulaires Plat <-> Allergene / Menu que provoquerait
     * un $serializer->serialize($plat) direct).
     * Le champ photoPlat est stocké et renvoyé tel quel sous forme de data URI
     * base64 (ex: "data:image/png;base64,....."), ce qui permet de l'utiliser
     * directement comme src d'une balise <img> côté front, sans étape de
     * décodage/upload séparée.
     */
    private function platToArray(Plat $plat): array
    {
        $photo = $plat->getPhotoPlat();
        if (is_resource($photo)) {
            $photo = stream_get_contents($photo);
        }
        // On n'accepte que le format qu'on écrit nous-mêmes (data URI base64,
        // donc toujours de l'UTF-8 valide). D'éventuelles données binaires
        // brutes stockées avant cette évolution (ou insérées à la main dans
        // la DB) feraient planter json_encode/JsonResponse, donc on les
        // ignore proprement plutôt que de renvoyer une 500.
        if (!is_string($photo) || !str_starts_with($photo, 'data:') || !mb_check_encoding($photo, 'UTF-8')) {
            $photo = null;
        }

        return [
            'id' => $plat->getId(),
            'titlePlat' => $plat->getTitlePlat(),
            'description' => $plat->getDescription(),
            'photoPlat' => $photo,
            'allergenes' => array_values(array_map(static fn ($allergene) => [
                'id' => $allergene->getId(),
                'libelle' => $allergene->getLibelle(),
            ], $plat->getAllergenes()->toArray())),
        ];
    }

    #[Route(name: 'index', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/plat",
     *     summary="Lister tous les plats",
     *     @OA\Response(response=200, description="Liste des plats")
     * )
     */
    public function index(): JsonResponse
    {
        $plats = $this->repository->findAll();
        $data = array_map(fn (Plat $plat) => $this->platToArray($plat), $plats);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route(methods: 'POST')]
    /** @OA\Post(
     *     path="/api/plat",
     *     summary="Créer un plat",
     *     description="photoPlat est optionnel, envoyé/renvoyé en data URI base64 (ex: data:image/png;base64,...). allergenes est un tableau d'IDs d'allergènes existants.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="titlePlat", type="string", example="Plateau de fruits de mer"),
     *             @OA\Property(property="description", type="string", example="Plateau généreux de fruits de mer frais."),
     *             @OA\Property(property="photoPlat", type="string", example="data:image/png;base64,iVBORw0KGgo..."),
     *             @OA\Property(property="allergenes", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Plat créé avec succès")
     * )
     */
    public function new(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $plat = new Plat();
        $plat->setTitlePlat($data['titlePlat'] ?? '');
        $plat->setDescription($data['description'] ?? null);

        if (!empty($data['photoPlat'])) {
            $plat->setPhotoPlat($data['photoPlat']);
        }

        if (!empty($data['allergenes']) && is_array($data['allergenes'])) {
            foreach ($this->allergeneRepository->findBy(['id' => $data['allergenes']]) as $allergene) {
                $plat->addAllergene($allergene);
            }
        }

        $this->manager->persist($plat);
        $this->manager->flush();

        return new JsonResponse($this->platToArray($plat), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'edit', methods: 'PUT', requirements: ['id' => '\d+'])]
    /** @OA\Put(
     *     path="/api/plat/{id}",
     *     summary="Modifier un plat par ID",
     *     description="Tous les champs sont optionnels. Si photoPlat est omis ou vide, la photo actuelle est conservée. Si allergenes est fourni, il remplace entièrement la liste des allergènes du plat.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Plat modifié avec succès"),
     *     @OA\Response(response=404, description="Plat non trouvé")
     * )
     */
    public function edit(int $id, Request $request): JsonResponse
    {
        $plat = $this->repository->findOneBy(['id' => $id]);
        if (!$plat) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('titlePlat', $data)) {
            $plat->setTitlePlat($data['titlePlat']);
        }
        if (array_key_exists('description', $data)) {
            $plat->setDescription($data['description']);
        }
        if (!empty($data['photoPlat'])) {
            $plat->setPhotoPlat($data['photoPlat']);
        }
        if (array_key_exists('allergenes', $data) && is_array($data['allergenes'])) {
            foreach ($plat->getAllergenes()->toArray() as $allergeneActuel) {
                $plat->removeAllergene($allergeneActuel);
            }
            foreach ($this->allergeneRepository->findBy(['id' => $data['allergenes']]) as $allergene) {
                $plat->addAllergene($allergene);
            }
        }

        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'show', methods: 'GET', requirements: ['id' => '\d+'])]
    /** @OA\Get(
     *     path="/api/plat/{id}",
     *     summary="Afficher un plat par ID",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Plat trouvé avec succès"),
     *     @OA\Response(response=404, description="Plat non trouvé")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $plat = $this->repository->findOneBy(['id' => $id]);
        if ($plat) {
            return new JsonResponse($this->platToArray($plat), Response::HTTP_OK);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'delete', methods: 'DELETE', requirements: ['id' => '\d+'])]
    /** @OA\Delete(
     *     path="/api/plat/{id}",
     *     summary="Supprimer un plat par ID",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Plat supprimé avec succès"),
     *     @OA\Response(response=404, description="Plat non trouvé")
     * )
     */
    public function delete(int $id): JsonResponse
    {
        $plat = $this->repository->findOneBy(['id' => $id]);
        if ($plat) {
            $this->manager->remove($plat);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
