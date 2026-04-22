<?php

namespace App\Tests;

use App\Entity\Notification;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase
{
    public function testNotifyProjectCreatedPersistsClientAndBackOfficeNotifications(): void
    {
        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(3))
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });

        $client = $this->createUser(10, 'client');
        $admin = $this->createUser(20, 'admin');
        $gerant = $this->createUser(30, 'gerant');
        $project = $this->createProject(77, 'Projet Atlas', $client);

        $service = new NotificationService($entityManager, new FakeNotificationUserRepository([$admin, $gerant]));
        $service->notifyProjectCreated($project);

        self::assertCount(3, $persisted);
        self::assertSame([10, 20, 30], array_map(
            static fn (Notification $notification): ?int => $notification->getRecipient()?->getIdUser(),
            $persisted
        ));
        self::assertSame(Notification::EVENT_PROJECT_CREATED, $persisted[0]->getEventType());
        self::assertSame(77, $persisted[0]->getTargetProjectId());
    }

    public function testNotifyProjectUpdatedPersistsOnlyClientNotification(): void
    {
        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });

        $client = $this->createUser(10, 'client');
        $project = $this->createProject(88, 'Projet Nova', $client);

        $service = new NotificationService($entityManager, new FakeNotificationUserRepository([]));
        $service->notifyProjectUpdated($project);

        self::assertCount(1, $persisted);
        self::assertSame(10, $persisted[0]->getRecipient()?->getIdUser());
        self::assertSame(Notification::EVENT_PROJECT_UPDATED, $persisted[0]->getEventType());
        self::assertSame(88, $persisted[0]->getTargetProjectId());
    }

    public function testNotifyDecisionAddedPersistsClientAndBackOfficeNotifications(): void
    {
        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(3))
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });

        $client = $this->createUser(10, 'client');
        $admin = $this->createUser(20, 'admin');
        $gerant = $this->createUser(30, 'gerant');
        $project = $this->createProject(51, 'Projet Orion', $client);

        $service = new NotificationService($entityManager, new FakeNotificationUserRepository([$admin, $gerant]));
        $service->notifyDecisionAdded($project);

        self::assertCount(3, $persisted);
        self::assertSame(Notification::EVENT_DECISION_ADDED, $persisted[0]->getEventType());
        self::assertStringContainsString('Projet Orion', $persisted[0]->getDescription());
    }

    private function createProject(int $id, string $title, User $client): Project
    {
        $project = new Project();
        $project->setIdProj($id);
        $project->setTitle($title);
        $project->setUser($client);

        return $project;
    }

    private function createUser(int $id, string $role): User
    {
        $user = new User();
        $user->setIdUser($id);
        $user->setRoleUser($role);
        $user->setPrenomUser('Test');
        $user->setNomUser(ucfirst($role));
        $user->setEmailUser(sprintf('%s-%d@example.test', $role, $id));

        return $user;
    }
}

final class FakeNotificationUserRepository extends UserRepository
{
    /**
     * @param list<User> $recipients
     */
    public function __construct(private array $recipients)
    {
    }

    public function findAdminsAndGerants(): array
    {
        return $this->recipients;
    }
}
