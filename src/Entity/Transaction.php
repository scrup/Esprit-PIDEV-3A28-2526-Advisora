<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\TransactionRepository;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transaction')]
class Transaction
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILED = 'FAILED';

    public const STATUSES = [
        self::STATUS_PENDING => 'En attente',
        self::STATUS_SUCCESS => 'Acceptee',
        self::STATUS_FAILED => 'Refusee',
    ];

    public const TYPE_INVESTMENT_PAYMENT = 'INVESTMENT_PAYMENT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idTransac = null;

    public function getIdTransac(): ?int
    {
        return $this->idTransac;
    }

    public function setIdTransac(int $idTransac): self
    {
        $this->idTransac = $idTransac;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->idTransac;
    }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $DateTransac = null;

    public function getDateTransac(): ?\DateTimeInterface
    {
        return $this->DateTransac;
    }

    public function setDateTransac(\DateTimeInterface $DateTransac): self
    {
        $this->DateTransac = $DateTransac;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $MontantTransac = null;

    public function getMontantTransac(): ?float
    {
        return $this->MontantTransac;
    }

    public function setMontantTransac(float $MontantTransac): self
    {
        $this->MontantTransac = $MontantTransac;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $type = null;

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $statut = null;

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->getStatut() ?? ''] ?? ((string) ($this->getStatut() ?? ''));
    }

    public function getStatusCssClass(): string
    {
        return strtolower((string) ($this->getStatut() ?? 'pending'));
    }

    public function isPending(): bool
    {
        return $this->getStatut() === self::STATUS_PENDING;
    }

    public function isSuccessful(): bool
    {
        return $this->getStatut() === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->getStatut() === self::STATUS_FAILED;
    }

    #[ORM\ManyToOne(targetEntity: Investment::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(name: 'idInv', referencedColumnName: 'idInv')]
    private ?Investment $investment = null;

    public function getInvestment(): ?Investment
    {
        return $this->investment;
    }

    public function setInvestment(?Investment $investment): self
    {
        $this->investment = $investment;
        return $this;
    }

    public function getClient(): ?User
    {
        return $this->getInvestment()?->getUser();
    }

    public function getClientDisplayName(): string
    {
        $client = $this->getClient();
        if (!$client instanceof User) {
            return 'Client inconnu';
        }

        $label = trim((string) ($client->getPrenomUser() . ' ' . $client->getNomUser()));

        return $label !== '' ? $label : ((string) $client->getEmailUser());
    }

}
