<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\AdminNotificationService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class BackNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly NotificationRepository $notificationRepository,
        private readonly AdminNotificationService $adminNotificationService,
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

        $request = $event->getRequest();
        $path = (string) $request->getPathInfo();
        if (!str_starts_with($path, '/back')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || mb_strtolower((string) $user->getRoleUser()) !== 'admin') {
            return;
        }

        $this->adminNotificationService->notifyInactiveGerants(4);

        $request->attributes->set(
            '_admin_notifications',
            $this->notificationRepository->findLatestForRole('admin', 8)
        );
        $request->attributes->set(
            '_admin_notifications_unread_count',
            $this->notificationRepository->countUnreadForRole('admin')
        );
    }
}

