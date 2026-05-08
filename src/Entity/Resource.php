<?php

namespace App\Entity;

use App\Repository\ResourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: ResourceRepository::class)]
#[ORM\Table(name: 'resource')]
class Resource
{
    public const STATUS_AVAILABLE = 'AVAILABLE';
    public const STATUS_RESERVED = 'RESERVED';
    public const STATUS_UNAVAILABLE = 'UNAVAILABLE';

    public const STATUSES = [
        self::STATUS_AVAILABLE => 'Disponible',
        self::STATUS_RESERVED => 'Réservée',
        self::STATUS_UNAVAILABLE => 'Indisponible',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idRs', type: 'integer')]
    private ?int $idRs = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $availabilityStatusRs = self::STATUS_AVAILABLE;

    #[ORM\Column(type: 'string', nullable: false)]
    #[Gedmo\Translatable]
    private string $nomRs = '';

    #[Gedmo\Locale]
    private ?string $locale = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $QuantiteRs = 0;

    #[ORM\Column(type: 'float', nullable: false)]
    private float $prixRs = 0.0;

    #[ORM\ManyToOne(targetEntity: Cataloguefournisseur::class, inversedBy: 'resources')]
    #[ORM\JoinColumn(name: 'cataloguefournisseur_id', referencedColumnName: 'idFr', nullable: true, onDelete: 'SET NULL')]
    private ?Cataloguefournisseur $cataloguefournisseur = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $imageUrlRs = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $imageSourceRs = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $thumbnailUrlRs = null;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'resources')]
    private Collection $projects;

    public function __construct()
    {
        $this->availabilityStatusRs = self::STATUS_AVAILABLE;
        $this->nomRs = '';
        $this->QuantiteRs = 0;
        $this->prixRs = 0.0;
        $this->projects = new ArrayCollection();
    }

    public function getIdRs(): ?int
    {
        return $this->idRs;
    }

    public function setIdRs(int $idRs): self
    {
        $this->idRs = $idRs;

        return $this;
    }

    public function getAvailabilityStatusRs(): string
    {
        return isset($this->availabilityStatusRs)
            ? $this->availabilityStatusRs
            : self::STATUS_AVAILABLE;
    }

    public function setAvailabilityStatusRs(?string $availabilityStatusRs): self
    {
        $this->availabilityStatusRs = $availabilityStatusRs ?: self::STATUS_AVAILABLE;

        return $this;
    }

    public function getNomRs(): string
    {
        return isset($this->nomRs) ? $this->nomRs : '';
    }

    public function setNomRs(?string $nomRs): self
    {
        $this->nomRs = $nomRs ?? '';

        return $this;
    }

    public function getQuantiteRs(): int
    {
        return isset($this->QuantiteRs) ? $this->QuantiteRs : 0;
    }

    public function setQuantiteRs(?int $QuantiteRs): self
    {
        $this->QuantiteRs = $QuantiteRs ?? 0;

        return $this;
    }

    public function getPrixRs(): float
    {
        return isset($this->prixRs) ? $this->prixRs : 0.0;
    }

    public function setPrixRs(?float $prixRs): self
    {
        $this->prixRs = $prixRs ?? 0.0;

        return $this;
    }

    public function getCataloguefournisseur(): ?Cataloguefournisseur
    {
        return $this->cataloguefournisseur;
    }

    public function setCataloguefournisseur(?Cataloguefournisseur $cataloguefournisseur): self
    {
        $this->cataloguefournisseur = $cataloguefournisseur;

        return $this;
    }

    public function getImageUrlRs(): ?string
    {
        return $this->imageUrlRs;
    }

    public function setImageUrlRs(?string $imageUrlRs): self
    {
        $this->imageUrlRs = $imageUrlRs;

        return $this;
    }

    public function getImageSourceRs(): ?string
    {
        return $this->imageSourceRs;
    }

    public function setImageSourceRs(?string $imageSourceRs): self
    {
        $this->imageSourceRs = $imageSourceRs;

        return $this;
    }

    public function getThumbnailUrlRs(): ?string
    {
        return $this->thumbnailUrlRs;
    }

    public function setThumbnailUrlRs(?string $thumbnailUrlRs): self
    {
        $this->thumbnailUrlRs = $thumbnailUrlRs;

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->addResource($this);
        }

        return $this;
    }

    public function removeProject(Project $project): self
    {
        if ($this->projects->removeElement($project)) {
            $project->removeResource($this);
        }

        return $this;
    }

    public function getId(): ?int
    {
        return $this->idRs;
    }

    public function getName(): string
    {
        return $this->getNomRs();
    }

    public function setName(?string $name): self
    {
        $this->nomRs = $name ?? '';

        return $this;
    }

    public function getStatus(): string
    {
        return $this->normalizeStatusValue($this->getAvailabilityStatusRs());
    }

    public function setStatus(?string $status): self
    {
        $this->availabilityStatusRs = $status ?: self::STATUS_AVAILABLE;

        return $this;
    }

    public function getStatusLabel(): string
    {
        $status = $this->normalizeStatusValue($this->getStatus());

        return self::STATUSES[$status] ?? ($status ?: 'Statut inconnu');
    }

    public function getQuantity(): int
    {
        return $this->getQuantiteRs();
    }

    public function setQuantity(?int $quantity): self
    {
        $this->QuantiteRs = $quantity ?? 0;

        return $this;
    }

    public function getPrice(): float
    {
        return $this->getPrixRs();
    }

    public function setPrice(?float $price): self
    {
        $this->prixRs = $price ?? 0.0;

        return $this;
    }

    public function getPrimaryImageUrl(): ?string
    {
        return $this->imageUrlRs ?: $this->thumbnailUrlRs;
    }

    public function getSupplierDisplayName(): string
    {
        if ($this->cataloguefournisseur?->getFournisseur()) {
            return (string) $this->cataloguefournisseur->getFournisseur();
        }

        if ($this->cataloguefournisseur?->getNomFr()) {
            return (string) $this->cataloguefournisseur->getNomFr();
        }

        return 'Non assigné';
    }

    private function normalizeStatusValue(?string $status): string
    {
        $normalized = strtoupper(trim((string) $status));

        return match ($normalized) {
            'AVAILABLE', 'DISPONIBLE' => self::STATUS_AVAILABLE,
            'RESERVED', 'LOW_STOCK', 'MAINTENANCE', 'RESERVEE', 'RÉSERVÉE', 'RESERVE', 'RÉSERVÉ' => self::STATUS_RESERVED,
            'UNAVAILABLE', 'OUT_OF_STOCK', 'INDISPONIBLE' => self::STATUS_UNAVAILABLE,
            default => $normalized ?: self::STATUS_AVAILABLE,
        };
    }

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }
}