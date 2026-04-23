<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\NotificationRepository;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
class Notification
{
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

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $title = null;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    private ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    #[ORM\Column(name: 'createdAt', type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $createdAt = null;

    public function getDateNotification(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setDateNotification(\DateTimeInterface $dateNotification): self
    {
        $this->createdAt = $dateNotification;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $isRead = null;

    public function isIsRead(): ?bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $target_project_id = null;

    public function getTarget_project_id(): ?int
    {
        return $this->target_project_id;
    }

    public function setTarget_project_id(?int $target_project_id): self
    {
        $this->target_project_id = $target_project_id;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recipient_id', referencedColumnName: 'idUser', nullable: true, onDelete: 'CASCADE')]
    private ?User $recipient = null;

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): self
    {
        $this->recipient = $recipient;
        return $this;
    }

    #[ORM\Column(name: 'eventType', type: 'string', length: 50, nullable: false)]
    private ?string $eventType = null;

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    private ?string $spokenText = null;

    public function getSpokenText(): ?string
    {
        return $this->spokenText;
    }

    public function setSpokenText(string $spokenText): self
    {
        $this->spokenText = $spokenText;
        return $this;
    }
}
