<?php

namespace App\Controller;

use App\Entity\Horraires;
use App\Repository\HorrairesRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/horraires', name: 'app_api_horraires_')]
class HorraireController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private SerializerInterface $serializer,
        private HorrairesRepository $repository,
    ) {
    }

    #[Route(name: 'index', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/horraires",
     *     summary="Lister tous les horaires",
     *     @OA\Response(
     *         response=200,
     *         description="Liste des horaires",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="jour", type="string", example="Lundi"),
     *                 @OA\Property(property="heure", type="string", example="09:00 - 18:00")
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $horraires = $this->repository->findAll();
        $responseData = $this->serializer->serialize($horraires, 'json');

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route(methods: 'POST')]
    /** @OA\Post(
     *     path="/api/horraires",
     *     summary="Créer un horaire",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données de l'horaire à créer",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="jour", type="string", example="Lundi"),
     *             @OA\Property(property="heure", type="string", example="09:00 - 18:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Horaire créé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="jour", type="string", example="Lundi"),
     *             @OA\Property(property="heure", type="string", example="09:00 - 18:00")
     *         )
     *     )
     * )
     */
    public function new(Request $request): JsonResponse
    {
        $horraires = $this->serializer->deserialize($request->getContent(), Horraires::class, 'json');

        $this->manager->persist($horraires);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($horraires, 'json');

        return new JsonResponse($responseData, Response::HTTP_CREATED, [], true);
    }

    #[Route('/{id}', name: 'edit', methods: 'PUT')]
    /** @OA\Put(
     *     path="/api/horraires/{id}",
     *     summary="Modifier un horaire par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'horaire à modifier",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Nouvelles données de l'horaire à mettre à jour",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="jour", type="string", example="Samedi"),
     *             @OA\Property(property="heure", type="string", example="09:00 - 13:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Horaire modifié avec succès"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Horaire non trouvé"
     *     )
     * )
     */
    public function edit(int $id, Request $request): JsonResponse
    {
        $horraires = $this->repository->findOneBy(['id' => $id]);
        if ($horraires) {
            $horraires = $this->serializer->deserialize(
                $request->getContent(),
                Horraires::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $horraires]
            );

            $this->manager->flush();
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/horraires/{id}",
     *     summary="Afficher un horaire par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'horaire à afficher",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Horaire trouvé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="jour", type="string", example="Lundi"),
     *             @OA\Property(property="heure", type="string", example="09:00 - 18:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Horaire non trouvé"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $horraires = $this->repository->findOneBy(['id' => $id]);
        if ($horraires) {
            $responseData = $this->serializer->serialize($horraires, 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'delete', methods: 'DELETE')]
    /** @OA\Delete(
     *     path="/api/horraires/{id}",
     *     summary="Supprimer un horaire par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'horaire à supprimer",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Horaire supprimé avec succès"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Horaire non trouvé"
     *     )
     * )
     */
    public function delete(int $id): JsonResponse
    {
        $horraires = $this->repository->findOneBy(['id' => $id]);
        if ($horraires) {
            $this->manager->remove($horraires);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}