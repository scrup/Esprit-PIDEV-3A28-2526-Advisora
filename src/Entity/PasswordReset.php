<?php

namespace App\Entity;

use App\Repository\PasswordResetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PasswordResetRepository::class)]
#[ORM\Table(name: 'password_reset')]
#[ORM\HasLifecycleCallbacks]
class PasswordReset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'idUser', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'idUser', nullable: false, onDelete: 'CASCADE')]
    private User $createdBy;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $code_hash = '';

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $expires_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $used_at = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $attempts = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getUser_id(): ?int
    {
        return $this->user?->getIdUser();
    }

    /*
     * Avoid using this setter if possible.
     * Prefer setUser(User $user), because Doctrine needs a managed User entity.
     */
    public function setUser_id(int $user_id): self
    {
        $user = new User();
        $user->setIdUser($user_id);
        $this->user = $user;

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

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCode_hash(): string
    {
        return $this->code_hash;
    }

    public function setCode_hash(string $code_hash): self
    {
        $this->code_hash = $code_hash;

        return $this;
    }

    public function getExpires_at(): \DateTimeInterface
    {
        return $this->expires_at;
    }

    
    public function getUsed_at(): ?\DateTimeInterface
    {
        return $this->used_at;
    }

    

    public function getCreated_at(): \DateTimeInterface
    {
        return $this->created_at;
    }

   

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): self
    {
        $this->attempts = $attempts;

        return $this;
    }

    #[ORM\PrePersist]
    public function initializeDefaults(): void
    {
        if (!isset($this->created_at)) {
            $this->created_at = new \DateTime();
        }

        if (!isset($this->expires_at)) {
            $this->expires_at = (new \DateTime())->modify('+15 minutes');
        }

        if (!isset($this->createdBy) && $this->user instanceof User) {
            $this->createdBy = $this->user;
        }
    }
}