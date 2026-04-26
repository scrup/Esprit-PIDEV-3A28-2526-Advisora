<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function notifyProjectCreated(Project $project): void
    {
        $client = $project->getUser();
        if ($client instanceof User) {
            $this->createNotification(
                $client,
                'Projet ajoute',
                'Votre projet a ete ajoute avec succes.',
                sprintf('Votre projet %s a ete ajoute avec succes.', $project->getTitle() ?: 'sans titre'),
                Notification::EVENT_PROJECT_CREATED,
                $project->getId()
            );
        }

        foreach ($this->userRepository->findAdminsAndGerants() as $recipient) {
            $this->createNotification(
                $recipient,
                'Nouveau projet client',
                sprintf('Un nouveau projet client a ete cree : %s.', $project->getTitle() ?: 'Projet sans titre'),
                sprintf('Nouveau projet client recu. Projet %s.', $project->getTitle() ?: 'sans titre'),
                Notification::EVENT_PROJECT_CREATED,
                $project->getId()
            );
        }
    }

    public function notifyProjectUpdated(Project $project): void
    {
        $client = $project->getUser();
        if (!$client instanceof User) {
            return;
        }

        $this->createNotification(
            $client,
            'Projet modifie',
            'Votre projet a ete modifie avec succes.',
            sprintf('Votre projet %s a ete modifie avec succes.', $project->getTitle() ?: 'sans titre'),
            Notification::EVENT_PROJECT_UPDATED,
            $project->getId()
        );
    }

    public function notifyProjectDeleted(Project $project): void
    {
        $client = $project->getUser();
        if (!$client instanceof User) {
            return;
        }

        $this->createNotification(
            $client,
            'Projet supprime',
            'Votre projet a ete supprime avec succes.',
            sprintf('Votre projet %s a ete supprime avec succes.', $project->getTitle() ?: 'sans titre'),
            Notification::EVENT_PROJECT_DELETED,
            null
        );
    }

    public function notifyDecisionAdded(Project $project): void
    {
        $client = $project->getUser();
        if ($client instanceof User) {
            $this->createNotification(
                $client,
                'Nouvelle decision',
                sprintf('Une nouvelle decision a ete ajoutee sur votre projet %s.', $project->getTitle() ?: 'Projet sans titre'),
                sprintf('Nouvelle decision ajoutee sur votre projet %s.', $project->getTitle() ?: 'sans titre'),
                Notification::EVENT_DECISION_ADDED,
                $project->getId()
            );
        }

        foreach ($this->userRepository->findAdminsAndGerants() as $recipient) {
            $this->createNotification(
                $recipient,
                'Decision ajoutee',
                sprintf('Une nouvelle decision a ete ajoutee sur le projet %s.', $project->getTitle() ?: 'Projet sans titre'),
                sprintf('Nouvelle decision ajoutee sur le projet %s.', $project->getTitle() ?: 'sans titre'),
                Notification::EVENT_DECISION_ADDED,
                $project->getId()
            );
        }
    }

    private function createNotification(
        User $recipient,
        string $title,
        string $description,
        string $spokenText,
        string $eventType,
        ?int $targetProjectId
    ): void {
        $notification = (new Notification())
            ->setRecipient($recipient)
            ->setTitle($title)
            ->setDescription($description)
            ->setSpokenText($spokenText)
            ->setEventType($eventType)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setIsRead(false)
            ->setTargetProjectId($targetProjectId);

        $this->entityManager->persist($notification);
    }
}
