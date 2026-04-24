<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\Mapping\Annotation as Gedmo;

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
    #[Gedmo\Translatable]
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

    #[ORM\Column(type: 'string', nullable: false)]
    #[Gedmo\Translatable]
    private ?string $description = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $dateNotification = null;

    public function getDateNotification(): ?\DateTimeInterface
    {
        return $this->dateNotification;
    }

    public function setDateNotification(\DateTimeInterface $dateNotification): self
    {
        $this->dateNotification = $dateNotification;
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

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $target_role = null;

    public function getTarget_role(): ?string
    {
        return $this->target_role;
    }

    public function setTarget_role(?string $target_role): self
    {
        $this->target_role = $target_role;
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

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

}
