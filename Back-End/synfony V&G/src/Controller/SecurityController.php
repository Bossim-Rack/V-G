<?php

namespace App\Controller;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;

#[Route('/api', name: 'app_api_')]
class SecurityController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private SerializerInterface $serializer,
        private UserPasswordHasherInterface $passwordHasher,
        private RoleRepository $roleRepository,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * Valide l'entité et renvoie une réponse 400 avec le détail des erreurs si besoin.
     * Retourne null si tout est valide (=> on peut continuer le traitement).
     */
    private function validateOrGetErrorResponse(User $user): ?JsonResponse
    {
        $errors = $this->validator->validate($user);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }

    #[Route('/registration', name: 'registration', methods: 'POST')]
    /** @OA\Post(
     *     path="/api/registration",
     *     summary="Inscription d'un nouvel utilisateur",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données de l'utilisateur à inscrire",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="firstName", type="string", example="Thomas"),
     *             @OA\Property(property="lastName", type="string", example="Dupont"),
     *             @OA\Property(property="email", type="string", example="thomas@email.com"),
     *             @OA\Property(property="password", type="string", example="Mot de passe")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Utilisateur inscrit avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="user", type="string", example="Nom d'utilisateur"),
     *             @OA\Property(property="apiToken", type="string", example="31a023e212f116124a36af14ea0c1c3806eb9378"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string", example="ROLE_USER"))
     *         )
     *     )
     * )
     */
    public function register(Request $request): JsonResponse
{
    $user = $this->serializer->deserialize($request->getContent(), User::class, 'json');

    // Validation AVANT le hashage : sinon le mot de passe validé serait le hash, pas la saisie utilisateur.
    if ($errorResponse = $this->validateOrGetErrorResponse($user)) {
        return $errorResponse;
    }

    $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));
    $user->setCreatedAt(new DateTimeImmutable());

    $roleClient = $this->roleRepository->findOneBy(['libelle' => 'Client']);
    $user->setRole($roleClient);

    $this->manager->persist($user);
    $this->manager->flush();

    return new JsonResponse(
        ['user' => $user->getUserIdentifier(), 'apiToken' => $user->getApiToken(), 'roles' => $user->getRoles()],
        Response::HTTP_CREATED
    );
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
     *      @OA\Response(
     *          response=200,
     *          description="Connexion réussie",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="X-AUTH-TOKEN", type="string")
     *          )
     *      )
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
     *     summary="Récupérer toutes les informations de l'objet User",
     *     @OA\Response(
     *         response=200,
     *         description="Tous les champs utilisateurs retournés",
     *     )
     * )
     */
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        $responseData = $this->serializer->serialize($user, 'json');

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route('/account/edit', name: 'edit', methods: 'PUT')]
    /** @OA\Put(
     *     path="/api/account/edit",
     *     summary="Modifier son compte utilisateur avec l'un ou tous les champs",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Nouvelles données éventuelles de l'utilisateur à mettre à jour",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="firstName", type="string", example="Nouveau prénom"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Utilisateur modifié avec succès"
     *     )
     * )
     */
    public function edit(Request $request): JsonResponse
    {
    $data = $request->toArray();
    $currentUser = $this->getUser();

    // Si on demande un changement de mot de passe, on vérifie l'ancien AVANT toute désérialisation
    // (sinon $currentUser->getPassword() sera déjà écrasé par le nouveau mot de passe en clair)
    if (isset($data['password'])) {
        if (empty($data['currentPassword']) || !$this->passwordHasher->isPasswordValid($currentUser, $data['currentPassword'])) {
            return new JsonResponse(['errors' => ['currentPassword' => 'Mot de passe actuel incorrect.']], Response::HTTP_BAD_REQUEST);
        }
    }

    $user = $this->serializer->deserialize(
        $request->getContent(),
        User::class,
        'json',
        [AbstractNormalizer::OBJECT_TO_POPULATE => $currentUser],
    );

    if ($errorResponse = $this->validateOrGetErrorResponse($user)) {
        return $errorResponse;
    }

    $user->setUpdatedAt(new DateTimeImmutable());

    if (isset($data['password'])) {
        $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));
    }

    $this->manager->flush();

    return new JsonResponse(null, Response::HTTP_NO_CONTENT);
}
#[Route('/forgot-password', name: 'forgot_password', methods: 'POST')]
/** @OA\Post(
 *     path="/api/forgot-password",
 *     summary="Réinitialiser son mot de passe via l'email du compte",
 *     description="Aucun envoi d'email n'est effectué (pas de transport mail configuré dans ce projet) : la réinitialisation se fait directement à partir de l'email et du nouveau mot de passe.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="email", type="string", example="thomas@email.com"),
 *             @OA\Property(property="password", type="string", example="Nouveau mot de passe")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Mot de passe réinitialisé (ou email inconnu, message volontairement générique)"),
 *     @OA\Response(response=400, description="Données manquantes ou mot de passe invalide")
 * )
 */
