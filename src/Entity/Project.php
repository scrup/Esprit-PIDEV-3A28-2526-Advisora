<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

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
        self::STATUS_ACCEPTED => 'AcceptÃ©',
        self::STATUS_REFUSED => 'RefusÃ©',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idProj', type: 'integer')]
    private ?int $idProj = null;

    #[ORM\Column(type: 'string', length: 160, nullable: false)]
    #[Gedmo\Translatable]
    private ?string $titleProj = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Gedmo\Translatable]
    private ?string $descriptionProj = null;

    #[ORM\Column(type: 'float', nullable: false, options: ['default' => 0])]
    private ?float $budgetProj = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Gedmo\Translatable]
    private ?string $typeProj = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    #[ORM\Column(
        type: 'string',
        nullable: false,
        columnDefinition: "ENUM('PENDING','ACCEPTED','REFUSED','ARCHIVED') NOT NULL DEFAULT 'PENDING'"
    )]
    private ?string $stateProj = null;

    #[ORM\Column(type: 'datetime', nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $createdAtProj = null;

    #[ORM\Column(type: 'datetime', nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $updatedAtProj = null;

    #[ORM\Column(type: 'float', nullable: false, options: ['default' => 0])]
    private ?float $avancementProj = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'projects')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'idUser', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\OneToMany(targetEntity: Decision::class, mappedBy: 'project', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['dateDecision' => 'DESC', 'idD' => 'DESC'])]
    private Collection $decisions;

    #[ORM\OneToMany(targetEntity: Investment::class, mappedBy: 'project')]
    private Collection $investments;

    #[ORM\OneToMany(targetEntity: Strategie::class, mappedBy: 'project')]
    private Collection $strategies;

    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'project')]
    private Collection $tasks;

    #[ORM\ManyToMany(targetEntity: Resource::class, inversedBy: 'projects')]
    #[ORM\JoinTable(
        name: 'project_resources',
        joinColumns: [
            new ORM\JoinColumn(name: 'idProj', referencedColumnName: 'idProj', onDelete: 'CASCADE')
        ],
        inverseJoinColumns: [
            new ORM\JoinColumn(name: 'idRs', referencedColumnName: 'idRs', onDelete: 'CASCADE')
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

    public function getIdProj(): ?int
    {
        return $this->idProj;
    }

    public function setIdProj(int $idProj): self
    {
        $this->idProj = $idProj;

        return $this;
    }

    public function getTitleProj(): ?string
    {
        return $this->titleProj;
    }

    public function setTitleProj(string $titleProj): self
    {
        $this->titleProj = $titleProj;

        return $this;
    }

    public function getDescriptionProj(): ?string
    {
        return $this->descriptionProj;
    }

    public function setDescriptionProj(?string $descriptionProj): self
    {
        $this->descriptionProj = $descriptionProj;

        return $this;
    }

    public function getBudgetProj(): ?float
    {
        return $this->budgetProj;
    }

    public function setBudgetProj(float $budgetProj): self
    {
        $this->budgetProj = $budgetProj;

        return $this;
    }

    public function getTypeProj(): ?string
    {
        return $this->typeProj;
    }

    public function setTypeProj(?string $typeProj): self
    {
        $this->typeProj = $typeProj;

        return $this;
    }

    public function getStateProj(): ?string
    {
        return $this->stateProj;
    }

    public function setStateProj(string $stateProj): self
    {
        $this->stateProj = $stateProj;

        return $this;
    }

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

    public function getUpdatedAtProj(): ?\DateTimeInterface
    {
        return $this->updatedAtProj;
    }

    public function setUpdatedAtProj(\DateTimeInterface $updatedAtProj): self
    {
        $this->updatedAtProj = $updatedAtProj;

        return $this;
    }

    public function getAvancementProj(): ?float
    {
        return $this->avancementProj;
    }

    public function setAvancementProj(float $avancementProj): self
    {
        $this->avancementProj = $avancementProj;

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

    /**
     * @return Collection<int, Decision>
     */
    public function getDecisions(): Collection
    {
        return $this->decisions;
    }

    public function addDecision(Decision $decision): self
    {
        if (!$this->decisions->contains($decision)) {
            $this->decisions->add($decision);
            $decision->setProject($this);
        }

        return $this;
    }

    public function removeDecision(Decision $decision): self
    {
        if ($this->decisions->removeElement($decision) && $decision->getProject() === $this) {
            $decision->setProject(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Investment>
     */
    public function getInvestments(): Collection
    {
        return $this->investments;
    }

    public function addInvestment(Investment $investment): self
    {
        if (!$this->investments->contains($investment)) {
            $this->investments->add($investment);
        }

        return $this;
    }

    public function removeInvestment(Investment $investment): self
    {
        $this->investments->removeElement($investment);

        return $this;
    }

    /**
     * @return Collection<int, Strategie>
     */
    public function getStrategies(): Collection
    {
        return $this->strategies;
    }

    public function addStrategie(Strategie $strategie): self
    {
        if (!$this->strategies->contains($strategie)) {
            $this->strategies->add($strategie);
        }

        return $this;
    }

    public function removeStrategie(Strategie $strategie): self
    {
        $this->strategies->removeElement($strategie);

        return $this;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): self
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setProject($this);
        }

        return $this;
    }

    public function removeTask(Task $task): self
    {
        if ($this->tasks->removeElement($task) && $task->getProject() === $this) {
            $task->setProject(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Resource>
     */
    public function getResources(): Collection
    {
        return $this->resources;
    }

    public function addResource(Resource $resource): self
    {
        if (!$this->resources->contains($resource)) {
            $this->resources->add($resource);
        }

        return $this;
    }

    public function removeResource(Resource $resource): self
    {
        $this->resources->removeElement($resource);

        return $this;
    }

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
        return $this->createdAtProj;
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
        if ($this->strategies->removeElement($strategy) && $strategy->getProject() === $this) {
            $strategy->setProject(null);
        }

        return $this;
    }

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }
}
