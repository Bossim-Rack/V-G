<?php

namespace App\Entity;

use App\Repository\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuRepository::class)]
class Menu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $title_menu = null;

    #[ORM\Column]
    private ?int $nombre_personne_minimum = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prix_par_personne = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $quantite_restante = null;

    #[ORM\Column(type: Types::BLOB, nullable: true)]
    private $photo_menu = null;

    #[ORM\ManyToMany(targetEntity: Plat::class, inversedBy: 'menus')]
    private Collection $plats;

    #[ORM\ManyToMany(targetEntity: Theme::class, inversedBy: 'menus')]
    private Collection $themes;

    // Distinct du champ texte "regime" ci-dessus (calculé automatiquement à
    // partir des allergènes des plats inclus) : ici, une sélection manuelle
    // de régimes alimentaires (Végétarien, Sans gluten, Végétalien, ...)
    // choisis dans la liste de la table "regime", avec création à la volée
    // si besoin — même principe que les thèmes.
    #[ORM\ManyToMany(targetEntity: Regime::class, inversedBy: 'menus')]
    private Collection $regimesAlimentaires;

    public function __construct()
    {
        $this->plats = new ArrayCollection();
        $this->themes = new ArrayCollection();
        $this->regimesAlimentaires = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitleMenu(): ?string
    {
        return $this->title_menu;
    }

    public function setTitleMenu(string $title_menu): static
    {
        $this->title_menu = $title_menu;

        return $this;
    }

    public function getNombrePersonneMinimum(): ?int
    {
        return $this->nombre_personne_minimum;
    }

    public function setNombrePersonneMinimum(int $nombre_personne_minimum): static
    {
        $this->nombre_personne_minimum = $nombre_personne_minimum;

        return $this;
    }

    public function getPrixParPersonne(): ?string
    {
        return $this->prix_par_personne;
    }

    public function setPrixParPersonne(string $prix_par_personne): static
    {
        $this->prix_par_personne = $prix_par_personne;

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

    public function getQuantiteRestante(): ?int
    {
        return $this->quantite_restante;
    }

    public function setQuantiteRestante(?int $quantite_restante): static
    {
        $this->quantite_restante = $quantite_restante;

        return $this;
    }

    public function getPhotoMenu()
    {
        return $this->photo_menu;
    }

    public function setPhotoMenu($photo_menu): static
    {
        $this->photo_menu = $photo_menu;

        return $this;
    }

    /**
     * @return Collection<int, Plat>
     */
    public function getPlats(): Collection
    {
        return $this->plats;
    }

    public function addPlat(Plat $plat): static
    {
        if (!$this->plats->contains($plat)) {
            $this->plats->add($plat);
        }

        return $this;
    }

    public function removePlat(Plat $plat): static
    {
        $this->plats->removeElement($plat);

        return $this;
    }

    /**
     * @return Collection<int, Theme>
     */
    public function getThemes(): Collection
    {
        return $this->themes;
    }

    public function addTheme(Theme $theme): static
    {
        if (!$this->themes->contains($theme)) {
            $this->themes->add($theme);
        }

        return $this;
    }

    public function removeTheme(Theme $theme): static
    {
        $this->themes->removeElement($theme);

        return $this;
    }

    /**
     * @return Collection<int, Regime>
     */
    public function getRegimesAlimentaires(): Collection
    {
        return $this->regimesAlimentaires;
    }

    public function addRegimeAlimentaire(Regime $regime): static
    {
        if (!$this->regimesAlimentaires->contains($regime)) {
            $this->regimesAlimentaires->add($regime);
        }

        return $this;
    }

    public function removeRegimeAlimentaire(Regime $regime): static
    {
        $this->regimesAlimentaires->removeElement($regime);

        return $this;
    }
}
