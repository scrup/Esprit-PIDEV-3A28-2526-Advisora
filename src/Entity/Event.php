<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_ev_id', type: 'integer')]
    private ?int $idEv = null;

    #[Gedmo\Translatable]
    #[ORM\Column(name: 'titleEv', type: 'string', length: 160)]
    private string $titleEv;

    #[Gedmo\Translatable]
    #[ORM\Column(name: 'descriptionEv', type: 'text', nullable: true)]
    private ?string $descriptionEv = null;

    #[ORM\Column(name: 'startDateEv', type: 'datetime')]
    private \DateTimeInterface $startDateEv;

    #[ORM\Column(name: 'endDateEv', type: 'datetime')]
    private \DateTimeInterface $endDateEv;

    #[ORM\Column(name: 'organisateurName', type: 'string', length: 160, nullable: true)]
    private ?string $organisateurName = null;

    #[ORM\Column(name: 'capaciteEvnt', type: 'integer', options: ['default' => 0])]
    private int $capaciteEvnt = 0;

    #[Gedmo\Translatable]
    #[ORM\Column(name: 'localisationEv', type: 'string', length: 190, nullable: true)]
    private ?string $localisationEv = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 8, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 11, scale: 8, nullable: true)]
    private ?string $longitude = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'events')]
    #[ORM\JoinColumn(name: 'gerant_id', referencedColumnName: 'idUser', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(
        targetEntity: Booking::class,
        mappedBy: 'event',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $bookings;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
    }

    public function getIdEv(): ?int
    {
        return $this->idEv;
    }

    public function getId(): ?int
    {
        return $this->idEv;
    }

    public function setIdEv(int $idEv): self
    {
        $this->idEv = $idEv;

        return $this;
    }

    public function getTitleEv(): string
    {
        return $this->titleEv;
    }

    public function getTitle(): string
    {
        return $this->titleEv;
    }

    public function setTitleEv(string $titleEv): self
    {
        $this->titleEv = $titleEv;

        return $this;
    }

    public function getDescriptionEv(): ?string
    {
        return $this->descriptionEv;
    }

    public function getDescription(): ?string
    {
        return $this->descriptionEv;
    }

    public function setDescriptionEv(?string $descriptionEv): self
    {
        $this->descriptionEv = $descriptionEv;

        return $this;
    }

    public function getStartDateEv(): \DateTimeInterface
    {
        return $this->startDateEv;
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDateEv;
    }

 public function setStartDateEv(?\DateTimeImmutable $startDateEv): self
{
    $this->startDateEv = $startDateEv;

    return $this;
}

public function setEndDateEv(?\DateTimeImmutable $endDateEv): self
{
    $this->endDateEv = $endDateEv;

    return $this;
}

    public function getEndDateEv(): \DateTimeInterface
    {
        return $this->endDateEv;
    }

    public function getEndDate(): \DateTimeInterface
    {
        return $this->endDateEv;
    }

    

    public function getOrganisateurName(): ?string
    {
        return $this->organisateurName;
    }

    public function getOrganizerName(): ?string
    {
        return $this->organisateurName;
    }

    public function setOrganisateurName(?string $organisateurName): self
    {
        $this->organisateurName = $organisateurName;

        return $this;
    }

    public function getCapaciteEvnt(): int
    {
        return $this->capaciteEvnt;
    }

    public function getCapacity(): int
    {
        return $this->capaciteEvnt;
    }

    public function setCapaciteEvnt(int $capaciteEvnt): self
    {
        $this->capaciteEvnt = $capaciteEvnt;

        return $this;
    }

    public function getLocalisationEv(): ?string
    {
        return $this->localisationEv;
    }

    public function getLocation(): ?string
    {
        return $this->localisationEv;
    }

    public function setLocalisationEv(?string $localisationEv): self
    {
        $this->localisationEv = $localisationEv;

        return $this;
    }

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

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
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): self
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setEvent($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        if ($this->bookings->removeElement($booking) && $booking->getEvent() === $this) {
            $booking->setEvent(null);
        }

        return $this;
    }

    public function getTimelineStatus(): string
    {
        $now = new \DateTimeImmutable();

        if ($this->endDateEv < $now) {
            return 'completed';
        }

        if (
            $this->startDateEv <= $now
            && $this->endDateEv >= $now
        ) {
            return 'in_progress';
        }

        return 'planned';
    }

    public function getTimelineLabel(): string
    {
        return match ($this->getTimelineStatus()) {
            'completed' => 'Termine',
            'in_progress' => 'En cours',
            default => 'A venir',
        };
    }

    public function getReservedTickets(): int
    {
        $reserved = 0;

        foreach ($this->bookings as $booking) {
            $reserved += max(0, (int) ($booking->getNumTicketBk() ?? 0));
        }

        return $reserved;
    }

    public function getRemainingTickets(): int
    {
        return max(0, $this->getCapacity() - $this->getReservedTickets());
    }

    public function getManagerDisplayName(): string
    {
        if (!$this->user instanceof User) {
            return 'Non assigne';
        }

        return trim(sprintf(
            '%s %s',
            (string) $this->user->getPrenomUser(),
            (string) $this->user->getNomUser()
        ));
    }

    public function hasStarted(): bool
    {
        return $this->startDateEv <= new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if ($this->endDateEv <= $this->startDateEv) {
            $context->buildViolation('La date de fin doit etre posterieure a la date de debut.')
                ->atPath('endDateEv')
                ->addViolation();
        }
    }

    public function getPrice(): ?float
    {
        return $this->price !== null ? (float) $this->price : null;
    }

    public function setPrice(int|float|string|null $price): self
    {
        $this->price = $price !== null ? number_format((float) $price, 2, '.', '') : null;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude !== null ? (float) $this->latitude : null;
    }

    public function setLatitude(int|float|string|null $latitude): self
    {
        $this->latitude = $latitude !== null ? number_format((float) $latitude, 8, '.', '') : null;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude !== null ? (float) $this->longitude : null;
    }

    public function setLongitude(int|float|string|null $longitude): self
    {
        $this->longitude = $longitude !== null ? number_format((float) $longitude, 8, '.', '') : null;

        return $this;
    }
}
