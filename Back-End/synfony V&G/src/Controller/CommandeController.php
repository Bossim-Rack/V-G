<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\CommandeHistorique;
use App\Entity\User;
use App\Repository\AvisRepository;
use App\Repository\CommandeRepository;
use App\Repository\MenuRepository;
use App\Service\AdresseIntrouvableException;
use App\Service\MailerService;
use App\Service\StatsMongoService;
use App\Service\TarifCommandeService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/commande', name: 'app_api_commande_')]
class CommandeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private CommandeRepository $repository,
        private MenuRepository $menuRepository,
        private AvisRepository $avisRepository,
        private TarifCommandeService $tarif,
        private MailerService $mailerService,
        private StatsMongoService $statsMongo,
    ) {
    }

    private function estStaff(): bool
    {
        return $this->isGranted('ROLE_EMPLOYE');
    }

    private function commandeToArray(Commande $commande, bool $avecHistorique = true): array
    {
        $user = $commande->getUser();
        $avis = $this->avisRepository->findOneBy(['commande' => $commande]);

        $data = [
            'id' => $commande->getId(),
            'dateCommande' => $commande->getDateCommande()?->format('Y-m-d'),
            'datePrestation' => $commande->getDatePrestation()?->format('Y-m-d'),
            'heureLivraison' => $commande->getHeureLivraison(),
            'adresse' => $commande->getAdresse(),
            'prixMenu' => $commande->getPrixMenu(),
            'nombrePersonne' => $commande->getNombrePersonne(),
            'prixLivraison' => $commande->getPrixLivraison(),
            'montantTotal' => $commande->getMontantTotal(),
            'statut' => $commande->getStatut(),
            'pretMateriel' => $commande->isPretMateriel(),
            'restitutionMateriel' => $commande->isRestitutionMateriel(),
            'motifAnnulation' => $commande->getMotifAnnulation(),
            'modeContact' => $commande->getModeContact(),
            // Le client peut annuler/modifier tant qu'un employé n'a pas accepté la commande.
            'modifiableParClient' => $commande->getStatut() === Commande::STATUT_EN_ATTENTE,
            'avisDonne' => $avis !== null,
            'menu' => $commande->getMenu() ? [
                'id' => $commande->getMenu()->getId(),
                'titleMenu' => $commande->getMenu()->getTitleMenu(),
                'nombrePersonneMinimum' => $commande->getMenu()->getNombrePersonneMinimum(),
                'prixParPersonne' => $commande->getMenu()->getPrixParPersonne(),
                'conditions' => $commande->getMenu()->getConditions(),
            ] : null,
            'user' => $user ? [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'phoneNumber' => $user->getphoneNumber(),
            ] : null,
        ];

        if ($avecHistorique) {
            $data['historique'] = array_values(array_map(static fn (CommandeHistorique $h) => [
                'statut' => $h->getStatut(),
                'date' => $h->getChangedAt()?->format('Y-m-d H:i:s'),
                'commentaire' => $h->getCommentaire(),
            ], $commande->getHistoriques()->toArray()));
        }

        return $data;
    }

    /** Une commande n'est visible/modifiable que par son client ou par un employé/admin. */
    private function trouverCommandeAutorisee(int $id): Commande|JsonResponse
    {
        $commande = $this->repository->find($id);
        if (!$commande) {
            return new JsonResponse(['message' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->estStaff() && $commande->getUser() !== $this->getUser()) {
            // On ne renvoie pas l'erreur 403 qui afficherait un accès refusé et confirmerait que la commande existe. 404 par défaut.
            return new JsonResponse(['message' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $commande;
    }

    private function lireJson(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : [];
    }

    private function lireDatePrestation(mixed $valeur): DateTimeImmutable|JsonResponse
    {
        try {
            $date = new DateTimeImmutable((string) $valeur);
        } catch (\Exception) {
            return new JsonResponse(['message' => 'Date de prestation invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if ($date->setTime(0, 0) < new DateTimeImmutable('today')) {
            return new JsonResponse(['message' => 'La date de prestation ne peut pas être dans le passé.'], Response::HTTP_BAD_REQUEST);
        }

        return $date;
    }

    private function lireHeure(mixed $valeur): ?string
    {
        $heure = trim((string) $valeur);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $heure) ? $heure : null;
    }

    /** calcule les prix côté serveur. Renvoie null si ok, sinon une erreur 400 avec le message */
    private function appliquerTarif(Commande $commande): ?JsonResponse
    {
        try {
            $devis = $this->tarif->calculer($commande->getMenu(), (int) $commande->getNombrePersonne(), (string) $commande->getAdresse());
        } catch (\InvalidArgumentException|AdresseIntrouvableException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $commande->setPrixMenu(number_format($devis['prixParPersonneRemise'], 2, '.', ''));
        $commande->setPrixLivraison(number_format($devis['livraison']['montant'], 2, '.', ''));

        return null;
    }

    #[Route(name: 'index', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/commande",
     *     summary="Toutes les commandes (employé/admin), filtrables par statut et par client",
     *     @OA\Parameter(name="statut", in="query", required=false, @OA\Schema(type="string", example="En attente")),
     *     @OA\Parameter(name="client", in="query", required=false, description="Recherche dans le nom, le prénom ou l'email du client", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Liste des commandes"),
     *     @OA\Response(response=403, description="Réservé aux employés/administrateurs")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->estStaff()) {
            return new JsonResponse(
                ['message' => 'Seul un employé ou un administrateur peut consulter toutes les commandes.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $statut = trim((string) $request->query->get('statut', ''));
        $client = trim((string) $request->query->get('client', ''));

        $commandes = $this->repository->rechercher($statut ?: null, $client ?: null);
        $data = array_map(fn (Commande $commande) => $this->commandeToArray($commande, false), $commandes);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/statuts', name: 'statuts', methods: 'GET')]
    /** @OA\Get(path="/api/commande/statuts", summary="Liste des statuts possibles d'une commande", @OA\Response(response=200, description="Statuts")) */
    public function statuts(): JsonResponse
    {
        return new JsonResponse(['statuts' => Commande::STATUTS, 'modesContact' => Commande::MODES_CONTACT]);
    }

    #[Route('/me', name: 'mine', methods: 'GET')]
    /** @OA\Get(path="/api/commande/me", summary="Mes commandes, avec leur suivi", @OA\Response(response=200, description="Mes commandes")) */
    public function mine(): JsonResponse
    {
        $commandes = $this->repository->findBy(['user' => $this->getUser()], ['id' => 'DESC']);
        $data = array_map(fn (Commande $commande) => $this->commandeToArray($commande), $commandes);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/devis', name: 'devis', methods: 'POST')]
    /** @OA\Post(
     *     path="/api/commande/devis",
     *     summary="Détail du prix AVANT validation : prix du menu (réduction comprise) + frais de livraison calculés depuis l'adresse",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="menuId", type="integer", example=1),
     *             @OA\Property(property="nombrePersonne", type="integer", example=12),
     *             @OA\Property(property="adresse", type="string", example="5 place de la Victoire, 33000 Bordeaux")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Détail du prix"),
     *     @OA\Response(response=400, description="Nombre de personnes insuffisant ou adresse introuvable")
     * )
     */
    public function devis(Request $request): JsonResponse
    {
        $data = $this->lireJson($request);
        $menu = !empty($data['menuId']) ? $this->menuRepository->find((int) $data['menuId']) : null;
        if (!$menu) {
            return new JsonResponse(['message' => 'Menu introuvable.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $devis = $this->tarif->calculer($menu, (int) ($data['nombrePersonne'] ?? 0), (string) ($data['adresse'] ?? ''));
        } catch (\InvalidArgumentException|AdresseIntrouvableException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($devis);
    }

    #[Route(methods: 'POST')]
    /** @OA\Post(
     *     path="/api/commande",
     *     summary="Passer une commande",
     *     description="Le client est l'utilisateur connecté. Les prix (menu, réduction de 10 %, livraison) sont TOUJOURS calculés par le serveur : un prix envoyé par le front est ignoré. Un mail de confirmation est envoyé.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="menuId", type="integer", example=1),
     *             @OA\Property(property="datePrestation", type="string", format="date", example="2026-12-24"),
     *             @OA\Property(property="heureLivraison", type="string", example="19:30"),
     *             @OA\Property(property="adresse", type="string", example="12 rue des Fauvettes, 33000 Bordeaux"),
     *             @OA\Property(property="nombrePersonne", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Commande créée"),
     *     @OA\Response(response=400, description="Données invalides"),
     *     @OA\Response(response=409, description="Plus de stock pour ce menu")
     * )
     */
    public function new(Request $request): JsonResponse
    {
        $data = $this->lireJson($request);

        $menu = !empty($data['menuId']) ? $this->menuRepository->find((int) $data['menuId']) : null;
        if (!$menu) {
            return new JsonResponse(['message' => 'Menu introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $adresse = trim((string) ($data['adresse'] ?? ''));
        if ($adresse === '') {
            return new JsonResponse(['message' => "L'adresse de livraison est obligatoire."], Response::HTTP_BAD_REQUEST);
        }
        if (empty($data['datePrestation'])) {
            return new JsonResponse(['message' => 'La date de prestation est obligatoire.'], Response::HTTP_BAD_REQUEST);
        }
        $datePrestation = $this->lireDatePrestation($data['datePrestation']);
        if ($datePrestation instanceof JsonResponse) {
            return $datePrestation;
        }
        $heure = $this->lireHeure($data['heureLivraison'] ?? '');
        if (!$heure) {
            return new JsonResponse(['message' => "L'heure de livraison souhaitée est obligatoire (format HH:MM)."], Response::HTTP_BAD_REQUEST);
        }

        // Stock disponible (null = illimité).
        if ($menu->getQuantiteRestante() !== null && $menu->getQuantiteRestante() <= 0) {
            return new JsonResponse(['message' => "Ce menu n'est plus disponible à la commande (stock épuisé)."], Response::HTTP_CONFLICT);
        }

        /** @var User $user */
        $user = $this->getUser();

        $commande = new Commande();
        $commande->setMenu($menu);
        $commande->setUser($user);
        $commande->setDateCommande(new DateTimeImmutable());
        $commande->setDatePrestation($datePrestation);
        $commande->setHeureLivraison($heure);
        $commande->setAdresse($adresse);
        $commande->setNombrePersonne(isset($data['nombrePersonne']) ? (int) $data['nombrePersonne'] : (int) $menu->getNombrePersonneMinimum());
        $commande->setPretMateriel(false);

        if ($erreur = $this->appliquerTarif($commande)) {
            return $erreur;
        }

        $commande->changerStatut(Commande::STATUT_EN_ATTENTE, 'Commande passée par le client', $user);

        if ($menu->getQuantiteRestante() !== null) {
            $menu->setQuantiteRestante($menu->getQuantiteRestante() - 1);
        }

        $this->manager->persist($commande);
        $this->manager->flush();

        $this->statsMongo->synchroniser($commande);
        $mailEnvoye = $this->mailerService->envoyerConfirmationCommande($commande);

        return new JsonResponse($this->commandeToArray($commande) + ['mailEnvoye' => $mailEnvoye], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'edit', methods: 'PUT', requirements: ['id' => '\d+'])]
    /** @OA\Put(
     *     path="/api/commande/{id}",
     *     summary="Modifier une commande (date, heure, adresse, nombre de personnes). Le menu n'est jamais modifiable.",
     *     description="Client : uniquement tant que la commande est 'En attente'. Employé : doit avoir contacté le client (modeContact = GSM ou Email + motif obligatoires). Les prix sont recalculés.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="datePrestation", type="string", format="date"),
     *             @OA\Property(property="heureLivraison", type="string", example="12:00"),
     *             @OA\Property(property="adresse", type="string"),
     *             @OA\Property(property="nombrePersonne", type="integer"),
     *             @OA\Property(property="pretMateriel", type="boolean", description="Employé uniquement"),
     *             @OA\Property(property="modeContact", type="string", enum={"GSM", "Email"}, description="Employé uniquement"),
     *             @OA\Property(property="motif", type="string", description="Employé uniquement")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Commande modifiée"),
     *     @OA\Response(response=400, description="Données invalides"),
     *     @OA\Response(response=409, description="La commande n'est plus modifiable")
     * )
     */
    public function edit(int $id, Request $request): JsonResponse
    {
        $commande = $this->trouverCommandeAutorisee($id);
        if ($commande instanceof JsonResponse) {
            return $commande;
        }

        $data = $this->lireJson($request);
        /** @var User $user */
        $user = $this->getUser();
        $estStaff = $this->estStaff();
        $commentaire = 'Commande modifiée par le client';

        if (in_array($commande->getStatut(), Commande::STATUTS_FINAUX, true)) {
            return new JsonResponse(['message' => 'Cette commande est ' . mb_strtolower($commande->getStatut()) . ' : elle ne peut plus être modifiée.'], Response::HTTP_CONFLICT);
        }

        if (!$estStaff && $commande->getStatut() !== Commande::STATUT_EN_ATTENTE) {
            return new JsonResponse(
                ['message' => 'Votre commande a déjà été acceptée : contactez-nous pour toute modification.'],
                Response::HTTP_CONFLICT
            );
        }

        if (array_key_exists('menuId', $data) && (int) $data['menuId'] !== $commande->getMenu()->getId()) {
            return new JsonResponse(['message' => "Le menu d'une commande ne peut pas être modifié."], Response::HTTP_BAD_REQUEST);
        }

        $modifContenu = array_intersect(array_keys($data), ['datePrestation', 'heureLivraison', 'adresse', 'nombrePersonne', 'pretMateriel']);

        if ($estStaff && $modifContenu) {
            // Contact client avant modification, sécurité pour éviter les conflits client
            $erreurContact = $this->verifierContactClient($data);
            if ($erreurContact) {
                return $erreurContact;
            }
            $commentaire = sprintf('Modifiée par un employé après contact du client par %s : %s', $data['modeContact'], trim((string) $data['motif']));
        }

        if (array_key_exists('datePrestation', $data)) {
            $date = $this->lireDatePrestation($data['datePrestation']);
            if ($date instanceof JsonResponse) {
                return $date;
            }
            $commande->setDatePrestation($date);
        }
        if (array_key_exists('heureLivraison', $data)) {
            $heure = $this->lireHeure($data['heureLivraison']);
            if (!$heure) {
                return new JsonResponse(['message' => "L'heure de livraison doit être au format HH:MM."], Response::HTTP_BAD_REQUEST);
            }
            $commande->setHeureLivraison($heure);
        }
        if (array_key_exists('adresse', $data)) {
            if (trim((string) $data['adresse']) === '') {
                return new JsonResponse(['message' => "L'adresse de livraison ne peut pas être vide."], Response::HTTP_BAD_REQUEST);
            }
            $commande->setAdresse(trim((string) $data['adresse']));
        }
        if (array_key_exists('nombrePersonne', $data)) {
            $commande->setNombrePersonne((int) $data['nombrePersonne']);
        }
        if ($estStaff && array_key_exists('pretMateriel', $data)) {
            $commande->setPretMateriel((bool) $data['pretMateriel']);
        }

        if (array_intersect(array_keys($data), ['adresse', 'nombrePersonne'])) {
            if ($erreur = $this->appliquerTarif($commande)) {
                $this->manager->refresh($commande);

                return $erreur;
            }
        }

        if ($modifContenu) {
            // On garde une trace de la modification dans le suivi (même statut).
            $commande->changerStatut($commande->getStatut(), $commentaire, $user);
        }

        $this->manager->flush();
        $this->statsMongo->synchroniser($commande);

        return new JsonResponse($this->commandeToArray($commande), Response::HTTP_OK);
    }

    #[Route('/{id}/statut', name: 'statut', methods: 'PUT', requirements: ['id' => '\d+'])]
    /** @OA\Put(
     *     path="/api/commande/{id}/statut",
     *     summary="Faire avancer une commande (employé/admin) : Accepté, En préparation, En cours de livraison, Livré, En attente du retour de matériel, Terminée",
     *     description="Chaque changement est horodaté dans le suivi. Mails automatiques : rappel matériel (10 jours ouvrés / 600 €) et invitation à donner un avis quand la commande est terminée. Pour annuler, utiliser /annuler.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="statut", type="string", example="Accepté"),
     *             @OA\Property(property="pretMateriel", type="boolean", example=false),
     *             @OA\Property(property="commentaire", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Statut mis à jour"),
     *     @OA\Response(response=400, description="Statut invalide"),
     *     @OA\Response(response=403, description="Réservé aux employés/administrateurs"),
     *     @OA\Response(response=409, description="Commande terminée ou annulée")
     * )
     */
    public function changerStatut(int $id, Request $request): JsonResponse
    {
        if (!$this->estStaff()) {
            return new JsonResponse(['message' => "Seul un employé ou un administrateur peut modifier le statut d'une commande."], Response::HTTP_FORBIDDEN);
        }

        $commande = $this->repository->find($id);
        if (!$commande) {
            return new JsonResponse(['message' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $data = $this->lireJson($request);
        $statut = (string) ($data['statut'] ?? '');

        if ($statut === Commande::STATUT_ANNULEE) {
            return new JsonResponse(['message' => "Pour annuler une commande, utilisez l'action « Annuler » (motif et mode de contact obligatoires)."], Response::HTTP_BAD_REQUEST);
        }
        if (!in_array($statut, Commande::STATUTS, true)) {
            return new JsonResponse(['message' => 'Statut invalide. Valeurs possibles : ' . implode(', ', Commande::STATUTS)], Response::HTTP_BAD_REQUEST);
        }
        if (in_array($commande->getStatut(), Commande::STATUTS_FINAUX, true)) {
            return new JsonResponse(['message' => 'Cette commande est ' . mb_strtolower($commande->getStatut()) . ' : son statut ne peut plus changer.'], Response::HTTP_CONFLICT);
        }

        if (array_key_exists('pretMateriel', $data)) {
            $commande->setPretMateriel((bool) $data['pretMateriel']);
        }

        if ($statut === Commande::STATUT_ATTENTE_MATERIEL && !$commande->isPretMateriel()) {
            return new JsonResponse(['message' => "Aucun matériel n'a été prêté pour cette commande : cochez « Prêt de matériel » d'abord."], Response::HTTP_BAD_REQUEST);
        }
        if ($statut === Commande::STATUT_TERMINEE && $commande->isPretMateriel()) {
            // Terminée avec prêt de matériel = le matériel a été restitué.
            $commande->setRestitutionMateriel(true);
        }

        if ($statut === $commande->getStatut()) {
            $this->manager->flush();

            return new JsonResponse($this->commandeToArray($commande), Response::HTTP_OK);
        }

        $commentaire = trim((string) ($data['commentaire'] ?? '')) ?: null;
        $commande->changerStatut($statut, $commentaire, $this->getUser());
        $this->manager->flush();
        $this->statsMongo->synchroniser($commande);

        $mailEnvoye = null;
        if ($statut === Commande::STATUT_ATTENTE_MATERIEL) {
            $mailEnvoye = $this->mailerService->envoyerRappelRetourMateriel($commande);
        } elseif ($statut === Commande::STATUT_TERMINEE) {
            $mailEnvoye = $this->mailerService->envoyerInvitationAvis($commande);
        }

        return new JsonResponse($this->commandeToArray($commande) + ['mailEnvoye' => $mailEnvoye], Response::HTTP_OK);
    }

    #[Route('/{id}/annuler', name: 'annuler', methods: 'POST', requirements: ['id' => '\d+'])]
    /** @OA\Post(
     *     path="/api/commande/{id}/annuler",
     *     summary="Annuler une commande",
     *     description="Client : possible tant que la commande est 'En attente'. Employé : après avoir contacté le client, modeContact (GSM/Email) et motif obligatoires. La commande est conservée avec le statut 'Annulée' et le stock du menu est rendu.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="modeContact", type="string", enum={"GSM", "Email"}),
     *             @OA\Property(property="motif", type="string", example="Le client a changé de date")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Commande annulée"),
     *     @OA\Response(response=400, description="Motif / mode de contact manquant"),
     *     @OA\Response(response=409, description="Commande déjà acceptée (client), terminée ou annulée")
     * )
     */
    public function annuler(int $id, Request $request): JsonResponse
    {
        $commande = $this->trouverCommandeAutorisee($id);
        if ($commande instanceof JsonResponse) {
            return $commande;
        }

        $data = $this->lireJson($request);
        /** @var User $user */
        $user = $this->getUser();

        if (in_array($commande->getStatut(), Commande::STATUTS_FINAUX, true)) {
            return new JsonResponse(['message' => 'Cette commande est déjà ' . mb_strtolower($commande->getStatut()) . '.'], Response::HTTP_CONFLICT);
        }

        if ($this->estStaff()) {
            $erreurContact = $this->verifierContactClient($data);
            if ($erreurContact) {
                return $erreurContact;
            }
            $motif = trim((string) $data['motif']);
            $commande->setModeContact($data['modeContact']);
            $commande->setMotifAnnulation($motif);
            $commentaire = sprintf('Annulée par un employé après contact du client par %s : %s', $data['modeContact'], $motif);
        } else {
            if ($commande->getStatut() !== Commande::STATUT_EN_ATTENTE) {
                return new JsonResponse(
                    ['message' => 'Votre commande a déjà été acceptée : contactez-nous pour l\'annuler.'],
                    Response::HTTP_CONFLICT
                );
            }
            $motif = trim((string) ($data['motif'] ?? '')) ?: null;
            $commande->setMotifAnnulation($motif);
            $commentaire = 'Annulée par le client' . ($motif ? ' : ' . $motif : '');
        }

        $commande->changerStatut(Commande::STATUT_ANNULEE, $commentaire, $user);

        // La commande annulée libère une place dans le stock du menu.
        $menu = $commande->getMenu();
        if ($menu && $menu->getQuantiteRestante() !== null) {
            $menu->setQuantiteRestante($menu->getQuantiteRestante() + 1);
        }

        $this->manager->flush();
        $this->statsMongo->synchroniser($commande);
        $this->mailerService->envoyerAnnulationCommande($commande);

        return new JsonResponse($this->commandeToArray($commande), Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'show', methods: 'GET', requirements: ['id' => '\d+'])]
    /** @OA\Get(path="/api/commande/{id}", summary="Détail d'une commande avec son suivi (client propriétaire ou employé)", @OA\Response(response=200, description="Commande")) */
    public function show(int $id): JsonResponse
    {
        $commande = $this->trouverCommandeAutorisee($id);
        if ($commande instanceof JsonResponse) {
            return $commande;
        }

        return new JsonResponse($this->commandeToArray($commande), Response::HTTP_OK);
    }

    /** Vérifie que l'employé indique comment il a joint le client, et pourquoi. */
    private function verifierContactClient(array $data): ?JsonResponse
    {
        $mode = $data['modeContact'] ?? null;
        $motif = trim((string) ($data['motif'] ?? ''));

        if (!in_array($mode, Commande::MODES_CONTACT, true) || $motif === '') {
            return new JsonResponse(
                ['message' => 'Vous devez contacter le client avant de modifier ou annuler sa commande : indiquez le mode de contact (GSM ou Email) et le motif.'],
                Response::HTTP_BAD_REQUEST
            );
        }
        if (mb_strlen($motif) > 200) {
            return new JsonResponse(['message' => 'Le motif ne peut pas dépasser 200 caractères.'], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }
}
