<?php

namespace App\Entity;

use App\Entity\Trait\BlameableTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ResourceWalletAccountRepository;

#[ORM\Entity(repositoryClass: ResourceWalletAccountRepository::class)]
#[ORM\Table(name: 'resource_wallet_account')]
class ResourceWalletAccount
{
    use BlameableTrait;

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

    #[ORM\Column(type: 'decimal', precision: 14, scale: 4, nullable: false)]
    private string $balanceCoins;

    public function getBalanceCoins(): float
    {
        return (float) $this->balanceCoins;
    }

    public function setBalanceCoins(int|float|string $balanceCoins): self
    {
        $this->balanceCoins = number_format((float) $balanceCoins, 4, '.', '');
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $updatedAt;

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    

}