public function forgotPassword(Request $request, UserRepository $userRepository): JsonResponse
{
    $data = json_decode($request->getContent(), true) ?? [];
    $email = $data['email'] ?? null;
    $newPassword = $data['password'] ?? null;

    if (!$email || !$newPassword) {
        return new JsonResponse(['message' => 'Email et nouveau mot de passe requis.'], Response::HTTP_BAD_REQUEST);
    }

    $user = $userRepository->findOneBy(['email' => $email]);
    if (!$user) {
        // Message volontairement générique : on ne confirme pas si un compte existe pour cet email.
        return new JsonResponse(['message' => 'Si un compte existe avec cet email, le mot de passe a été réinitialisé.'], Response::HTTP_OK);
    }

    $user->setPassword($newPassword);

    if ($errorResponse = $this->validateOrGetErrorResponse($user)) {
        return $errorResponse;
    }

    $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
    $user->setUpdatedAt(new DateTimeImmutable());
    $this->manager->flush();

    return new JsonResponse(['message' => 'Mot de passe réinitialisé avec succès.'], Response::HTTP_OK);
}

#[Route('/user/create-employe', name: 'create_employe', methods: 'POST')]
/** @OA\Post(
 *     path="/api/user/create-employe",
 *     summary="Créer un compte employé (réservé aux administrateurs)",
 *     @OA\RequestBody(
 *         required=true,
 *         description="Données de l'employé à créer",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="firstName", type="string", example="Sacha"),
 *             @OA\Property(property="lastName", type="string", example="Martin"),
 *             @OA\Property(property="email", type="string", example="sacha.martin@vitegourmand.fr"),
 *             @OA\Property(property="password", type="string", example="Mot de passe")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Employé créé avec succès",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="user", type="string", example="Nom d'utilisateur"),
 *             @OA\Property(property="apiToken", type="string", example="31a023e212f116124a36af14ea0c1c3806eb9378"),
 *             @OA\Property(property="roles", type="array", @OA\Items(type="string", example="ROLE_EMPLOYE"))
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Données invalides"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Accès refusé : réservé aux administrateurs"
 *     )
 * )
 */
public function createEmploye(Request $request): JsonResponse
{
    $user = $this->serializer->deserialize($request->getContent(), User::class, 'json');

    if ($errorResponse = $this->validateOrGetErrorResponse($user)) {
        return $errorResponse;
    }

    $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));
    $user->setCreatedAt(new DateTimeImmutable());

    $roleEmploye = $this->roleRepository->findOneBy(['libelle' => 'Employé']);
    $user->setRole($roleEmploye);

    $this->manager->persist($user);
    $this->manager->flush();

    return new JsonResponse(
        ['user' => $user->getUserIdentifier(), 'apiToken' => $user->getApiToken(), 'roles' => $user->getRoles()],
        Response::HTTP_CREATED
    );
}

#[Route('/user/count-clients', name: 'count_clients', methods: 'GET')]
/** @OA\Get(
 *     path="/api/user/count-clients",
 *     summary="Nombre de comptes ayant le rôle Client (réservé admin/employé)",
 *     @OA\Response(
 *         response=200,
 *         description="Nombre de clients inscrits",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="count", type="integer", example=128)
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Accès refusé : réservé aux employés/administrateurs"
 *     )
 * )
 */
public function countClients(UserRepository $userRepository): JsonResponse
{
    if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_EMPLOYE')) {
        return new JsonResponse(
            ['message' => 'Seul un employé ou un administrateur peut consulter cette statistique.'],
            Response::HTTP_FORBIDDEN
        );
    }

    // On ne compte que les comptes "Client" : ni les employés, ni les administrateurs.
    $roleClient = $this->roleRepository->findOneBy(['libelle' => 'Client']);
    $count = $roleClient ? $userRepository->count(['role' => $roleClient]) : 0;

    return new JsonResponse(['count' => $count]);
}

#[Route('/user', name: 'list_users', methods: 'GET')]
/** @OA\Get(
 *     path="/api/user",
 *     summary="Lister tous les comptes utilisateurs (réservé aux administrateurs)",
 *     @OA\Response(
 *         response=200,
 *         description="Liste de tous les comptes, tous rôles confondus",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="firstName", type="string", example="Camille"),
 *                 @OA\Property(property="lastName", type="string", example="Client"),
 *                 @OA\Property(property="email", type="string", example="camille@test.fr"),
 *                 @OA\Property(property="role", type="string", example="Client"),
 *                 @OA\Property(property="createdAt", type="string", format="date", example="03/01/2026")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Accès refusé : réservé aux administrateurs"
 *     )
 * )
 */
