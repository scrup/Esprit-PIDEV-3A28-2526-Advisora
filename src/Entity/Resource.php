<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ResourceRepository;

#[ORM\Entity(repositoryClass: ResourceRepository::class)]
#[ORM\Table(name: 'resources')]
class Resource
{
    public const STATUS_AVAILABLE = 'AVAILABLE';
    public const STATUS_RESERVED = 'RESERVED';
    public const STATUS_UNAVAILABLE = 'UNAVAILABLE';

    public const STATUSES = [
        self::STATUS_AVAILABLE => 'Disponible',
        self::STATUS_RESERVED => 'Reservee',
        self::STATUS_UNAVAILABLE => 'Indisponible',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idRs', type: 'integer')]
    private ?int $idRs = null;

    public function getIdRs(): ?int
    {
        return $this->idRs;
    }

    public function setIdRs(int $idRs): self
    {
        $this->idRs = $idRs;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $availabilityStatusRs = null;

    public function getAvailabilityStatusRs(): ?string
    {
        return $this->availabilityStatusRs;
    }

    public function setAvailabilityStatusRs(string $availabilityStatusRs): self
    {
        $this->availabilityStatusRs = $availabilityStatusRs;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $nomRs = null;

    public function getNomRs(): ?string
    {
        return $this->nomRs;
    }

    public function setNomRs(string $nomRs): self
    {
        $this->nomRs = $nomRs;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $QuantiteRs = null;

    public function getQuantiteRs(): ?int
    {
        return $this->QuantiteRs;
    }

    public function setQuantiteRs(int $QuantiteRs): self
    {
        $this->QuantiteRs = $QuantiteRs;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $prixRs = null;

    public function getPrixRs(): ?float
    {
        return $this->prixRs;
    }

    public function setPrixRs(float $prixRs): self
    {
        $this->prixRs = $prixRs;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Cataloguefournisseur::class, inversedBy: 'resources')]
    #[ORM\JoinColumn(name: 'idFr', referencedColumnName: 'idFr')]
    private ?Cataloguefournisseur $cataloguefournisseur = null;

    public function getCataloguefournisseur(): ?Cataloguefournisseur
    {
        return $this->cataloguefournisseur;
    }

    public function setCataloguefournisseur(?Cataloguefournisseur $cataloguefournisseur): self
    {
        $this->cataloguefournisseur = $cataloguefournisseur;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $imageUrlRs = null;

    public function getImageUrlRs(): ?string
    {
        return $this->imageUrlRs;
    }

    public function setImageUrlRs(?string $imageUrlRs): self
    {
        $this->imageUrlRs = $imageUrlRs;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $imageSourceRs = null;

    public function getImageSourceRs(): ?string
    {
        return $this->imageSourceRs;
    }

    public function setImageSourceRs(?string $imageSourceRs): self
    {
        $this->imageSourceRs = $imageSourceRs;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $thumbnailUrlRs = null;

    public function getThumbnailUrlRs(): ?string
    {
        return $this->thumbnailUrlRs;
    }

    public function setThumbnailUrlRs(?string $thumbnailUrlRs): self
    {
        $this->thumbnailUrlRs = $thumbnailUrlRs;
        return $this;
    }

    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'resources')]
    private Collection $projects;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        if (!$this->projects instanceof Collection) {
            $this->projects = new ArrayCollection();
        }
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if (!$this->getProjects()->contains($project)) {
            $this->getProjects()->add($project);
        }
        return $this;
    }

    public function removeProject(Project $project): self
    {
        $this->getProjects()->removeElement($project);
        return $this;
    }

    public function getId(): ?int
    {
        return $this->idRs;
    }

    public function getName(): ?string
    {
        return $this->nomRs;
    }

    public function setName(string $name): self
    {
        $this->nomRs = $name;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->normalizeStatusValue($this->availabilityStatusRs);
    }

    public function setStatus(string $status): self
    {
        $this->availabilityStatusRs = $status;

        return $this;
    }

    public function getStatusLabel(): string
    {
        $status = $this->normalizeStatusValue($this->getStatus());

        return self::STATUSES[$status] ?? ($status ?: 'Statut inconnu');
    }

    public function getQuantity(): ?int
    {
        return $this->QuantiteRs;
    }

    public function setQuantity(int $quantity): self
    {
        $this->QuantiteRs = $quantity;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->prixRs;
    }

    public function setPrice(float $price): self
    {
        $this->prixRs = $price;

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

        return 'Non assigne';
    }

    private function normalizeStatusValue(?string $status): string
    {
        $normalized = strtoupper(trim((string) $status));

        return match ($normalized) {
            'AVAILABLE', 'DISPONIBLE' => self::STATUS_AVAILABLE,
            'RESERVED', 'LOW_STOCK', 'MAINTENANCE', 'RESERVEE', 'RESERVE' => self::STATUS_RESERVED,
            'UNAVAILABLE', 'OUT_OF_STOCK', 'INDISPONIBLE' => self::STATUS_UNAVAILABLE,
            default => $normalized,
        };
    }

}
