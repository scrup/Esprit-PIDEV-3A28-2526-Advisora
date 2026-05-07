<?php

namespace App\Tests;

use App\Controller\EventController;
use App\Entity\Booking;
use App\Entity\Event;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class EventManagementTest extends TestCase
{
    public function testEventManagementPermissionsDependOnRole(): void
    {
        $controller = new EventController();

        self::assertTrue($this->invokePrivate($controller, 'canManageEvents', [$this->makeUser('admin')]));
        self::assertTrue($this->invokePrivate($controller, 'canManageEvents', [$this->makeUser('gerant')]));
        self::assertFalse($this->invokePrivate($controller, 'canManageEvents', [$this->makeUser('client')]));
        self::assertFalse($this->invokePrivate($controller, 'canManageEvents', [null]));
    }

    public function testClientRoleCanBookEventsOnly(): void
    {
        $controller = new EventController();

        self::assertTrue($this->invokePrivate($controller, 'isClient', [$this->makeUser('client')]));
        self::assertFalse($this->invokePrivate($controller, 'isClient', [$this->makeUser('admin')]));
        self::assertFalse($this->invokePrivate($controller, 'isClient', [null]));
    }

    public function testNormalizeEventForPersistenceTrimsFieldsAndAssignsManager(): void
    {
        $controller = new EventController();
        $manager = $this->makeUser('gerant');

        $event = (new Event())
            ->setTitleEv('  Workshop IA  ')
            ->setOrganisateurName('  Advisora  ')
            ->setLocalisationEv('  Tunis  ')
            ->setDescriptionEv('   ')
            ->setCapaciteEvnt(0);

        $this->invokePrivate($controller, 'normalizeEventForPersistence', [$event, $manager]);

        self::assertSame('Workshop IA', $event->getTitleEv());
        self::assertSame('Advisora', $event->getOrganisateurName());
        self::assertSame('Tunis', $event->getLocalisationEv());
        self::assertNull($event->getDescriptionEv());
        self::assertSame(1, $event->getCapacity());
        self::assertSame($manager, $event->getUser());
    }

    public function testNormalizeEventForPersistenceKeepsMeaningfulDescriptionAndCapacity(): void
    {
        $controller = new EventController();

        $event = (new Event())
            ->setTitleEv('  Forum startup  ')
            ->setOrganisateurName('  Equipe innovation  ')
            ->setLocalisationEv('  Sfax  ')
            ->setDescriptionEv('  Rencontre avec investisseurs  ')
            ->setCapaciteEvnt(80);

        $this->invokePrivate($controller, 'normalizeEventForPersistence', [$event, null]);

        self::assertSame('Forum startup', $event->getTitleEv());
        self::assertSame('Equipe innovation', $event->getOrganisateurName());
        self::assertSame('Sfax', $event->getLocalisationEv());
        self::assertSame('Rencontre avec investisseurs', $event->getDescriptionEv());
        self::assertSame(80, $event->getCapacity());
        self::assertNull($event->getUser());
    }

    public function testEventRemainingTicketsNeverDropsBelowZero(): void
    {
        $event = (new Event())
            ->setTitleEv('Conference')
            ->setStartDateEv(new \DateTimeImmutable('+1 day'))
            ->setEndDateEv(new \DateTimeImmutable('+2 days'))
            ->setCapaciteEvnt(5);

        $event->addBooking((new Booking())->setNumTicketBk(3));
        $event->addBooking((new Booking())->setNumTicketBk(4));

        self::assertSame(7, $event->getReservedTickets());
        self::assertSame(0, $event->getRemainingTickets());
    }

    public function testBookingsAreSortedNewestFirstForBackOfficeView(): void
    {
        $controller = new EventController();

        $old = (new Booking())->setIdBk(1)->setBookingDate(new \DateTimeImmutable('2026-01-01 10:00:00'));
        $new = (new Booking())->setIdBk(2)->setBookingDate(new \DateTimeImmutable('2026-01-03 10:00:00'));
        $middle = (new Booking())->setIdBk(3)->setBookingDate(new \DateTimeImmutable('2026-01-02 10:00:00'));

        $sorted = $this->invokePrivate($controller, 'sortBookingsByDate', [[$old, $new, $middle]]);

        self::assertSame([2, 3, 1], array_map(static fn (Booking $booking): ?int => $booking->getId(), $sorted));
    }

    private function makeUser(string $role): User
    {
        $user = new User();
        $user->setRoleUser($role);

        return $user;
    }

    private function invokePrivate(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
