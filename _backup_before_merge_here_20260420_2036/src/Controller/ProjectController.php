<?php

namespace App\Controller;

use App\Entity\Decision;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Form\ProjectType;
use App\Form\TaskType;
use App\Repository\DecisionRepository;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Service\ProjectAcceptanceService;
use App\Service\TaskProgressService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProjectController extends AbstractController
{
    #[Route('/projects', name: 'project_index', methods: ['GET'])]
    public function index(
        Request $request,
        ProjectRepository $projectRepository,
        ProjectAcceptanceService $projectAcceptanceService
    ): Response
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
        $pendingProjects = array_values(array_filter(
            $projects,
            static fn (Project $project): bool => $project->getStatus() === Project::STATUS_PENDING
        ));
        $projectAcceptanceEstimates = $projectAcceptanceService->estimateForPending($pendingProjects);

        return $this->render('front/project/index.html.twig', [
            'projects' => $projects,
            'project_acceptance_estimates' => $projectAcceptanceEstimates,
            'can_manage_projects' => $this->canManageProjects($user),
            'can_see_all_projects' => $canSeeAll,
            'can_client_decide_strategies' => $user?->getRoleUser() === 'client',
            'filters' => $filters,
            'status_choices' => Project::STATUSES,
            'strategy_statuses' => [
                'approved' => \App\Entity\Strategie::STATUS_APPROVED,
                'rejected' => \App\Entity\Strategie::STATUS_REJECTED,
            ],
            'type_choices' => $projectRepository->findDistinctFrontTypes($user, $canSeeAll),
        ]);
    }

    #[Route('/back/projects/overview', name: 'back_project_overview', methods: ['GET'])]
    public function backOverview(ProjectRepository $projectRepository, DecisionRepository $decisionRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canSeeAllProjects($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter la vue d ensemble back des projets.');
        }

        $statusCounters = $projectRepository->getStatusCounters();

        return $this->render('back/project/index.html.twig', [
            'page_title' => 'Gestion des projets',
            'total_projects' => array_sum($statusCounters),
            'pending_projects' => $statusCounters['PENDING'] ?? 0,
            'accepted_projects' => $statusCounters['ACCEPTED'] ?? 0,
            'refused_projects' => $statusCounters['REFUSED'] ?? 0,
            'total_decisions' => $decisionRepository->count([]),
            'latest_projects' => $projectRepository->findLatestProjects(6),
            'latest_decisions' => $decisionRepository->findLatestGlobal(6),
        ]);
    }

    #[Route('/back/projects', name: 'back_project_index', methods: ['GET'])]
    public function backIndex(
        Request $request,
        ProjectRepository $projectRepository,
        ProjectAcceptanceService $projectAcceptanceService
    ): Response
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

        $projects = $projectRepository->findBackOfficeProjects($filters);
        $pendingProjects = array_values(array_filter(
            $projects,
            static fn (Project $project): bool => $project->getStatus() === Project::STATUS_PENDING
        ));

        return $this->render('back/project/back-projet.html.twig', [
            'projects' => $projects,
            'project_acceptance_estimates' => $projectAcceptanceService->estimateForPending($pendingProjects),
            'filters' => $filters,
            'status_choices' => Project::STATUSES,
            'can_edit_any_project' => $user?->getRoleUser() === 'admin',
            'can_delete_any_project' => $user?->getRoleUser() === 'admin',
        ]);
    }

    #[Route('/projects/{id}', name: 'project_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        int $id,
        ProjectRepository $projectRepository,
        DecisionRepository $decisionRepository,
        TaskRepository $taskRepository,
        TaskProgressService $taskProgressService
    ): Response {
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
            'latest_decision' => $decisionRepository->findLatestForProject($project),
            'can_manage_project' => $this->canManageProject($project, $user),
            'can_manage_decisions' => $this->canManageDecisions($user),
            'can_manage_tasks' => $this->canManageTaskContent($project, $user),
            'can_access_task_board' => $this->canAccessProjectManagement($project, $user),
            'task_board' => $this->buildTaskBoard($taskRepository->findByProject($project), $taskProgressService),
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
                : 'Renseignez les informations de votre projet. Toute modification client remettra le dossier en attente de validation.',
            'back_route' => $this->isBackOfficeProjectUser($user) ? 'back_project_index' : 'project_index',
        ]);
    }

    #[Route('/projects/{id}/manage', name: 'project_manage', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function manage(
        int $id,
        Request $request,
        ProjectRepository $projectRepository,
        DecisionRepository $decisionRepository,
        TaskRepository $taskRepository,
        TaskProgressService $taskProgressService,
        EntityManagerInterface $entityManager
    ): Response {
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

        [$taskFormResult, $editingTask] = $this->handleTaskForm(
            $request,
            $project,
            $user,
            $taskRepository,
            $taskProgressService,
            $entityManager,
            'project_manage'
        );

        if ($taskFormResult instanceof RedirectResponse) {
            return $taskFormResult;
        }

        return $this->render('front/project/manage.html.twig', [
            'project' => $project,
            'latest_decision' => $decisionRepository->findLatestForProject($project),
            'can_manage_project' => $this->canManageProject($project, $user),
            'can_edit_project' => $this->canEditProject($project, $user),
            'can_delete_project' => $this->canDeleteProject($project, $user),
            'can_manage_tasks' => $this->canManageTaskContent($project, $user),
            'can_move_tasks' => $this->canChangeTaskStatus($project, $user),
            'task_readonly_hint' => $this->getTaskReadonlyHint($project, $user),
            'task_form' => $taskFormResult->createView(),
            'editing_task' => $editingTask,
            'task_board' => $this->buildTaskBoard($taskRepository->findByProject($project), $taskProgressService),
            'is_project_accepted' => $project->getStatus() === Project::STATUS_ACCEPTED,
        ]);
    }

    #[Route('/back/projects/{id}/manage', name: 'project_back_manage', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function backManage(
        int $id,
        Request $request,
        ProjectRepository $projectRepository,
        DecisionRepository $decisionRepository,
        TaskRepository $taskRepository,
        TaskProgressService $taskProgressService,
        EntityManagerInterface $entityManager
    ): Response {
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

        [$taskFormResult, $editingTask] = $this->handleTaskForm(
            $request,
            $project,
            $user,
            $taskRepository,
            $taskProgressService,
            $entityManager,
            'project_back_manage'
        );

        if ($taskFormResult instanceof RedirectResponse) {
            return $taskFormResult;
        }

        return $this->render('back/project/manage.html.twig', [
            'project' => $project,
            'can_manage_project' => $this->canManageProject($project, $user),
            'can_edit_project' => $this->canEditProject($project, $user),
            'can_delete_project' => $this->canDeleteProject($project, $user),
            'can_manage_decisions' => $this->canManageDecisions($user),
            'can_manage_tasks' => $this->canManageTaskContent($project, $user),
            'can_move_tasks' => $this->canChangeTaskStatus($project, $user),
            'task_readonly_hint' => $this->getTaskReadonlyHint($project, $user),
            'task_form' => $taskFormResult->createView(),
            'editing_task' => $editingTask,
            'task_board' => $this->buildTaskBoard($taskRepository->findByProject($project), $taskProgressService),
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
            $entityManager->flush();

            $this->addFlash('success', 'Le projet a ete modifie avec succes.');
            if ($user?->getRoleUser() === 'client') {
                $this->addFlash('info', 'Votre modification a remis le projet en attente de validation.');
            }

            return $this->redirectToRoute($this->isBackOfficeProjectUser($user) ? 'project_back_manage' : 'project_manage', ['id' => $project->getId()]);
        }

        return $this->render($this->resolveProjectFormTemplate($user), [
            'project' => $project,
            'form' => $form->createView(),
            'page_title' => 'Modifier un projet',
            'page_badge' => $this->isBackOfficeProjectUser($user) ? 'Back office' : 'Mon projet',
            'page_message' => $this->isBackOfficeProjectUser($user)
                ? 'Mettez a jour les informations du projet et son contexte metier.'
                : 'Mettez a jour votre projet. Toute modification client remet le statut en attente de validation.',
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

        if (!$this->isCsrfTokenValid('delete_project_'.$project->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite de suppression est invalide.');

            return $this->redirectToRoute($this->getProjectManagementRoute($user), ['id' => $project->getId()]);
        }

        if ($this->hasBlockingProjectDependencies($project)) {
            $this->addFlash('error', 'Ce projet ne peut pas etre supprime tant qu il possede des investissements, strategies ou taches associees.');

            return $this->redirectToRoute($this->getProjectManagementRoute($user), ['id' => $project->getId()]);
        }

        $this->removeProjectTechnicalDependencies($project, $entityManager);
        $entityManager->remove($project);
        $entityManager->flush();

        $this->addFlash('success', 'Le projet a ete supprime avec succes.');

        return $this->redirectToRoute($this->isBackOfficeProjectUser($user) ? 'back_project_index' : 'project_index');
    }

    #[Route('/projects/{id}/tasks/{taskId}/delete', name: 'project_task_delete', methods: ['POST'], requirements: ['id' => '\d+', 'taskId' => '\d+'])]
    public function deleteTask(
        int $id,
        int $taskId,
        Request $request,
        ProjectRepository $projectRepository,
        TaskRepository $taskRepository,
        TaskProgressService $taskProgressService,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getCurrentUser();
        $project = $projectRepository->findOneVisibleWithDecisions(
            $id,
            $user,
            $this->canSeeAllProjects($user)
        );

        if (!$project instanceof Project) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas acceder a ce projet.');
        }

        if (!$this->canManageTaskContent($project, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer de taches sur ce projet.');
        }

        $task = $taskRepository->findOneForProject($taskId, $project);
        if (!$task instanceof Task) {
            $this->addFlash('error', 'La tache a supprimer est introuvable.');

            return $this->redirectToRoute($this->getProjectManagementRoute($user), ['id' => $project->getId()]);
        }

        if (!$this->isCsrfTokenValid('delete_task_'.$project->getId().'_'.$task->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite de suppression de la tache est invalide.');

            return $this->redirectToRoute($this->getProjectManagementRoute($user), ['id' => $project->getId()]);
        }

        $project->removeTask($task);
        $entityManager->remove($task);
        $taskProgressService->syncProject($project);
        $entityManager->flush();

        $this->addFlash('success', 'La tache a ete supprimee avec succes.');

        return $this->redirectToRoute($this->getProjectManagementRoute($user), ['id' => $project->getId()]);
    }

    #[Route('/projects/{id}/tasks/{taskId}/move', name: 'project_task_move', methods: ['POST'], requirements: ['id' => '\d+', 'taskId' => '\d+'])]
    public function moveTask(
        int $id,
        int $taskId,
        Request $request,
        ProjectRepository $projectRepository,
        TaskRepository $taskRepository,
        TaskProgressService $taskProgressService,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getCurrentUser();
        $project = $projectRepository->findOneVisibleWithDecisions(
            $id,
            $user,
            $this->canSeeAllProjects($user)
        );

        if (!$project instanceof Project) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas acceder a ce projet.');
        }

        if (!$this->canChangeTaskStatus($project, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas deplacer les taches de ce projet.');
        }

        $task = $taskRepository->findOneForProject($taskId, $project);
        if (!$task instanceof Task) {
            return new JsonResponse(['message' => 'La tache a deplacer est introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('move_task_'.$project->getId().'_'.$task->getId(), $token)) {
            return new JsonResponse(['message' => 'Le jeton de securite de deplacement est invalide.'], Response::HTTP_FORBIDDEN);
        }

        $nextStatus = Task::normalizeStatus((string) $request->request->get('status'));
        $task->setStatus($nextStatus);
        $taskProgressService->syncProject($project);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'status' => $task->getNormalizedStatus(),
            'statusLabel' => $task->getStatusLabel(),
            'statusCssClass' => $task->getStatusCssClass(),
            'progress' => $taskProgressService->calculate($project->getTasks()),
        ]);
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

    private function canManageTaskContent(Project $project, ?User $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        if (in_array($user->getRoleUser(), ['admin', 'gerant'], true)) {
            return true;
        }

        return false;
    }

    private function canChangeTaskStatus(Project $project, ?User $user): bool
    {
        if ($this->canManageTaskContent($project, $user)) {
            return true;
        }

        if (!$user instanceof User) {
            return false;
        }

        return $user->getRoleUser() === 'client' && $project->getUser()?->getIdUser() === $user->getIdUser();
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

    private function hasBlockingProjectDependencies(Project $project): bool
    {
        return !$project->getInvestments()->isEmpty()
            || !$project->getStrategies()->isEmpty()
            || !$project->getTasks()->isEmpty();
    }

    private function removeProjectTechnicalDependencies(Project $project, EntityManagerInterface $entityManager): void
    {
        foreach ($project->getDecisions()->toArray() as $decision) {
            if ($decision instanceof Decision) {
                $entityManager->remove($decision);
            }
        }

        $project->getResources()->clear();
    }

    private function getProjectManagementRoute(?User $user): string
    {
        return $this->isBackOfficeProjectUser($user) ? 'project_back_manage' : 'project_manage';
    }

    private function handleTaskForm(
        Request $request,
        Project $project,
        ?User $user,
        TaskRepository $taskRepository,
        TaskProgressService $taskProgressService,
        EntityManagerInterface $entityManager,
        string $routeName
    ): array {
        $taskId = $request->isMethod('POST')
            ? (int) $request->request->get('task_id', 0)
            : (int) $request->query->get('task', 0);

        $editingTask = $taskId > 0 ? $taskRepository->findOneForProject($taskId, $project) : null;
        if ($taskId > 0 && !$editingTask instanceof Task) {
            $this->addFlash('error', 'La tache selectionnee est introuvable pour ce projet.');

            return [$this->redirectToRoute($routeName, ['id' => $project->getId()]), null];
        }

        $task = $editingTask ?? new Task();
        if (!$editingTask instanceof Task) {
            $task->setStatus(Task::STATUS_TODO);
            $task->setWeight(1);
        }

        $canManageTaskContent = $this->canManageTaskContent($project, $user);

        $form = $this->createForm(TaskType::class, $task, [
            'submit_label' => $editingTask instanceof Task ? 'Mettre a jour la tache' : 'Ajouter la tache',
            'is_readonly' => !$canManageTaskContent,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$canManageTaskContent) {
                throw $this->createAccessDeniedException('Vous ne pouvez pas modifier les taches de ce projet.');
            }

            if ($form->isValid()) {
                $task->setProject($project);

                if ($task->getDuration_days() === null || $task->getDuration_days() < 1) {
                    $task->setDuration_days(1);
                }

                if ($task->getCreated_at() === null) {
                    $task->setCreated_at(new \DateTime());
                }

                if (!$editingTask instanceof Task) {
                    $entityManager->persist($task);
                    $project->addTask($task);
                }

                $taskProgressService->syncProject($project);
                $entityManager->flush();

                $this->addFlash(
                    'success',
                    $editingTask instanceof Task
                        ? 'La tache a ete mise a jour avec succes.'
                        : 'La tache a ete ajoutee avec succes.'
                );

                return [$this->redirectToRoute($routeName, ['id' => $project->getId()]), $editingTask];
            }

            $this->addFlash('error', 'Merci de corriger les erreurs du formulaire de tache avant de continuer.');
        }

        return [$form, $editingTask];
    }

    private function getTaskReadonlyHint(Project $project, ?User $user): ?string
    {
        if ($this->canManageTaskContent($project, $user)) {
            return null;
        }

        if ($this->canChangeTaskStatus($project, $user)) {
            return 'Vous pouvez consulter les taches et changer leur statut, mais seul un administrateur ou un gerant peut ajouter, modifier ou supprimer une tache.';
        }

        return 'Les taches de ce projet sont en lecture seule pour votre profil.';
    }

    /**
     * @param Task[] $tasks
     */
    private function buildTaskBoard(array $tasks, TaskProgressService $taskProgressService): array
    {
        $columns = [
            Task::STATUS_TODO => [],
            Task::STATUS_IN_PROGRESS => [],
            Task::STATUS_DONE => [],
        ];

        foreach ($tasks as $task) {
            $columns[$task->getNormalizedStatus()][] = $task;
        }

        foreach ($columns as &$columnTasks) {
            usort($columnTasks, static function (Task $left, Task $right): int {
                $weightCompare = ($right->getWeight() ?? 0) <=> ($left->getWeight() ?? 0);
                if ($weightCompare !== 0) {
                    return $weightCompare;
                }

                return ($left->getId() ?? 0) <=> ($right->getId() ?? 0);
            });
        }
        unset($columnTasks);

        $counts = [];
        foreach ($columns as $status => $columnTasks) {
            $counts[$status] = count($columnTasks);
        }

        return [
            'columns' => $columns,
            'counts' => $counts,
            'total' => count($tasks),
            'progress' => $taskProgressService->calculate($tasks),
        ];
    }
}
