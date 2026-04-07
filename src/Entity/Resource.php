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

}
