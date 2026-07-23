<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Repository\AvisRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/avis', name: 'app_api_avis_')]
class AvisController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private AvisRepository $repository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route(name: 'index', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/avis",
     *     summary="Lister tous les avis (modération, réservé admin/employé)",
     *     @OA\Response(
     *         response=200,
     *         description="Liste de tous les avis",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="note", type="integer", example=4),
     *                 @OA\Property(property="description", type="string", example="Super prestation !"),
     *                 @OA\Property(property="statut", type="string", enum={"En attente", "Publié", "Refusé"}, example="En attente")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé : réservé aux employés/administrateurs"
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_EMPLOYE')) {
            return new JsonResponse(
                ['message' => 'Seul un employé ou un administrateur peut consulter tous les avis.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Les avis en attente de modération sont remontés en premier.
        $avis = $this->repository->findBy([], ['statut' => 'ASC', 'id' => 'DESC']);
        $responseData = $this->serializer->serialize($avis, 'json');

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route('/publies', name: 'publiees', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/avis/publies",
     *     summary="Lister les avis publiés (public, pour la page d'accueil)",
     *     @OA\Response(
     *         response=200,
     *         description="Liste des avis validés",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="note", type="integer", example=5),
     *                 @OA\Property(property="description", type="string", example="Super prestation !"),
     *                 @OA\Property(property="prenom", type="string", example="Camille")
     *             )
     *         )
     *     )
     * )
     */
    public function publiees(): JsonResponse
    {
        $avis = $this->repository->findBy(['statut' => Avis::STATUT_PUBLIE], ['id' => 'DESC']);

        // Route publique : on ne renvoie surtout pas l'entité User complète
        // (mot de passe hashé, apiToken...), juste le prénom de l'auteur.
        $data = array_map(static fn (Avis $a) => [
            'id' => $a->getId(),
            'note' => $a->getNote(),
            'description' => $a->getDescription(),
            'prenom' => $a->getUser()?->getFirstName(),
        ], $avis);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route(methods: 'POST')]
    /** @OA\Post(
     *     path="/api/avis",
     *     summary="Créer un Avis",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données de l'avis à créer",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="note", type="integer", example=4),
     *             @OA\Property(property="description", type="string", example="Un excellent repas, service impeccable.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Succès création",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="note", type="integer", example=4),
     *             @OA\Property(property="description", type="string", example="Un excellent repas, service impeccable."),
     *             @OA\Property(property="statut", type="string", enum={"En attente", "Publié", "Refusé"}, example="En attente")
     *         )
     *     )
     * )
     */
    public function new(Request $request): JsonResponse
    {
        $avis = $this->serializer->deserialize($request->getContent(), Avis::class, 'json');

        // Le statut (avis validé ou non) et l'auteur ne doivent jamais dépendre
        // de ce que le client envoie : un avis est toujours "En attente" à la
        // création, et l'auteur est l'utilisateur authentifié via son token,
        // jamais un user_id fourni dans la requête.
        $avis->setStatut(Avis::STATUT_ATTENTE);
        $avis->setUser($this->getUser());

        $this->manager->persist($avis);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($avis, 'json');
        $location = $this->urlGenerator->generate(
            'app_api_avis_show',
            ['id' => $avis->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new JsonResponse($responseData, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[Route('/me', name: 'mine', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/avis/me",
     *     summary="Lister mes avis (utilisateur connecté)",
     *     @OA\Response(
     *         response=200,
     *         description="Liste de mes avis",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="note", type="integer", example=5),
     *                 @OA\Property(property="description", type="string", example="Un excellent repas, service impeccable."),
     *                 @OA\Property(property="statut", type="string", enum={"En attente", "Publié", "Refusé"}, example="En attente")
     *             )
     *         )
     *     )
     * )
     */
    public function mine(): JsonResponse
    {
        $avis = $this->repository->findBy(['user' => $this->getUser()], ['id' => 'DESC']);
        $responseData = $this->serializer->serialize($avis, 'json');

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'show', methods: 'GET', requirements: ['id' => '\d+'])]
        /** @OA\Get(
     *     path="/api/avis/{id}",
     *     summary="Afficher un Avis par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'avis a afficher",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rôle trouvé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="note", type="integer", example=5),
     *             @OA\Property(property="description", type="string", example= "donnez votre avis"),
     *             @OA\Property(property="statut", type="string", enum={"En attente", "Publié", "Refusé"}, example="En attente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Avis non trouvé"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $avis = $this->repository->findOneBy(['id' => $id]);
        if ($avis) {
            $responseData = $this->serializer->serialize($avis, 'json');
            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'edit', methods: 'PUT', requirements: ['id' => '\d+'])]
        /** @OA\Put(
     *     path="/api/avis/{id}",
     *     summary="Modifier un avis par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'avis à modifier",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Nouvelles données de l'id à mettre à jour",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="note", type="integer", example="1"),
     *             @OA\Property(property="description", type="string", example="Donnez votre avis"),
     *             @OA\Property(property="statut", type="string", enum={"En attente", "Publié", "Refusé"}, example="Publié")
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
    public function edit(int $id, Request $request): JsonResponse
    {
        $avis = $this->repository->findOneBy(['id' => $id]);
        if ($avis) {
            $data = json_decode($request->getContent(), true) ?? [];

            if (array_key_exists('statut', $data)) {
                // Seuls les admins et employés peuvent valider/refuser un avis.
                if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_EMPLOYE')) {
                    return new JsonResponse(
                        ['message' => "Seul un employé ou un administrateur peut modifier le statut d'un avis."],
                        Response::HTTP_FORBIDDEN
                    );
                }

                if (!in_array($data['statut'], Avis::STATUTS, true)) {
                    return new JsonResponse(
                        ['message' => 'Statut invalide. Valeurs acceptées : ' . implode(', ', Avis::STATUTS)],
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }

            $avis = $this->serializer->deserialize(
                $request->getContent(),
                Avis::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $avis]
            );

            $this->manager->flush();
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'delete', methods: 'DELETE', requirements: ['id' => '\d+'])]
    /** @OA\Delete(
     *     path="/api/avis/{id}",
     *     summary="Supprimer un avis par ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'avis à supprimer",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Avis supprimé avec succès"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Avis non trouvé"
     *     )
     * )
     */
    public function delete(int $id): JsonResponse
    {
        $avis = $this->repository->findOneBy(['id' => $id]);
        if ($avis) {
            $this->manager->remove($avis);
            $this->manager->flush();
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}