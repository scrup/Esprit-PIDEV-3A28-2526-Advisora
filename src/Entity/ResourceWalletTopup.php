<?php

namespace App\Entity;

use App\Entity\Trait\BlameableTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Repository\ResourceWalletTopupRepository;

#[ORM\Entity(repositoryClass: ResourceWalletTopupRepository::class)]
#[ORM\Table(name: 'resource_wallet_topup')]
class ResourceWalletTopup
{
    use BlameableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idTopup = null;

    public function getIdTopup(): ?int
    {
        return $this->idTopup;
    }

    public function setIdTopup(int $idTopup): self
    {
        $this->idTopup = $idTopup;
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
    private string $provider;

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 14, scale: 4, nullable: false)]
    private string $amountMoney;

    public function getAmountMoney(): float
    {
        return (float) $this->amountMoney;
    }

    public function setAmountMoney(int|float|string $amountMoney): self
    {
        $this->amountMoney = number_format((float) $amountMoney, 4, '.', '');
        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 14, scale: 4, nullable: false)]
    private string $coinAmount;

    public function getCoinAmount(): float
    {
        return (float) $this->coinAmount;
    }

    public function setCoinAmount(int|float|string $coinAmount): self
    {
        $this->coinAmount = number_format((float) $coinAmount, 4, '.', '');
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

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $externalRef = null;

    public function getExternalRef(): ?string
    {
        return $this->externalRef;
    }

    public function setExternalRef(?string $externalRef): self
    {
        $this->externalRef = $externalRef;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 700, nullable: true)]
    private ?string $paymentUrl = null;

    public function getPaymentUrl(): ?string
    {
        return $this->paymentUrl;
    }

    public function setPaymentUrl(?string $paymentUrl): self
    {
        $this->paymentUrl = $paymentUrl;
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

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $createdAt;

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

   

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $confirmedAt = null;

    public function getConfirmedAt(): ?\DateTimeInterface
    {
        return $this->confirmedAt;
    }

   

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

}
