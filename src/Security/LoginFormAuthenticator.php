<?php

namespace App\Security;

use App\Entity\OtpCode;
use App\Entity\User;
use App\Repository\OtpCodeRepository;
use App\Repository\UserRepository;
use App\Service\AdminNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Twig\Environment;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'app_login';
    private const FAILED_ATTEMPT_WINDOW_SECONDS = 900;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager,
        private OtpCodeRepository $otpCodeRepository,
        private UserRepository $userRepository,
        private AdminNotificationService $adminNotificationService,
        private MailerInterface $mailer,
        private Environment $twig,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $identifier = trim((string) $request->request->get('_username', ''));
        $password = (string) $request->request->get('_password', '');

        $request->getSession()->set(Security::LAST_USERNAME, $identifier);

        if ($identifier === '' || $password === '') {
            throw new CustomUserMessageAuthenticationException('Email et mot de passe obligatoires.');
        }

        if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            throw new CustomUserMessageAuthenticationException('Veuillez saisir une adresse email valide.');
        }

        return new Passport(
            new UserBadge($identifier, function (string $userIdentifier): User {
                $user = $this->userRepository->findOneByEmailInsensitive($userIdentifier);

                if (!$user instanceof User) {
                    throw new UserNotFoundException();
                }

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
            }),
            new CustomCredentials(
                function (string $plainPassword, UserInterface $user): bool {
                    if (!$user instanceof User) {
                        return false;
                    }

                    return $this->isStoredPasswordValid($user, $plainPassword);
                },
                $password
            ),
            [
                new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof CustomUserMessageAuthenticationException) {
            return parent::onAuthenticationFailure($request, $exception);
        }

        $identifier = trim((string) $request->request->get('_username', ''));
        if ($identifier === '') {
            return parent::onAuthenticationFailure($request, $exception);
        }

        if ($request->attributes->getBoolean('_login_attempt_already_counted')) {
            return parent::onAuthenticationFailure($request, $exception);
        }
        $request->attributes->set('_login_attempt_already_counted', true);

        $user = $this->userRepository->findOneByEmailInsensitive($identifier);
        if (!$user instanceof User) {
            return parent::onAuthenticationFailure(
                $request,
                new CustomUserMessageAuthenticationException('Email invalide.')
            );
        }

        $now = new \DateTimeImmutable();
        $lockUntil = $user->getLock_until();
        $lockExpiredReset = false;

        if ($lockUntil instanceof \DateTimeInterface && $lockUntil > $now) {
            $minutesLeft = (int) ceil(($lockUntil->getTimestamp() - $now->getTimestamp()) / 60);
            $minutesLeft = max(1, $minutesLeft);

            return parent::onAuthenticationFailure(
                $request,
                new CustomUserMessageAuthenticationException(
                    sprintf('Compte verrouille. Ressayez dans %d min.', $minutesLeft)
                )
            );
        }

        if ($lockUntil instanceof \DateTimeInterface && $lockUntil <= $now) {
            $user->setLock_until(null);
            $user->setFailed_login_count(0);
            $lockExpiredReset = true;
        }

        if (!$exception instanceof BadCredentialsException) {
            if ($lockExpiredReset) {
                $user->setUpdatedAt(new \DateTime());
                $this->entityManager->flush();
            }

            return parent::onAuthenticationFailure($request, $exception);
        }

        $currentFailedCount = (int) $user->getFailed_login_count();
        $updatedAt = $user->getUpdatedAt();
        $secondsSinceLastUpdate = $updatedAt instanceof \DateTimeInterface
            ? ($now->getTimestamp() - $updatedAt->getTimestamp())
            : self::FAILED_ATTEMPT_WINDOW_SECONDS + 1;

        if ($currentFailedCount > 0 && $secondsSinceLastUpdate > self::FAILED_ATTEMPT_WINDOW_SECONDS) {
            $currentFailedCount = 0;
            $user->setFailed_login_count(0);
        }

        $attempt = $currentFailedCount + 1;

        if ($attempt >= 3) {
            $lockedUntil = (new \DateTimeImmutable())->modify('+15 minutes');
            $user->setFailed_login_count(3);
            $user->setLock_until(\DateTime::createFromInterface($lockedUntil));
            $user->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            $this->adminNotificationService->notifyFailedLoginLock($user, $lockedUntil);

            return parent::onAuthenticationFailure(
                $request,
                new CustomUserMessageAuthenticationException('Mot de passe invalide. Tentative de connexion 3/3. Ressayez dans 15 min.')
            );
        }

        $user->setFailed_login_count($attempt);
        $user->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return parent::onAuthenticationFailure(
            $request,
            new CustomUserMessageAuthenticationException(sprintf('Mot de passe invalide. Tentative de connexion %d/3.', $attempt))
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            throw new AuthenticationException('Utilisateur invalide.');
        }

        $session = $request->getSession();
        $userId = $user->getIdUser();
        $verifiedUserId = (int) $session->get('login_otp_verified_user_id', 0);

        $user->setFailed_login_count(0);
        $user->setLock_until(null);
        $user->setLast_activity_at(new \DateTime());
        $user->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        if ($verifiedUserId > 0 && $verifiedUserId === $userId) {
            $session->remove('login_otp_verified');
            $session->remove('login_otp_verified_user_id');
            $session->remove('login_otp_cooldown_until');

            return match ($user->getRoleUser()) {
                'client' => new RedirectResponse($this->urlGenerator->generate('project_index')),
                'gerant' => new RedirectResponse($this->urlGenerator->generate('app_role_choice')),
                default => new RedirectResponse($this->urlGenerator->generate('app_back')),
            };
        }

        $email = mb_strtolower(trim((string) $user->getUserIdentifier()));

        $oldCodes = $this->otpCodeRepository->findUnusedForPurpose($email, 'login_otp');
        foreach ($oldCodes as $oldCode) {
            $oldCode->setUsed_at(new \DateTime());
        }

        $code = (string) random_int(100000, 999999);

        $otp = new OtpCode();
        $otp->setEmail($email);
        $otp->setPurpose('login_otp');
        $otp->setCode_hash(password_hash($code, PASSWORD_DEFAULT));
        $otp->setCreated_at(new \DateTime());
        $otp->setExpires_at((new \DateTime())->modify('+10 minutes'));
        $otp->setUsed_at(null);

        $this->entityManager->persist($otp);
        $this->entityManager->flush();

        $message = (new Email())
            ->from($_ENV['MAILER_FROM'] ?? 'lyynda19@gmail.com')
            ->to($email)
            ->subject('Code de vérification - Connexion Advisora')
            ->html($this->twig->render('emails/login_otp.html.twig', [
                'code' => $code,
                'expiresIn' => '10 minutes',
            ]));

        try {
            $this->mailer->send($message);
        } catch (\Throwable $e) {
            throw new AuthenticationException('Impossible d’envoyer le code de vérification.');
        }

        $this->tokenStorage->setToken(null);

        $session->invalidate();
        $session->start();
        $session->set('login_otp_user_id', $userId);
        $session->set('login_otp_verified', false);
        $session->set('login_otp_verified_user_id', 0);
        $session->set('login_otp_cooldown_until', time() + 30);

        return new RedirectResponse($this->urlGenerator->generate('app_login_verify_code'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    private function isStoredPasswordValid(User $user, string $plainPassword): bool
    {
        $storedPassword = (string) $user->getPassword();

        if ($storedPassword === '' || $plainPassword === '') {
            return false;
        }

        if ($this->looksHashed($storedPassword)) {
            return password_verify($plainPassword, $storedPassword);
        }

        return hash_equals($storedPassword, $plainPassword);
    }

    private function looksHashed(string $value): bool
    {
        return str_starts_with($value, '$2y$')
            || str_starts_with($value, '$2a$')
            || str_starts_with($value, '$2b$')
            || str_starts_with($value, '$argon2i$')
            || str_starts_with($value, '$argon2id$');
    }
}
