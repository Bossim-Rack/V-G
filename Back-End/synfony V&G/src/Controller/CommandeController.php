<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Repository\MenuRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/commande', name: 'app_api_commande_')]
class CommandeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private SerializerInterface $serializer,
        private CommandeRepository $repository,
        private MenuRepository $menuRepository,
    ) {
    }

    /**
     * Représentation "plate" et sûre de la commande (évite de renvoyer
     * l'objet User complet -- password/apiToken -- ou de déclencher les
     * références circulaires Menu <-> Plat/Theme qu'un
     * $serializer->serialize() direct provoquerait).
     */
    private function commandeToArray(Commande $commande): array
    {
        return [
            'id' => $commande->getId(),
            'dateCommande' => $commande->getDateCommande()?->format('Y-m-d'),
            'datePrestation' => $commande->getDatePrestation()?->format('Y-m-d'),
            'heureLivraison' => $commande->getHeureLivraison(),
            'adresse' => $commande->getAdresse(),
            'prixMenu' => $commande->getPrixMenu(),
            'nombrePersonne' => $commande->getNombrePersonne(),
            'prixLivraison' => $commande->getPrixLivraison(),
            'statut' => $commande->getStatut(),
            'pretMateriel' => $commande->isPretMateriel(),
            'restitutionMateriel' => $commande->isRestitutionMateriel(),
            'menu' => $commande->getMenu() ? [
                'id' => $commande->getMenu()->getId(),
                'titleMenu' => $commande->getMenu()->getTitleMenu(),
            ] : null,
            'user' => $commande->getUser() ? [
                'id' => $commande->getUser()->getId(),
                'firstName' => $commande->getUser()->getFirstName(),
                'lastName' => $commande->getUser()->getLastName(),
                'email' => $commande->getUser()->getEmail(),
            ] : null,
        ];
    }

    #[Route(name: 'index', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/commande",
     *     summary="Lister toutes les commandes (réservé admin/employé, pour le tableau de bord)",
     *     @OA\Response(response=200, description="Liste des commandes, les plus récentes en premier"),
     *     @OA\Response(response=403, description="Accès refusé : réservé aux employés/administrateurs")
     * )
     */
    public function index(): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_EMPLOYE')) {
            return new JsonResponse(
                ['message' => 'Seul un employé ou un administrateur peut consulter toutes les commandes.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $commandes = $this->repository->findBy([], ['id' => 'DESC']);
        $data = array_map(fn (Commande $commande) => $this->commandeToArray($commande), $commandes);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/me', name: 'mine', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/commande/me",
     *     summary="Lister mes commandes (utilisateur connecté)",
     *     @OA\Response(response=200, description="Liste de mes commandes, les plus récentes en premier")
     * )
     */
    public function mine(): JsonResponse
    {
        $commandes = $this->repository->findBy(['user' => $this->getUser()], ['id' => 'DESC']);
        $data = array_map(fn (Commande $commande) => $this->commandeToArray($commande), $commandes);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route(methods: 'POST')]
    /** @OA\Post(
     *     path="/api/commande",
     *     summary="Passer une commande pour un menu",
     *     description="user est déduit automatiquement du compte connecté (X-AUTH-TOKEN), pas envoyé par le client. prixMenu est repris automatiquement du prix actuel du menu (snapshot au moment de la commande), avec une réduction de 10% si nombrePersonne >= (minimum du menu + 5). adresse est obligatoire (pré-remplie côté front depuis le profil si elle y est renseignée, sinon à saisir).",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"menuId", "adresse"},
     *             @OA\Property(property="menuId", type="integer", example=1),
     *             @OA\Property(property="datePrestation", type="string", format="date", example="2026-08-15"),
     *             @OA\Property(property="heureLivraison", type="string", example="19:30"),
     *             @OA\Property(property="adresse", type="string", example="12 rue des Fauvettes, 33000 Bordeaux"),
     *             @OA\Property(property="nombrePersonne", type="integer", example=6),
     *             @OA\Property(property="prixLivraison", type="number", format="float", example=15.00)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Commande créée avec succès"),
     *     @OA\Response(response=400, description="Données invalides (menuId manquant, nombre de personnes insuffisant, date invalide...)"),
     *     @OA\Response(response=404, description="Menu introuvable")
     * )
     */
    public function new(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['menuId'])) {
            return new JsonResponse(['message' => 'menuId est obligatoire.'], Response::HTTP_BAD_REQUEST);
        }

        // Toujours obligatoire, qu'elle vienne du pré-remplissage (profil) ou d'une saisie
        // manuelle côté front : une commande a besoin d'une adresse de livraison.
        if (empty(trim((string) ($data['adresse'] ?? '')))) {
            return new JsonResponse(['message' => "L'adresse de livraison est obligatoire."], Response::HTTP_BAD_REQUEST);
        }

        $menu = $this->menuRepository->findOneBy(['id' => $data['menuId']]);
        if (!$menu) {
            return new JsonResponse(['message' => 'Menu introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $nombrePersonne = isset($data['nombrePersonne']) ? (int) $data['nombrePersonne'] : $menu->getNombrePersonneMinimum();
        if ($nombrePersonne < $menu->getNombrePersonneMinimum()) {
            return new JsonResponse(
                ['message' => 'Le nombre de personnes minimum pour ce menu est de ' . $menu->getNombrePersonneMinimum() . '.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $datePrestation = null;
        if (!empty($data['datePrestation'])) {
            try {
                $datePrestation = new DateTimeImmutable($data['datePrestation']);
            } catch (\Exception) {
                return new JsonResponse(['message' => 'datePrestation invalide.'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Réduction de 10% sur le prix/personne si le nombre de personnes dépasse
        // le minimum du menu d'au moins 5 (grosse commande = tarif dégressif).
        $prixParPersonne = (float) $menu->getPrixParPersonne();
        if ($nombrePersonne >= $menu->getNombrePersonneMinimum() + 5) {
            $prixParPersonne *= 0.9;
        }

        $commande = new Commande();
        $commande->setMenu($menu);
        $commande->setUser($this->getUser());
        $commande->setDateCommande(new DateTimeImmutable());
        $commande->setDatePrestation($datePrestation);
        $commande->setHeureLivraison($data['heureLivraison'] ?? null);
        $commande->setAdresse(trim($data['adresse']));
        $commande->setNombrePersonne($nombrePersonne);
        $commande->setPrixMenu(number_format($prixParPersonne, 2, '.', ''));
        $commande->setPrixLivraison(isset($data['prixLivraison']) ? (string) $data['prixLivraison'] : null);
        $commande->setStatut('En attente');

        $this->manager->persist($commande);
        $this->manager->flush();

        return new JsonResponse($this->commandeToArray($commande), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'edit', methods: 'PUT', requirements: ['id' => '\d+'])]
    /** @OA\Put(
     *     path="/api/commande/{id}",
     *     summary="Modifier une commande par ID",
     *     description="Tous les champs sont optionnels. menu et user ne sont pas modifiables ici. Changer le statut est réservé aux admins/employés.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Commande modifiée avec succès"),
     *     @OA\Response(response=403, description="Accès refusé : changement de statut réservé aux employés/administrateurs"),
     *     @OA\Response(response=404, description="Commande non trouvée")
     * )
     */
    public function edit(int $id, Request $request): JsonResponse
    {
        $commande = $this->repository->findOneBy(['id' => $id]);
        if (!$commande) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('datePrestation', $data)) {
            try {
                $commande->setDatePrestation($data['datePrestation'] ? new DateTimeImmutable($data['datePrestation']) : null);
            } catch (\Exception) {
                return new JsonResponse(['message' => 'datePrestation invalide.'], Response::HTTP_BAD_REQUEST);
            }
        }
        if (array_key_exists('heureLivraison', $data)) {
            $commande->setHeureLivraison($data['heureLivraison']);
        }
        if (array_key_exists('adresse', $data)) {
            if (empty(trim((string) $data['adresse']))) {
                return new JsonResponse(['message' => "L'adresse de livraison ne peut pas être vide."], Response::HTTP_BAD_REQUEST);
            }
            $commande->setAdresse(trim($data['adresse']));
        }
        if (array_key_exists('nombrePersonne', $data)) {
            $commande->setNombrePersonne($data['nombrePersonne'] !== null ? (int) $data['nombrePersonne'] : null);
        }
        if (array_key_exists('prixLivraison', $data)) {
            $commande->setPrixLivraison($data['prixLivraison'] !== null ? (string) $data['prixLivraison'] : null);
        }
        if (array_key_exists('statut', $data)) {
            // Seuls les admins et employés peuvent changer le statut d'une commande
            // (le client passe commande, mais ne décide pas si elle est confirmée/annulée).
            if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_EMPLOYE')) {
                return new JsonResponse(
                    ['message' => "Seul un employé ou un administrateur peut modifier le statut d'une commande."],
                    Response::HTTP_FORBIDDEN
                );
            }
            $commande->setStatut($data['statut']);
        }
        if (array_key_exists('pretMateriel', $data)) {
            $commande->setPretMateriel($data['pretMateriel']);
        }
        if (array_key_exists('restitutionMateriel', $data)) {
            $commande->setRestitutionMateriel($data['restitutionMateriel']);
        }

        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'show', methods: 'GET', requirements: ['id' => '\d+'])]
    /** @OA\Get(
     *     path="/api/commande/{id}",
     *     summary="Afficher une commande par ID",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Commande trouvée avec succès"),
     *     @OA\Response(response=404, description="Commande non trouvée")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $commande = $this->repository->findOneBy(['id' => $id]);
        if ($commande) {
            return new JsonResponse($this->commandeToArray($commande), Response::HTTP_OK);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'delete', methods: 'DELETE', requirements: ['id' => '\d+'])]
    /** @OA\Delete(
     *     path="/api/commande/{id}",
     *     summary="Supprimer une commande par ID",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Commande supprimée avec succès"),
     *     @OA\Response(response=404, description="Commande non trouvée")
     * )
     */
    public function delete(int $id): JsonResponse
    {
        $commande = $this->repository->findOneBy(['id' => $id]);
        if ($commande) {
            $this->manager->remove($commande);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
