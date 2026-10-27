<?php

namespace App\Entity;

use App\Repository\AvisRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AvisRepository::class)]
class Avis
{
    // Statut à trois états : un avis commence toujours "En attente", puis un
    // employé/admin le fait basculer vers "Publié" ou "Refusé".
    public const STATUT_ATTENTE = 'En attente';
    public const STATUT_PUBLIE = 'Publié';
    public const STATUT_REFUSE = 'Refusé';
    public const STATUTS = [self::STATUT_ATTENTE, self::STATUT_PUBLIE, self::STATUT_REFUSE];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'la note doit être comprise entre {{ min }} et {{ max }}.'
    )]
    private ?int $note = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: self::STATUTS, message: 'Le statut doit être : En attente, Publié ou Refusé.')]
    private ?string $statut = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    // L'avis est donné depuis une commande "Terminée" (un seul avis par commande).
    // Nullable pour conserver les avis créés avant l'ajout de ce lien.
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?Commande $commande = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNote(): ?int
    {
        return $this->note;
    }

    public function setNote(int $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;

        return $this;
    }
}
