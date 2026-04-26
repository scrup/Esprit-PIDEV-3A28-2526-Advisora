<?php

namespace App\Tests;

use App\Controller\ClientProfileController;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class ClientProfileControllerTest extends KernelTestCase
{
    public function testShowRendersProfileForClient(): void
    {
        self::bootKernel();

        $controller = self::getContainer()->get(ClientProfileController::class);
        $request = Request::create('/profile', 'GET');
        $requestStack = $this->pushRequestWithSession($request);
        $this->authenticate('client');

        try {
            $response = $controller->show();
        } finally {
            $requestStack->pop();
            $this->clearAuthentication();
        }

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Mon profil', (string) $response->getContent());
    }

    public function testShowDeniedForNonClient(): void
    {
        self::bootKernel();

        $controller = self::getContainer()->get(ClientProfileController::class);
        $request = Request::create('/profile', 'GET');
        $requestStack = $this->pushRequestWithSession($request);
        $this->authenticate('admin');

        $this->expectException(AccessDeniedException::class);

        try {
            $controller->show();
        } finally {
            $requestStack->pop();
            $this->clearAuthentication();
        }
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

    private function authenticate(string $roleUser): void
    {
        $user = new User();
        $user->setRoleUser($roleUser);
        $user->setPrenomUser('Test');
        $user->setNomUser('User');
        $user->setEmailUser(sprintf('%s@example.test', $roleUser));

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = self::getContainer()->get('security.token_storage');
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    private function clearAuthentication(): void
    {
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = self::getContainer()->get('security.token_storage');
        $tokenStorage->setToken(null);
    }
}
