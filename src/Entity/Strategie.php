<?php

namespace App\Entity;

use App\Repository\StrategieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: StrategieRepository::class)]
#[ORM\Table(name: 'strategy')]
class Strategie
{
    public const STATUS_PENDING = 'En_attente';
    public const STATUS_APPROVED = 'Acceptée';
    public const STATUS_REJECTED = 'Refusée';
    public const STATUS_IN_PROGRESS = 'En_cours';
    public const STATUS_UNASSIGNED = 'Non_affectée';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idStrategie', type: 'integer')]
    private ?int $idStrategie = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $statusStrategie;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $CreatedAtS;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lockedAt = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'strategies')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'idProj', nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'strategies')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'idUser', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', nullable: false)]
    #[Gedmo\Translatable]
    private string $nomStrategie;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Gedmo\Translatable]
    private ?string $justification = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Gedmo\Translatable]
    private ?string $type = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    private ?string $news = null;

    #[ORM\Column(name: 'budgetTotal', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $budgetTotal = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $gainEstime = null;

    #[ORM\Column(name: 'DureeTerme', type: 'integer', nullable: true)]
    private ?int $DureeTerme = null;

    /**
     * @var Collection<int, Objective>
     */
    #[ORM\OneToMany(targetEntity: Objective::class, mappedBy: 'strategie', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $objectives;

    /**
     * @var Collection<int, SwotItem>
     */
    #[ORM\OneToMany(targetEntity: SwotItem::class, mappedBy: 'strategie', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $swotItems;

    public function __construct()
    {
        $this->objectives = new ArrayCollection();
        $this->swotItems = new ArrayCollection();
    }

    public function getIdStrategie(): ?int
    {
        return $this->idStrategie;
    }

    public function setIdStrategie(int $idStrategie): self
    {
        $this->idStrategie = $idStrategie;

        return $this;
    }

    public function getStatusStrategie(): string
    {
        return $this->statusStrategie;
    }

    public function setStatusStrategie(string $statusStrategie): self
    {
        $this->statusStrategie = $statusStrategie;

        return $this;
    }

    public function getStatusLabel(): string
    {
        return match ($this->statusStrategie) {
            self::STATUS_PENDING => 'En attente',
            self::STATUS_APPROVED => 'Acceptée',
            self::STATUS_REJECTED => 'Refusée',
            self::STATUS_IN_PROGRESS => 'En cours',
            self::STATUS_UNASSIGNED => 'Non affectée',
            default => $this->statusStrategie ?? 'Non défini',
        };
    }

    public function getStatusCssClass(): string
    {
        return match ($this->statusStrategie) {
            self::STATUS_PENDING => 'pending',
            self::STATUS_APPROVED => 'approved',
            self::STATUS_REJECTED => 'rejected',
            self::STATUS_IN_PROGRESS => 'in-progress',
            self::STATUS_UNASSIGNED => 'unassigned',
            default => 'unknown',
        };
    }

    public function getCreatedAtS(): \DateTimeInterface
    {
        return $this->CreatedAtS;
    }

    

    public function getLockedAt(): ?\DateTimeInterface
    {
        return $this->lockedAt;
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

    public function getNomStrategie(): string
    {
        return $this->nomStrategie;
    }

    public function setNomStrategie(string $nomStrategie): self
    {
        $this->nomStrategie = $nomStrategie;

        return $this;
    }

    public function getJustification(): ?string
    {
        return $this->justification;
    }

    public function setJustification(?string $justification): self
    {
        $this->justification = $justification;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getNews(): ?string
    {
        return $this->news;
    }

    public function setNews(?string $news): self
    {
        $this->news = $news;

        return $this;
    }

    public function getBudgetTotal(): ?float
    {
        return $this->budgetTotal !== null ? (float) $this->budgetTotal : null;
    }

    public function setBudgetTotal(int|float|string|null $budgetTotal): self
    {
        $this->budgetTotal = $budgetTotal !== null ? number_format((float) $budgetTotal, 2, '.', '') : null;

        return $this;
    }

    public function getGainEstime(): ?float
    {
        return $this->gainEstime;
    }

    public function setGainEstime(?float $gainEstime): self
    {
        $this->gainEstime = $gainEstime;

        return $this;
    }

    public function getEstimatedRoiPercent(): ?float
    {
        $budget = $this->getBudgetTotal();
        $gainAmount = $this->getGainEstime();

        if ($budget === null || $budget <= 0 || $gainAmount === null) {
            return null;
        }

        return ($gainAmount / $budget) * 100;
    }

    public function getDureeTerme(): ?int
    {
        return $this->DureeTerme;
    }

    public function setDureeTerme(?int $DureeTerme): self
    {
        $this->DureeTerme = $DureeTerme;

        return $this;
    }

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return Collection<int, Objective>
     */
    public function getObjectives(): Collection
    {
        return $this->objectives;
    }

    public function addObjective(Objective $objective): self
    {
        if (!$this->objectives->contains($objective)) {
            $this->objectives->add($objective);
            $objective->setStrategie($this);
        }

        return $this;
    }

    public function removeObjective(Objective $objective): self
    {
        $this->objectives->removeElement($objective);

        return $this;
    }

    /**
     * @return Collection<int, SwotItem>
     */
    public function getSwotItems(): Collection
    {
        return $this->swotItems;
    }

    public function addSwotItem(SwotItem $swotItem): self
    {
        if (!$this->swotItems->contains($swotItem)) {
            $this->swotItems->add($swotItem);
            $swotItem->setStrategie($this);
        }

        return $this;
    }

    public function removeSwotItem(SwotItem $swotItem): self
    {
        $this->swotItems->removeElement($swotItem);

        return $this;
    }
}
