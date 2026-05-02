<?php

namespace App\Entity;

use App\Entity\Trait\BlameableTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Repository\TaskRepository;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task')]
#[ORM\HasLifecycleCallbacks]
class Task
{
    use BlameableTrait;

    public const STATUS_TODO = 'TODO';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_DONE = 'DONE';

    public const STATUSES = [
        self::STATUS_TODO => 'A faire',
        self::STATUS_IN_PROGRESS => 'En cours',
        self::STATUS_DONE => 'Terminee',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'idProj', nullable: true, onDelete: 'SET NULL')]
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

    #[ORM\Column(type: 'string', nullable: false)]
    #[Gedmo\Translatable]
    private string $title = '';

    #[Gedmo\Locale]
    private ?string $locale = null;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private string $status = self::STATUS_TODO;

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = self::normalizeStatus($status);
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $weight = 1;

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): self
    {
        $this->weight = $weight;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $duration_days = 1;

    public function getDuration_days(): int
    {
        return $this->duration_days;
    }

    public function setDuration_days(int $duration_days): self
    {
        $this->duration_days = $duration_days;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $last_warning_date = null;

    public function getLast_warning_date(): ?\DateTimeInterface
    {
        return $this->last_warning_date;
    }

    public function setLast_warning_date(?\DateTimeInterface $last_warning_date): self
    {
        $this->last_warning_date = $last_warning_date;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    public function getCreated_at(): \DateTimeInterface
    {
        return $this->created_at;
    }

    

    public function getUpdated_at(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    

    #[ORM\PrePersist]
    public function initializeDefaults(): void
    {
        if ($this->status === null || $this->status === '') {
            $this->status = self::STATUS_TODO;
        } else {
            $this->status = self::normalizeStatus($this->status);
        }

        if ($this->weight === null) {
            $this->weight = 1;
        }

        if ($this->duration_days === null || $this->duration_days < 1) {
            $this->duration_days = 1;
        }

        if ($this->created_at === null) {
            $this->created_at = new \DateTime();
        }

        if ($this->updated_at === null) {
            $this->updated_at = new \DateTime();
        }
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updated_at = new \DateTime();
    }

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->getNormalizedStatus()] ?? 'Statut inconnu';
    }

    public function getStatusCssClass(): string
    {
        return match ($this->getNormalizedStatus()) {
            self::STATUS_TODO => 'todo',
            self::STATUS_IN_PROGRESS => 'in_progress',
            self::STATUS_DONE => 'done',
            default => 'unknown',
        };
    }

    public function getNormalizedStatus(): string
    {
        return self::normalizeStatus($this->status);
    }

    public function isCompleted(): bool
    {
        return $this->getNormalizedStatus() === self::STATUS_DONE;
    }

    public static function normalizeStatus(?string $status): string
    {
        $normalized = strtoupper(trim((string) $status));

        return match ($normalized) {
            'TODO', 'A_FAIRE', 'A FAIRE', 'AFAIRE', 'TO_DO' => self::STATUS_TODO,
            'IN_PROGRESS', 'EN_COURS', 'EN COURS', 'INPROGRESS', 'IN-PROGRESS' => self::STATUS_IN_PROGRESS,
            'DONE', 'TERMINEE', 'TERMINE', 'TERMINATED', 'COMPLETED' => self::STATUS_DONE,
            default => self::STATUS_TODO,
        };
    }

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

}
