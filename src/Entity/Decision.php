<?php

namespace App\Entity;

use App\Repository\DecisionRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: DecisionRepository::class)]
#[ORM\Table(name: 'decision')]
class Decision
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REFUSED = 'refused';

    public const STATUSES = [
        self::STATUS_PENDING => 'En attente',
        self::STATUS_ACTIVE => 'Accepté',
        self::STATUS_REFUSED => 'Refusé',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idD', type: 'integer')]
    private ?int $idD = null;

    #[ORM\Column(name: 'StatutD', type: 'string', length: 20, nullable: false, options: ['default' => self::STATUS_PENDING])]
    private string $StatutD = self::STATUS_PENDING;

    #[ORM\Column(name: 'descriptionD', type: 'text', nullable: true)]
    #[Gedmo\Translatable]
    private ?string $descriptionD = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    #[ORM\Column(name: 'dateDecision', type: 'date', nullable: false)]
    private \DateTimeInterface $dateDecision;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'decisions')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'idProj', nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'decisions')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'idUser', nullable: false)]
    private ?User $user = null;

    public function getIdD(): ?int
    {
        return $this->idD;
    }

    public function setIdD(int $idD): self
    {
        $this->idD = $idD;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->idD;
    }

    public function getStatutD(): string
    {
        return $this->StatutD;
    }

    public function setStatutD(string $StatutD): self
    {
        $this->StatutD = $StatutD;

        return $this;
    }

    public function getDecisionTitle(): ?string
    {
        return $this->StatutD;
    }

    public function setDecisionTitle(string $decisionTitle): self
    {
        $normalized = strtolower(trim($decisionTitle));

        $this->StatutD = match ($normalized) {
            'accepted', 'accepté', 'accepte', 'accept', 'active' => self::STATUS_ACTIVE,
            'rejected', 'refused', 'refusé', 'refuse', 'reject' => self::STATUS_REFUSED,
            default => self::STATUS_PENDING,
        };

        return $this;
    }

    public function getDescriptionD(): ?string
    {
        return $this->descriptionD;
    }

    public function setDescriptionD(?string $descriptionD): self
    {
        $this->descriptionD = $descriptionD;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->descriptionD;
    }

    public function setDescription(?string $description): self
    {
        $this->descriptionD = $description;

        return $this;
    }

    public function getDateDecision(): \DateTimeInterface
    {
        return $this->dateDecision;
    }

    public function setDateDecision(\DateTimeInterface $dateDecision): self
    {
        $this->dateDecision = $dateDecision;

        return $this;
    }

    public function getDecisionDate(): \DateTimeInterface
    {
        return $this->dateDecision;
    }

    public function setDecisionDate(\DateTimeInterface $decisionDate): self
    {
        $this->dateDecision = $decisionDate;

        return $this;
    }

    public function getDecisionTitleLabel(): string
    {
        return match ($this->getDecisionTitle()) {
            self::STATUS_ACTIVE => 'AcceptÃ©',
            self::STATUS_REFUSED => 'RefusÃ©',
            self::STATUS_PENDING => 'En attente',
            default => (string) ($this->getDecisionTitle() ?? ''),
        };
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }
}
