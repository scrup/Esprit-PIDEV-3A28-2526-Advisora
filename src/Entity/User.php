<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use App\Repository\UserRepository;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idUser', type: 'integer')]
    private ?int $idUser = null;

    public function getIdUser(): ?int
    {
        return $this->idUser;
    }

    public function setIdUser(int $idUser): self
    {
        $this->idUser = $idUser;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $cin = null;

    public function getCin(): ?string
    {
        return $this->cin;
    }

    public function setCin(?string $cin): self
    {
        $this->cin = $cin;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $EmailUser = null;

    public function getEmailUser(): ?string
    {
        return $this->EmailUser;
    }

    public function setEmailUser(string $EmailUser): self
    {
        $this->EmailUser = $EmailUser;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $passwordUser = null;

    public function getPasswordUser(): ?string
    {
        return $this->passwordUser;
    }

    public function setPasswordUser(string $passwordUser): self
    {
        $this->passwordUser = $passwordUser;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $nomUser = null;

    public function getNomUser(): ?string
    {
        return $this->nomUser;
    }

    public function setNomUser(string $nomUser): self
    {
        $this->nomUser = $nomUser;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $PrenomUser = null;

    public function getPrenomUser(): ?string
    {
        return $this->PrenomUser;
    }

    public function setPrenomUser(string $PrenomUser): self
    {
        $this->PrenomUser = $PrenomUser;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $NumTelUser = null;

    public function getNumTelUser(): ?string
    {
        return $this->NumTelUser;
    }

    public function setNumTelUser(?string $NumTelUser): self
    {
        $this->NumTelUser = $NumTelUser;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateNUser = null;

    public function getDateNUser(): ?\DateTimeInterface
    {
        return $this->dateNUser;
    }

    public function setDateNUser(?\DateTimeInterface $dateNUser): self
    {
        $this->dateNUser = $dateNUser;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $roleUser = null;

    public function getRoleUser(): ?string
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

    public function getPassword(): ?string
    {
        return $this->passwordUser;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $expertiseAreaUser = null;

    public function getExpertiseAreaUser(): ?string
    {
        return $this->expertiseAreaUser;
    }

    public function setExpertiseAreaUser(?string $expertiseAreaUser): self
    {
        $this->expertiseAreaUser = $expertiseAreaUser;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $createdAt = null;

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $updatedAt = null;

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $image_path = null;

    public function getImage_path(): ?string
    {
        return $this->image_path;
    }

    public function setImage_path(?string $image_path): self
    {
        $this->image_path = $image_path;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $face_path = null;

    public function getFace_path(): ?string
    {
        return $this->face_path;
    }

    public function setFace_path(?string $face_path): self
    {
        $this->face_path = $face_path;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $totp_secret = null;

    public function getTotp_secret(): ?string
    {
        return $this->totp_secret;
    }

    public function setTotp_secret(?string $totp_secret): self
    {
        $this->totp_secret = $totp_secret;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $totp_enabled = null;

    public function isTotp_enabled(): ?bool
    {
        return $this->totp_enabled;
    }

    public function setTotp_enabled(bool $totp_enabled): self
    {
        $this->totp_enabled = $totp_enabled;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $failed_login_count = null;

    public function getFailed_login_count(): ?int
    {
        return $this->failed_login_count;
    }

    public function setFailed_login_count(int $failed_login_count): self
    {
        $this->failed_login_count = $failed_login_count;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lock_until = null;

    public function getLock_until(): ?\DateTimeInterface
    {
        return $this->lock_until;
    }

    public function setLock_until(?\DateTimeInterface $lock_until): self
    {
        $this->lock_until = $lock_until;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $last_activity_at = null;

    public function getLast_activity_at(): ?\DateTimeInterface
    {
        return $this->last_activity_at;
    }

    public function setLast_activity_at(?\DateTimeInterface $last_activity_at): self
    {
        $this->last_activity_at = $last_activity_at;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $password_changed_at = null;

    public function getPassword_changed_at(): ?\DateTimeInterface
    {
        return $this->password_changed_at;
    }

    public function setPassword_changed_at(?\DateTimeInterface $password_changed_at): self
    {
        $this->password_changed_at = $password_changed_at;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Decision::class, mappedBy: 'user')]
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

    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'user')]
    private Collection $events;

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        if (!$this->events instanceof Collection) {
            $this->events = new ArrayCollection();
        }
        return $this->events;
    }

    public function addEvent(Event $event): self
    {
        if (!$this->getEvents()->contains($event)) {
            $this->getEvents()->add($event);
        }
        return $this;
    }

    public function removeEvent(Event $event): self
    {
        $this->getEvents()->removeElement($event);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Investment::class, mappedBy: 'user')]
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

    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'user')]
    private Collection $projects;

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        if (!$this->projects instanceof Collection) {
            $this->projects = new ArrayCollection();
        }
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if (!$this->getProjects()->contains($project)) {
            $this->getProjects()->add($project);
        }
        return $this;
    }

    public function removeProject(Project $project): self
    {
        $this->getProjects()->removeElement($project);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Strategie::class, mappedBy: 'user')]
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

    #[ORM\OneToMany(targetEntity: Userlog::class, mappedBy: 'user')]
    private Collection $userlogs;

    /**
     * @return Collection<int, Userlog>
     */
    public function getUserlogs(): Collection
    {
        if (!$this->userlogs instanceof Collection) {
            $this->userlogs = new ArrayCollection();
        }
        return $this->userlogs;
    }

    public function addUserlog(Userlog $userlog): self
    {
        if (!$this->getUserlogs()->contains($userlog)) {
            $this->getUserlogs()->add($userlog);
        }
        return $this;
    }

    public function removeUserlog(Userlog $userlog): self
    {
        $this->getUserlogs()->removeElement($userlog);
        return $this;
    }

}
