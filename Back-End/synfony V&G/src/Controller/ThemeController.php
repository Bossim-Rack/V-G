<?php

namespace App\Controller;

use App\Entity\Theme;
use App\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/theme', name: 'app_api_theme_')]
class ThemeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private SerializerInterface $serializer,
        private ThemeRepository $repository,
    ) {
    }

    #[Route(name: 'index', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/theme",
     *     summary="Lister tous les thèmes",
     *     @OA\Response(
     *         response=200,
     *         description="Liste des thèmes",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="libelle", type="string", example="Anniversaire")
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $themes = $this->repository->findAll();
        $data = array_map(static fn (Theme $theme) => [
            'id' => $theme->getId(),
            'libelle' => $theme->getLibelle(),
        ], $themes);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route(methods: 'POST')]
    /** @OA\Post(
     *     path="/api/theme",
     *     summary="Créer un thème",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données du thème à créer",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="libelle", type="string", example="Anniversaire")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Thème créé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="libelle", type="string", example="Anniversaire")
     *         )
     *     )
     * )
     */
    public function new(Request $request): JsonResponse
    {
        $theme = $this->serializer->deserialize($request->getContent(), Theme::class, 'json');

        $this->manager->persist($theme);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($theme, 'json');

        return new JsonResponse($responseData, Response::HTTP_CREATED, [], true);
    }

    #[Route('/{id}', name: 'edit', methods: 'PUT')]
    /** @OA\Put(
     *     path="/api/theme/{id}",
     *     summary="Modifier un thème par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du thème à modifier",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Nouvelles données du thème à mettre à jour",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="libelle", type="string", example="Mariage")
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Thème modifié avec succès"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Thème non trouvé"
     *     )
     * )
     */
    public function edit(int $id, Request $request): JsonResponse
    {
        $theme = $this->repository->findOneBy(['id' => $id]);
        if ($theme) {
            $theme = $this->serializer->deserialize(
                $request->getContent(),
                Theme::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $theme]
            );

            $this->manager->flush();
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/theme/{id}",
     *     summary="Afficher un thème par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du thème à afficher",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Thème trouvé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="libelle", type="string", example="Anniversaire")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Thème non trouvé"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $theme = $this->repository->findOneBy(['id' => $id]);
        if ($theme) {
            $responseData = $this->serializer->serialize($theme, 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'delete', methods: 'DELETE')]
    /** @OA\Delete(
     *     path="/api/theme/{id}",
     *     summary="Supprimer un thème par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du thème à supprimer",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Thème supprimé avec succès"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Thème non trouvé"
     *     )
     * )
     */
    public function delete(int $id): JsonResponse
    {
        $theme = $this->repository->findOneBy(['id' => $id]);
        if ($theme) {
            $this->manager->remove($theme);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}