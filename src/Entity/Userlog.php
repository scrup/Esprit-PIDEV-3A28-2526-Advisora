<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Repository\UserlogRepository;

#[ORM\Entity(repositoryClass: UserlogRepository::class)]
#[ORM\Table(name: 'userlog')]
class Userlog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_log = null;

    public function getId_log(): ?int
    {
        return $this->id_log;
    }

    public function setId_log(int $id_log): self
    {
        $this->id_log = $id_log;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userlogs')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'idUser', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Gedmo\Translatable]
    private string $actionLog;

    #[Gedmo\Locale]
    private ?string $locale = null;

    public function getActionLog(): string
    {
        return $this->actionLog;
    }

    public function setActionLog(string $actionLog): self
    {
        $this->actionLog = $actionLog;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $dateLog;

    public function getDateLog(): \DateTimeInterface
    {
        return $this->dateLog;
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

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

}
