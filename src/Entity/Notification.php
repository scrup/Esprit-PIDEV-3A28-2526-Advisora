<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(
    name: 'notification',
    indexes: [
        new ORM\Index(name: 'IDX_NOTIFICATION_RECIPIENT', columns: ['recipient_id']),
        new ORM\Index(name: 'IDX_NOTIFICATION_READ', columns: ['isRead']),
        new ORM\Index(name: 'IDX_NOTIFICATION_CREATED_AT', columns: ['createdAt']),
    ]
)]
class Notification
{
    public const EVENT_PROJECT_CREATED = 'project_created';
    public const EVENT_PROJECT_UPDATED = 'project_updated';
    public const EVENT_PROJECT_DELETED = 'project_deleted';
    public const EVENT_DECISION_ADDED = 'decision_added';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    #[ORM\Column(type: 'text')]
    private ?string $spokenText = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $eventType = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $isRead = false;

    #[ORM\Column(name: 'target_project_id', type: 'integer', nullable: true)]
    private ?int $targetProjectId = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'receivedNotifications')]
    #[ORM\JoinColumn(name: 'recipient_id', referencedColumnName: 'idUser', nullable: true, onDelete: 'CASCADE')]
    private ?User $recipient = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getSpokenText(): ?string
    {
        return $this->spokenText;
    }

    public function setSpokenText(string $spokenText): self
    {
        $this->spokenText = $spokenText;

        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isRead(): bool
    {
        return (bool) $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getTargetProjectId(): ?int
    {
        return $this->targetProjectId;
    }

    public function setTargetProjectId(?int $targetProjectId): self
    {
        $this->targetProjectId = $targetProjectId;

        return $this;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): self
    {
        $this->recipient = $recipient;

        return $this;
    }
}
