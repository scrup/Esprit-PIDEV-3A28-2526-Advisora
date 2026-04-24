<?php

namespace App\Controller;

use App\Entity\OtpCode;
use App\Entity\User;
use App\Repository\OtpCodeRepository;
use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        AuthenticationUtils $authenticationUtils,
        TranslatorInterface $translator
    ): Response
    {
        $session = $request->getSession();

        if ($session->get('login_otp_user_id') && !$session->get('login_otp_verified', false)) {
            return $this->redirectToRoute('app_login_verify_code');
        }

        $user = $this->getUser();
        if ($user instanceof User) {
            return match ($user->getRoleUser()) {
                'client' => $this->redirectToRoute('project_index'),
                'gerant' => $this->redirectToRoute('app_role_choice'),
                default => $this->redirectToRoute('app_back'),
            };
        }

        $authError = $authenticationUtils->getLastAuthenticationError();
        [$fieldErrors, $formError] = $this->resolveLoginErrors($authError, $translator);

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authError,
            'field_errors' => $fieldErrors,
            'form_error' => $formError,
        ]);
    }

    /**
     * @return array{0: array{email: ?string, password: ?string}, 1: ?string}
     */
    private function resolveLoginErrors(
        ?AuthenticationException $authError,
        TranslatorInterface $translator
    ): array {
        $fieldErrors = [
            'email' => null,
            'password' => null,
        ];
        $formError = null;

        if (!$authError instanceof AuthenticationException) {
            return [$fieldErrors, $formError];
        }

        $message = trim((string) $translator->trans(
            $authError->getMessageKey(),
            $authError->getMessageData(),
            'security'
        ));
        $normalized = mb_strtolower($message);

        if ($message === '') {
            return [$fieldErrors, $formError];
        }

        if (str_contains($normalized, 'email et mot de passe obligatoires')) {
            $fieldErrors['email'] = 'Email obligatoire.';
            $fieldErrors['password'] = 'Mot de passe obligatoire.';

            return [$fieldErrors, $formError];
        }

        if (
            str_contains($normalized, 'adresse email valide')
            || str_contains($normalized, 'email invalide')
        ) {
            $fieldErrors['email'] = 'Email invalide.';

            return [$fieldErrors, $formError];
        }

        if (
            str_contains($normalized, 'mot de passe invalide')
            || str_contains($normalized, 'tentative de connexion')
            || str_contains($normalized, 'invalid credentials')
            || str_contains($normalized, 'bad credentials')
        ) {
            $fieldErrors['password'] = $message;

            return [$fieldErrors, $formError];
        }

        $formError = $message;

        return [$fieldErrors, $formError];
    }

    #[Route('/role-choice', name: 'app_role_choice', methods: ['GET'])]
    public function roleChoice(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_GERANT');

        return $this->render('security/role_choice.html.twig');
    }

    #[Route('/connect/google', name: 'connect_google_start', methods: ['GET'])]
    public function connectGoogle(ClientRegistry $clientRegistry): Response
    {
        $clientId = trim((string) ($_ENV['OAUTH_GOOGLE_CLIENT_ID'] ?? ''));
        $clientSecret = trim((string) ($_ENV['OAUTH_GOOGLE_CLIENT_SECRET'] ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            $this->addFlash(
                'error',
                'Connexion Google indisponible. Configurez OAUTH_GOOGLE_CLIENT_ID et OAUTH_GOOGLE_CLIENT_SECRET.'
            );

            return $this->redirectToRoute('app_login');
        }

        return $clientRegistry
            ->getClient('google_main')
            ->redirect(['email', 'profile'], []);
    }

    #[Route('/connect/google/check', name: 'connect_google_check', methods: ['GET'])]
    public function connectGoogleCheck(): never
    {
        throw new \LogicException('Cette methode est interceptee par GoogleAuthenticator.');
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the logout key on your firewall.');
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        OtpCodeRepository $otpCodeRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        $session = $request->getSession();
        $cooldownEndsAt = (int) $session->get('reset_cooldown_until', 0);
        $nowTs = time();

        if ($request->isMethod('POST')) {
            if ($cooldownEndsAt > $nowTs) {
                $this->addFlash('error', 'Veuillez patienter avant de demander un nouveau code.');
                return $this->render('security/forgot_password.html.twig', [
                    'cooldownEndsAt' => $cooldownEndsAt,
                ]);
            }

            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            $errors = [];

            if ($email === '') {
                $errors[] = 'L’email est obligatoire.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email invalide.';
            }

            if (empty($errors)) {
                $user = $userRepository->findOneByEmailInsensitive($email);

                if (!$user) {
                    $this->addFlash('error', 'Aucun email trouvé.');
                    return $this->render('security/forgot_password.html.twig', [
                        'cooldownEndsAt' => $cooldownEndsAt,
                    ]);
                }

                $oldCodes = $otpCodeRepository->findUnusedForPurpose($email, 'forgot_password');
                foreach ($oldCodes as $oldCode) {
                    $oldCode->setUsed_at(new \DateTime());
                }

                $code = (string) random_int(100000, 999999);

                $otp = new OtpCode();
                $otp->setEmail($email);
                $otp->setPurpose('forgot_password');
                $otp->setCode_hash(password_hash($code, PASSWORD_DEFAULT));
                $otp->setCreated_at(new \DateTime());
                $otp->setExpires_at((new \DateTime())->modify('+15 minutes'));
                $otp->setUsed_at(null);

                $entityManager->persist($otp);
                $entityManager->flush();

                try {
                    $message = (new Email())
                        ->from('lyynda19@gmail.com')
                        ->to($email)
                        ->bcc('lyynda19@gmail.com')
                        ->subject('Réinitialisation du mot de passe - ' . (new \DateTime())->format('d/m H:i:s'))
                        ->text(
                            "Bonjour,\n\n" .
                            "Votre code de réinitialisation est : {$code}\n" .
                            "Ce code expire dans 15 minutes.\n\n" .
                            "Si vous n’êtes pas à l’origine de cette demande, ignorez cet email."
                        );

                    $mailer->send($message);

                    $session->set('reset_email', $email);
                    $session->set('reset_code_verified', false);
                    $session->set('reset_verified_otp_id', null);
                    $session->set('reset_cooldown_until', time() + 30);

                    $this->addFlash('success', 'Code envoyé avec succès à votre adresse email.');
                    return $this->redirectToRoute('app_reset_password');
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Erreur lors de l’envoi de l’email : ' . $e->getMessage());
                }
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('security/forgot_password.html.twig', [
            'cooldownEndsAt' => $cooldownEndsAt,
        ]);
    }

    #[Route('/reset-password', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        Request $request,
        UserRepository $userRepository,
        OtpCodeRepository $otpCodeRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $session = $request->getSession();
        $email = (string) $session->get('reset_email', '');
        $codeVerified = (bool) $session->get('reset_code_verified', false);

        if ($email === '') {
            $this->addFlash('error', 'Veuillez d’abord demander un code de réinitialisation.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', '');

            if ($action === 'verify_code') {
                $code = trim((string) $request->request->get('code', ''));
                $errors = [];

                if ($code === '') {
                    $errors[] = 'Le code est obligatoire.';
                }

                if (empty($errors)) {
                    $user = $userRepository->findOneByEmailInsensitive($email);
                    $otp = $otpCodeRepository->findLatestUnusedForPurpose($email, 'forgot_password');

                    if (!$user) {
                        $errors[] = 'Aucun email trouvé.';
                    } elseif (!$otp) {
                        $errors[] = 'Aucun code valide trouvé.';
                    } else {
                        $now = new \DateTime();

                        if ($otp->getExpires_at() < $now) {
                            $errors[] = 'Le code a expiré.';
                        } elseif (!password_verify($code, $otp->getCode_hash())) {
                            $errors[] = 'Code invalide.';
                        } else {
                            $session->set('reset_code_verified', true);
                            $session->set('reset_verified_otp_id', $otp->getId());

                            $this->addFlash('success', 'Code vérifié avec succès.');
                            return $this->redirectToRoute('app_reset_password');
                        }
                    }
                }

                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            }

            if ($action === 'change_password') {
                if (!$codeVerified) {
                    $this->addFlash('error', 'Veuillez vérifier votre code avant de changer le mot de passe.');
                    return $this->redirectToRoute('app_reset_password');
                }

                $password = (string) $request->request->get('password', '');
                $confirmPassword = (string) $request->request->get('confirm_password', '');
                $errors = [];

                if ($password === '') {
                    $errors[] = 'Le nouveau mot de passe est obligatoire.';
                } elseif (mb_strlen($password) < 6) {
                    $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
                }

                if ($confirmPassword === '') {
                    $errors[] = 'La confirmation du mot de passe est obligatoire.';
                } elseif ($password !== $confirmPassword) {
                    $errors[] = 'Les mots de passe ne correspondent pas.';
                }

                if (empty($errors)) {
                    $user = $userRepository->findOneByEmailInsensitive($email);
                    $otp = $otpCodeRepository->findLatestUnusedForPurpose($email, 'forgot_password');

                    if (!$user) {
                        $errors[] = 'Aucun email trouvé.';
                    } elseif (!$otp) {
                        $errors[] = 'Aucun code valide trouvé.';
                    } else {
                        $user->setPasswordUser($passwordHasher->hashPassword($user, $password));
                        $user->setUpdatedAt(new \DateTime());

                        $otp->setUsed_at(new \DateTime());

                        $entityManager->flush();

                        $session->remove('reset_email');
                        $session->remove('reset_code_verified');
                        $session->remove('reset_verified_otp_id');

                        $this->addFlash('success', 'Mot de passe réinitialisé avec succès.');
                        return $this->redirectToRoute('app_login');
                    }
                }

                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'resetEmail' => $email,
            'codeVerified' => $codeVerified,
        ]);
    }

    #[Route('/test-mail', name: 'app_test_mail')]
    public function testMail(MailerInterface $mailer): Response
    {
        try {
            $email = (new Email())
                ->from('lyynda19@gmail.com')
                ->to('lyynda19@gmail.com')
                ->subject('Test email')
                ->text('Test envoyé avec succès.');

            $mailer->send($email);

            return new Response('Email sent successfully');
        } catch (\Throwable $e) {
            return new Response('Mail error: ' . $e->getMessage());
        }
    }

    #[Route('/login/verify-code', name: 'app_login_verify_code', methods: ['GET', 'POST'])]
    public function verifyLoginCode(
        Request $request,
        UserRepository $userRepository,
        OtpCodeRepository $otpCodeRepository,
        EntityManagerInterface $entityManager,
        Security $security
    ): Response {
        $session = $request->getSession();
        $userId = $session->get('login_otp_user_id');

        if (!$userId) {
            $this->addFlash('error', 'Votre session de vérification a expiré. Veuillez vous reconnecter.');
            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->find($userId);

        if (!$user instanceof User) {
            $session->remove('login_otp_user_id');
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $code = trim((string) $request->request->get('code', ''));

            if ($code === '') {
                $this->addFlash('error', 'Le code est obligatoire.');
            } else {
                $email = mb_strtolower(trim((string) $user->getUserIdentifier()));
                $otp = $otpCodeRepository->findLatestUnusedForPurpose($email, 'login_otp');

                if (!$otp) {
                    $this->addFlash('error', 'Aucun code valide trouvé.');
                } elseif ($otp->getExpires_at() < new \DateTime()) {
                    $this->addFlash('error', 'Le code a expiré.');
                } elseif (!password_verify($code, $otp->getCode_hash())) {
                    $this->addFlash('error', 'Code invalide.');
                } else {
                    $otp->setUsed_at(new \DateTime());
                    $entityManager->flush();

                    $session->set('login_otp_verified', true);
                    $session->set('login_otp_verified_user_id', $user->getIdUser());
                    $session->remove('login_otp_user_id');

                    return $security->login($user, LoginFormAuthenticator::class, 'main');
                }
            }
        }

        return $this->render('security/verify_login_code.html.twig', [
            'email' => $user->getUserIdentifier(),
        ]);
    }

    #[Route('/login/resend-code', name: 'app_login_resend_code', methods: ['POST'])]
    public function resendLoginCode(
        Request $request,
        UserRepository $userRepository,
        OtpCodeRepository $otpCodeRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        $session = $request->getSession();
        $userId = $session->get('login_otp_user_id');

        if (!$userId) {
            $this->addFlash('error', 'Session expirée. Veuillez vous reconnecter.');
            return $this->redirectToRoute('app_login');
        }

        $cooldownEndsAt = (int) $session->get('login_otp_cooldown_until', 0);
        if ($cooldownEndsAt > time()) {
            $this->addFlash('error', 'Veuillez patienter avant de renvoyer un code.');
            return $this->redirectToRoute('app_login_verify_code');
        }

        $user = $userRepository->find($userId);
        if (!$user instanceof User) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_login');
        }

        $email = mb_strtolower(trim((string) $user->getUserIdentifier()));

        $oldCodes = $otpCodeRepository->findUnusedForPurpose($email, 'login_otp');
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

        $entityManager->persist($otp);
        $entityManager->flush();

        $message = (new Email())
            ->from($_ENV['MAILER_FROM'] ?? 'lyynda19@gmail.com')
            ->to($email)
            ->subject('Nouveau code de vérification - Advisora')
            ->html("
                <div style='font-family:Arial,sans-serif;color:#1f2937;line-height:1.6'>
                    <h2>Connexion sécurisée</h2>
                    <p>Voici votre nouveau code :</p>
                    <div style='font-size:32px;font-weight:700;letter-spacing:6px;background:#f3f4f6;padding:16px 24px;border-radius:12px;display:inline-block;margin:10px 0;'>
                        {$code}
                    </div>
                    <p>Ce code expire dans 10 minutes.</p>
                </div>
            ");

        $mailer->send($message);

        $session->set('login_otp_cooldown_until', time() + 30);
        $this->addFlash('success', 'Un nouveau code a été envoyé.');

        return $this->redirectToRoute('app_login_verify_code');
    }
}
