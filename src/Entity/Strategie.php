<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\StrategieRepository;

#[ORM\Entity(repositoryClass: StrategieRepository::class)]
#[ORM\Table(name: 'strategies')]
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

    public function getIdStrategie(): ?int
    {
        return $this->idStrategie;
    }

    public function setIdStrategie(int $idStrategie): self
    {
        $this->idStrategie = $idStrategie;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $statusStrategie = null;

    public function getStatusStrategie(): ?string
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

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $CreatedAtS = null;

    public function getCreatedAtS(): ?\DateTimeInterface
    {
        return $this->CreatedAtS;
    }

    public function setCreatedAtS(\DateTimeInterface $CreatedAtS): self
    {
        $this->CreatedAtS = $CreatedAtS;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lockedAt = null;

    public function getLockedAt(): ?\DateTimeInterface
    {
        return $this->lockedAt;
    }

    public function setLockedAt(?\DateTimeInterface $lockedAt): self
    {
        $this->lockedAt = $lockedAt;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $news = null;

    public function getNews(): ?string
    {
        return $this->news;
    }

    public function setNews(?string $news): self
    {
        $this->news = $news;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'strategies')]
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'strategies')]
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

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $nomStrategie = null;

    public function getNomStrategie(): ?string
    {
        return $this->nomStrategie;
    }

    public function setNomStrategie(string $nomStrategie): self
    {
        $this->nomStrategie = $nomStrategie;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $justification = null;

    public function getJustification(): ?string
    {
        return $this->justification;
    }

    public function setJustification(?string $justification): self
    {
        $this->justification = $justification;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $type = null;

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $budgetTotal = null;

    public function getBudgetTotal(): ?float
    {
        return $this->budgetTotal;
    }

    public function setBudgetTotal(?float $budgetTotal): self
    {
        $this->budgetTotal = $budgetTotal;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $gainEstime = null;

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

  #[ORM\Column(name: 'DureeTerme', type: 'integer', nullable: true)]
private ?int $DureeTerme = null;

public function getDureeTerme(): ?int
{
    return $this->DureeTerme;
}

public function setDureeTerme(?int $DureeTerme): self
{
    $this->DureeTerme = $DureeTerme;

    return $this;
}


    #[ORM\OneToMany(targetEntity: Objective::class, mappedBy: 'strategie')]
    private Collection $objectives;

    /**
     * @return Collection<int, Objective>
     */
    public function getObjectives(): Collection
    {
        if (!$this->objectives instanceof Collection) {
            $this->objectives = new ArrayCollection();
        }
        return $this->objectives;
    }

    public function addObjective(Objective $objective): self
    {
        if (!$this->getObjectives()->contains($objective)) {
            $this->getObjectives()->add($objective);
        }
        return $this;
    }

    public function removeObjective(Objective $objective): self
    {
        $this->getObjectives()->removeElement($objective);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: SwotItem::class, mappedBy: 'strategie')]
    private Collection $swotItems;

    /**
     * @return Collection<int, SwotItem>
     */
    public function getSwotItems(): Collection
    {
        if (!$this->swotItems instanceof Collection) {
            $this->swotItems = new ArrayCollection();
        }
        return $this->swotItems;
    }

    public function addSwotItem(SwotItem $swotItem): self
    {
        if (!$this->getSwotItems()->contains($swotItem)) {
            $this->getSwotItems()->add($swotItem);
        }
        return $this;
    }

    public function removeSwotItem(SwotItem $swotItem): self
    {
        $this->getSwotItems()->removeElement($swotItem);
        return $this;
    }

}
