<?php

namespace App\Entity;

use App\Entity\Trait\BlameableTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Repository\OtpCodeRepository;

#[ORM\Entity(repositoryClass: OtpCodeRepository::class)]
#[ORM\Table(name: 'otp_code')]
#[ORM\HasLifecycleCallbacks]
class OtpCode
{
    use BlameableTrait;

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
    private string $email;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private string $purpose;

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function setPurpose(string $purpose): self
    {
        $this->purpose = $purpose;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private string $code_hash;

    public function getCode_hash(): string
    {
        return $this->code_hash;
    }

    public function setCode_hash(string $code_hash): self
    {
        $this->code_hash = $code_hash;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $expires_at;

    public function getExpires_at(): \DateTimeInterface
    {
        return $this->expires_at;
    }

    public function setExpires_at(\DateTimeInterface $expires_at): self
    {
        $this->expires_at = $expires_at;

        return $this;
    }

    

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $used_at = null;

    public function getUsed_at(): ?\DateTimeInterface
    {
        return $this->used_at;
    }

    public function setUsed_at(?\DateTimeInterface $used_at): self
    {
        $this->used_at = $used_at;

        return $this;
    }

    

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $created_at;

    public function getCreated_at(): \DateTimeInterface
    {
        return $this->created_at;
    }

    

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    public function getUpdated_at(): ?\DateTimeInterface
    {
        return $this->updated_at;
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
