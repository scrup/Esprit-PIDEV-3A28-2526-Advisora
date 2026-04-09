<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $user = $token->getUser();

        if ($user instanceof User) {
            return match ($user->getRoleUser()) {
                'client' => new RedirectResponse($this->urlGenerator->generate('project_index')),
                'gerant' => new RedirectResponse($this->urlGenerator->generate('app_role_choice')),
                default => new RedirectResponse($this->urlGenerator->generate('app_back')),
            };
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }
}
