<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ResourceWalletTxnRepository;

#[ORM\Entity(repositoryClass: ResourceWalletTxnRepository::class)]
#[ORM\Table(name: 'resource_wallet_txn')]
class ResourceWalletTxn
{
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

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $txnType = null;

    public function getTxnType(): ?string
    {
        return $this->txnType;
    }

    public function setTxnType(string $txnType): self
    {
        $this->txnType = $txnType;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $amountCoins = null;

    public function getAmountCoins(): ?float
    {
        return $this->amountCoins;
    }

    public function setAmountCoins(float $amountCoins): self
    {
        $this->amountCoins = $amountCoins;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $balanceAfter = null;

    public function getBalanceAfter(): ?float
    {
        return $this->balanceAfter;
    }

    public function setBalanceAfter(float $balanceAfter): self
    {
        $this->balanceAfter = $balanceAfter;
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

}
