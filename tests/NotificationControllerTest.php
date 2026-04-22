<?php

namespace App\Tests;

use App\Controller\NotificationController;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class NotificationControllerTest extends KernelTestCase
{
    public function testFeedReturnsOnlyCurrentUserUnreadNotifications(): void
    {
        self::bootKernel();

        $controller = self::getContainer()->get(NotificationController::class);
        $requestStack = $this->pushRequestWithSession(Request::create('/notifications/feed', 'GET'));
        $client = $this->authenticate('client', 101);
        $other = $this->createUser(202, 'admin');

        $mine = $this->createNotification(1, $client, 'Projet ajoute');
        $others = $this->createNotification(2, $other, 'Projet admin');
        $repository = new FakeNotificationRepository([$mine, $others]);

        try {
            $response = $controller->feed($repository);
        } finally {
            $requestStack->pop();
            $this->clearAuthentication();
        }

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(1, $payload['count']);
        self::assertCount(1, $payload['notifications']);
        self::assertSame(1, $payload['notifications'][0]['id']);
    }

    public function testConsumeMarksNotificationAsReadAndReturnsNewCount(): void
    {
        self::bootKernel();

        $controller = self::getContainer()->get(NotificationController::class);
        $requestStack = $this->pushRequestWithSession(Request::create('/notifications/1/consume', 'POST'));
        $client = $this->authenticate('client', 101);

        $notification = $this->createNotification(1, $client, 'Projet modifie');
        $repository = new FakeNotificationRepository([$notification]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        try {
            $response = $controller->consume(1, $repository, $entityManager);
        } finally {
            $requestStack->pop();
            $this->clearAuthentication();
        }

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($notification->isRead());
        self::assertTrue($payload['success']);
        self::assertSame(0, $payload['count']);
    }

    private function createNotification(int $id, User $recipient, string $title): Notification
    {
        return (new Notification())
            ->setId($id)
            ->setRecipient($recipient)
            ->setTitle($title)
            ->setDescription($title)
            ->setSpokenText($title)
            ->setEventType(Notification::EVENT_PROJECT_CREATED)
            ->setCreatedAt(new \DateTimeImmutable('2026-04-22 12:00:00'))
            ->setIsRead(false)
            ->setTargetProjectId(9);
    }

    private function pushRequestWithSession(Request $request): RequestStack
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request->setSession($session);

        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        return $requestStack;
    }

    private function authenticate(string $roleUser, int $id): User
    {
        $user = $this->createUser($id, $roleUser);

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = self::getContainer()->get('security.token_storage');
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        return $user;
    }

    private function clearAuthentication(): void
    {
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = self::getContainer()->get('security.token_storage');
        $tokenStorage->setToken(null);
    }

    private function createUser(int $id, string $role): User
    {
        $user = new User();
        $user->setIdUser($id);
        $user->setRoleUser($role);
        $user->setPrenomUser('Test');
        $user->setNomUser('User');
        $user->setEmailUser(sprintf('%s-%d@example.test', $role, $id));

        return $user;
    }
}

final class FakeNotificationRepository extends NotificationRepository
{
    /**
     * @param list<Notification> $notifications
     */
    public function __construct(private array $notifications)
    {
    }

    public function findUnreadForRecipient(User $recipient): array
    {
        return array_values(array_filter(
            $this->notifications,
            static fn (Notification $notification): bool => !$notification->isRead() && $notification->getRecipient()?->getIdUser() === $recipient->getIdUser()
        ));
    }

    public function countUnreadForRecipient(User $recipient): int
    {
        return count($this->findUnreadForRecipient($recipient));
    }

    public function findOneForRecipient(int $id, User $recipient): ?Notification
    {
        foreach ($this->notifications as $notification) {
            if ($notification->getId() === $id && $notification->getRecipient()?->getIdUser() === $recipient->getIdUser()) {
                return $notification;
            }
        }

        return null;
    }
}
