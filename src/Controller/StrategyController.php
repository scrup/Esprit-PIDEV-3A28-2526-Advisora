<?php

namespace App\Controller;

use App\Entity\Objective;
use App\Entity\Strategie;
use App\Entity\User;
use App\Form\StrategyType;
use App\Repository\StrategieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StrategyController extends AbstractController
{
    private const OBJECTIVE_PRIORITY_MAP = [
        'low' => Objective::PRIORITY_LOW,
        'medium' => Objective::PRIORITY_MEDIUM,
        'high' => Objective::PRIORITY_HIGH,
        'urgent' => Objective::PRIORITY_URGENT,
    ];

    #[Route('/back/strategies', name: 'app_back_strategies', methods: ['GET'])]
    public function index(StrategieRepository $strategieRepository): Response
    {
        return $this->render('back/strategie/strategie.html.twig', [
            'strategies' => $strategieRepository->findBy([], ['CreatedAtS' => 'DESC']),
        ]);
    }

    #[Route('/back/strategies/nouvelle', name: 'app_back_strategies_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $strategy = new Strategie();
        $form = $this->createForm(StrategyType::class, $strategy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$strategy->getCreatedAtS()) {
                $strategy->setCreatedAtS(new \DateTime());
            }

            $this->syncLockedAtWithStatus($strategy);
            $entityManager->persist($strategy);
            $entityManager->flush();

            $this->addFlash('success', 'Stratégie créée avec succès !');

            return $this->redirectToRoute('app_back_strategies');
        }

        return $this->render('back/strategie/strategy-form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/back/strategies/{id}/edit', name: 'app_back_strategies_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Strategie $strategy, EntityManagerInterface $entityManager): Response
    {
        $previousStatus = $strategy->getStatusStrategie();
        $form = $this->createForm(StrategyType::class, $strategy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->syncLockedAtWithStatus($strategy, $previousStatus);
            $entityManager->flush();

            $this->addFlash('success', 'Stratégie modifiée avec succès !');

            return $this->redirectToRoute('app_back_strategies');
        }

        return $this->render('back/strategie/strategy-form.html.twig', [
            'form' => $form->createView(),
            'strategy' => $strategy,
        ]);
    }

    #[Route('/back/strategies/{id}/delete', name: 'app_back_strategies_delete', methods: ['POST'])]
    public function delete(Request $request, Strategie $strategy, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $strategy->getIdStrategie(), $request->request->get('_token'))) {
            $entityManager->remove($strategy);
            $entityManager->flush();
            $this->addFlash('success', 'Stratégie supprimée avec succès !');
        } else {
            $this->addFlash('error', 'Token invalide. Suppression impossible.');
        }

        return $this->redirectToRoute('app_back_strategies');
    }

    #[Route('/back/strategies/{id}/show', name: 'app_back_strategies_show', methods: ['GET'])]
    public function show(Strategie $strategy): Response
    {
        return $this->render('back/strategie/show.html.twig', [
            'strategy' => $strategy,
        ]);
    }

    #[Route('/projects/strategies/{id}/decision', name: 'project_strategy_decision', methods: ['POST'])]
    public function updateStatus(Request $request, Strategie $strategy, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canClientDecideStrategy($strategy, $user)) {
            throw $this->createAccessDeniedException('Seul le client proprietaire du projet peut decider du statut de cette strategie.');
        }

        $status = trim((string) $request->request->get('status'));
        $previousStatus = $strategy->getStatusStrategie();
        $allowedStatuses = [
            Strategie::STATUS_APPROVED,
            Strategie::STATUS_REJECTED,
        ];

        if ($this->isCsrfTokenValid('status' . $strategy->getIdStrategie(), $request->request->get('_token'))) {
            if (in_array($status, $allowedStatuses, true)) {
                $strategy->setStatusStrategie($status);
                $this->syncLockedAtWithStatus($strategy, $previousStatus);
                $entityManager->flush();
                $this->addFlash('success', 'Statut de la stratégie mis à jour !');
            } else {
                $this->addFlash('error', 'Statut invalide.');
            }
        }

        $referer = (string) $request->headers->get('referer', '');

        if ($referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_back_strategies');
    }

    #[Route('/back/strategies/{id}/lock', name: 'app_back_strategies_lock', methods: ['POST'])]
    public function lock(Request $request, Strategie $strategy, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('lock' . $strategy->getIdStrategie(), $request->request->get('_token'))) {
            $this->syncLockedAtWithStatus($strategy, $strategy->getStatusStrategie());
            $entityManager->flush();
            $this->addFlash('success', 'StratÃ©gie verrouillÃ©e avec succÃ¨s !');
        }

        return $this->redirectToRoute('app_back_strategies');
    }

    #[Route('/back/strategies/objectives/new', name: 'app_back_strategies_objective_new', methods: ['POST'])]
    public function createObjective(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('create_objective', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide. Creation de l objectif impossible.');

            return $this->redirectToStrategyReferer($request);
        }

        $strategyId = (int) $request->request->get('strategyId');
        $strategy = $entityManager->getRepository(Strategie::class)->find($strategyId);

        if (!$strategy) {
            $this->addFlash('error', 'Strategie introuvable.');

            return $this->redirectToStrategyReferer($request);
        }

        $objectiveData = $this->extractObjectiveData($request);

        if ($objectiveData['name'] === '') {
            $this->addFlash('error', 'Le nom de l objectif est obligatoire.');

            return $this->redirectToStrategyReferer($request);
        }

        $objective = new Objective();
        $this->applyObjectiveData($objective, $objectiveData);
        $objective->setStrategie($strategy);

        $entityManager->persist($objective);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Objectif "%s" ajoute a la strategie "%s".', $objective->getNomObj(), $strategy->getNomStrategie()));

        return $this->redirectToStrategyReferer($request);
    }

    #[Route('/back/strategies/objectives/{id}/edit', name: 'app_back_strategies_objective_edit', methods: ['POST'])]
    public function updateObjective(Request $request, Objective $objective, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('edit_objective_' . $objective->getIdOb(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide. Modification de l objectif impossible.');

            return $this->redirectToStrategyReferer($request);
        }

        $objectiveData = $this->extractObjectiveData($request);

        if ($objectiveData['name'] === '') {
            $this->addFlash('error', 'Le nom de l objectif est obligatoire.');

            return $this->redirectToStrategyReferer($request);
        }

        $this->applyObjectiveData($objective, $objectiveData);

        $entityManager->flush();

        $this->addFlash('success', sprintf('Objectif "%s" mis a jour avec succes.', $objective->getNomObj()));

        return $this->redirectToStrategyReferer($request);
    }

    #[Route('/back/strategies/objectives/{id}/delete', name: 'app_back_strategies_objective_delete', methods: ['POST'])]
    public function deleteObjective(Request $request, Objective $objective, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_objective_' . $objective->getIdOb(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide. Suppression de l objectif impossible.');

            return $this->redirectToStrategyReferer($request);
        }

        $objectiveName = $objective->getNomObj() ?: 'Objectif';
        $strategy = $objective->getStrategie();

        if ($strategy) {
            $strategy->removeObjective($objective);
        }

        $entityManager->remove($objective);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Objectif "%s" supprime avec succes.', $objectiveName));

        return $this->redirectToStrategyReferer($request);
    }

    private function extractObjectiveData(Request $request): array
    {
        return [
            'name' => trim((string) $request->request->get('objectifName')),
            'description' => trim((string) $request->request->get('objectifDescription')),
            'priority_key' => trim((string) $request->request->get('objectifPriority', 'medium')),
        ];
    }

    private function applyObjectiveData(Objective $objective, array $objectiveData): void
    {
        $objective->setNomObj($objectiveData['name']);
        $objective->setDescriptionOb($objectiveData['description'] !== '' ? $objectiveData['description'] : $objectiveData['name']);
        $objective->setPriorityOb(self::OBJECTIVE_PRIORITY_MAP[$objectiveData['priority_key']] ?? Objective::PRIORITY_MEDIUM);
    }

    private function syncLockedAtWithStatus(Strategie $strategy, ?string $previousStatus = null): void
    {
        if ($strategy->getStatusStrategie() === Strategie::STATUS_APPROVED) {
            if ($strategy->getLockedAt() === null || $previousStatus !== Strategie::STATUS_APPROVED) {
                $strategy->setLockedAt(new \DateTime());
            }

            return;
        }

        $strategy->setLockedAt(null);
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function canClientDecideStrategy(Strategie $strategy, ?User $user): bool
    {
        if (!$user instanceof User || $user->getRoleUser() !== 'client') {
            return false;
        }

        $project = $strategy->getProject();
        if ($project === null || $project->getUser() === null) {
            return false;
        }

        return $project->getUser()?->getIdUser() === $user->getIdUser();
    }

    private function redirectToStrategyReferer(Request $request): Response
    {
        $referer = (string) $request->headers->get('referer', '');

        if ($referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_back_strategies');
    }
}
