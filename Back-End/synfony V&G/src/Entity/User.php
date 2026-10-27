<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Groupes de validation utilisés :
 *  - Default      : champs toujours contrôlés (nom, prénom, email, format du téléphone...)
 *  - registration : champs obligatoires à l'inscription d'un client (GSM + adresse postale)
 *  - password     : contrôle du mot de passe EN CLAIR ($plainPassword), avant hashage
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /** 10 caractères minimum, au moins 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial. */
    public const PASSWORD_MIN_LENGTH = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank(message: "Le prénom est obligatoire.")]
    #[Assert\Length(
        min: 2,
        max: 32,
        minMessage: "Le prénom doit contenir au moins {{ limit }} caractères.",
        maxMessage: "Le prénom ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Assert\Regex(
        pattern: "/^[a-zA-ZÀ-ÿ\s\-']+$/",
        message: "Le prénom contient des caractères non autorisés."
    )]
    private ?string $firstName = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    #[Assert\Length(
        min: 2,
        max: 64,
        minMessage: "Le nom doit contenir au moins {{ limit }} caractères.",
        maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Assert\Regex(
        pattern: "/^[a-zA-ZÀ-ÿ\s\-']+$/",
        message: "Le nom contient des caractères non autorisés."
    )]
    private ?string $lastName = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\NotBlank(message: "Le numéro de GSM est obligatoire.", groups: ['registration'])]
    #[Assert\Regex(
        pattern: "/^0[67]\d{8}$/",
        message: "Le numéro de téléphone doit être un numéro de mobile français valide (ex : 0612345678)."
    )]
    private ?string $phoneNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: "Les allergies ne peuvent pas dépasser {{ limit }} caractères.")]
    private ?string $allergy = null;

    // Obligatoire à l'inscription ("adresse mail et postale"), reprise ensuite
    // pour pré-remplir l'adresse de livraison lors d'une commande.
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: "L'adresse postale est obligatoire.", groups: ['registration'])]
    #[Assert\Length(max: 255, maxMessage: "L'adresse ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $adresse = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'email n'est pas valide.")]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $apiToken;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    /** @var string|null Le mot de passe HASHÉ (jamais le mot de passe en clair) */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * Mot de passe en clair, uniquement en mémoire (jamais enregistré en base).
     * C'est lui qui est validé, puis hashé dans $password par le contrôleur.
     */
    #[Assert\NotBlank(message: "Le mot de passe est obligatoire.", groups: ['password'])]
    #[Assert\Length(
        min: self::PASSWORD_MIN_LENGTH,
        minMessage: "Le mot de passe doit contenir au moins {{ limit }} caractères.",
        groups: ['password']
    )]
    #[Assert\Regex(pattern: '/[a-z]/', message: "Le mot de passe doit contenir au moins une minuscule.", groups: ['password'])]
    #[Assert\Regex(pattern: '/[A-Z]/', message: "Le mot de passe doit contenir au moins une majuscule.", groups: ['password'])]
    #[Assert\Regex(pattern: '/\d/', message: "Le mot de passe doit contenir au moins un chiffre.", groups: ['password'])]
    #[Assert\Regex(pattern: '/[^a-zA-Z0-9]/', message: "Le mot de passe doit contenir au moins un caractère spécial.", groups: ['password'])]
    private ?string $plainPassword = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Role $role = null;

    // Adresse email confirmée via le lien reçu par mail à l'inscription.
    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    // Un administrateur peut rendre un compte (employé) inutilisable sans le supprimer.
    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    // On ne stocke que l'empreinte SHA-256 des jetons envoyés par mail :
    // une fuite de la base ne permet pas de réutiliser un lien.
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $emailVerificationTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $emailVerificationExpiresAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $resetPasswordTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $resetPasswordExpiresAt = null;

    /** @throws \Exception */
    public function __construct()
    {
        $this->apiToken = bin2hex(random_bytes(20));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getphoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setphoneNumber(?string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getAllergy(): ?string
    {
        return $this->allergy;
    }

    public function setAllergy(?string $allergy): static
    {
        $this->allergy = $allergy;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    #[Ignore]
    public function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    public function setApiToken(string $apiToken): static
    {
        $this->apiToken = $apiToken;

        return $this;
    }

    /** Génère un nouveau jeton d'API : toutes les sessions ouvertes avec l'ancien sont coupées. */
    public function regenerateApiToken(): static
    {
        $this->apiToken = bin2hex(random_bytes(20));

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /** @deprecated since Symfony 5.3, use getUserIdentifier instead */
    public function getUsername(): string
    {
        return (string) $this->email;
    }

    /** @see UserInterface */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        if ($this->role) {
            if ($this->role->getLibelle() === 'Admin') {
                $roles[] = 'ROLE_ADMIN';
            }
            if ($this->role->getLibelle() === 'Employé') {
                $roles[] = 'ROLE_EMPLOYE';
            }
        }

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /** @see PasswordAuthenticatedUserInterface */
    #[Ignore]
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    #[Ignore]
    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    #[Ignore]
    public function getEmailVerificationTokenHash(): ?string
    {
        return $this->emailVerificationTokenHash;
    }

    #[Ignore]
    public function getEmailVerificationExpiresAt(): ?DateTimeImmutable
    {
        return $this->emailVerificationExpiresAt;
    }

    public function setEmailVerificationToken(?string $tokenHash, ?DateTimeImmutable $expiresAt): static
    {
        $this->emailVerificationTokenHash = $tokenHash;
        $this->emailVerificationExpiresAt = $expiresAt;

        return $this;
    }

    #[Ignore]
    public function getResetPasswordTokenHash(): ?string
    {
        return $this->resetPasswordTokenHash;
    }

    #[Ignore]
    public function getResetPasswordExpiresAt(): ?DateTimeImmutable
    {
        return $this->resetPasswordExpiresAt;
    }

    public function setResetPasswordToken(?string $tokenHash, ?DateTimeImmutable $expiresAt): static
    {
        $this->resetPasswordTokenHash = $tokenHash;
        $this->resetPasswordExpiresAt = $expiresAt;

        return $this;
    }

    /**
     * Returning a salt is only needed, if you are not using a modern
     * hashing algorithm (e.g. bcrypt or sodium) in your security.yaml.
     *
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /** @see UserInterface */
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): static
    {
        $this->role = $role;

        return $this;
    }
}
