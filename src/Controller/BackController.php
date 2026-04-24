<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Investment;
use App\Entity\Notification;
use App\Entity\Project;
use App\Entity\Resource;
use App\Entity\User;
use App\Repository\DecisionRepository;
use App\Repository\EventRepository;
use App\Repository\InvestmentRepository;
use App\Repository\ProjectRepository;
use App\Repository\ResourceRepository;
use App\Repository\StrategieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BackController extends AbstractController
{
    #[Route('/back', name: 'app_back')]
    public function index(
        ProjectRepository $projectRepository,
        DecisionRepository $decisionRepository,
        StrategieRepository $strategieRepository,
        EventRepository $eventRepository,
        ResourceRepository $resourceRepository,
        InvestmentRepository $investmentRepository
    ): Response {
        $statusCounters = $projectRepository->getStatusCounters();
        $strategyAcceptanceTimeline = $strategieRepository->getAcceptanceTimeline();
        $latestProjects = $projectRepository->findLatestProjects(6);
        $latestDecisions = $decisionRepository->findLatestGlobal(6);
        $resources = $resourceRepository->findBackOfficeResources([]);
        $investments = $investmentRepository->findBackOfficeInvestments([]);
        $upcomingEvents = $this->getUpcomingEvents($eventRepository->findFrontEvents());

        $acceptedProjects = $statusCounters[Project::STATUS_ACCEPTED] ?? 0;
        $pendingProjects = $statusCounters[Project::STATUS_PENDING] ?? 0;
        $refusedProjects = $statusCounters[Project::STATUS_REFUSED] ?? 0;
        $totalProjects = array_sum($statusCounters);

        return $this->render('back/back.html.twig', [
            'user' => $this->getUser(),
            'total_projects' => $totalProjects,
            'pending_projects' => $pendingProjects,
            'accepted_projects' => $acceptedProjects,
            'refused_projects' => $refusedProjects,
            'total_decisions' => $decisionRepository->count([]),
            'latest_projects' => $latestProjects,
            'latest_decisions' => $latestDecisions,
            'strategy_acceptance_timeline' => $strategyAcceptanceTimeline,
            'upcoming_events' => $upcomingEvents,
            'upcoming_events_count' => count($upcomingEvents),
            'recent_resources' => array_slice($resources, 0, 5),
            'resource_metrics' => $this->buildResourceMetrics($resources),
            'investment_metrics' => $this->buildInvestmentMetrics($investments),
        ]);
    }

    /**
     * @param Event[] $events
     * @return Event[]
     */
    private function getUpcomingEvents(array $events): array
    {
        $now = new \DateTimeImmutable();
        $upcoming = array_values(array_filter(
            $events,
            static fn (Event $event): bool => $event->getStartDate() instanceof \DateTimeInterface && $event->getStartDate() >= $now
        ));

        return array_slice($upcoming, 0, 4);
    }

    /**
     * @param Resource[] $resources
     * @return array{total:int,available:int,linked:int}
     */
    private function buildResourceMetrics(array $resources): array
    {
        $available = 0;
        $linked = 0;

        foreach ($resources as $resource) {
            if (($resource->getQuantity() ?? 0) > 0 && $resource->getStatus() !== Resource::STATUS_UNAVAILABLE) {
                ++$available;
            }

            if ($resource->getProjects()->count() > 0) {
                ++$linked;
            }
        }

        return [
            'total' => count($resources),
            'available' => $available,
            'linked' => $linked,
        ];
    }

    /**
     * @param Investment[] $investments
     * @return array{count:int,total_amount:float,largest_ticket:float,distribution:array<int,array{label:string,value:float}>}
     */
    private function buildInvestmentMetrics(array $investments): array
    {
        $distribution = [];
        $totalAmount = 0.0;
        $largestTicket = 0.0;

        foreach ($investments as $investment) {
            $amount = (($investment->getBudMinInv() ?? 0.0) + ($investment->getBudMaxInv() ?? 0.0)) / 2;
            $label = strtoupper(trim((string) ($investment->getCurrencyInv() ?? 'TND')));
            $label = $label !== '' ? $label : 'TND';

            $totalAmount += $amount;
            $largestTicket = max($largestTicket, $amount);
            $distribution[$label] = ($distribution[$label] ?? 0.0) + $amount;
        }

        arsort($distribution);

        $topSlices = [];
        foreach (array_slice($distribution, 0, 4, true) as $label => $value) {
            $topSlices[] = [
                'label' => $label,
                'value' => round($value, 2),
            ];
        }

        return [
            'count' => count($investments),
            'total_amount' => round($totalAmount, 2),
            'largest_ticket' => round($largestTicket, 2),
            'distribution' => $topSlices,
        ];
    }

    #[Route('/back/notifications/{id}/read', name: 'back_notification_mark_read', methods: ['POST'])]
    public function markNotificationAsRead(
        Notification $notification,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || mb_strtolower((string) $user->getRoleUser()) !== 'admin') {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        if ($notification->getRecipient()?->getIdUser() !== $user->getIdUser()) {
            throw $this->createAccessDeniedException('Notification introuvable.');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('mark_notification_read_' . $notification->getId(), $token)) {
            $this->addFlash('error', 'Action invalide.');
        } else {
            $notification->setIsRead(true);
            $entityManager->flush();
        }

        $referer = (string) $request->headers->get('referer', '');
        if ($referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_back');
    }
}
