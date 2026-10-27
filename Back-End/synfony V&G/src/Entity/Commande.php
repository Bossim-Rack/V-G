<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    public const STATUT_EN_ATTENTE = 'En attente';
    public const STATUT_ACCEPTEE = 'Accepté';
    public const STATUT_EN_PREPARATION = 'En préparation';
    public const STATUT_EN_LIVRAISON = 'En cours de livraison';
    public const STATUT_LIVREE = 'Livré';
    public const STATUT_ATTENTE_MATERIEL = 'En attente du retour de matériel';
    public const STATUT_TERMINEE = 'Terminée';
    public const STATUT_ANNULEE = 'Annulée';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_ACCEPTEE,
        self::STATUT_EN_PREPARATION,
        self::STATUT_EN_LIVRAISON,
        self::STATUT_LIVREE,
        self::STATUT_ATTENTE_MATERIEL,
        self::STATUT_TERMINEE,
        self::STATUT_ANNULEE,
    ];

    // Statuts après lesquels plus rien ne bouge.
    public const STATUTS_FINAUX = [self::STATUT_TERMINEE, self::STATUT_ANNULEE];

    // Moyen par lequel l'employé a joint le client avant de modifier/annuler sa commande.
    public const MODES_CONTACT = ['GSM', 'Email'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_commande = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_prestation = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $heure_livraison = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $prix_menu = null;

    #[ORM\Column(nullable: true)]
    private ?int $nombre_personne = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $prix_livraison = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(nullable: true)]
    private ?bool $pret_materiel = null;

    #[ORM\Column(nullable: true)]
    private ?bool $restitution_materiel = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Menu $menu = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    // Renseignés uniquement quand un employé annule la commande.
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motif_annulation = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $mode_contact = null;

    /** Suivi : chaque changement de statut, avec sa date et son heure. */
    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: CommandeHistorique::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['changedAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $historiques;

    public function __construct()
    {
        $this->historiques = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateCommande(): ?\DateTimeInterface
    {
        return $this->date_commande;
    }

    public function setDateCommande(?\DateTimeInterface $date_commande): static
    {
        $this->date_commande = $date_commande;

        return $this;
    }

    public function getDatePrestation(): ?\DateTimeInterface
    {
        return $this->date_prestation;
    }

    public function setDatePrestation(?\DateTimeInterface $date_prestation): static
    {
        $this->date_prestation = $date_prestation;

        return $this;
    }

    public function getHeureLivraison(): ?string
    {
        return $this->heure_livraison;
    }

    public function setHeureLivraison(?string $heure_livraison): static
    {
        $this->heure_livraison = $heure_livraison;

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

    public function getPrixMenu(): ?string
    {
        return $this->prix_menu;
    }

    public function setPrixMenu(?string $prix_menu): static
    {
        $this->prix_menu = $prix_menu;

        return $this;
    }

    public function getNombrePersonne(): ?int
    {
        return $this->nombre_personne;
    }

    public function setNombrePersonne(?int $nombre_personne): static
    {
        $this->nombre_personne = $nombre_personne;

        return $this;
    }

    public function getPrixLivraison(): ?string
    {
        return $this->prix_livraison;
    }

    public function setPrixLivraison(?string $prix_livraison): static
    {
        $this->prix_livraison = $prix_livraison;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function isPretMateriel(): ?bool
    {
        return $this->pret_materiel;
    }

    public function setPretMateriel(?bool $pret_materiel): static
    {
        $this->pret_materiel = $pret_materiel;

        return $this;
    }

    public function isRestitutionMateriel(): ?bool
    {
        return $this->restitution_materiel;
    }

    public function setRestitutionMateriel(?bool $restitution_materiel): static
    {
        $this->restitution_materiel = $restitution_materiel;

        return $this;
    }

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): static
    {
        $this->menu = $menu;

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

    public function getMotifAnnulation(): ?string
    {
        return $this->motif_annulation;
    }

    public function setMotifAnnulation(?string $motif_annulation): static
    {
        $this->motif_annulation = $motif_annulation;

        return $this;
    }

    public function getModeContact(): ?string
    {
        return $this->mode_contact;
    }

    public function setModeContact(?string $mode_contact): static
    {
        $this->mode_contact = $mode_contact;

        return $this;
    }

    /** @return Collection<int, CommandeHistorique> */
    public function getHistoriques(): Collection
    {
        return $this->historiques;
    }

    /**
     * Change le statut ET trace le changement dans l'historique (suivi de commande).
     */
    public function changerStatut(string $statut, ?string $commentaire = null, ?User $auteur = null): static
    {
        $this->statut = $statut;

        $historique = (new CommandeHistorique())
            ->setCommande($this)
            ->setStatut($statut)
            ->setCommentaire($commentaire)
            ->setAuteur($auteur)
            ->setChangedAt(new \DateTimeImmutable());
        $this->historiques->add($historique);

        return $this;
    }

    /** Montant total TTC : prix par personne (réduction incluse) x nb de personnes + livraison. */
    public function getMontantTotal(): float
    {
        return round(((float) $this->prix_menu) * ((int) $this->nombre_personne) + (float) $this->prix_livraison, 2);
    }
}
