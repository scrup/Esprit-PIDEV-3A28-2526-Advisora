<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $client = $this->clientRegistry->getClient('google_main');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($client, $accessToken): User {
                /** @var AccessToken $accessToken */
                $googleUser = $client->fetchUserFromToken($accessToken);
                if (!$googleUser instanceof GoogleUser) {
                    throw new CustomUserMessageAuthenticationException('Le profil Google retourne est invalide.');
                }
                $email = mb_strtolower(trim((string) $googleUser->getEmail()));

                if ($email === '') {
                    throw new CustomUserMessageAuthenticationException('Google n a pas retourne d email valide.');
                }

                $user = $this->userRepository->findOneByEmailInsensitive($email);

                if ($user instanceof User) {
                    $lockUntil = $user->getLock_until();
                    $now = new \DateTimeImmutable();

                    if ($lockUntil instanceof \DateTimeInterface && $lockUntil > $now) {
                        $minutesLeft = (int) ceil(($lockUntil->getTimestamp() - $now->getTimestamp()) / 60);
                        $minutesLeft = max(1, $minutesLeft);

                        throw new CustomUserMessageAuthenticationException(
                            sprintf('Compte verrouille. Ressayez dans %d min.', $minutesLeft)
                        );
                    }

                    return $user;
                }

                $fullName = trim((string) $googleUser->getName());
                $firstName = trim((string) $googleUser->getFirstName());
                $lastName = trim((string) $googleUser->getLastName());

                if ($firstName === '' && $lastName === '' && $fullName !== '') {
                    $parts = preg_split('/\s+/', $fullName) ?: [];
                    if (!empty($parts)) {
                        $firstName = (string) array_shift($parts);
                        $lastName = trim((string) implode(' ', $parts));
                    }
                }

                if ($firstName === '') {
                    $firstName = 'Google';
                }
                if ($lastName === '') {
                    $lastName = 'User';
                }

                $now = new \DateTime();
                $newUser = new User();
                $newUser->setEmailUser($email);
                $newUser->setPrenomUser($firstName);
                $newUser->setNomUser($lastName);
                $newUser->setRoleUser('client');
                $newUser->setTotp_enabled(false);
                $newUser->setFailed_login_count(0);
                $newUser->setLock_until(null);
                $newUser->setLast_activity_at($now);
                $newUser->setCreatedAt($now);
                $newUser->setUpdatedAt($now);
                $newUser->setPasswordUser(
                    $this->passwordHasher->hashPassword($newUser, bin2hex(random_bytes(24)))
                );

                $this->entityManager->persist($newUser);
                $this->entityManager->flush();

                return $newUser;
            })
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?Response {
        $user = $token->getUser();

        if ($user instanceof User) {
            $user->setFailed_login_count(0);
            $user->setLock_until(null);
            $user->setLast_activity_at(new \DateTime());
            $user->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();
        }

        return match ($user instanceof User ? $user->getRoleUser() : null) {
            'client' => new RedirectResponse($this->urlGenerator->generate('project_index')),
            'gerant' => new RedirectResponse($this->urlGenerator->generate('app_role_choice')),
            default => new RedirectResponse($this->urlGenerator->generate('app_back')),
        };
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = $exception->getMessageKey();
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', $message);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
