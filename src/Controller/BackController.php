<?php

namespace App\Controller;

use App\Repository\DecisionRepository;
use App\Repository\ProjectRepository;
use App\Entity\Strategie;
use App\Form\StrategyType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    // ============================================
    // STRATEGIES CRUD OPERATIONS
    // ============================================

    /**
     * List all strategies
     */
    #[Route('/back/strategies', name: 'app_back_strategies', methods: ['GET'])]
    public function strategies(EntityManagerInterface $em): Response
    {
        $strategies = $em->getRepository(Strategie::class)->findBy([], ['CreatedAtS' => 'DESC']);
        
        return $this->render('back/strategie/strategie.html.twig', [
            'strategies' => $strategies,
        ]);
    }

    /**
     * Create a new strategy
     */
    #[Route('/back/strategies/nouvelle', name: 'app_back_strategies_new', methods: ['GET', 'POST'])]
    public function newStrategy(Request $request, EntityManagerInterface $em): Response
    {
        $strategy = new Strategie();
        $form = $this->createForm(StrategyType::class, $strategy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set created date if not already set
            if (!$strategy->getCreatedAtS()) {
                $strategy->setCreatedAtS(new \DateTimeImmutable());
            }
            
            $em->persist($strategy);
            $em->flush();
            
            $this->addFlash('success', 'Stratégie créée avec succès !');
            return $this->redirectToRoute('app_back_strategies');
        }

        return $this->render('back/strategie/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Edit an existing strategy
     */
    #[Route('/back/strategies/{id}/edit', name: 'app_back_strategies_edit', methods: ['GET', 'POST'])]
    public function editStrategy(Request $request, Strategie $strategy, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(StrategyType::class, $strategy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $strategy->setLockedAt(new \DateTimeImmutable());
            $em->flush();
            
            $this->addFlash('success', 'Stratégie modifiée avec succès !');
            return $this->redirectToRoute('app_back_strategies');
        }

        return $this->render('back/strategie/edit.html.twig', [
            'form' => $form->createView(),
            'strategy' => $strategy,
        ]);
    }

    /**
     * Delete a strategy
     */
    #[Route('/back/strategies/{id}/delete', name: 'app_back_strategies_delete', methods: ['POST'])]
    public function deleteStrategy(Request $request, Strategie $strategy, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $strategy->getIdStrategie(), $request->request->get('_token'))) {
            $em->remove($strategy);
            $em->flush();
            $this->addFlash('success', 'Stratégie supprimée avec succès !');
        } else {
            $this->addFlash('error', 'Token invalide. Suppression impossible.');
        }

        return $this->redirectToRoute('app_back_strategies');
    }

    /**
     * View a single strategy
     */
    #[Route('/back/strategies/{id}/show', name: 'app_back_strategies_show', methods: ['GET'])]
    public function showStrategy(Strategie $strategy): Response
    {
        return $this->render('back/strategie/show.html.twig', [
            'strategy' => $strategy,
        ]);
    }

    /**
     * Update strategy status
     */
    #[Route('/back/strategies/{id}/status', name: 'app_back_strategies_status', methods: ['POST'])]
    public function updateStrategyStatus(Request $request, Strategie $strategy, EntityManagerInterface $em): Response
    {
        $status = $request->request->get('status');
        
        if ($this->isCsrfTokenValid('status' . $strategy->getIdStrategie(), $request->request->get('_token'))) {
            // Check if status is valid (pending, approved, rejected)
            if (in_array($status, ['pending', 'approved', 'rejected'])) {
                $strategy->setStatusStrategie($status);
                $em->flush();
                $this->addFlash('success', 'Statut de la stratégie mis à jour !');
            } else {
                $this->addFlash('error', 'Statut invalide.');
            }
        }

        return $this->redirectToRoute('app_back_strategies');
    }

    /**
     * Lock a strategy
     */
    #[Route('/back/strategies/{id}/lock', name: 'app_back_strategies_lock', methods: ['POST'])]
    public function lockStrategy(Request $request, Strategie $strategy, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('lock' . $strategy->getIdStrategie(), $request->request->get('_token'))) {
            $strategy->setLockedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Stratégie verrouillée avec succès !');
        }

        return $this->redirectToRoute('app_back_strategies');
    }
}