<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\User;
use App\Repository\AvisRepository;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/avis', name: 'app_api_avis_')]
class AvisController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private AvisRepository $repository,
        private CommandeRepository $commandeRepository,
        private ValidatorInterface $validator,
    ) {
    }

    private function estStaff(): bool
    {
        return $this->isGranted('ROLE_EMPLOYE');
    }

    /** transforme l'avis en tableau, on renvoie juste le nom de l'auteur (pas de mdp ni token) */
    private function avisToArray(Avis $avis): array
    {
        $commande = $avis->getCommande();

        return [
            'id' => $avis->getId(),
            'note' => $avis->getNote(),
            'description' => $avis->getDescription(),
            'statut' => $avis->getStatut(),
            'user' => $avis->getUser() ? [
                'id' => $avis->getUser()->getId(),
                'firstName' => $avis->getUser()->getFirstName(),
                'lastName' => $avis->getUser()->getLastName(),
            ] : null,
            'commande' => $commande ? [
                'id' => $commande->getId(),
                'menu' => $commande->getMenu()?->getTitleMenu(),
                'datePrestation' => $commande->getDatePrestation()?->format('Y-m-d'),
            ] : null,
        ];
    }

    #[Route(name: 'index', methods: 'GET')]
    /** @OA\Get(path="/api/avis", summary="Tous les avis, à modérer en premier (employé/admin)", @OA\Response(response=200, description="Avis")) */
    public function index(): JsonResponse
    {
        if (!$this->estStaff()) {
            return new JsonResponse(
                ['message' => 'Seul un employé ou un administrateur peut consulter tous les avis.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Les avis en attente de modération sont remontés en premier.
        $avis = $this->repository->findBy([], ['statut' => 'ASC', 'id' => 'DESC']);

        return new JsonResponse(array_map(fn (Avis $a) => $this->avisToArray($a), $avis), Response::HTTP_OK);
    }

    #[Route('/publies', name: 'publiees', methods: 'GET')]
    /** @OA\Get(path="/api/avis/publies", summary="Avis validés, affichés sur la page d'accueil (public)", @OA\Response(response=200, description="Avis publiés")) */
    public function publiees(): JsonResponse
    {
        $avis = $this->repository->findBy(['statut' => Avis::STATUT_PUBLIE], ['id' => 'DESC']);

        // Route publique : juste le prénom de l'auteur.
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
     *     summary="Donner son avis sur une de ses commandes terminées (note de 1 à 5 + commentaire)",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="commandeId", type="integer", example=12),
     *             @OA\Property(property="note", type="integer", example=5),
     *             @OA\Property(property="description", type="string", example="Un repas parfait !")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Avis enregistré, en attente de validation"),
     *     @OA\Response(response=400, description="Données invalides"),
     *     @OA\Response(response=409, description="Commande non terminée ou avis déjà donné")
     * )
     */
    public function new(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];
        /** @var User $user */
        $user = $this->getUser();

        $commande = !empty($data['commandeId']) ? $this->commandeRepository->find((int) $data['commandeId']) : null;
        if (!$commande || $commande->getUser() !== $user) {
            return new JsonResponse(['message' => 'Choisissez une de vos commandes pour donner votre avis.'], Response::HTTP_BAD_REQUEST);
        }
        if ($commande->getStatut() !== Commande::STATUT_TERMINEE) {
            return new JsonResponse(['message' => 'Vous pourrez donner votre avis une fois la commande terminée.'], Response::HTTP_CONFLICT);
        }
        if ($this->repository->findOneBy(['commande' => $commande])) {
            return new JsonResponse(['message' => 'Vous avez déjà donné votre avis sur cette commande.'], Response::HTTP_CONFLICT);
        }

        $note = filter_var($data['note'] ?? null, FILTER_VALIDATE_INT);
        $description = trim((string) ($data['description'] ?? ''));
        if ($note === false || $note < 1 || $note > 5) {
            return new JsonResponse(['message' => 'La note doit être comprise entre 1 et 5.'], Response::HTTP_BAD_REQUEST);
        }
        if ($description === '' || mb_strlen($description) > 255) {
            return new JsonResponse(['message' => 'Le commentaire est obligatoire (255 caractères maximum).'], Response::HTTP_BAD_REQUEST);
        }

        // Statut et auteur ne dépendent jamais de ce que le client envoie.
        $avis = (new Avis())
            ->setNote($note)
            ->setDescription($description)
            ->setStatut(Avis::STATUT_ATTENTE)
            ->setUser($user)
            ->setCommande($commande);

        if (count($erreurs = $this->validator->validate($avis)) > 0) {
            return new JsonResponse(['message' => $erreurs[0]->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $this->manager->persist($avis);
        $this->manager->flush();

        return new JsonResponse($this->avisToArray($avis), Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'mine', methods: 'GET')]
    /** @OA\Get(path="/api/avis/me", summary="Mes avis", @OA\Response(response=200, description="Mes avis")) */
    public function mine(): JsonResponse
    {
        $avis = $this->repository->findBy(['user' => $this->getUser()], ['id' => 'DESC']);

        return new JsonResponse(array_map(fn (Avis $a) => $this->avisToArray($a), $avis), Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'show', methods: 'GET', requirements: ['id' => '\d+'])]
    /** @OA\Get(path="/api/avis/{id}", summary="Détail d'un avis (auteur ou employé)", @OA\Response(response=200, description="Avis")) */
    public function show(int $id): JsonResponse
    {
        $avis = $this->repository->find($id);
        if (!$avis || (!$this->estStaff() && $avis->getUser() !== $this->getUser())) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->avisToArray($avis), Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'edit', methods: 'PUT', requirements: ['id' => '\d+'])]
    /** @OA\Put(
     *     path="/api/avis/{id}",
     *     summary="Valider (Publié) ou refuser (Refusé) un avis (employé/admin)",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object", @OA\Property(property="statut", type="string", enum={"En attente", "Publié", "Refusé"}))),
     *     @OA\Response(response=204, description="Statut modifié"),
     *     @OA\Response(response=400, description="Statut invalide"),
     *     @OA\Response(response=403, description="Réservé aux employés/administrateurs")
     * )
     */
    public function edit(int $id, Request $request): JsonResponse
    {
        if (!$this->estStaff()) {
            return new JsonResponse(
                ['message' => "Seul un employé ou un administrateur peut modifier le statut d'un avis."],
                Response::HTTP_FORBIDDEN
            );
        }

        $avis = $this->repository->find($id);
        if (!$avis) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $statut = is_array($data) ? ($data['statut'] ?? null) : null;
        if (!in_array($statut, Avis::STATUTS, true)) {
            return new JsonResponse(
                ['message' => 'Statut invalide. Valeurs acceptées : ' . implode(', ', Avis::STATUTS)],
                Response::HTTP_BAD_REQUEST
            );
        }

        $avis->setStatut($statut);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'delete', methods: 'DELETE', requirements: ['id' => '\d+'])]
    /** @OA\Delete(path="/api/avis/{id}", summary="Supprimer un avis (son auteur ou un employé)", @OA\Response(response=204, description="Avis supprimé")) */
    public function delete(int $id): JsonResponse
    {
        $avis = $this->repository->find($id);
        if (!$avis || (!$this->estStaff() && $avis->getUser() !== $this->getUser())) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $this->manager->remove($avis);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
