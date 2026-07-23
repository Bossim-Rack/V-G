<?php

namespace App\Controller;

use App\Entity\Role;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/role', name: 'app_api_role_')]
class RoleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private RoleRepository $repository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route(methods: 'POST')]
    /** @OA\Post(
     *     path="/api/role",
     *     summary="Créer un rôle",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données du rôle à créer",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="libelle", type="string", example="Admin")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Rôle créé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="libelle", type="string", example="Admin")
     *         )
     *     )
     * )
     */
    public function new(Request $request): JsonResponse
    {
        $role = $this->serializer->deserialize($request->getContent(), Role::class, 'json');

        $this->manager->persist($role);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($role, 'json');
        $location = $this->urlGenerator->generate(
            'app_api_role_show',
            ['id' => $role->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new JsonResponse($responseData, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /** @OA\Get(
     *     path="/api/role/{id}",
     *     summary="Afficher un rôle par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du rôle à afficher",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rôle trouvé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="libelle", type="string", example="Admin")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Rôle non trouvé"
     *     )
     * )
     */
    #[Route('/{id}', name: 'show', methods: 'GET')]
    public function show(int $id): JsonResponse
    {
        $role = $this->repository->findOneBy(['id' => $id]);
        if ($role) {
            $responseData = $this->serializer->serialize($role, 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    /** @OA\Put(
     *     path="/api/role/{id}",
     *     summary="Modifier un rôle par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du rôle à modifier",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Nouvelles données du rôle à mettre à jour",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="libelle", type="string", example="Employé")
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Rôle modifié avec succès"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Rôle non trouvé"
     *     )
     * )
     */
    #[Route('/{id}', name: 'edit', methods: 'PUT')]
    public function edit(int $id, Request $request): JsonResponse
    {
        $role = $this->repository->findOneBy(['id' => $id]);
        if ($role) {
            $role = $this->serializer->deserialize(
                $request->getContent(),
                Role::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $role]
            );

            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    /** @OA\Delete(
     *     path="/api/role/{id}",
     *     summary="Supprimer un rôle par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du rôle à supprimer",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Rôle supprimé avec succès"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Rôle non trouvé"
     *     )
     * )
     */
    #[Route('/{id}', name: 'delete', methods: 'DELETE')]
    public function delete(int $id): JsonResponse
    {
        $role = $this->repository->findOneBy(['id' => $id]);
        if ($role) {
            $this->manager->remove($role);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}