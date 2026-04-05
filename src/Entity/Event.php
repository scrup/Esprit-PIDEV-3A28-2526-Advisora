<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\EventRepository;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'events')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idEv = null;

    public function getIdEv(): ?int
    {
        return $this->idEv;
    }

    public function setIdEv(int $idEv): self
    {
        $this->idEv = $idEv;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $titleEv = null;

    public function getTitleEv(): ?string
    {
        return $this->titleEv;
    }

    public function setTitleEv(string $titleEv): self
    {
        $this->titleEv = $titleEv;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionEv = null;

    public function getDescriptionEv(): ?string
    {
        return $this->descriptionEv;
    }

    public function setDescriptionEv(?string $descriptionEv): self
    {
        $this->descriptionEv = $descriptionEv;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $startDateEv = null;

    public function getStartDateEv(): ?\DateTimeInterface
    {
        return $this->startDateEv;
    }

    public function setStartDateEv(\DateTimeInterface $startDateEv): self
    {
        $this->startDateEv = $startDateEv;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $endDateEv = null;

    public function getEndDateEv(): ?\DateTimeInterface
    {
        return $this->endDateEv;
    }

    public function setEndDateEv(\DateTimeInterface $endDateEv): self
    {
        $this->endDateEv = $endDateEv;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $organisateurName = null;

    public function getOrganisateurName(): ?string
    {
        return $this->organisateurName;
    }

    public function setOrganisateurName(?string $organisateurName): self
    {
        $this->organisateurName = $organisateurName;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $capaciteEvnt = null;

    public function getCapaciteEvnt(): ?int
    {
        return $this->capaciteEvnt;
    }

    public function setCapaciteEvnt(int $capaciteEvnt): self
    {
        $this->capaciteEvnt = $capaciteEvnt;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $localisationEv = null;

    public function getLocalisationEv(): ?string
    {
        return $this->localisationEv;
    }

    public function setLocalisationEv(?string $localisationEv): self
    {
        $this->localisationEv = $localisationEv;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'events')]
    #[ORM\JoinColumn(name: 'idGerant', referencedColumnName: 'idUser')]
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

    #[ORM\Column(type: 'decimal', nullable: false)]
    private ?float $ticketPrice = null;

    public function getTicketPrice(): ?float
    {
        return $this->ticketPrice;
    }

    public function setTicketPrice(float $ticketPrice): self
    {
        $this->ticketPrice = $ticketPrice;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $currencyCode = null;

    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): self
    {
        $this->currencyCode = $currencyCode;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $minReservationThreshold = null;

    public function getMinReservationThreshold(): ?float
    {
        return $this->minReservationThreshold;
    }

    public function setMinReservationThreshold(?float $minReservationThreshold): self
    {
        $this->minReservationThreshold = $minReservationThreshold;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $thresholdDeadline = null;

    public function getThresholdDeadline(): ?\DateTimeInterface
    {
        return $this->thresholdDeadline;
    }

    public function setThresholdDeadline(?\DateTimeInterface $thresholdDeadline): self
    {
        $this->thresholdDeadline = $thresholdDeadline;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $statusEv = null;

    public function getStatusEv(): ?string
    {
        return $this->statusEv;
    }

    public function setStatusEv(string $statusEv): self
    {
        $this->statusEv = $statusEv;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $categoryEv = null;

    public function getCategoryEv(): ?string
    {
        return $this->categoryEv;
    }

    public function setCategoryEv(?string $categoryEv): self
    {
        $this->categoryEv = $categoryEv;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $imageUrlEv = null;

    public function getImageUrlEv(): ?string
    {
        return $this->imageUrlEv;
    }

    public function setImageUrlEv(?string $imageUrlEv): self
    {
        $this->imageUrlEv = $imageUrlEv;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'event')]
    private Collection $bookings;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        if (!$this->bookings instanceof Collection) {
            $this->bookings = new ArrayCollection();
        }
        return $this->bookings;
    }

    public function addBooking(Booking $booking): self
    {
        if (!$this->getBookings()->contains($booking)) {
            $this->getBookings()->add($booking);
        }
        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        $this->getBookings()->removeElement($booking);
        return $this;
    }

}
