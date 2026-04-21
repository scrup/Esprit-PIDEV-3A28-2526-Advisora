<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\AuthSessionRepository;

#[ORM\Entity(repositoryClass: AuthSessionRepository::class)]
#[ORM\Table(name: 'auth_session')]
class AuthSession
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

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $user_id = null;

    public function getUser_id(): ?int
    {
        return $this->user_id;
    }

    public function setUser_id(int $user_id): self
    {
        $this->user_id = $user_id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $token_hash = null;

    public function getToken_hash(): ?string
    {
        return $this->token_hash;
    }

    public function setToken_hash(string $token_hash): self
    {
        $this->token_hash = $token_hash;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $device_name = null;

    public function getDevice_name(): ?string
    {
        return $this->device_name;
    }

    public function setDevice_name(?string $device_name): self
    {
        $this->device_name = $device_name;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $created_at = null;

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $expires_at = null;

    public function getExpires_at(): ?\DateTimeInterface
    {
        return $this->expires_at;
    }

    public function setExpires_at(\DateTimeInterface $expires_at): self
    {
        $this->expires_at = $expires_at;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $revoked_at = null;

    public function getRevoked_at(): ?\DateTimeInterface
    {
        return $this->revoked_at;
    }

    public function setRevoked_at(?\DateTimeInterface $revoked_at): self
    {
        $this->revoked_at = $revoked_at;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $last_seen_at = null;

    public function getLast_seen_at(): ?\DateTimeInterface
    {
        return $this->last_seen_at;
    }

    public function setLast_seen_at(?\DateTimeInterface $last_seen_at): self
    {
        $this->last_seen_at = $last_seen_at;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $ip_address = null;

    public function getIp_address(): ?string
    {
        return $this->ip_address;
    }

    public function setIp_address(?string $ip_address): self
    {
        $this->ip_address = $ip_address;
        return $this;
    }

}
