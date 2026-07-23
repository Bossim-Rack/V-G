<?php

namespace App\Controller;

use App\Entity\Regime;
use App\Repository\RegimeRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/regime', name: 'app_api_regime_')]
class RegimeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private SerializerInterface $serializer,
        private RegimeRepository $repository,
    ) {
    }

    /**
     * Représentation "plate" et sûre du régime : depuis l'ajout de la
     * relation ManyToMany Menu <-> Regime, sérialiser l'entité directement
     * ($serializer->serialize()) risquerait une référence circulaire
     * (Regime -> menus -> regimesAlimentaires -> ...). On ne renvoie donc
     * jamais les menus liés ici, le front n'en a pas besoin.
     */
    private function regimeToArray(Regime $regime): array
    {
        return [
            'id' => $regime->getId(),
            'libelle' => $regime->getLibelle(),
        ];
    }

    #[Route(name: 'index', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/regime",
     *     summary="Lister tous les régimes alimentaires",
     *     @OA\Response(
     *         response=200,
     *         description="Liste des régimes",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="libelle", type="string", example="Végétarien")
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $regimes = $this->repository->findAll();
        $data = array_map(fn (Regime $regime) => $this->regimeToArray($regime), $regimes);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route(methods: 'POST')]
    /** @OA\Post(
     *     path="/api/regime",
     *     summary="Créer un régime alimentaire",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données du régime à créer",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="libelle", type="string", example="Végétarien")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Régime créé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="libelle", type="string", example="Végétarien")
     *         )
     *     )
     * )
     */
    public function new(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $regime = new Regime();
        $regime->setLibelle($data['libelle'] ?? '');

        $this->manager->persist($regime);
        $this->manager->flush();

        return new JsonResponse($this->regimeToArray($regime), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'edit', methods: 'PUT')]
    /** @OA\Put(
     *     path="/api/regime/{id}",
     *     summary="Modifier un régime alimentaire par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du régime à modifier",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Nouvelles données du régime à mettre à jour",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="libelle", type="string", example="Sans gluten")
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Régime modifié avec succès"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Régime non trouvé"
     *     )
     * )
     */
    public function edit(int $id, Request $request): JsonResponse
    {
        $regime = $this->repository->findOneBy(['id' => $id]);
        if (!$regime) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (array_key_exists('libelle', $data)) {
            $regime->setLibelle($data['libelle']);
        }

        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/regime/{id}",
     *     summary="Afficher un régime alimentaire par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du régime à afficher",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Régime trouvé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="libelle", type="string", example="Végétarien")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Régime non trouvé"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $regime = $this->repository->findOneBy(['id' => $id]);
        if ($regime) {
            return new JsonResponse($this->regimeToArray($regime), Response::HTTP_OK);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'delete', methods: 'DELETE')]
    /** @OA\Delete(
     *     path="/api/regime/{id}",
     *     summary="Supprimer un régime alimentaire par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du régime à supprimer",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Régime supprimé avec succès"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Régime non trouvé"
     *     )
     * )
     */
    public function delete(int $id): JsonResponse
    {
        $regime = $this->repository->findOneBy(['id' => $id]);
        if ($regime) {
            $this->manager->remove($regime);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
