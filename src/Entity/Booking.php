<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\BookingRepository;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'bookings')]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idBk = null;

    public function getIdBk(): ?int
    {
        return $this->idBk;
    }

    public function setIdBk(int $idBk): self
    {
        $this->idBk = $idBk;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $bookingDate = null;

    public function getBookingDate(): ?\DateTimeInterface
    {
        return $this->bookingDate;
    }

    public function setBookingDate(\DateTimeInterface $bookingDate): self
    {
        $this->bookingDate = $bookingDate;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $numTicketBk = null;

    public function getNumTicketBk(): ?int
    {
        return $this->numTicketBk;
    }

    public function setNumTicketBk(int $numTicketBk): self
    {
        $this->numTicketBk = $numTicketBk;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $totalPrixBk = null;

    public function getTotalPrixBk(): ?float
    {
        return $this->totalPrixBk;
    }

    public function setTotalPrixBk(float $totalPrixBk): self
    {
        $this->totalPrixBk = $totalPrixBk;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(name: 'idEv', referencedColumnName: 'idEv')]
    private ?Event $event = null;

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'bookings')]
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

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $bookingStatus = null;

    public function getBookingStatus(): ?string
    {
        return $this->bookingStatus;
    }

    public function setBookingStatus(string $bookingStatus): self
    {
        $this->bookingStatus = $bookingStatus;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $paymentReference = null;

    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }

    public function setPaymentReference(?string $paymentReference): self
    {
        $this->paymentReference = $paymentReference;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $refundAmountBk = null;

    public function getRefundAmountBk(): ?float
    {
        return $this->refundAmountBk;
    }

    public function setRefundAmountBk(?float $refundAmountBk): self
    {
        $this->refundAmountBk = $refundAmountBk;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $refundDateBk = null;

    public function getRefundDateBk(): ?\DateTimeInterface
    {
        return $this->refundDateBk;
    }

    public function setRefundDateBk(?\DateTimeInterface $refundDateBk): self
    {
        $this->refundDateBk = $refundDateBk;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $cancelReasonBk = null;

    public function getCancelReasonBk(): ?string
    {
        return $this->cancelReasonBk;
    }

    public function setCancelReasonBk(?string $cancelReasonBk): self
    {
        $this->cancelReasonBk = $cancelReasonBk;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $notificationSentBk = null;

    public function isNotificationSentBk(): ?bool
    {
        return $this->notificationSentBk;
    }

    public function setNotificationSentBk(bool $notificationSentBk): self
    {
        $this->notificationSentBk = $notificationSentBk;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $reminder24SentBk = null;

    public function isReminder24SentBk(): ?bool
    {
        return $this->reminder24SentBk;
    }

    public function setReminder24SentBk(bool $reminder24SentBk): self
    {
        $this->reminder24SentBk = $reminder24SentBk;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $reminder48SentBk = null;

    public function isReminder48SentBk(): ?bool
    {
        return $this->reminder48SentBk;
    }

    public function setReminder48SentBk(bool $reminder48SentBk): self
    {
        $this->reminder48SentBk = $reminder48SentBk;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $qrTokenBk = null;

    public function getQrTokenBk(): ?string
    {
        return $this->qrTokenBk;
    }

    public function setQrTokenBk(?string $qrTokenBk): self
    {
        $this->qrTokenBk = $qrTokenBk;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $qrImagePathBk = null;

    public function getQrImagePathBk(): ?string
    {
        return $this->qrImagePathBk;
    }

    public function setQrImagePathBk(?string $qrImagePathBk): self
    {
        $this->qrImagePathBk = $qrImagePathBk;
        return $this;
    }

}
