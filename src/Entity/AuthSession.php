<?php

namespace App\Entity;

use App\Entity\Trait\BlameableTrait;
use App\Repository\AuthSessionRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: AuthSessionRepository::class)]
#[ORM\Table(name: 'auth_session')]
#[ORM\HasLifecycleCallbacks]
class AuthSession
{
    use BlameableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'idUser', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $token_hash = '';

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $device_name = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $expires_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $revoked_at = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $last_seen_at = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $ip_address = null;

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

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getToken_hash(): string
    {
        return $this->token_hash;
    }

    public function setToken_hash(string $token_hash): self
    {
        $this->token_hash = $token_hash;

        return $this;
    }

    public function getDevice_name(): ?string
    {
        return $this->device_name;
    }

    public function setDevice_name(?string $device_name): self
    {
        $this->device_name = $device_name;

        return $this;
    }

    public function getCreated_at(): \DateTimeInterface
    {
        return $this->created_at;
    }

    

    public function getUpdated_at(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

   

    public function getExpires_at(): \DateTimeInterface
    {
        return $this->expires_at;
    }



    public function getRevoked_at(): ?\DateTimeInterface
    {
        return $this->revoked_at;
    }

    

    public function getLast_seen_at(): ?\DateTimeInterface
    {
        return $this->last_seen_at;
    }

    

    public function getIp_address(): ?string
    {
        return $this->ip_address;
    }

    public function setIp_address(?string $ip_address): self
    {
        $this->ip_address = $ip_address;

        return $this;
    }

    #[ORM\PrePersist]
    public function initializeAuditDates(): void
    {
        $now = new \DateTime();

        if (!isset($this->created_at)) {
            $this->created_at = $now;
        }

        if ($this->updated_at === null) {
            $this->updated_at = $now;
        }
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updated_at = new \DateTime();
    }
}
