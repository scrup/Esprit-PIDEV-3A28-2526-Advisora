<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class UserActivitySubscriber implements EventSubscriberInterface
{
    private const ACTIVITY_REFRESH_SECONDS = 300;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onRequest',
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $now = new \DateTimeImmutable();
        $lastActivity = $user->getLast_activity_at();
        if (
            $lastActivity instanceof \DateTimeInterface
            && ($now->getTimestamp() - $lastActivity->getTimestamp()) < self::ACTIVITY_REFRESH_SECONDS
        ) {
            return;
        }

        $user->setLast_activity_at(\DateTime::createFromInterface($now));
        $this->entityManager->flush();
    }
}
