<?php

namespace App\Entity;

use App\Repository\InvestmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvestmentRepository::class)]
#[ORM\Table(name: 'investment')]
class Investment
{
    private const DURATION_MARKER_PREFIX = '[DURATION]';
    private const DURATION_MARKER_SUFFIX = '[/DURATION]';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idInv', type: 'integer')]
    private ?int $idInv = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $commentaireInv = null;

    private ?string $durationEstimateLabel = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $dureeInv = null;

    #[ORM\Column(type: 'float', nullable: false)]
    private float $bud_minInv;

    #[ORM\Column(type: 'float', nullable: false)]
    private float $bud_maxInv;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $CurrencyInv;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'investments')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'idProj', nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'investments')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'idUser', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

  /**
 * @var Collection<int, Transaction>
 */
#[ORM\OneToMany(
    targetEntity: Transaction::class,
    mappedBy: 'investment',
    cascade: ['persist', 'remove'],
    orphanRemoval: true
)]
private Collection $transactions;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
    }

    public function getIdInv(): ?int
    {
        return $this->idInv;
    }

    public function setIdInv(int $idInv): self
    {
        $this->idInv = $idInv;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->idInv;
    }

    public function getCommentaireInv(): ?string
    {
        return $this->extractStoredComment()['comment'];
    }

    public function setCommentaireInv(?string $commentaireInv): self
    {
        $stored = $this->extractStoredComment();
        $this->commentaireInv = $this->composeStoredComment(
            $commentaireInv,
            $this->durationEstimateLabel ?? $stored['duration']
        );

        return $this;
    }

    public function getDureeInv(): ?\DateTimeInterface
    {
        return $this->dureeInv;
    }

    public function setDureeInv(?\DateTimeInterface $dureeInv): self
    {
        $this->dureeInv = $dureeInv;

        return $this;
    }

    public function getDurationEstimateLabel(): ?string
    {
        if ($this->durationEstimateLabel !== null) {
            return $this->durationEstimateLabel;
        }

        $stored = $this->extractStoredComment();
        if ($stored['duration'] !== null) {
            return $stored['duration'];
        }

        return $this->dureeInv?->format('H:i:s');
    }

    public function setDurationEstimateLabel(?string $durationEstimateLabel): self
    {
        $normalized = trim((string) $durationEstimateLabel);
        $this->durationEstimateLabel = $normalized !== '' ? $normalized : null;

        $stored = $this->extractStoredComment();
        $this->commentaireInv = $this->composeStoredComment(
            $stored['comment'],
            $this->durationEstimateLabel
        );

        return $this;
    }

    public function getDurationEstimateDisplay(): string
    {
        return $this->getDurationEstimateLabel() ?? 'Non precisee';
    }

    public function getBud_minInv(): float
    {
        return $this->bud_minInv;
    }

    public function getBudMinInv(): float
    {
        return $this->bud_minInv;
    }

    public function setBud_minInv(float $bud_minInv): self
    {
        $this->bud_minInv = $bud_minInv;

        return $this;
    }

    public function setBudMinInv(float $budMinInv): self
    {
        $this->bud_minInv = $budMinInv;

        return $this;
    }

    public function getBud_maxInv(): float
    {
        return $this->bud_maxInv;
    }

    public function getBudMaxInv(): float
    {
        return $this->bud_maxInv;
    }

    public function setBud_maxInv(float $bud_maxInv): self
    {
        $this->bud_maxInv = $bud_maxInv;

        return $this;
    }

    public function setBudMaxInv(float $budMaxInv): self
    {
        $this->bud_maxInv = $budMaxInv;

        return $this;
    }

    public function getCurrencyInv(): string
    {
        return $this->CurrencyInv;
    }

    public function setCurrencyInv(string $CurrencyInv): self
    {
        $this->CurrencyInv = $CurrencyInv;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): self
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setInvestment($this);
        }

        return $this;
    }

    public function removeTransaction(Transaction $transaction): self
    {
        if ($this->transactions->removeElement($transaction) && $transaction->getInvestment() === $this) {
            $transaction->setInvestment(null);
        }

        return $this;
    }

   public function getBudgetRangeLabel(): string
{
    return sprintf(
        '%s - %s %s',
        number_format((float) $this->getBud_minInv(), 2, '.', ' '),
        number_format((float) $this->getBud_maxInv(), 2, '.', ' '),
        $this->getCurrencyInv()
    );
}

public function getLatestTransaction(): ?Transaction
{
    $transactions = $this->getTransactions()->toArray();

    if ($transactions === []) {
        return null;
    }

    usort($transactions, static function (Transaction $left, Transaction $right): int {
        $dateComparison = $right->getDateTransac() <=> $left->getDateTransac();

        if ($dateComparison !== 0) {
            return $dateComparison;
        }

        return ($right->getId() ?? 0) <=> ($left->getId() ?? 0);
    });

    return $transactions[0];
}

public function getLatestTransactionStatus(): ?string
{
    return $this->getLatestTransaction()?->getStatut();
}

public function getTotalActiveTransactionsAmount(): float
{
    $total = 0.0;

    foreach ($this->transactions as $transaction) {
        if ($transaction->isFailed()) {
            continue;
        }

        $total += (float) $transaction->getMontantTransac();
    }

    return $total;
}

    public function isEditableByClient(): bool
    {
        $latestTransaction = $this->getLatestTransaction();

        return $latestTransaction === null || $latestTransaction->isPending();
    }

    public function isLockedForClient(): bool
    {
        return !$this->isEditableByClient();
    }

    /**
     * @return array{duration: ?string, comment: ?string}
     */
    private function extractStoredComment(): array
    {
        $raw = trim((string) ($this->commentaireInv ?? ''));

        if ($raw === '') {
            return [
                'duration' => null,
                'comment' => null,
            ];
        }

        $pattern = '/^\[DURATION\](.*?)\[\/DURATION\](?:\R(.*))?$/s';

        if (preg_match($pattern, $raw, $matches) === 1) {
            $duration = trim((string) $matches[1]);
            $comment = trim((string) ($matches[2] ?? ''));

            return [
                'duration' => $duration !== '' ? $duration : null,
                'comment' => $comment !== '' ? $comment : null,
            ];
        }

        return [
            'duration' => null,
            'comment' => $raw,
        ];
    }

    private function composeStoredComment(?string $comment, ?string $duration): ?string
    {
        $normalizedComment = trim((string) $comment);
        $normalizedDuration = trim((string) $duration);

        $parts = [];

        if ($normalizedDuration !== '') {
            $parts[] = self::DURATION_MARKER_PREFIX . $normalizedDuration . self::DURATION_MARKER_SUFFIX;
        }

        if ($normalizedComment !== '') {
            $parts[] = $normalizedComment;
        }

        $stored = implode("\n", $parts);

        return $stored !== '' ? $stored : null;
    }
}
