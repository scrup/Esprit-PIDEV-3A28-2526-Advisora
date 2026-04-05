<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ObjectiveRepository;

#[ORM\Entity(repositoryClass: ObjectiveRepository::class)]
#[ORM\Table(name: 'objectives')]
class Objective
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idOb = null;

    public function getIdOb(): ?int
    {
        return $this->idOb;
    }

    public function setIdOb(int $idOb): self
    {
        $this->idOb = $idOb;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $descriptionOb = null;

    public function getDescriptionOb(): ?string
    {
        return $this->descriptionOb;
    }

    public function setDescriptionOb(string $descriptionOb): self
    {
        $this->descriptionOb = $descriptionOb;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $priorityOb = null;

    public function getPriorityOb(): ?int
    {
        return $this->priorityOb;
    }

    public function setPriorityOb(?int $priorityOb): self
    {
        $this->priorityOb = $priorityOb;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Strategie::class, inversedBy: 'objectives')]
    #[ORM\JoinColumn(name: 'ids', referencedColumnName: 'idStrategie')]
    private ?Strategie $strategie = null;

    public function getStrategie(): ?Strategie
    {
        return $this->strategie;
    }

    public function setStrategie(?Strategie $strategie): self
    {
        $this->strategie = $strategie;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $nomObj = null;

    public function getNomObj(): ?string
    {
        return $this->nomObj;
    }

    public function setNomObj(?string $nomObj): self
    {
        $this->nomObj = $nomObj;
        return $this;
    }

}
