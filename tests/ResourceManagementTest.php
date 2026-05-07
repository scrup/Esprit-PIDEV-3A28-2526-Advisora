<?php

namespace App\Tests;

use App\Controller\ResourceController;
use App\Entity\Project;
use App\Entity\Resource;
use App\Entity\ResourceMarketListing;
use App\Entity\ResourceMarketOrder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class ResourceManagementTest extends TestCase
{
    public function testResourceManagementPermissionsDependOnRole(): void
    {
        $controller = new ResourceController();

        self::assertTrue($this->invokePrivate($controller, 'canManageResources', [$this->makeUser('admin')]));
        self::assertTrue($this->invokePrivate($controller, 'canManageResources', [$this->makeUser('gerant')]));
        self::assertFalse($this->invokePrivate($controller, 'canManageResources', [$this->makeUser('client')]));
        self::assertFalse($this->invokePrivate($controller, 'canManageResources', [null]));
    }

    public function testOnlyClientsCanReserveResources(): void
    {
        $controller = new ResourceController();

        self::assertTrue($this->invokePrivate($controller, 'canReserveResources', [$this->makeUser('client')]));
        self::assertTrue($this->invokePrivate($controller, 'canReserveResources', [$this->makeUser('CLIENT')]));
        self::assertFalse($this->invokePrivate($controller, 'canReserveResources', [$this->makeUser('admin')]));
        self::assertFalse($this->invokePrivate($controller, 'canReserveResources', [null]));
    }

    public function testNormalizeResourceForPersistenceHandlesNameImageAndStatus(): void
    {
        $controller = new ResourceController();

        $resource = (new Resource())
            ->setName('   ')
            ->setQuantity(0)
            ->setStatus(Resource::STATUS_AVAILABLE)
            ->setPrice(25.0)
            ->setImageUrlRs('   ');

        $this->invokePrivate($controller, 'normalizeResourceForPersistence', [$resource]);

        self::assertSame('Ressource sans nom', $resource->getName());
        self::assertNull($resource->getImageUrlRs());
        self::assertSame(Resource::STATUS_UNAVAILABLE, $resource->getStatus());
    }

    public function testNormalizeResourceForPersistenceDefaultsUnknownPositiveStockStatus(): void
    {
        $controller = new ResourceController();

        $resource = (new Resource())
            ->setName('  Serveur GPU  ')
            ->setQuantity(5)
            ->setStatus('BROKEN')
            ->setPrice(500.0);

        $this->invokePrivate($controller, 'normalizeResourceForPersistence', [$resource]);

        self::assertSame('Serveur GPU', $resource->getName());
        self::assertSame(Resource::STATUS_AVAILABLE, $resource->getStatus());
    }

    public function testResourceDeletionIsBlockedWhenLinkedToAProject(): void
    {
        $controller = new ResourceController();
        $resource = (new Resource())->setIdRs(10)->addProject(new Project());
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entityManager->expects(self::never())->method('getRepository');

        self::assertTrue($this->invokePrivate($controller, 'hasBlockingResourceDependencies', [$resource, $entityManager]));
    }

    public function testResourceDeletionChecksMiniShopDependencies(): void
    {
        $controller = new ResourceController();
        $resource = (new Resource())->setIdRs(11);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $listingRepository = $this->createCountingRepository(1);
        $orderRepository = $this->createCountingRepository(0);

        $entityManager
            ->method('getRepository')
            ->willReturnCallback(static function (string $className) use ($listingRepository, $orderRepository): EntityRepository {
                return match ($className) {
                    ResourceMarketListing::class => $listingRepository,
                    ResourceMarketOrder::class => $orderRepository,
                    default => $orderRepository,
                };
            });

        self::assertTrue($this->invokePrivate($controller, 'hasBlockingResourceDependencies', [$resource, $entityManager]));
    }

    public function testResourceDeletionIsAllowedWithoutProjectOrShopDependencies(): void
    {
        $controller = new ResourceController();
        $resource = (new Resource())->setIdRs(12);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emptyRepository = $this->createCountingRepository(0);

        $entityManager
            ->method('getRepository')
            ->willReturn($emptyRepository);

        self::assertFalse($this->invokePrivate($controller, 'hasBlockingResourceDependencies', [$resource, $entityManager]));
    }

    private function createCountingRepository(int $count): EntityRepository
    {
        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['count'])
            ->getMock();
        $repository->method('count')->willReturn($count);

        return $repository;
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
