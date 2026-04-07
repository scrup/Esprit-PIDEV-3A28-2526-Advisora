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

    // Alias for compatibility with form/controller naming
    public function getId(): ?int
    {
        return $this->idD;
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

    // Form / controller expect these names
    public function getDecisionTitle(): ?string
    {
        return $this->StatutD;
    }

    public function setDecisionTitle(string $decisionTitle): self
    {
        $this->StatutD = $decisionTitle;
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

    // Alias names used by form and controller
    public function getDescription(): ?string
    {
        return $this->descriptionD;
    }

    public function setDescription(?string $description): self
    {
        $this->descriptionD = $description;
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

    // Alias names
    public function getDecisionDate(): ?\DateTimeInterface
    {
        return $this->dateDecision;
    }

    public function setDecisionDate(\DateTimeInterface $decisionDate): self
    {
        $this->dateDecision = $decisionDate;
        return $this;
    }

    /**
     * Human readable label for the decision title/status used in templates.
     */
    public function getDecisionTitleLabel(): string
    {
        $value = (string) ($this->getDecisionTitle() ?? '');
        $v = strtolower($value);

        return match ($v) {
            'pending', 'p', 'en attente' => 'En attente',
            'accepted', 'accepté', 'accepte', 'accepter', 'accept' => 'Accepté',
            'rejected', 'refused', 'refuse', 'refuser', 'reject' => 'Refusé',
            default => $value,
        };
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
