<?php

namespace App\Controller;

use App\Entity\Allergene;
use App\Repository\AllergeneRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/allergene', name: 'app_api_allergene_')]
class AllergeneController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private SerializerInterface $serializer,
        private AllergeneRepository $repository,
    ) {
    }

    #[Route(name: 'index', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/allergene",
     *     summary="Lister tous les allergènes",
     *     @OA\Response(
     *         response=200,
     *         description="Liste des allergènes",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="libelle", type="string", example="Gluten")
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $allergenes = $this->repository->findAll();
        $data = array_map(static fn (Allergene $allergene) => [
            'id' => $allergene->getId(),
            'libelle' => $allergene->getLibelle(),
        ], $allergenes);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route(methods: 'POST')]
    /** @OA\Post(
     *     path="/api/allergene",
     *     summary="Créer un allergène",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données de l'allergène à créer",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="libelle", type="string", example="Gluten")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Allergène créé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="libelle", type="string", example="Gluten")
     *         )
     *     )
     * )
     */
    public function new(Request $request): JsonResponse
    {
        $allergene = $this->serializer->deserialize($request->getContent(), Allergene::class, 'json');

        $this->manager->persist($allergene);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($allergene, 'json');

        return new JsonResponse($responseData, Response::HTTP_CREATED, [], true);
    }

    #[Route('/{id}', name: 'edit', methods: 'PUT')]
    /** @OA\Put(
     *     path="/api/allergene/{id}",
     *     summary="Modifier un allergène par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'allergène à modifier",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Nouvelles données de l'allergène à mettre à jour",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="libelle", type="string", example="Fruits à coque")
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Allergène modifié avec succès"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Allergène non trouvé"
     *     )
     * )
     */
    public function edit(int $id, Request $request): JsonResponse
    {
        $allergene = $this->repository->findOneBy(['id' => $id]);
        if ($allergene) {
            $allergene = $this->serializer->deserialize(
                $request->getContent(),
                Allergene::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $allergene]
            );

            $this->manager->flush();
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/allergene/{id}",
     *     summary="Afficher un allergène par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'allergène à afficher",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Allergène trouvé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="libelle", type="string", example="Gluten")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Allergène non trouvé"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $allergene = $this->repository->findOneBy(['id' => $id]);
        if ($allergene) {
            $responseData = $this->serializer->serialize($allergene, 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'delete', methods: 'DELETE')]
    /** @OA\Delete(
     *     path="/api/allergene/{id}",
     *     summary="Supprimer un allergène par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'allergène à supprimer",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Allergène supprimé avec succès"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Allergène non trouvé"
     *     )
     * )
     */
    public function delete(int $id): JsonResponse
    {
        $allergene = $this->repository->findOneBy(['id' => $id]);
        if ($allergene) {
            $this->manager->remove($allergene);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}