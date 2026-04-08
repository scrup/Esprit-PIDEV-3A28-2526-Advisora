<?php

namespace App\Controller;

use App\Repository\DecisionRepository;
use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BackController extends AbstractController
{
    #[Route('/back', name: 'app_back')]
    public function index(ProjectRepository $projectRepository, DecisionRepository $decisionRepository): Response
    {
        $statusCounters = $projectRepository->getStatusCounters();

        return $this->render('back/back.html.twig', [
            'user' => $this->getUser(),
            'total_projects' => array_sum($statusCounters),
            'pending_projects' => $statusCounters['PENDING'] ?? 0,
            'accepted_projects' => $statusCounters['ACCEPTED'] ?? 0,
            'refused_projects' => $statusCounters['REFUSED'] ?? 0,
            'total_decisions' => $decisionRepository->count([]),
            'latest_projects' => $projectRepository->findLatestProjects(6),
            'latest_decisions' => $decisionRepository->findLatestGlobal(6),
        ]);
    }
}
