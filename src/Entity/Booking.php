<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'booking')]
class Booking
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REFUSED = 'refused';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idBk', type: 'integer')]
    private ?int $idBk = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'bookingDate', type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $bookingDate;

    #[ORM\Column(name: 'numTicketBk', type: 'integer', options: ['default' => 1])]
    private int $numTicketBk = 1;

    #[ORM\Column(name: 'totalPrixBk', type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $totalPrixBk = '0.00';

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(name: 'event_id', referencedColumnName: 'id_ev_id', nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'idUser', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    private ?string $workflowStatus = null;

    public function getIdBk(): ?int
    {
        return $this->idBk;
    }

    public function getId(): ?int
    {
        return $this->idBk;
    }

    public function setIdBk(int $idBk): self
    {
        $this->idBk = $idBk;

        return $this;
    }

    public function getBookingDate(): \DateTimeInterface
    {
        return $this->bookingDate;
    }

    public function getBookedAt(): \DateTimeInterface
    {
        return $this->bookingDate;
    }

    

    public function getNumTicketBk(): ?int
    {
        return $this->numTicketBk;
    }

    public function getTicketCount(): int
    {
        return $this->numTicketBk;
    }

    public function setNumTicketBk(int $numTicketBk): self
    {
        $this->numTicketBk = $numTicketBk;

        return $this;
    }

    public function getTotalPrixBk(): ?float
    {
        return (float) $this->totalPrixBk;
    }

    public function setTotalPrixBk(int|float|string $totalPrixBk): self
    {
        $this->totalPrixBk = number_format((float) $totalPrixBk, 2, '.', '');

        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;

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

    public function isOwnedBy(?User $user): bool
    {
        return $user instanceof User
            && $this->user instanceof User
            && $this->user->getIdUser() === $user->getIdUser();
    }

    public function getClientDisplayName(): string
    {
        if (!$this->user instanceof User) {
            return 'Client inconnu';
        }

        return trim(sprintf('%s %s', (string) $this->user->getPrenomUser(), (string) $this->user->getNomUser()));
    }

    public function setWorkflowStatus(?string $workflowStatus): self
    {
        $normalizedStatus = strtolower(trim((string) $workflowStatus));

        if (!in_array($normalizedStatus, [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_REFUSED], true)) {
            $normalizedStatus = self::STATUS_PENDING;
        }

        $this->workflowStatus = $normalizedStatus;

        return $this;
    }

    public function getWorkflowStatus(): string
    {
        return $this->workflowStatus ?? self::STATUS_PENDING;
    }

    public function getWorkflowStatusLabel(): string
    {
        return match ($this->getWorkflowStatus()) {
            self::STATUS_ACCEPTED => 'Acceptee',
            self::STATUS_REFUSED => 'Refusee',
            default => 'En attente',
        };
    }

    public function getWorkflowStatusCssClass(): string
    {
        return match ($this->getWorkflowStatus()) {
            self::STATUS_ACCEPTED => 'accepted',
            self::STATUS_REFUSED => 'refused',
            default => 'pending',
        };
    }

    public function isPending(): bool
    {
        return $this->getWorkflowStatus() === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->getWorkflowStatus() === self::STATUS_ACCEPTED;
    }

    public function isRefused(): bool
    {
        return $this->getWorkflowStatus() === self::STATUS_REFUSED;
    }
}
