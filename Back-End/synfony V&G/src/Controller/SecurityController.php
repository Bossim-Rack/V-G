<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Repository\AvisRepository;
use App\Repository\CommandeRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Service\MailerService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'app_api_')]
class SecurityController extends AbstractController
{
    // Durée de validité des liens envoyés par mail.
    private const DUREE_LIEN_VERIFICATION = '+48 hours';
    private const DUREE_LIEN_REINITIALISATION = '+1 hour';

    // Rôles qu'un administrateur peut attribuer depuis l'application :
    // il ne doit PAS être possible de créer un compte Administrateur depuis l'application.
    private const ROLES_ATTRIBUABLES = ['Client', 'Employé'];

    public function __construct(
        private EntityManagerInterface $manager,
        private UserPasswordHasherInterface $passwordHasher,
        private RoleRepository $roleRepository,
        private UserRepository $userRepository,
        private ValidatorInterface $validator,
        private MailerService $mailerService,
    ) {
    }

    /**
     * Valide l'entité (avec les groupes demandés) et renvoie une réponse 400 avec le détail des erreurs si besoin.
     * Retourne null si tout est valide (=> on peut continuer le traitement).
     */
    private function validateOrGetErrorResponse(User $user, array $groups = ['Default']): ?JsonResponse
    {
        $errors = $this->validator->validate($user, null, $groups);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $champ = $error->getPropertyPath() === 'plainPassword' ? 'password' : $error->getPropertyPath();
                // On garde le premier message par champ (le plus parlant).
                $errorMessages[$champ] ??= $error->getMessage();
            }

            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }

    /** Lit le corps JSON de la requête (tableau vide si absent ou invalide). */
    private function lireJson(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Génère un jeton aléatoire à envoyer par mail.
     * Retourne [jeton en clair (pour le lien), empreinte SHA-256 (pour la base)].
     */
    private function genererJeton(): array
    {
        $jeton = bin2hex(random_bytes(32));

        return [$jeton, hash('sha256', $jeton)];
    }

    /** Hashe le mot de passe en clair validé, puis l'efface de la mémoire. */
    private function appliquerMotDePasse(User $user): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $user->getPlainPassword()));
        $user->eraseCredentials();
    }

    /** Ce qui est renvoyé au front pour un compte : jamais le hash du mot de passe ni les jetons. */
    private function userToArray(User $user): array
    {
        return [
            'id' => $user->getId(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
            'phoneNumber' => $user->getphoneNumber(),
            'adresse' => $user->getAdresse(),
            'allergy' => $user->getAllergy(),
            'role' => $user->getRole() ? ['id' => $user->getRole()->getId(), 'libelle' => $user->getRole()->getLibelle()] : null,
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            'isVerified' => $user->isVerified(),
            'isActive' => $user->isActive(),
        ];
    }

    #[Route('/registration', name: 'registration', methods: 'POST')]
    /** @OA\Post(
     *     path="/api/registration",
     *     summary="Inscription d'un nouvel utilisateur (rôle Client). Un mail de bienvenue contenant un lien de confirmation est envoyé.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="firstName", type="string", example="Thomas"),
     *             @OA\Property(property="lastName", type="string", example="Dupont"),
     *             @OA\Property(property="email", type="string", example="thomas@email.com"),
     *             @OA\Property(property="phoneNumber", type="string", example="0612345678"),
     *             @OA\Property(property="adresse", type="string", example="12 rue Sainte-Catherine, 33000 Bordeaux"),
     *             @OA\Property(property="password", type="string", example="MotDePasse1!")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Compte créé : l'utilisateur doit confirmer son email avant de se connecter"),
     *     @OA\Response(response=400, description="Données invalides"),
     *     @OA\Response(response=409, description="Un compte existe déjà avec cet email")
     * )
     */
    public function register(Request $request): JsonResponse
    {
        $data = $this->lireJson($request);

        // Champs recopiés un par un (et non désérialisés) : le client ne peut pas
        // s'attribuer un rôle, un jeton, ou se déclarer "vérifié".
        $user = (new User())
            ->setFirstName(trim((string) ($data['firstName'] ?? '')))
            ->setLastName(trim((string) ($data['lastName'] ?? '')))
            ->setEmail(mb_strtolower(trim((string) ($data['email'] ?? ''))))
            ->setphoneNumber($this->normaliserTelephone($data['phoneNumber'] ?? null))
            ->setAdresse(trim((string) ($data['adresse'] ?? '')) ?: null)
            ->setPlainPassword((string) ($data['password'] ?? ''));

        if ($errorResponse = $this->validateOrGetErrorResponse($user, ['Default', 'registration', 'password'])) {
            return $errorResponse;
        }

        if ($this->userRepository->findOneBy(['email' => $user->getEmail()])) {
            return new JsonResponse(['errors' => ['email' => 'Un compte existe déjà avec cet email.']], Response::HTTP_CONFLICT);
        }

        $this->appliquerMotDePasse($user);
        $user->setCreatedAt(new DateTimeImmutable());
        $user->setRole($this->roleRepository->findOneBy(['libelle' => 'Client']));
        $user->setIsVerified(false);

        [$jeton, $empreinte] = $this->genererJeton();
        $user->setEmailVerificationToken($empreinte, new DateTimeImmutable(self::DUREE_LIEN_VERIFICATION));

        $this->manager->persist($user);
        $this->manager->flush();

        $mailEnvoye = $this->mailerService->envoyerBienvenue($user, $jeton);

        return new JsonResponse([
            'message' => 'Compte créé ! Un email de confirmation vient de vous être envoyé : cliquez sur le lien pour activer votre compte.',
            'user' => $user->getUserIdentifier(),
            'mailEnvoye' => $mailEnvoye,
        ], Response::HTTP_CREATED);
    }

    #[Route('/verify-email', name: 'verify_email', methods: 'POST')]
    /** @OA\Post(
     *     path="/api/verify-email",
     *     summary="Confirme l'adresse email à partir du jeton reçu par mail",
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object", @OA\Property(property="token", type="string"))),
     *     @OA\Response(response=200, description="Adresse confirmée"),
     *     @OA\Response(response=400, description="Lien invalide ou expiré")
     * )
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $jeton = (string) ($this->lireJson($request)['token'] ?? '');
        $user = $jeton !== '' ? $this->userRepository->findOneBy(['emailVerificationTokenHash' => hash('sha256', $jeton)]) : null;

        if (!$user || !$user->getEmailVerificationExpiresAt() || $user->getEmailVerificationExpiresAt() < new DateTimeImmutable()) {
            return new JsonResponse(
                ['message' => 'Ce lien de confirmation est invalide ou a expiré. Demandez-en un nouveau depuis la page de connexion.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user->setIsVerified(true);
        $user->setEmailVerificationToken(null, null);
        $user->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return new JsonResponse(['message' => 'Votre adresse email est confirmée. Vous pouvez maintenant vous connecter.']);
    }

    #[Route('/resend-verification', name: 'resend_verification', methods: 'POST')]
    /** @OA\Post(
     *     path="/api/resend-verification",
     *     summary="Renvoie un lien de confirmation d'email (réponse volontairement identique que le compte existe ou non)",
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object", @OA\Property(property="email", type="string"))),
     *     @OA\Response(response=200, description="Demande prise en compte")
     * )
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $email = mb_strtolower(trim((string) ($this->lireJson($request)['email'] ?? '')));
        $user = $email !== '' ? $this->userRepository->findOneBy(['email' => $email]) : null;

        if ($user && !$user->isVerified() && $user->isActive()) {
            [$jeton, $empreinte] = $this->genererJeton();
            $user->setEmailVerificationToken($empreinte, new DateTimeImmutable(self::DUREE_LIEN_VERIFICATION));
            $this->manager->flush();
            $this->mailerService->envoyerVerification($user, $jeton);
        }

        return new JsonResponse(['message' => "Si un compte non confirmé existe avec cet email, un nouveau lien vient d'être envoyé."]);
    }

    #[Route('/login', name: 'login', methods: 'POST')]
    /** @OA\Post(
     *     path="/api/login",
     *     summary="Connecter un utilisateur",
     *     @OA\RequestBody(
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  required={"username", "password"},
     *                  @OA\Property(property="username", type="string"),
     *                  @OA\Property(property="password", type="string")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=200, description="Connexion réussie"),
     *      @OA\Response(response=401, description="Identifiants invalides, email non confirmé ou compte désactivé (voir le champ error)")
     * )
     */
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['message' => 'Missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'user'  => $user->getUserIdentifier(),
            'apiToken' => $user->getApiToken(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/account/me', name: 'me', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/account/me",
     *     summary="Informations du compte connecté (sans mot de passe ni jeton)",
     *     @OA\Response(response=200, description="Informations du compte")
     * )
     */
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return new JsonResponse($this->userToArray($user), Response::HTTP_OK);
    }

    #[Route('/account/edit', name: 'edit', methods: 'PUT')]
    /** @OA\Put(
     *     path="/api/account/edit",
     *     summary="Modifier son compte. Seuls firstName, lastName, phoneNumber, adresse, allergy et password (avec currentPassword) sont pris en compte.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="firstName", type="string", example="Nouveau prénom"),
     *             @OA\Property(property="password", type="string", example="NouveauMdp1!"),
     *             @OA\Property(property="currentPassword", type="string", example="AncienMdp1!")
     *         )
     *     ),
     *     @OA\Response(response=204, description="Compte modifié avec succès"),
     *     @OA\Response(response=400, description="Données invalides ou mot de passe actuel incorrect")
     * )
     */
    public function edit(Request $request): JsonResponse
    {
        $data = $this->lireJson($request);
        /** @var User $user */
        $user = $this->getUser();
        $groups = ['Default'];

        // Liste blanche : email, rôle, jeton, statut de vérification... ne sont jamais modifiables ici.
        if (array_key_exists('firstName', $data)) {
            $user->setFirstName(trim((string) $data['firstName']));
        }
        if (array_key_exists('lastName', $data)) {
            $user->setLastName(trim((string) $data['lastName']));
        }
        if (array_key_exists('phoneNumber', $data)) {
            $user->setphoneNumber($this->normaliserTelephone($data['phoneNumber']));
        }
        if (array_key_exists('adresse', $data)) {
            $user->setAdresse(trim((string) $data['adresse']) ?: null);
        }
        if (array_key_exists('allergy', $data)) {
            $user->setAllergy(trim((string) $data['allergy']) ?: null);
        }

        if (isset($data['password'])) {
            if (empty($data['currentPassword']) || !$this->passwordHasher->isPasswordValid($user, (string) $data['currentPassword'])) {
                $this->manager->refresh($user);

                return new JsonResponse(['errors' => ['currentPassword' => 'Mot de passe actuel incorrect.']], Response::HTTP_BAD_REQUEST);
            }
            $user->setPlainPassword((string) $data['password']);
            $groups[] = 'password';
        }

        if ($errorResponse = $this->validateOrGetErrorResponse($user, $groups)) {
            // On annule les modifications en mémoire pour ne rien enregistrer par erreur.
            $this->manager->refresh($user);

            return $errorResponse;
        }

        if ($user->getPlainPassword()) {
            $this->appliquerMotDePasse($user);
        }

        $user->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/forgot-password', name: 'forgot_password', methods: 'POST')]
    /** @OA\Post(
     *     path="/api/forgot-password",
     *     summary="Demande de réinitialisation : envoie par mail un lien valable 1 heure",
     *     description="La réponse est toujours la même, que le compte existe ou non (on ne révèle pas quels emails sont inscrits).",
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object", @OA\Property(property="email", type="string", example="thomas@email.com"))),
     *     @OA\Response(response=200, description="Demande prise en compte")
     * )
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $email = mb_strtolower(trim((string) ($this->lireJson($request)['email'] ?? '')));

        if ($email === '') {
            return new JsonResponse(['message' => "L'email est obligatoire."], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user && $user->isActive()) {
            [$jeton, $empreinte] = $this->genererJeton();
            $user->setResetPasswordToken($empreinte, new DateTimeImmutable(self::DUREE_LIEN_REINITIALISATION));
            $this->manager->flush();
            $this->mailerService->envoyerReinitialisationMotDePasse($user, $jeton);
        }

        return new JsonResponse([
            'message' => "Si un compte existe avec cet email, un lien de réinitialisation vient d'être envoyé. Il est valable 1 heure.",
        ]);
    }

    #[Route('/reset-password', name: 'reset_password', methods: 'POST')]
    /** @OA\Post(
     *     path="/api/reset-password",
     *     summary="Choisit un nouveau mot de passe à partir du jeton reçu par mail",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="password", type="string", example="NouveauMdp1!")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Mot de passe modifié"),
     *     @OA\Response(response=400, description="Lien invalide/expiré ou mot de passe trop faible")
     * )
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $this->lireJson($request);
        $jeton = (string) ($data['token'] ?? '');
        $user = $jeton !== '' ? $this->userRepository->findOneBy(['resetPasswordTokenHash' => hash('sha256', $jeton)]) : null;

        if (!$user || !$user->getResetPasswordExpiresAt() || $user->getResetPasswordExpiresAt() < new DateTimeImmutable()) {
            return new JsonResponse(
                ['message' => 'Ce lien de réinitialisation est invalide ou a expiré. Faites une nouvelle demande.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user->setPlainPassword((string) ($data['password'] ?? ''));
        if ($errorResponse = $this->validateOrGetErrorResponse($user, ['password'])) {
            return $errorResponse;
        }

        $this->appliquerMotDePasse($user);
        // Le lien ne sert qu'une fois, et on déconnecte toutes les sessions ouvertes.
        $user->setResetPasswordToken(null, null);
        $user->regenerateApiToken();
        // On valide le compte avec le liens transferer
        $user->setIsVerified(true);
        $user->setEmailVerificationToken(null, null);
        $user->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return new JsonResponse(['message' => 'Mot de passe modifié. Vous pouvez vous connecter avec votre nouveau mot de passe.']);
    }

    #[Route('/account', name: 'delete_account', methods: 'DELETE')]
    /** @OA\Delete(
     *     path="/api/account",
     *     summary="Supprimer son propre compte (droit à l'effacement, RGPD)",
     *     description="Le mot de passe actuel est demandé pour confirmer. Les avis sont supprimés. Si le client a déjà passé des commandes, elles sont conservées (obligations comptables) mais le compte est anonymisé : nom, email, téléphone, adresse et allergies sont effacés et plus personne ne peut s'y connecter. Impossible tant qu'une commande est en cours.",
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object", @OA\Property(property="password", type="string"))),
     *     @OA\Response(response=204, description="Compte supprimé"),
     *     @OA\Response(response=400, description="Mot de passe incorrect"),
     *     @OA\Response(response=403, description="Un administrateur ne peut pas supprimer son compte"),
     *     @OA\Response(response=409, description="Une commande est encore en cours")
     * )
     */
    public function deleteAccount(Request $request, CommandeRepository $commandeRepository, AvisRepository $avisRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $motDePasse = (string) ($this->lireJson($request)['password'] ?? '');

        if ($motDePasse === '' || !$this->passwordHasher->isPasswordValid($user, $motDePasse)) {
            return new JsonResponse(['errors' => ['password' => 'Mot de passe incorrect.']], Response::HTTP_BAD_REQUEST);
        }

        // Le seul compte administrateur (José) ne doit pas disparaître depuis l'application.
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new JsonResponse(['message' => 'Un compte administrateur ne peut pas être supprimé depuis l\'application.'], Response::HTTP_FORBIDDEN);
        }

        $commandes = $commandeRepository->findBy(['user' => $user]);
        foreach ($commandes as $commande) {
            if (!in_array($commande->getStatut(), Commande::STATUTS_FINAUX, true)) {
                return new JsonResponse(
                    ['message' => 'Vous avez une commande en cours (n°' . $commande->getId() . ') : annulez-la ou attendez qu\'elle soit terminée avant de supprimer votre compte.'],
                    Response::HTTP_CONFLICT
                );
            }
        }

        foreach ($avisRepository->findBy(['user' => $user]) as $avis) {
            $this->manager->remove($avis);
        }

        if (!$commandes) {
            // Aucune commande : on efface réellement le compte.
            $this->manager->remove($user);
        } else {
            // Des commandes existent (factures à conserver) : on anonymise le compte (mail fictif).
            $user->setFirstName('Utilisateur')
                ->setLastName('Supprimé')
                ->setEmail('supprime-' . $user->getId() . '@compte-supprime.invalid')
                ->setphoneNumber(null)
                ->setAdresse(null)
                ->setAllergy(null)
                ->setPassword(bin2hex(random_bytes(32)))
                ->setIsActive(false)
                ->setEmailVerificationToken(null, null)
                ->setResetPasswordToken(null, null)
                ->regenerateApiToken()
                ->setUpdatedAt(new DateTimeImmutable());
        }

        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/user/create-employe', name: 'create_employe', methods: 'POST')]
    /** @OA\Post(
     *     path="/api/user/create-employe",
     *     summary="Créer un compte employé (réservé aux administrateurs). L'employé reçoit un mail, sans son mot de passe.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="firstName", type="string", example="Sacha"),
     *             @OA\Property(property="lastName", type="string", example="Martin"),
     *             @OA\Property(property="email", type="string", example="sacha.martin@vitegourmand.fr"),
     *             @OA\Property(property="password", type="string", example="MotDePasse1!")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Employé créé avec succès"),
     *     @OA\Response(response=400, description="Données invalides"),
     *     @OA\Response(response=403, description="Accès refusé : réservé aux administrateurs"),
     *     @OA\Response(response=409, description="Un compte existe déjà avec cet email")
     * )
     */
    public function createEmploye(Request $request): JsonResponse
    {
        $data = $this->lireJson($request);

        $user = (new User())
            ->setFirstName(trim((string) ($data['firstName'] ?? '')))
            ->setLastName(trim((string) ($data['lastName'] ?? '')))
            ->setEmail(mb_strtolower(trim((string) ($data['email'] ?? ''))))
            ->setPlainPassword((string) ($data['password'] ?? ''));

        if ($errorResponse = $this->validateOrGetErrorResponse($user, ['Default', 'password'])) {
            return $errorResponse;
        }

        if ($this->userRepository->findOneBy(['email' => $user->getEmail()])) {
            return new JsonResponse(['errors' => ['email' => 'Un compte existe déjà avec cet email.']], Response::HTTP_CONFLICT);
        }

        $this->appliquerMotDePasse($user);
        $user->setCreatedAt(new DateTimeImmutable());
        $user->setRole($this->roleRepository->findOneBy(['libelle' => 'Employé']));
        // Compte créé par l'administrateur : pas d'étape de confirmation d'email.
        $user->setIsVerified(true);

        $this->manager->persist($user);
        $this->manager->flush();

        $mailEnvoye = $this->mailerService->envoyerCompteEmployeCree($user);

        return new JsonResponse(
            ['user' => $user->getUserIdentifier(), 'roles' => $user->getRoles(), 'mailEnvoye' => $mailEnvoye],
            Response::HTTP_CREATED
        );
    }

    #[Route('/user/count-clients', name: 'count_clients', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/user/count-clients",
     *     summary="Nombre de comptes ayant le rôle Client (réservé admin/employé)",
     *     @OA\Response(response=200, description="Nombre de clients inscrits"),
     *     @OA\Response(response=403, description="Accès refusé : réservé aux employés/administrateurs")
     * )
     */
    public function countClients(): JsonResponse
    {
        // On ne compte que les comptes "Client" : ni les employés, ni les administrateurs.
        $roleClient = $this->roleRepository->findOneBy(['libelle' => 'Client']);
        $count = $roleClient ? $this->userRepository->count(['role' => $roleClient]) : 0;

        return new JsonResponse(['count' => $count]);
    }

    #[Route('/user', name: 'list_users', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/user",
     *     summary="Lister tous les comptes utilisateurs (réservé aux administrateurs)",
     *     @OA\Response(response=200, description="Liste de tous les comptes, tous rôles confondus"),
     *     @OA\Response(response=403, description="Accès refusé : réservé aux administrateurs")
     * )
     */
    public function listUsers(): JsonResponse
    {
        $users = $this->userRepository->findBy([], ['id' => 'DESC']);

        // On ne renvoie jamais le mot de passe hashé ni l'apiToken.
        $data = array_map(static fn (User $u) => [
            'id' => $u->getId(),
            'firstName' => $u->getFirstName(),
            'lastName' => $u->getLastName(),
            'email' => $u->getEmail(),
            'phoneNumber' => $u->getphoneNumber(),
            'role' => $u->getRole()?->getLibelle(),
            'createdAt' => $u->getCreatedAt()?->format('d/m/Y'),
            'isActive' => $u->isActive(),
            'isVerified' => $u->isVerified(),
        ], $users);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/user/{id}', name: 'edit_user', methods: 'PUT', requirements: ['id' => '\d+'])]
    /** @OA\Put(
     *     path="/api/user/{id}",
     *     summary="Modifier un compte (réservé aux administrateurs) : prénom, nom, téléphone, rôle (Client/Employé) et activation",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="firstName", type="string", example="Camille"),
     *             @OA\Property(property="lastName", type="string", example="Client"),
     *             @OA\Property(property="phoneNumber", type="string", example="0612345678"),
     *             @OA\Property(property="role", type="string", enum={"Client", "Employé"}, example="Employé"),
     *             @OA\Property(property="isActive", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=204, description="Compte modifié avec succès"),
     *     @OA\Response(response=400, description="Rôle invalide ou données invalides"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=404, description="Compte introuvable")
     * )
     */
    public function editUser(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $data = $this->lireJson($request);
        $estAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
        $estSoiMeme = $this->getUser() instanceof User && $this->getUser()->getId() === $user->getId();

        if (array_key_exists('firstName', $data) && $data['firstName']) {
            $user->setFirstName(trim((string) $data['firstName']));
        }
        if (array_key_exists('lastName', $data) && $data['lastName']) {
            $user->setLastName(trim((string) $data['lastName']));
        }
        if (array_key_exists('phoneNumber', $data)) {
            $user->setphoneNumber($this->normaliserTelephone($data['phoneNumber']));
        }

        if (array_key_exists('role', $data) && $data['role'] && $data['role'] !== $user->getRole()?->getLibelle()) {
            if ($estAdmin) {
                $this->manager->refresh($user);

                return new JsonResponse(['message' => "Le rôle d'un administrateur ne peut pas être modifié depuis l'application."], Response::HTTP_FORBIDDEN);
            }
            if (!in_array($data['role'], self::ROLES_ATTRIBUABLES, true)) {
                $this->manager->refresh($user);

                return new JsonResponse(
                    ['message' => "Rôle invalide. Il n'est pas possible de créer un administrateur depuis l'application."],
                    Response::HTTP_BAD_REQUEST
                );
            }
            $user->setRole($this->roleRepository->findOneBy(['libelle' => $data['role']]));
        }

        if (array_key_exists('isActive', $data)) {
            $actif = (bool) $data['isActive'];
            if (!$actif && ($estAdmin || $estSoiMeme)) {
                $this->manager->refresh($user);

                return new JsonResponse(['message' => 'Un compte administrateur ne peut pas être désactivé.'], Response::HTTP_BAD_REQUEST);
            }
            if ($user->isActive() && !$actif) {
                // Coupe immédiatement les sessions ouvertes du compte désactivé.
                $user->regenerateApiToken();
            }
            $user->setIsActive($actif);
        }

        if ($errorResponse = $this->validateOrGetErrorResponse($user)) {
            $this->manager->refresh($user);

            return $errorResponse;
        }

        $user->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/user/{id}', name: 'delete_user', methods: 'DELETE', requirements: ['id' => '\d+'])]
    /** @OA\Delete(
     *     path="/api/user/{id}",
     *     summary="Supprimer le compte d'un utilisateur par ID (réservé aux administrateurs)",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Compte supprimé avec succès"),
     *     @OA\Response(response=400, description="Un administrateur ne peut pas supprimer son propre compte depuis cet écran"),
     *     @OA\Response(response=403, description="Accès refusé : réservé aux administrateurs"),
     *     @OA\Response(response=404, description="Compte introuvable"),
     *     @OA\Response(response=409, description="Suppression impossible : des commandes et/ou avis sont liés à ce compte")
     * )
     */
    public function deleteUser(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        // Empêche un admin de se supprimer lui-même (ou un autre admin) depuis cet écran.
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new JsonResponse(
                ['message' => 'Un compte administrateur ne peut pas être supprimé depuis cet écran.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $this->manager->remove($user);
            $this->manager->flush();
        } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException) {
            // Pas de suppression en cascade (commande/avis référencent user_id) : message clair plutôt qu'un 500.
            return new JsonResponse(
                ['message' => 'Impossible de supprimer ce compte : il a des commandes et/ou des avis liés. Vous pouvez le désactiver à la place.'],
                Response::HTTP_CONFLICT
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** "06 12 34 56 78" ou "+33612345678" -> "0612345678" (null si vide). */
    private function normaliserTelephone(mixed $telephone): ?string
    {
        $telephone = preg_replace('/[\s.\-]/', '', (string) $telephone);
        if ($telephone === '') {
            return null;
        }

        return preg_replace('/^\+33/', '0', $telephone);
    }
}