public function listUsers(UserRepository $userRepository): JsonResponse
{
    // Cette section du tableau de bord (gestion des comptes) est réservée
    // aux administrateurs, contrairement aux autres sections accessibles
    // aux employés.
    if (!$this->isGranted('ROLE_ADMIN')) {
        return new JsonResponse(
            ['message' => 'Seul un administrateur peut consulter la liste des comptes.'],
            Response::HTTP_FORBIDDEN
        );
    }

    $users = $userRepository->findBy([], ['id' => 'DESC']);

    // On ne renvoie jamais le mot de passe hashé ni l'apiToken.
    $data = array_map(static fn (User $u) => [
        'id' => $u->getId(),
        'firstName' => $u->getFirstName(),
        'lastName' => $u->getLastName(),
        'email' => $u->getEmail(),
        'phoneNumber' => $u->getphoneNumber(),
        'role' => $u->getRole()?->getLibelle(),
        'createdAt' => $u->getCreatedAt()?->format('d/m/Y'),
    ], $users);

    return new JsonResponse($data, Response::HTTP_OK);
}

#[Route('/user/{id}', name: 'edit_user', methods: 'PUT', requirements: ['id' => '\d+'])]
/** @OA\Put(
 *     path="/api/user/{id}",
 *     summary="Modifier le compte d'un utilisateur par ID (réservé aux administrateurs)",
 *     description="Permet à un administrateur de modifier le prénom, nom, téléphone et rôle d'un compte, depuis le tableau de bord.",
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="firstName", type="string", example="Camille"),
 *             @OA\Property(property="lastName", type="string", example="Client"),
 *             @OA\Property(property="phoneNumber", type="string", example="0612345678"),
 *             @OA\Property(property="role", type="string", enum={"Client", "Employé", "Admin"}, example="Employé")
 *         )
 *     ),
 *     @OA\Response(response=204, description="Compte modifié avec succès"),
 *     @OA\Response(response=400, description="Rôle invalide ou données invalides"),
 *     @OA\Response(response=403, description="Accès refusé : réservé aux administrateurs"),
 *     @OA\Response(response=404, description="Compte introuvable")
 * )
 */
public function editUser(int $id, Request $request, UserRepository $userRepository): JsonResponse
{
    if (!$this->isGranted('ROLE_ADMIN')) {
        return new JsonResponse(
            ['message' => 'Seul un administrateur peut modifier un compte.'],
            Response::HTTP_FORBIDDEN
        );
    }

    $user = $userRepository->findOneBy(['id' => $id]);
    if (!$user) {
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    $data = json_decode($request->getContent(), true) ?? [];

    if (array_key_exists('firstName', $data) && $data['firstName']) {
        $user->setFirstName($data['firstName']);
    }
    if (array_key_exists('lastName', $data) && $data['lastName']) {
        $user->setLastName($data['lastName']);
    }
    if (array_key_exists('phoneNumber', $data)) {
        $user->setphoneNumber($data['phoneNumber'] ?: null);
    }
    if (array_key_exists('role', $data) && $data['role']) {
        $role = $this->roleRepository->findOneBy(['libelle' => $data['role']]);
        if (!$role) {
            return new JsonResponse(['message' => 'Rôle invalide.'], Response::HTTP_BAD_REQUEST);
        }
        $user->setRole($role);
    }

    if ($errorResponse = $this->validateOrGetErrorResponse($user)) {
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
public function deleteUser(int $id, UserRepository $userRepository): JsonResponse
{
    if (!$this->isGranted('ROLE_ADMIN')) {
        return new JsonResponse(
            ['message' => 'Seul un administrateur peut supprimer un compte.'],
            Response::HTTP_FORBIDDEN
        );
    }

    $user = $userRepository->findOneBy(['id' => $id]);
    if (!$user) {
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    // Empêche un admin de se supprimer lui-même depuis cet écran (il se
    // retrouverait déconnecté en plein milieu de la gestion des comptes).
    if ($this->getUser() && $user->getId() === $this->getUser()->getId()) {
        return new JsonResponse(
            ['message' => 'Vous ne pouvez pas supprimer votre propre compte depuis cet écran.'],
            Response::HTTP_BAD_REQUEST
        );
    }

    try {
        $this->manager->remove($user);
        $this->manager->flush();
    } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException) {
        // Pas de suppression en cascade en base (commande/avis référencent user_id
        // sans ON DELETE CASCADE) : on renvoie un message clair plutôt qu'un 500.
        return new JsonResponse(
            ['message' => 'Impossible de supprimer ce compte : il a des commandes et/ou des avis liés.'],
            Response::HTTP_CONFLICT
        );
    }

    return new JsonResponse(null, Response::HTTP_NO_CONTENT);
}
}