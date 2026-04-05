<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\DecisionRepository;

#[ORM\Entity(repositoryClass: DecisionRepository::class)]
#[ORM\Table(name: 'decisions')]
class Decision
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idD = null;

    public function getIdD(): ?int
    {
        return $this->idD;
    }

    public function setIdD(int $idD): self
    {
        $this->idD = $idD;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $StatutD = null;

    public function getStatutD(): ?string
    {
        return $this->StatutD;
    }

    public function setStatutD(string $StatutD): self
    {
        $this->StatutD = $StatutD;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionD = null;

    public function getDescriptionD(): ?string
    {
        return $this->descriptionD;
    }

    public function setDescriptionD(?string $descriptionD): self
    {
        $this->descriptionD = $descriptionD;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $dateDecision = null;

    public function getDateDecision(): ?\DateTimeInterface
    {
        return $this->dateDecision;
    }

    public function setDateDecision(\DateTimeInterface $dateDecision): self
    {
        $this->dateDecision = $dateDecision;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'decisions')]
    #[ORM\JoinColumn(name: 'idProj', referencedColumnName: 'idProj')]
    private ?Project $project = null;

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'decisions')]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'idUser')]
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

}
