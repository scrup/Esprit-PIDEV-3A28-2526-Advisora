<?php

namespace App\Entity;

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

    #[ORM\Column(type: 'decimal', precision: 14, scale: 3, nullable: false)]
    private ?string $balanceCoins = null;

    public function getBalanceCoins(): ?float
    {
        return $this->balanceCoins !== null ? (float) $this->balanceCoins : null;
    }

    public function setBalanceCoins(int|float|string $balanceCoins): self
    {
        $this->balanceCoins = number_format((float) $balanceCoins, 3, '.', '');
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
