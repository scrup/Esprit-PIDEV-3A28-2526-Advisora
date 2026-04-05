<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ResourceMarketOrderRepository;

#[ORM\Entity(repositoryClass: ResourceMarketOrderRepository::class)]
#[ORM\Table(name: 'resource_market_order')]
class ResourceMarketOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idOrder = null;

    public function getIdOrder(): ?int
    {
        return $this->idOrder;
    }

    public function setIdOrder(int $idOrder): self
    {
        $this->idOrder = $idOrder;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $idListing = null;

    public function getIdListing(): ?int
    {
        return $this->idListing;
    }

    public function setIdListing(int $idListing): self
    {
        $this->idListing = $idListing;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $buyerUserId = null;

    public function getBuyerUserId(): ?int
    {
        return $this->buyerUserId;
    }

    public function setBuyerUserId(int $buyerUserId): self
    {
        $this->buyerUserId = $buyerUserId;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $sellerUserId = null;

    public function getSellerUserId(): ?int
    {
        return $this->sellerUserId;
    }

    public function setSellerUserId(int $sellerUserId): self
    {
        $this->sellerUserId = $sellerUserId;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $buyerProjectId = null;

    public function getBuyerProjectId(): ?int
    {
        return $this->buyerProjectId;
    }

    public function setBuyerProjectId(int $buyerProjectId): self
    {
        $this->buyerProjectId = $buyerProjectId;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $idRs = null;

    public function getIdRs(): ?int
    {
        return $this->idRs;
    }

    public function setIdRs(int $idRs): self
    {
        $this->idRs = $idRs;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $quantity = null;

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: false)]
    private ?float $unitPrice = null;

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): self
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: false)]
    private ?float $totalPrice = null;

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): self
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $status = null;

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
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
