<?php

namespace App\Entity;

use App\Entity\Embeddable\Phone;
use App\Entity\Trait\BlameableTrait;
use App\Repository\ResourceMarketDeliveryRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: ResourceMarketDeliveryRepository::class)]
#[ORM\Table(name: 'resource_market_delivery')]
#[ORM\HasLifecycleCallbacks]
class ResourceMarketDelivery
{
    use BlameableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idDelivery = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $idOrder;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $buyerUserId;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $recipientName = '';

    #[ORM\Column(type: 'string', nullable: false)]
    private string $city = '';

    #[ORM\Column(type: 'string', nullable: false)]
    private string $addressLine = '';

    #[ORM\Embedded(class: Phone::class, columnPrefix: false)]
    private Phone $phone;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Gedmo\Translatable]
    private ?string $resourceName = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $quantity = 0;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: false)]
    private string $totalPrice = '0.0000';

    #[ORM\Column(type: 'string', nullable: false)]
    private string $status = '';

    #[ORM\Column(type: 'string', nullable: false)]
    private string $provider = '';

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $trackingCode = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $labelUrl = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Gedmo\Translatable]
    private ?string $providerMessage = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Gedmo\Timestampable(on: 'create')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Gedmo\Timestampable(on: 'update')]
    private \DateTimeInterface $updatedAt;

    #[Gedmo\Locale]
    private ?string $locale = null;

    public function __construct()
    {
        $this->phone = new Phone();
    }

    public function getIdDelivery(): ?int
    {
        return $this->idDelivery;
    }

    public function setIdDelivery(int $idDelivery): self
    {
        $this->idDelivery = $idDelivery;

        return $this;
    }

    public function getIdOrder(): int
    {
        return $this->idOrder;
    }

    public function setIdOrder(int $idOrder): self
    {
        $this->idOrder = $idOrder;

        return $this;
    }

    public function getBuyerUserId(): int
    {
        return $this->buyerUserId;
    }

    public function setBuyerUserId(int $buyerUserId): self
    {
        $this->buyerUserId = $buyerUserId;

        return $this;
    }

    public function getRecipientName(): string
    {
        return $this->recipientName;
    }

    public function setRecipientName(string $recipientName): self
    {
        $this->recipientName = $recipientName;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getAddressLine(): string
    {
        return $this->addressLine;
    }

    public function setAddressLine(string $addressLine): self
    {
        $this->addressLine = $addressLine;

        return $this;
    }

    public function getPhoneObject(): Phone
    {
        return $this->phone;
    }

    public function setPhoneObject(Phone $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone->getPrimary();
    }

    public function setPhone(?string $phone): self
    {
        $this->phone->setPrimary($phone);

        return $this;
    }

    public function getPhone2(): ?string
    {
        return $this->phone->getSecondary();
    }

    public function setPhone2(?string $phone2): self
    {
        $this->phone->setSecondary($phone2);

        return $this;
    }

    public function getResourceName(): ?string
    {
        return $this->resourceName;
    }

    public function setResourceName(?string $resourceName): self
    {
        $this->resourceName = $resourceName;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getTotalPrice(): float
    {
        return (float) $this->totalPrice;
    }

    public function setTotalPrice(int|float|string $totalPrice): self
    {
        $this->totalPrice = number_format((float) $totalPrice, 4, '.', '');

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getTrackingCode(): ?string
    {
        return $this->trackingCode;
    }

    public function setTrackingCode(?string $trackingCode): self
    {
        $this->trackingCode = $trackingCode;

        return $this;
    }

    public function getLabelUrl(): ?string
    {
        return $this->labelUrl;
    }

    public function setLabelUrl(?string $labelUrl): self
    {
        $this->labelUrl = $labelUrl;

        return $this;
    }

    public function getProviderMessage(): ?string
    {
        return $this->providerMessage;
    }

    public function setProviderMessage(?string $providerMessage): self
    {
        $this->providerMessage = $providerMessage;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

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