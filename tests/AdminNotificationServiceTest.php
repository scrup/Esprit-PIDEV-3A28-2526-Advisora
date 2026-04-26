<?php

namespace App\Tests;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Service\AdminNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AdminNotificationServiceTest extends TestCase
{
    public function testNotifyFailedLoginLockCreatesOneNotificationPerAdmin(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $persisted = [];
        $entityManager
            ->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(static function (Notification $notification) use (&$persisted): void {
                $persisted[] = $notification;
            });
        $entityManager->expects(self::once())->method('flush');

        $notificationRepository = $this->createMock(NotificationRepository::class);
        $notificationRepository
            ->expects(self::exactly(2))
            ->method('existsForRecipientTitleDescriptionOnDate')
            ->willReturn(false);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findAdmins')
            ->willReturn([$this->makeUser(1, 'admin1@example.com', 'Admin', 'One', 'admin'), $this->makeUser(2, 'admin2@example.com', 'Admin', 'Two', 'admin')]);

        $service = new AdminNotificationService($entityManager, $notificationRepository, $userRepository);

        $user = $this->makeUser(99, 'test@example.com', 'Test', 'User', 'client');

        $service->notifyFailedLoginLock($user, new \DateTimeImmutable('+15 minutes'));

        self::assertCount(2, $persisted);
        self::assertSame('failed_login_lock', $persisted[0]->getEventType());
        self::assertSame('failed_login_lock', $persisted[1]->getEventType());
        self::assertSame('admin1@example.com', $persisted[0]->getRecipient()?->getEmailUser());
        self::assertSame('admin2@example.com', $persisted[1]->getRecipient()?->getEmailUser());
    }

    public function testNotifyInactiveGerantsSkipsExistingUnreadNotificationForAdmin(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $persisted = [];
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (Notification $notification) use (&$persisted): void {
                $persisted[] = $notification;
            });
        $entityManager->expects(self::once())->method('flush');

        $notificationRepository = $this->createMock(NotificationRepository::class);
        $notificationRepository
            ->expects(self::exactly(2))
            ->method('existsUnreadForRecipientAndTitle')
            ->willReturnCallback(static function (User $recipient): bool {
                return $recipient->getIdUser() === 1;
            });

        $userRepository = $this->createMock(UserRepository::class);
        $admins = [
            $this->makeUser(1, 'admin1@example.com', 'Admin', 'One', 'admin'),
            $this->makeUser(2, 'admin2@example.com', 'Admin', 'Two', 'admin'),
        ];
        $inactiveGerant = $this->makeUser(7, 'gerant@example.com', 'Gerant', 'Dormant', 'gerant');
        $inactiveGerant->setLast_activity_at(null);

        $userRepository
            ->expects(self::once())
            ->method('findAdmins')
            ->willReturn($admins);
        $userRepository
            ->expects(self::once())
            ->method('findInactiveGerants')
            ->willReturn([$inactiveGerant]);

        $service = new AdminNotificationService($entityManager, $notificationRepository, $userRepository);

        self::assertSame(1, $service->notifyInactiveGerants());
        self::assertCount(1, $persisted);
        self::assertSame('admin2@example.com', $persisted[0]->getRecipient()?->getEmailUser());
        self::assertSame('gerant_inactive', $persisted[0]->getEventType());
    }

    private function makeUser(int $id, string $email, string $prenom, string $nom, string $role): User
    {
        $user = new User();
        $user->setIdUser($id);
        $user->setEmailUser($email);
        $user->setPrenomUser($prenom);
        $user->setNomUser($nom);
        $user->setRoleUser($role);

        return $user;
    }
}
