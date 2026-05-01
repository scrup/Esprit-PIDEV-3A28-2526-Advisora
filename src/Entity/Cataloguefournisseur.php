<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Repository\CataloguefournisseurRepository;

#[ORM\Entity(repositoryClass: CataloguefournisseurRepository::class)]
#[ORM\Table(name: 'cataloguefournisseur')]
class Cataloguefournisseur
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EMPTY = 'empty';

    public function __construct()
    {
        $this->resources = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idFr', type: 'integer')]
    private ?int $idFr = null;

    public function getIdFr(): ?int
    {
        return $this->idFr;
    }

    public function setIdFr(int $idFr): self
    {
        $this->idFr = $idFr;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Gedmo\Translatable]
    private ?string $nomFr = null;

    public function getNomFr(): ?string
    {
        return $this->nomFr;
    }

    public function setNomFr(string $nomFr): self
    {
        $this->nomFr = $nomFr;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $quantite = null;

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): self
    {
        $this->quantite = $quantite;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    #[Gedmo\Translatable]
    private ?string $fournisseur = null;

    public function getFournisseur(): ?string
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?string $fournisseur): self
    {
        $this->fournisseur = $fournisseur;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $emailFr = null;

    public function getEmailFr(): ?string
    {
        return $this->emailFr;
    }

    public function setEmailFr(?string $emailFr): self
    {
        $this->emailFr = $emailFr;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    #[Gedmo\Translatable]
    private ?string $localisationFr = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    public function getLocalisationFr(): ?string
    {
        return $this->localisationFr;
    }

    public function setLocalisationFr(?string $localisationFr): self
    {
        $this->localisationFr = $localisationFr;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $numTelFr = null;

    public function getNumTelFr(): ?string
    {
        return $this->numTelFr;
    }

    public function setNumTelFr(?string $numTelFr): self
    {
        $this->numTelFr = $numTelFr;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Resource::class, mappedBy: 'cataloguefournisseur')]
    private Collection $resources;

    /**
     * @return Collection<int, Resource>
     */
    public function getResources(): Collection
    {
        return $this->resources;
    }

    public function addResource(Resource $resource): self
    {
        if (!$this->getResources()->contains($resource)) {
            $this->getResources()->add($resource);
        }
        return $this;
    }

    public function removeResource(Resource $resource): self
    {
        $this->getResources()->removeElement($resource);
        return $this;
    }

    public function getId(): ?int
    {
        return $this->idFr;
    }

    public function getDisplayName(): string
    {
        $primary = trim((string) ($this->fournisseur ?: $this->nomFr));

        return $primary !== '' ? $primary : 'Fournisseur #' . ($this->idFr ?? '');
    }

    public function getStatus(): string
    {
        return ($this->quantite ?? 0) > 0 ? self::STATUS_ACTIVE : self::STATUS_EMPTY;
    }

    public function getStatusLabel(): string
    {
        return $this->getStatus() === self::STATUS_ACTIVE ? 'Actif' : 'Vide';
    }

    public function isActiveSupplier(): bool
    {
        return $this->getStatus() === self::STATUS_ACTIVE;
    }

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

}
