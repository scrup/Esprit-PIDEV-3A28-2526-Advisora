<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ResourceWalletAccountRepository;

#[ORM\Entity(repositoryClass: ResourceWalletAccountRepository::class)]
#[ORM\Table(name: 'resource_wallet_account')]
class ResourceWalletAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
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

    #[ORM\Column(type: 'decimal', nullable: false)]
    private ?float $balanceCoins = null;

    public function getBalanceCoins(): ?float
    {
        return $this->balanceCoins;
    }

    public function setBalanceCoins(float $balanceCoins): self
    {
        $this->balanceCoins = $balanceCoins;
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

}
