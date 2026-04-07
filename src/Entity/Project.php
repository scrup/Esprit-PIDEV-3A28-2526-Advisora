<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ProjectRepository;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'projects')]
#[ORM\HasLifecycleCallbacks]
class Project
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_REFUSED = 'REFUSED';

    public const STATUSES = [
        self::STATUS_PENDING => 'En attente',
        self::STATUS_ACCEPTED => 'Accepté',
        self::STATUS_REFUSED => 'Refusé',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idProj', type: 'integer')]
    private ?int $idProj = null;

    public function getIdProj(): ?int
    {
        return $this->idProj;
    }

    public function setIdProj(int $idProj): self
    {
        $this->idProj = $idProj;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $titleProj = null;

    public function getTitleProj(): ?string
    {
        return $this->titleProj;
    }

    public function setTitleProj(string $titleProj): self
    {
        $this->titleProj = $titleProj;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionProj = null;

    public function getDescriptionProj(): ?string
    {
        return $this->descriptionProj;
    }

    public function setDescriptionProj(?string $descriptionProj): self
    {
        $this->descriptionProj = $descriptionProj;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $budgetProj = null;

    public function getBudgetProj(): ?float
    {
        return $this->budgetProj;
    }

    public function setBudgetProj(float $budgetProj): self
    {
        $this->budgetProj = $budgetProj;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $typeProj = null;

    public function getTypeProj(): ?string
    {
        return $this->typeProj;
    }

    public function setTypeProj(?string $typeProj): self
    {
        $this->typeProj = $typeProj;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $stateProj = null;

    public function getStateProj(): ?string
    {
        return $this->stateProj;
    }

    public function setStateProj(string $stateProj): self
    {
        $this->stateProj = $stateProj;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $createdAtProj = null;

    public function getCreatedAtProj(): ?\DateTimeInterface
    {
        return $this->createdAtProj;
    }

    public function setCreatedAtProj(\DateTimeInterface $createdAtProj): self
    {
        $this->createdAtProj = $createdAtProj;
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTime();
        if ($this->createdAtProj === null) {
            $this->createdAtProj = $now;
        }
        if ($this->updatedAtProj === null) {
            $this->updatedAtProj = clone $now;
        }
        if ($this->avancementProj === null) {
            $this->avancementProj = 0.0;
        }
        if ($this->stateProj === null || $this->stateProj === '') {
            $this->stateProj = self::STATUS_PENDING;
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAtProj = new \DateTime();
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $updatedAtProj = null;

    public function getUpdatedAtProj(): ?\DateTimeInterface
    {
        return $this->updatedAtProj;
    }

    public function setUpdatedAtProj(\DateTimeInterface $updatedAtProj): self
    {
        $this->updatedAtProj = $updatedAtProj;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $avancementProj = null;

    public function getAvancementProj(): ?float
    {
        return $this->avancementProj;
    }

    public function setAvancementProj(float $avancementProj): self
    {
        $this->avancementProj = $avancementProj;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'projects')]
    #[ORM\JoinColumn(name: 'idClient', referencedColumnName: 'idUser')]
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

    #[ORM\OneToMany(targetEntity: Decision::class, mappedBy: 'project')]
    private Collection $decisions;

    /**
     * @return Collection<int, Decision>
     */
    public function getDecisions(): Collection
    {
        if (!$this->decisions instanceof Collection) {
            $this->decisions = new ArrayCollection();
        }
        return $this->decisions;
    }

    public function addDecision(Decision $decision): self
    {
        if (!$this->getDecisions()->contains($decision)) {
            $this->getDecisions()->add($decision);
        }
        return $this;
    }

    public function removeDecision(Decision $decision): self
    {
        $this->getDecisions()->removeElement($decision);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Investment::class, mappedBy: 'project')]
    private Collection $investments;

    /**
     * @return Collection<int, Investment>
     */
    public function getInvestments(): Collection
    {
        if (!$this->investments instanceof Collection) {
            $this->investments = new ArrayCollection();
        }
        return $this->investments;
    }

    public function addInvestment(Investment $investment): self
    {
        if (!$this->getInvestments()->contains($investment)) {
            $this->getInvestments()->add($investment);
        }
        return $this;
    }

    public function removeInvestment(Investment $investment): self
    {
        $this->getInvestments()->removeElement($investment);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Strategie::class, mappedBy: 'project')]
    private Collection $strategies;

    /**
     * @return Collection<int, Strategie>
     */
    public function getStrategies(): Collection
    {
        if (!$this->strategies instanceof Collection) {
            $this->strategies = new ArrayCollection();
        }
        return $this->strategies;
    }

    public function addStrategie(Strategie $strategie): self
    {
        if (!$this->getStrategies()->contains($strategie)) {
            $this->getStrategies()->add($strategie);
        }
        return $this;
    }

    public function removeStrategie(Strategie $strategie): self
    {
        $this->getStrategies()->removeElement($strategie);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'project')]
    private Collection $tasks;

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        if (!$this->tasks instanceof Collection) {
            $this->tasks = new ArrayCollection();
        }
        return $this->tasks;
    }

    public function addTask(Task $task): self
    {
        if (!$this->getTasks()->contains($task)) {
            $this->getTasks()->add($task);
        }
        return $this;
    }

    public function removeTask(Task $task): self
    {
        $this->getTasks()->removeElement($task);
        return $this;
    }

    #[ORM\ManyToMany(targetEntity: Resource::class, inversedBy: 'projects')]
    #[ORM\JoinTable(
        name: 'project_resources',
        joinColumns: [
            new ORM\JoinColumn(name: 'idProj', referencedColumnName: 'idProj')
        ],
        inverseJoinColumns: [
            new ORM\JoinColumn(name: 'idRs', referencedColumnName: 'idRs')
        ]
    )]
    private Collection $resources;

    public function __construct()
    {
        $this->decisions = new ArrayCollection();
        $this->investments = new ArrayCollection();
        $this->strategies = new ArrayCollection();
        $this->tasks = new ArrayCollection();
        $this->resources = new ArrayCollection();
    }

    /**
     * @return Collection<int, Resource>
     */
    public function getResources(): Collection
    {
        if (!$this->resources instanceof Collection) {
            $this->resources = new ArrayCollection();
        }
        return $this->resources;
    }

    // Compatibility getters used by templates and controllers
    public function getId(): ?int
    {
        return $this->idProj;
    }

    public function getTitle(): ?string
    {
        return $this->titleProj;
    }

    public function setTitle(string $title): self
    {
        $this->titleProj = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->descriptionProj;
    }

    public function setDescription(?string $description): self
    {
        $this->descriptionProj = $description;
        return $this;
    }

    public function getLegacyBudget(): ?float
    {
        return $this->budgetProj;
    }

    public function setLegacyBudget(float $budget): self
    {
        $this->budgetProj = $budget;
        return $this;
    }

    public function getLegacyType(): ?string
    {
        return $this->typeProj;
    }

    public function setLegacyType(?string $type): self
    {
        $this->typeProj = $type;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        if ($this->createdAtProj !== null) {
            return $this->createdAtProj;
        }

        if ($this->updatedAtProj !== null) {
            return $this->updatedAtProj;
        }

        return new \DateTime('today');
    }

    public function setStartDate(\DateTimeInterface $dt): self
    {
        $this->createdAtProj = $dt;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->updatedAtProj;
    }

    public function setEndDate(\DateTimeInterface $dt): self
    {
        $this->updatedAtProj = $dt;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->stateProj;
    }

    public function setStatus(string $status): self
    {
        $this->stateProj = $status;
        return $this;
    }

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->getStatus()] ?? ($this->getStatus() ?? 'N/A');
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

    public function addStrategy(Strategie $strategy): static
    {
        if (!$this->strategies->contains($strategy)) {
            $this->strategies->add($strategy);
            $strategy->setProject($this);
        }

        return $this;
    }

    public function removeStrategy(Strategie $strategy): static
    {
        if ($this->strategies->removeElement($strategy)) {
            // set the owning side to null (unless already changed)
            if ($strategy->getProject() === $this) {
                $strategy->setProject(null);
            }
        }

        return $this;
    }

}
