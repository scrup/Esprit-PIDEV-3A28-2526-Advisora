<?php

namespace App\Entity;

use App\Entity\Trait\BlameableTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Repository\ResourceMarketListingRepository;

#[ORM\Entity(repositoryClass: ResourceMarketListingRepository::class)]
#[ORM\Table(name: 'resource_market_listing')]
#[ORM\HasLifecycleCallbacks]
class ResourceMarketListing
{
    use BlameableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
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
    private int $idProj;

    public function getIdProj(): int
    {
        return $this->idProj;
    }

    public function setIdProj(int $idProj): self
    {
        $this->idProj = $idProj;
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
    private int $qtyInitial;

    public function getQtyInitial(): int
    {
        return $this->qtyInitial;
    }

    public function setQtyInitial(int $qtyInitial): self
    {
        $this->qtyInitial = $qtyInitial;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $qtyRemaining;

    public function getQtyRemaining(): int
    {
        return $this->qtyRemaining;
    }

    public function setQtyRemaining(int $qtyRemaining): self
    {
        $this->qtyRemaining = $qtyRemaining;
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

    #[ORM\Column(type: 'string', nullable: true)]
    #[Gedmo\Translatable]
    private ?string $note = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $imageUrl = null;

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
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
    #[Gedmo\Timestampable(on: 'create')]
    private \DateTimeInterface $createdAt;

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Gedmo\Timestampable(on: 'update')]
    private \DateTimeInterface $updatedAt;

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

   

    #[ORM\PrePersist]
    public function initializeAuditDates(): void
    {
        $now = new \DateTime();

        if (!isset($this->createdAt)) {
            $this->createdAt = $now;
        }

        if (!isset($this->updatedAt)) {
            $this->updatedAt = $now;
        }
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

}
