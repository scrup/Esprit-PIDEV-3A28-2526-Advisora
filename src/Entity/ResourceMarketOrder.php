<?php

namespace App\Entity;

use App\Entity\Trait\BlameableTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ResourceMarketOrderRepository;

#[ORM\Entity(repositoryClass: ResourceMarketOrderRepository::class)]
#[ORM\Table(name: 'resource_market_order')]
class ResourceMarketOrder
{
    use BlameableTrait;

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
    private int $idListing;

    public function getIdListing(): int
    {
        return $this->idListing;
    }

    public function setIdListing(int $idListing): self
    {
        $this->idListing = $idListing;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $buyerUserId;

    public function getBuyerUserId(): int
    {
        return $this->buyerUserId;
    }

    public function setBuyerUserId(int $buyerUserId): self
    {
        $this->buyerUserId = $buyerUserId;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $sellerUserId;

    public function getSellerUserId(): int
    {
        return $this->sellerUserId;
    }

    public function setSellerUserId(int $sellerUserId): self
    {
        $this->sellerUserId = $sellerUserId;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $buyerProjectId;

    public function getBuyerProjectId(): int
    {
        return $this->buyerProjectId;
    }

    public function setBuyerProjectId(int $buyerProjectId): self
    {
        $this->buyerProjectId = $buyerProjectId;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $idRs;

    public function getIdRs(): int
    {
        return $this->idRs;
    }

    public function setIdRs(int $idRs): self
    {
        $this->idRs = $idRs;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $quantity;

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: false)]
    private string $unitPrice;

    public function getUnitPrice(): float
    {
        return (float) $this->unitPrice;
    }

    public function setUnitPrice(int|float|string $unitPrice): self
    {
        $this->unitPrice = number_format((float) $unitPrice, 4, '.', '');
        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: false)]
    private string $totalPrice;

    public function getTotalPrice(): float
    {
        return (float) $this->totalPrice;
    }

    public function setTotalPrice(int|float|string $totalPrice): self
    {
        $this->totalPrice = number_format((float) $totalPrice, 4, '.', '');
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private string $status;

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
private \DateTimeInterface $createdAt;

public function getCreatedAt(): \DateTimeInterface
{
    return $this->createdAt;
}

#[ORM\PrePersist]
public function initializeCreatedAt(): void
{
    if (!isset($this->createdAt)) {
        $this->createdAt = new \DateTime();
    }
}

   
}
