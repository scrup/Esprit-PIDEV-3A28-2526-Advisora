<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\InvestmentRepository;

#[ORM\Entity(repositoryClass: InvestmentRepository::class)]
#[ORM\Table(name: 'investments')]
class Investment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idInv', type: 'integer')]
    private ?int $idInv = null;

    public function getIdInv(): ?int
    {
        return $this->idInv;
    }

    public function setIdInv(int $idInv): self
    {
        $this->idInv = $idInv;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $commentaireInv = null;

    public function getCommentaireInv(): ?string
    {
        return $this->commentaireInv;
    }

    public function setCommentaireInv(?string $commentaireInv): self
    {
        $this->commentaireInv = $commentaireInv;
        return $this;
    }

    #[ORM\Column(type: 'time', nullable: true)]
    private ?string $dureeInv = null;

    public function getDureeInv(): ?string
    {
        return $this->dureeInv;
    }

    public function setDureeInv(?string $dureeInv): self
    {
        $this->dureeInv = $dureeInv;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $bud_minInv = null;

    public function getBud_minInv(): ?float
    {
        return $this->bud_minInv;
    }

    public function setBud_minInv(float $bud_minInv): self
    {
        $this->bud_minInv = $bud_minInv;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $bud_maxInv = null;

    public function getBud_maxInv(): ?float
    {
        return $this->bud_maxInv;
    }

    public function setBud_maxInv(float $bud_maxInv): self
    {
        $this->bud_maxInv = $bud_maxInv;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $CurrencyInv = null;

    public function getCurrencyInv(): ?string
    {
        return $this->CurrencyInv;
    }

    public function setCurrencyInv(string $CurrencyInv): self
    {
        $this->CurrencyInv = $CurrencyInv;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'investments')]
    #[ORM\JoinColumn(name: 'idProj', referencedColumnName: 'idProj')]
    private ?Project $project = null;

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'investments')]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'idUser')]
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'investment')]
    private Collection $transactions;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        if (!$this->transactions instanceof Collection) {
            $this->transactions = new ArrayCollection();
        }
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): self
    {
        if (!$this->getTransactions()->contains($transaction)) {
            $this->getTransactions()->add($transaction);
        }
        return $this;
    }

    public function removeTransaction(Transaction $transaction): self
    {
        $this->getTransactions()->removeElement($transaction);
        return $this;
    }

    public function getBudMinInv(): ?string
    {
        return $this->bud_minInv;
    }

    public function setBudMinInv(string $bud_minInv): static
    {
        $this->bud_minInv = $bud_minInv;

        return $this;
    }

    public function getBudMaxInv(): ?string
    {
        return $this->bud_maxInv;
    }

    public function setBudMaxInv(string $bud_maxInv): static
    {
        $this->bud_maxInv = $bud_maxInv;

        return $this;
    }

}
