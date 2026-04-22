<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NotificationController extends AbstractController
{
    #[Route('/notifications/feed', name: 'notification_feed', methods: ['GET'])]
    public function feed(NotificationRepository $notificationRepository): JsonResponse
    {
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Vous devez etre connecte pour consulter les notifications.');
        }

        $notifications = $notificationRepository->findUnreadForRecipient($user);

        return $this->json([
            'count' => count($notifications),
            'notifications' => array_map(
                fn (Notification $notification): array => $this->serializeNotification($notification, $user),
                $notifications
            ),
        ]);
    }

    #[Route('/notifications/{id}/consume', name: 'notification_consume', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function consume(
        int $id,
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Vous devez etre connecte pour consommer une notification.');
        }

        $notification = $notificationRepository->findOneForRecipient($id, $user);
        if (!$notification instanceof Notification) {
            return $this->json(['message' => 'Notification introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'count' => $notificationRepository->countUnreadForRecipient($user),
        ]);
    }

    private function serializeNotification(Notification $notification, User $user): array
    {
        return [
            'id' => $notification->getId(),
            'title' => $notification->getTitle(),
            'description' => $notification->getDescription(),
            'spokenText' => $notification->getSpokenText(),
            'targetProjectId' => $notification->getTargetProjectId(),
            'targetUrl' => $this->resolveTargetUrl($notification, $user),
            'createdAt' => $notification->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'eventType' => $notification->getEventType(),
        ];
    }

    private function resolveTargetUrl(Notification $notification, User $user): string
    {
        if ($notification->getEventType() === Notification::EVENT_PROJECT_DELETED || $notification->getTargetProjectId() === null) {
            return $this->generateUrl(
                $this->isBackOfficeUser($user) ? 'back_project_index' : 'project_index',
                [],
                UrlGeneratorInterface::ABSOLUTE_PATH
            );
        }

        return $this->generateUrl(
            $this->isBackOfficeUser($user) ? 'project_back_manage' : 'project_manage',
            ['id' => $notification->getTargetProjectId()],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function isBackOfficeUser(User $user): bool
    {
        return in_array($user->getRoleUser(), ['admin', 'gerant'], true);
    }
}
