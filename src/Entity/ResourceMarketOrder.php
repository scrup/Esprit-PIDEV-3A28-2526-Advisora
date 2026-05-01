<?php

namespace App\Entity;

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

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, nullable: false)]
    private ?string $unitPrice = null;

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice !== null ? (float) $this->unitPrice : null;
    }

    public function setUnitPrice(int|float|string $unitPrice): self
    {
        $this->unitPrice = number_format((float) $unitPrice, 3, '.', '');
        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, nullable: false)]
    private ?string $totalPrice = null;

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice !== null ? (float) $this->totalPrice : null;
    }

    public function setTotalPrice(int|float|string $totalPrice): self
    {
        $this->totalPrice = number_format((float) $totalPrice, 3, '.', '');
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
