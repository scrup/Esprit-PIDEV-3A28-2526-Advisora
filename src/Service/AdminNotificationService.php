<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class AdminNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notificationRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function notifyFailedLoginLock(User $user, \DateTimeInterface $lockUntil): void
    {
        $displayName = $this->buildUserDisplayName($user);
        $title = sprintf('3 tentatives echouees - %s', $displayName);
        $description = sprintf(
            'Le compte %s (%s) a atteint 3 tentatives de connexion sans succes. Verrouille jusqu a %s.',
            $displayName,
            mb_strtolower(trim((string) $user->getUserIdentifier())),
            $lockUntil->format('d/m/Y H:i')
        );

        $this->createRoleNotification('admin', $title, $description);
    }

    public function notifyInactiveGerants(int $inactiveDays = 4): int
    {
        $inactiveDays = max(1, $inactiveDays);
        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d days', $inactiveDays));
        $created = 0;

        foreach ($this->userRepository->findInactiveGerants($cutoff) as $gerant) {
            if (!$gerant instanceof User) {
                continue;
            }

            $displayName = $this->buildUserDisplayName($gerant);
            $title = sprintf('Gerant inactif (%d jours) - %s', $inactiveDays, $displayName);

            if ($this->notificationRepository->existsUnreadForRoleAndTitle('admin', $title)) {
                continue;
            }

            $lastActivity = $gerant->getLast_activity_at();
            $description = sprintf(
                'Le gerant %s (%s) est inactif depuis au moins %d jours. Derniere activite: %s.',
                $displayName,
                mb_strtolower(trim((string) $gerant->getUserIdentifier())),
                $inactiveDays,
                $lastActivity instanceof \DateTimeInterface ? $lastActivity->format('d/m/Y H:i') : 'jamais'
            );

            $notification = new Notification();
            $notification->setTitle($title);
            $notification->setDescription($description);
            $notification->setDateNotification(new \DateTime('today'));
            $notification->setIsRead(false);
            $notification->setTarget_role('admin');
            $notification->setTarget_project_id(null);

            $this->entityManager->persist($notification);
            ++$created;
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        return $created;
    }

    private function createRoleNotification(string $role, string $title, string $description): void
    {
        $date = new \DateTime('today');

        if ($this->notificationRepository->existsForRoleTitleDescriptionOnDate($role, $title, $description, $date)) {
            return;
        }

        $notification = new Notification();
        $notification->setTitle($title);
        $notification->setDescription($description);
        $notification->setDateNotification($date);
        $notification->setIsRead(false);
        $notification->setTarget_role(mb_strtolower(trim($role)));
        $notification->setTarget_project_id(null);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    private function buildUserDisplayName(User $user): string
    {
        $name = trim(sprintf('%s %s', (string) $user->getPrenomUser(), (string) $user->getNomUser()));

        return $name !== '' ? $name : mb_strtolower(trim((string) $user->getUserIdentifier()));
    }
}
