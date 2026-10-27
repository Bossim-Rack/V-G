<?php

namespace App\Entity;

use App\Repository\CommandeHistoriqueRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une ligne par changement de statut d'une commande : c'est ce qui permet
 * à l'utilisateur de voir le suivi (statut + date et heure de modification).
 */
#[ORM\Entity(repositoryClass: CommandeHistoriqueRepository::class)]
class CommandeHistorique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'historiques')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Commande $commande = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column]
    private ?DateTimeImmutable $changedAt = null;

    // Qui a fait le changement (client ou employé). Mis à NULL si le compte est supprimé.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $auteur = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getChangedAt(): ?DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function setChangedAt(DateTimeImmutable $changedAt): static
    {
        $this->changedAt = $changedAt;

        return $this;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function setAuteur(?User $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }
}
