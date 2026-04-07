<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Form\ProjectType;
use App\Repository\DecisionRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProjectController extends AbstractController
{
    #[Route('/projects', name: 'project_index', methods: ['GET'])]
    public function index(Request $request, ProjectRepository $projectRepository): Response
    {
        $user = $this->getCurrentUser();
        $canSeeAll = $this->canSeeAllProjects($user);

        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'type' => trim((string) $request->query->get('type', '')),
            'min_price' => $request->query->get('min_price', null),
            'max_price' => $request->query->get('max_price', null),
        ];

        $projects = $projectRepository->findFrontProjects($filters, $user, $canSeeAll);

        return $this->render('front/project/index.html.twig', [
            'projects' => $projects,
            'can_manage_projects' => $this->canManageProjects($user),
            'can_see_all_projects' => $canSeeAll,
            'filters' => $filters,
            'status_choices' => Project::STATUSES,
            'type_choices' => $projectRepository->findDistinctFrontTypes($user, $canSeeAll),
        ]);
    }

    #[Route('/back/projects', name: 'back_project_index', methods: ['GET'])]
    public function backIndex(Request $request, ProjectRepository $projectRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canSeeAllProjects($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter la gestion back des projets.');
        }

        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'owner' => trim((string) $request->query->get('owner', '')),
        ];

        return $this->render('back/project/index.html.twig', [
            'projects' => $projectRepository->findBackOfficeProjects($filters),
            'filters' => $filters,
            'status_choices' => Project::STATUSES,
            'can_edit_any_project' => $user?->getRoleUser() === 'admin',
            'can_delete_any_project' => $user?->getRoleUser() === 'admin',
        ]);
    }

    #[Route('/projects/{id}', name: 'project_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, ProjectRepository $projectRepository): Response
    {
        $user = $this->getCurrentUser();
        $project = $projectRepository->findOneVisibleWithDecisions(
            $id,
            $user,
            $this->canSeeAllProjects($user)
        );

        if (!$project instanceof Project) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter ce projet.');
        }

        return $this->render('front/project/show.html.twig', [
            'project' => $project,
            'can_manage_project' => $this->canManageProject($project, $user),
            'can_manage_decisions' => $this->canManageDecisions($user),
            'use_back_manage' => $this->isBackOfficeProjectUser($user),
        ]);
    }

    #[Route('/projects/new', name: 'project_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Vous devez etre connecte pour creer un projet.');
        }
        if (!$this->canManageProjects($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas creer de projet.');
        }

        $project = new Project();
        $project->setUser($user);
        $project->setStatus(Project::STATUS_PENDING);
        // default creation date to today so the form always displays it
        if ($project->getStartDate() === null) {
            $project->setStartDate(new \DateTime('today'));
        }
        $form = $this->createForm(ProjectType::class, $project, [
            'submit_label' => 'Ajouter le projet',
            'include_status' => $user->getRoleUser() === 'admin',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeProjectForPersistence($project, $user);

            if ($user->getRoleUser() === 'client') {
                $project->setStatus(Project::STATUS_PENDING);
            }

            $entityManager->persist($project);
            $entityManager->flush();

            $this->addFlash('success', 'Le projet a ete cree avec succes.');

            return $this->redirectToRoute($this->isBackOfficeProjectUser($user) ? 'project_back_manage' : 'project_manage', ['id' => $project->getId()]);
        }

        return $this->render($this->resolveProjectFormTemplate($user), [
            'project' => $project,
            'form' => $form->createView(),
            'page_title' => 'Ajouter un projet',
            'page_badge' => $this->isBackOfficeProjectUser($user) ? 'Back office' : 'Nouveau projet',
            'page_message' => $this->isBackOfficeProjectUser($user)
                ? 'Le proprietaire du projet est associe automatiquement a l utilisateur connecte.'
                : 'Renseignez les informations de votre projet. Il sera cree avec le statut En attente.',
            'back_route' => 'project_index',
        ]);
    }

    #[Route('/projects/{id}/manage', name: 'project_manage', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function manage(int $id, ProjectRepository $projectRepository): Response
    {
        $user = $this->getCurrentUser();
        $project = $projectRepository->findOneVisibleWithDecisions(
            $id,
            $user,
            false
        );

        if (!$project instanceof Project) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas acceder a la gestion de ce projet.');
        }

        if (!$this->canManageProject($project, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas acceder a la gestion de ce projet.');
        }

        return $this->render('front/project/manage.html.twig', [
            'project' => $project,
            'can_manage_project' => $this->canManageProject($project, $user),
            'can_edit_project' => $this->canEditProject($project, $user),
            'can_delete_project' => $this->canDeleteProject($project, $user),
            'is_project_accepted' => $project->getStatus() === Project::STATUS_ACCEPTED,
        ]);
    }

    #[Route('/back/projects/{id}/manage', name: 'project_back_manage', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function backManage(int $id, ProjectRepository $projectRepository, DecisionRepository $decisionRepository): Response
    {
        $user = $this->getCurrentUser();
        $project = $projectRepository->findOneVisibleWithDecisions(
            $id,
            $user,
            $this->canSeeAllProjects($user)
        );

        if (!$project instanceof Project) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas acceder a la gestion de ce projet.');
        }

        if (!$this->canAccessProjectManagement($project, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas acceder a la gestion de ce projet.');
        }

        return $this->render('back/project/manage.html.twig', [
            'project' => $project,
            'can_manage_project' => $this->canManageProject($project, $user),
            'can_edit_project' => $this->canEditProject($project, $user),
            'can_delete_project' => $this->canDeleteProject($project, $user),
            'can_manage_decisions' => $this->canManageDecisions($user),
            'is_project_accepted' => $project->getStatus() === Project::STATUS_ACCEPTED,
            'latest_decision' => $decisionRepository->findLatestForProject($project),
        ]);
    }

    #[Route('/projects/{id}/edit', name: 'project_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager, ProjectRepository $projectRepository): Response
    {
        $user = $this->getCurrentUser();
        $project = $projectRepository->findOneVisibleWithDecisions(
            $id,
            $user,
            $this->canSeeAllProjects($user)
        );

        if (!$project instanceof Project) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce projet.');
        }

        if (!$this->canEditProject($project, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce projet.');
        }

        $form = $this->createForm(ProjectType::class, $project, [
            'submit_label' => 'Mettre a jour le projet',
            'include_status' => $user?->getRoleUser() === 'admin',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeProjectForPersistence($project, $user);

            if ($user?->getRoleUser() === 'client') {
                // The client cannot alter the workflow state directly.
                $project->setStatus($project->getStatus());
            }

            $entityManager->flush();

            $this->addFlash('success', 'Le projet a ete modifie avec succes.');

            return $this->redirectToRoute($this->isBackOfficeProjectUser($user) ? 'project_back_manage' : 'project_manage', ['id' => $project->getId()]);
        }

        return $this->render($this->resolveProjectFormTemplate($user), [
            'project' => $project,
            'form' => $form->createView(),
            'page_title' => 'Modifier un projet',
            'page_badge' => $this->isBackOfficeProjectUser($user) ? 'Back office' : 'Mon projet',
            'page_message' => $this->isBackOfficeProjectUser($user)
                ? 'Mettez a jour les informations du projet et son contexte metier.'
                : 'Mettez a jour votre projet. Son statut reste gere par la decision du gerant ou de l admin.',
            'back_route' => $this->isBackOfficeProjectUser($user) ? 'project_back_manage' : 'project_manage',
        ]);
    }

    #[Route('/projects/{id}/delete', name: 'project_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager, ProjectRepository $projectRepository): Response
    {
        $user = $this->getCurrentUser();
        $project = $projectRepository->findOneVisibleWithDecisions(
            $id,
            $user,
            $this->canSeeAllProjects($user)
        );

        if (!$project instanceof Project) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce projet.');
        }

        if (!$this->canDeleteProject($project, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce projet.');
        }

        if ($this->isCsrfTokenValid('delete_project_'.$project->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($project);
            $entityManager->flush();
            $this->addFlash('success', 'Le projet a ete supprime avec succes.');
        }

        return $this->redirectToRoute($this->isBackOfficeProjectUser($user) ? 'back_project_index' : 'project_index');
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function canSeeAllProjects(?User $user): bool
    {
        return $user instanceof User && in_array($user->getRoleUser(), ['admin', 'gerant'], true);
    }

    private function canManageProjects(?User $user): bool
    {
        return $user instanceof User && in_array($user->getRoleUser(), ['admin', 'client'], true);
    }

    private function canManageProject(Project $project, ?User $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        if ($user->getRoleUser() === 'admin') {
            return true;
        }

        return $user->getRoleUser() === 'client' && $project->getUser()?->getIdUser() === $user->getIdUser();
    }

    private function canEditProject(Project $project, ?User $user): bool
    {
        if (!$this->canManageProject($project, $user)) {
            return false;
        }

        if ($user?->getRoleUser() === 'admin') {
            return true;
        }

        return $project->getStatus() !== Project::STATUS_ACCEPTED;
    }

    private function canDeleteProject(Project $project, ?User $user): bool
    {
        if (!$this->canManageProject($project, $user)) {
            return false;
        }

        if ($user?->getRoleUser() === 'admin') {
            return true;
        }

        return in_array($project->getStatus(), [Project::STATUS_PENDING, Project::STATUS_REFUSED], true);
    }

    private function canManageDecisions(?User $user): bool
    {
        return $user instanceof User && in_array($user->getRoleUser(), ['admin', 'gerant'], true);
    }

    private function canAccessProjectManagement(Project $project, ?User $user): bool
    {
        return $this->canManageProject($project, $user) || $this->canManageDecisions($user);
    }

    private function isBackOfficeProjectUser(?User $user): bool
    {
        return $user instanceof User && in_array($user->getRoleUser(), ['admin', 'gerant'], true);
    }

    private function resolveProjectFormTemplate(?User $user): string
    {
        return $this->isBackOfficeProjectUser($user) ? 'back/project/form.html.twig' : 'front/project/form.html.twig';
    }

    private function normalizeProjectForPersistence(Project $project, ?User $user): void
    {
        if ($project->getStartDate() === null) {
            $project->setStartDate(new \DateTime('today'));
        }

        if ($project->getEndDate() === null) {
            $project->setEndDate(clone $project->getStartDate());
        }

        if ($project->getTitle() === null || trim((string) $project->getTitle()) === '') {
            $project->setTitle('Projet sans titre');
        }

        if ($project->getLegacyType() !== null && trim($project->getLegacyType()) === '') {
            $project->setLegacyType(null);
        }

        if ($project->getDescription() !== null && trim($project->getDescription()) === '') {
            $project->setDescription(null);
        }

        if ($project->getLegacyBudget() === null) {
            $project->setLegacyBudget(0.01);
        }

        if ($project->getAvancementProj() === null) {
            $project->setAvancementProj(0.0);
        }

        if ($project->getStatus() === null || $project->getStatus() === '') {
            $project->setStatus(Project::STATUS_PENDING);
        }

        if ($user?->getRoleUser() === 'client') {
            $project->setStatus(Project::STATUS_PENDING);
        }
    }
}
