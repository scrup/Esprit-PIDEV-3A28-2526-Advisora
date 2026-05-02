<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use SensitiveParameter;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['NumTelUser'], message: 'Ce numero de telephone existe deja.')]
#[UniqueEntity(fields: ['EmailUser'], message: 'Cet email existe deja.')]
#[UniqueEntity(fields: ['cin'], message: 'Ce CIN existe deja.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idUser', type: 'integer')]
    private ?int $idUser = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $uuid;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $cin = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $EmailUser = '';

    /**
     * @Ignore
     * @JsonIgnore
     */
    #[ORM\Column(type: 'string', nullable: false)]
    private string $passwordUser = '';

    #[ORM\Column(type: 'string', nullable: false)]
    private string $nomUser = '';

    #[ORM\Column(type: 'string', nullable: false)]
    private string $PrenomUser = '';

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $NumTelUser = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateNUser = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $roleUser = '';

    #[ORM\Column(type: 'string', nullable: true)]
    #[Gedmo\Translatable]
    private ?string $expertiseAreaUser = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $updatedAt;

    #[Gedmo\Blameable(on: 'create')]
    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $createdBy = 'system';

    #[Gedmo\Blameable(on: 'update')]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $updatedBy = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $image_path = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $face_path = null;

    /**
     * @Ignore
     * @JsonIgnore
     */
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $totp_secret = null;

    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $totp_enabled = false;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $failed_login_count = 0;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lock_until = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $last_activity_at = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $password_changed_at = null;

    /**
     * @var Collection<int, Decision>
     */
    #[ORM\OneToMany(targetEntity: Decision::class, mappedBy: 'user')]
    private Collection $decisions;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'user')]
    private Collection $events;

    /**
     * @var Collection<int, Investment>
     */
    #[ORM\OneToMany(
        targetEntity: Investment::class,
        mappedBy: 'user',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $investments;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $bookings;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'user')]
    private Collection $projects;

    /**
     * @var Collection<int, Strategie>
     */
    #[ORM\OneToMany(targetEntity: Strategie::class, mappedBy: 'user')]
    private Collection $strategies;

    /**
     * @var Collection<int, Userlog>
     */
    #[ORM\OneToMany(targetEntity: Userlog::class, mappedBy: 'user')]
    private Collection $userlogs;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'recipient', cascade: ['remove'])]
    private Collection $receivedNotifications;

    public function __construct()
    {
        $this->decisions = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->investments = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->strategies = new ArrayCollection();
        $this->userlogs = new ArrayCollection();
        $this->receivedNotifications = new ArrayCollection();
        $this->uuid = new UuidV7();
    }

    public function getIdUser(): ?int
    {
        return $this->idUser;
    }

    public function setIdUser(int $idUser): self
    {
        $this->idUser = $idUser;

        return $this;
    }

    public function getCin(): ?string
    {
        return $this->cin;
    }

    public function setCin(?string $cin): self
    {
        $this->cin = $cin;

        return $this;
    }

    public function getEmailUser(): string
    {
        return $this->EmailUser;
    }

    public function setEmailUser(string $EmailUser): self
    {
        $this->EmailUser = $EmailUser;

        return $this;
    }

    /**
     * @Ignore
     * @JsonIgnore
     */
    public function getPasswordUser(): string
    {
        return $this->passwordUser;
    }

    public function setPasswordUser(#[SensitiveParameter] string $passwordUser): self
    {
        $this->passwordUser = $passwordUser;

        return $this;
    }

    public function getNomUser(): string
    {
        return $this->nomUser;
    }

    public function setNomUser(string $nomUser): self
    {
        $this->nomUser = $nomUser;

        return $this;
    }

    public function getPrenomUser(): string
    {
        return $this->PrenomUser;
    }

    public function setPrenomUser(string $PrenomUser): self
    {
        $this->PrenomUser = $PrenomUser;

        return $this;
    }

    public function getNumTelUser(): ?string
    {
        return $this->NumTelUser;
    }

    public function setNumTelUser(?string $NumTelUser): self
    {
        $this->NumTelUser = $NumTelUser;

        return $this;
    }

    public function getDateNUser(): ?\DateTimeInterface
    {
        return $this->dateNUser;
    }

    public function setDateNUser(?\DateTimeInterface $dateNUser): self
    {
        $this->dateNUser = $dateNUser;

        return $this;
    }

    public function getRoleUser(): string
    {
        return $this->roleUser;
    }

    public function setRoleUser(string $roleUser): self
    {
        $this->roleUser = $roleUser;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) ($this->EmailUser ?? '');
    }

    /**
     * @return array<int, string>
     */
    public function getRoles(): array
    {
        $legacyRole = strtolower((string) $this->roleUser);

        $role = match ($legacyRole) {
            'admin' => 'ROLE_ADMIN',
            'gerant' => 'ROLE_GERANT',
            default => 'ROLE_CLIENT',
        };

        return array_values(array_unique([$role, 'ROLE_USER']));
    }

    public function eraseCredentials(): void
    {
        // No temporary sensitive data stored on the entity.
    }

    /**
     * @Ignore
     * @JsonIgnore
     */
    public function getPassword(): string
    {
        return $this->passwordUser;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function setUuid(Uuid $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }


    public function getExpertiseAreaUser(): ?string
    {
        return $this->expertiseAreaUser;
    }

    public function setExpertiseAreaUser(?string $expertiseAreaUser): self
    {
        $this->expertiseAreaUser = $expertiseAreaUser;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(string $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }   

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $updatedBy): self
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function getImage_path(): ?string
    {
        return $this->image_path;
    }

    public function setImage_path(?string $image_path): self
    {
        $this->image_path = $image_path;

        return $this;
    }

    public function getFace_path(): ?string
    {
        return $this->face_path;
    }

    public function setFace_path(?string $face_path): self
    {
        $this->face_path = $face_path;

        return $this;
    }

    /**
     * @Ignore
     * @JsonIgnore
     */
    public function getTotp_secret(): ?string
    {
        return $this->totp_secret;
    }

    public function setTotp_secret(#[SensitiveParameter] ?string $totp_secret): self
    {
        $this->totp_secret = $totp_secret;

        return $this;
    }

    public function isTotp_enabled(): bool
    {
        return $this->totp_enabled;
    }

    public function setTotp_enabled(bool $totp_enabled): self
    {
        $this->totp_enabled = $totp_enabled;

        return $this;
    }

    public function getFailed_login_count(): int
    {
        return $this->failed_login_count;
    }

    public function setFailed_login_count(int $failed_login_count): self
    {
        $this->failed_login_count = $failed_login_count;

        return $this;
    }

    public function getLock_until(): ?\DateTimeInterface
    {
        return $this->lock_until;
    }

    

    public function getLast_activity_at(): ?\DateTimeInterface
    {
        return $this->last_activity_at;
    }

   
    public function getPassword_changed_at(): ?\DateTimeInterface
    {
        return $this->password_changed_at;
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
        }

        return $this;
    }

    public function removeDecision(Decision $decision): self
    {
        $this->decisions->removeElement($decision);

        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(Event $event): self
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
        }

        return $this;
    }

    public function removeEvent(Event $event): self
    {
        $this->events->removeElement($event);

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
            $investment->setUser($this);
        }

        return $this;
    }

    public function removeInvestment(Investment $investment): self
    {
        if ($this->investments->removeElement($investment) && $investment->getUser() === $this) {
            $investment->setUser(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): self
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        $this->bookings->removeElement($booking);

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->setUser($this);
        }

        return $this;
    }

    public function removeProject(Project $project): self
    {
        if ($this->projects->removeElement($project) && $project->getUser() === $this) {
            $project->setUser(null);
        }

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
     * @return Collection<int, Userlog>
     */
    public function getUserlogs(): Collection
    {
        return $this->userlogs;
    }

    public function addUserlog(Userlog $userlog): self
    {
        if (!$this->userlogs->contains($userlog)) {
            $this->userlogs->add($userlog);
        }

        return $this;
    }

    public function removeUserlog(Userlog $userlog): self
    {
        $this->userlogs->removeElement($userlog);

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getReceivedNotifications(): Collection
    {
        return $this->receivedNotifications;
    }

    public function addReceivedNotification(Notification $notification): self
    {
        if (!$this->receivedNotifications->contains($notification)) {
            $this->receivedNotifications->add($notification);
            $notification->setRecipient($this);
        }

        return $this;
    }

    public function removeReceivedNotification(Notification $notification): self
    {
        if ($this->receivedNotifications->removeElement($notification) && $notification->getRecipient() === $this) {
            $notification->setRecipient(null);
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function initializeAuditDates(): void
    {
        $now = new \DateTime();

        if (!isset($this->createdAt)) {
            $this->createdAt = $now;
        }

        if (!isset($this->updatedAt)) {
            $this->updatedAt = $now;
        }
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
