<?php

namespace App\Entity;

use App\Entity\Trait\BlameableTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ResourceWalletTxnRepository;

#[ORM\Entity(repositoryClass: ResourceWalletTxnRepository::class)]
#[ORM\Table(name: 'resource_wallet_txn')]
class ResourceWalletTxn
{
    use BlameableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idTxn = null;

    public function getIdTxn(): ?int
    {
        return $this->idTxn;
    }

    public function setIdTxn(int $idTxn): self
    {
        $this->idTxn = $idTxn;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $idUser;

    public function getIdUser(): int
    {
        return $this->idUser;
    }

    public function setIdUser(int $idUser): self
    {
        $this->idUser = $idUser;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private string $txnType;

    public function getTxnType(): string
    {
        return $this->txnType;
    }

    public function setTxnType(string $txnType): self
    {
        $this->txnType = $txnType;
        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 14, scale: 4, nullable: false)]
    private string $amountCoins;

    public function getAmountCoins(): float
    {
        return (float) $this->amountCoins;
    }

    public function setAmountCoins(int|float|string $amountCoins): self
    {
        $this->amountCoins = number_format((float) $amountCoins, 4, '.', '');
        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 14, scale: 4, nullable: false)]
    private string $balanceAfter;

    public function getBalanceAfter(): float
    {
        return (float) $this->balanceAfter;
    }

    public function setBalanceAfter(int|float|string $balanceAfter): self
    {
        $this->balanceAfter = number_format((float) $balanceAfter, 4, '.', '');
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $ref = null;

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $ref): self
    {
        $this->ref = $ref;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $createdAt;

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

  
}
