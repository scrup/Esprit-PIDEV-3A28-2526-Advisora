<?php

namespace App\Controller;

use App\Entity\Decision;
use App\Entity\Project;
use App\Entity\User;
use App\Form\DecisionType;
use App\Repository\DecisionRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DecisionController extends AbstractController
{
    #[Route('/back/projects/{projectId}/decisions/new', name: 'decision_new', methods: ['GET', 'POST'], requirements: ['projectId' => '\d+'])]
    public function new(int $projectId, Request $request, EntityManagerInterface $entityManager, ProjectRepository $projectRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageDecisions($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas creer de decision.');
        }

        $project = $projectRepository->findOneWithDecisions($projectId);
        if (!$project instanceof Project) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $decision = new Decision();
        $decision->setProject($project);
        $decision->setUser($user ?? $project->getUser());

        $form = $this->createForm(DecisionType::class, $decision, [
            'submit_label' => 'Ajouter la decision',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->syncProjectStatusFromDecision($project, $decision);
            $entityManager->persist($decision);
            $entityManager->flush();

            $this->addFlash('success', 'La decision a ete ajoutee avec succes.');

            return $this->redirectToRoute('project_back_manage', ['id' => $project->getId()]);
        }

        return $this->render('back/decision/form.html.twig', [
            'decision' => $decision,
            'project' => $project,
            'form' => $form->createView(),
            'page_title' => 'Ajouter une decision',
            'form_badge' => 'Nouvelle decision',
            'form_message' => 'Cette decision sera ajoutee a l historique du projet et mettra a jour son statut courant.',
        ]);
    }

    #[Route('/back/decisions/{id}/edit', name: 'decision_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager, DecisionRepository $decisionRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageDecisions($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette decision.');
        }

        $sourceDecision = $decisionRepository->find($id);
        if (!$sourceDecision instanceof Decision) {
            throw $this->createNotFoundException('Decision introuvable.');
        }

        $decision = (new Decision())
            ->setProject($sourceDecision->getProject())
            ->setUser($user ?? $sourceDecision->getUser())
            ->setDecisionTitle((string) $sourceDecision->getDecisionTitle())
            ->setDescription($sourceDecision->getDescription())
            ->setDecisionDate(new \DateTime('today'));

        $form = $this->createForm(DecisionType::class, $decision, [
            'submit_label' => 'Ajouter cette nouvelle version',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->syncProjectStatusFromDecision($decision->getProject(), $decision);
            $entityManager->persist($decision);
            $entityManager->flush();

            $this->addFlash('success', 'Une nouvelle version de la decision a ete ajoutee avec succes.');

            return $this->redirectToRoute('project_back_manage', ['id' => $sourceDecision->getProject()?->getId()]);
        }

        return $this->render('back/decision/form.html.twig', [
            'decision' => $decision,
            'project' => $sourceDecision->getProject(),
            'form' => $form->createView(),
            'page_title' => 'Nouvelle version de decision',
            'form_badge' => 'Historique conserve',
            'form_message' => 'La decision existante reste visible dans l historique. Cette action ajoute une nouvelle version qui devient la decision courante du projet.',
            'source_decision' => $sourceDecision,
        ]);
    }

    #[Route('/back/decisions/{id}/delete', name: 'decision_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager, DecisionRepository $decisionRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageDecisions($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette decision.');
        }

        $decision = $decisionRepository->find($id);
        if (!$decision instanceof Decision) {
            throw $this->createNotFoundException('Decision introuvable.');
        }

        $projectId = $decision->getProject()?->getId();

        if ($this->isCsrfTokenValid('delete_decision_'.$decision->getId(), (string) $request->request->get('_token'))) {
            $project = $decision->getProject();
            $entityManager->remove($decision);
            $entityManager->flush();
            $this->recalculateProjectStatus($project, $entityManager);
            $this->addFlash('success', 'La decision a ete supprimee avec succes.');
        }

        return $this->redirectToRoute('project_back_manage', ['id' => $projectId]);
    }

    private function syncProjectStatusFromDecision(?Project $project, Decision $decision): void
    {
        if (!$project instanceof Project) {
            return;
        }

        $project->setStatus(match ($decision->getDecisionTitle()) {
            'active' => Project::STATUS_ACCEPTED,
            'refused' => Project::STATUS_REFUSED,
            default => Project::STATUS_PENDING,
        });
    }

    private function recalculateProjectStatus(?Project $project, EntityManagerInterface $entityManager): void
    {
        if (!$project instanceof Project) {
            return;
        }

        $latestDecision = $entityManager->getRepository(Decision::class)
            ->createQueryBuilder('d')
            ->andWhere('d.project = :project')
            ->setParameter('project', $project)
            ->orderBy('d.decisionDate', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($latestDecision instanceof Decision) {
            $this->syncProjectStatusFromDecision($project, $latestDecision);
        } else {
            $project->setStatus(Project::STATUS_PENDING);
        }

        $entityManager->flush();
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function canManageDecisions(?User $user): bool
    {
        return $user instanceof User && in_array($user->getRoleUser(), ['admin', 'gerant'], true);
    }
}
