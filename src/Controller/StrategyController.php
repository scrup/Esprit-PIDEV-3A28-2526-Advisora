<?php

namespace App\Controller;

use App\Entity\Objective;
use App\Entity\Project;
use App\Entity\Strategie;
use App\Entity\User;
use App\Form\StrategyType;
use App\Repository\StrategieRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\GeminiPdfContentGenerator;
use App\Service\PdfGeneratorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\LibreTranslateService;
use App\Service\StrategyPlaybookLocalizationService;
use Gedmo\Translatable\Entity\Translation;
use App\Repository\ProjetRepository;
use App\Service\PythonRecommendationService;


final class StrategyController extends AbstractController
{
    public function __construct(
        private LibreTranslateService $translator // ← Inject the service
    ) {}
    private const OBJECTIVE_PRIORITY_MAP = [
        'low' => Objective::PRIORITY_LOW,
        'medium' => Objective::PRIORITY_MEDIUM,
        'high' => Objective::PRIORITY_HIGH,
        'urgent' => Objective::PRIORITY_URGENT,
    ];

    private const STRATEGY_SORT_OPTIONS = [
        'id' => [
            'label' => 'ID',
        ],
        'created_at' => [
            'label' => 'Date de creation',
        ],
        'name' => [
            'label' => 'Nom',
        ],
        'project' => [
            'label' => 'Projet',
        ],
        'status' => [
            'label' => 'Statut',
        ],
        'type' => [
            'label' => 'Type',
        ],
        'budget' => [
            'label' => 'Budget',
        ],
        'gain' => [
            'label' => 'Gain estime',
        ],
        'objectives' => [
            'label' => 'Nombre d objectifs',
        ],
    ];

    private const STRATEGY_STATUS_OPTIONS = [
        'all' => [
            'value' => null,
            'label' => 'Tous',
        ],
        'pending' => [
            'value' => Strategie::STATUS_PENDING,
            'label' => 'En attente',
        ],
        'in_progress' => [
            'value' => Strategie::STATUS_IN_PROGRESS,
            'label' => 'En cours',
        ],
        'approved' => [
            'value' => Strategie::STATUS_APPROVED,
            'label' => 'Approuvees',
        ],
        'rejected' => [
            'value' => Strategie::STATUS_REJECTED,
            'label' => 'Refusees',
        ],
        'unassigned' => [
            'value' => Strategie::STATUS_UNASSIGNED,
            'label' => 'Non affectees',
        ],
    ];

    #[Route('/back/strategies', name: 'app_back_strategies', methods: ['GET'])]
    public function index(Request $request, StrategieRepository $strategieRepository): Response
    {
        $searchQuery = trim((string) $request->query->get('q', ''));
        $statusKey = (string) $request->query->get('status', 'all');
        $selectedType = mb_strtolower(trim((string) $request->query->get('type', '')));
        $sortBy = (string) $request->query->get('sort', 'created_at');
        $direction = strtoupper((string) $request->query->get('direction', 'DESC'));
        $typeOptions = $this->buildStrategyTypeOptions($strategieRepository->findAvailableTypes());

        if (!array_key_exists($sortBy, self::STRATEGY_SORT_OPTIONS)) {
            $sortBy = 'created_at';
        }

        if (!array_key_exists($statusKey, self::STRATEGY_STATUS_OPTIONS)) {
            $statusKey = 'all';
        }

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'DESC';
        }

        if ($selectedType !== '' && !array_key_exists($selectedType, $typeOptions)) {
            $selectedType = '';
        }

        $filters = [
            'query' => $searchQuery,
            'status' => self::STRATEGY_STATUS_OPTIONS[$statusKey]['value'],
            'type' => $selectedType,
        ];
        $sortOptions = $this->getStrategySortLabels();
        $statusOptions = $this->getStrategyStatusLabels();
        $strategies = $strategieRepository->findBackOfficeStrategies($filters, $sortBy, $direction);
        $activeCriteria = $this->buildActiveStrategyCriteria(
            $searchQuery,
            $statusKey,
            $selectedType,
            $statusOptions,
            $typeOptions
        );

        return $this->render('back/strategie/strategie.html.twig', [
            'strategies' => $strategies,
            'sortOptions' => $sortOptions,
            'statusOptions' => $statusOptions,
            'typeOptions' => $typeOptions,
            'searchQuery' => $searchQuery,
            'currentStatus' => $statusKey,
            'currentType' => $selectedType,
            'currentSort' => $sortBy,
            'currentDirection' => $direction,
            'currentSortLabel' => $sortOptions[$sortBy] ?? $sortOptions['created_at'],
            'filteredStrategiesCount' => count($strategies),
            'totalStrategiesCount' => $strategieRepository->count([]),
            'activeCriteria' => $activeCriteria,
            'hasActiveFilters' => $activeCriteria !== [],
        ]);
    }

     #[Route('/back/strategies/nouvelle', name: 'app_back_strategies_new', methods: ['GET', 'POST'])]
public function new(Request $request, EntityManagerInterface $entityManager): Response
{
    $strategy = new Strategie();
    $currentUser = $this->getCurrentUser();

    if ($currentUser instanceof User) {
        $strategy->setUser($currentUser);
    }

    $strategy->setStatusStrategie(Strategie::STATUS_UNASSIGNED);
    $form = $this->createForm(StrategyType::class, $strategy);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        if (!$strategy->getCreatedAtS()) {
            $strategy->setCreatedAtS(new \DateTime());
        }

        $this->applyAutomaticStatusRules($strategy);
        $this->syncLockedAtWithStatus($strategy);

        // Base locale = français
        $strategy->setTranslatableLocale('fr');

        $entityManager->persist($strategy);
        $entityManager->flush();

        // Traductions anglaises
        $this->syncEnglishStrategyTranslations($entityManager, $strategy);
        $entityManager->flush();

        $this->addFlash('success', 'Strategie creee avec succes.');

        return $this->redirectToRoute('app_back_strategies');
    }

    return $this->render('back/strategie/strategy-form.html.twig', [
        'form' => $form->createView(),
        'strategy' => $strategy,
    ]);
}


   #[Route('/back/strategies/{id}/edit', name: 'app_back_strategies_edit', methods: ['GET', 'POST'])]
public function edit(Request $request, Strategie $strategy, EntityManagerInterface $entityManager): Response
{
    $previousStatus = $strategy->getStatusStrategie();
    $previousProject = $strategy->getProject();
    $currentUser = $this->getCurrentUser();

    if ($strategy->getUser() === null && $currentUser instanceof User) {
        $strategy->setUser($currentUser);
    }

    $form = $this->createForm(StrategyType::class, $strategy);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $projectChanged = $this->hasStrategyProjectChanged($previousProject, $strategy->getProject());
        $this->applyAutomaticStatusRules($strategy, $previousStatus, $projectChanged);
        $this->syncLockedAtWithStatus($strategy, $previousStatus);

        // On sauvegarde d'abord la version FR
        $strategy->setTranslatableLocale('fr');
        $entityManager->flush();

        // Puis on met à jour les traductions EN
        $this->syncEnglishStrategyTranslations($entityManager, $strategy);
        $entityManager->flush();

        $this->addFlash('success', 'Strategie modifiee avec succes.');

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
            $this->addFlash('success', 'Strategie supprimee avec succes.');
        } else {
            $this->addFlash('error', 'Token invalide. Suppression impossible.');
        }

        return $this->redirectToStrategyListReferer($request);
    }

    #[Route('/back/strategies/{id}/show', name: 'app_back_strategies_show', methods: ['GET'])]
public function show(Strategie $strategy, EntityManagerInterface $entityManager, Request $request): Response
{
    // Determine the user's locale (e.g., from session, user preference, or request)
    $locale = $request->getLocale(); // Defaults to 'fr' if not set
    
    // Or, if you store locale in the User entity:
    // $user = $this->getCurrentUser();
    // $locale = $user?->getPreferredLocale() ?? $request->getLocale();

    // Tell Gedmo which language to load
    $strategy->setTranslatableLocale($locale);
    
    // Reload the entity to apply the translation
    $entityManager->refresh($strategy);

    return $this->render('back/strategie/show.html.twig', [
        'strategy' => $strategy,
    ]);
}
    #[Route('/back/strategies/{id}/decision', name: 'app_back_strategies_decision', methods: ['POST'])]
public function adminDecision(Request $request, Strategie $strategy, EntityManagerInterface $entityManager): Response
{
    $user = $this->getCurrentUser();

    if (!$this->isAdminUser($user)) {
        throw $this->createAccessDeniedException('Seul un administrateur peut decider du statut de cette strategie.');
    }

    if (!$this->isCsrfTokenValid(
        'admin_strategy_decision_' . $strategy->getIdStrategie(),
        (string) $request->request->get('_token')
    )) {
        $this->addFlash('error', 'Token invalide. Decision administrateur impossible.');
        return $this->redirectToStrategyReferer($request);
    }

    if (!$this->canAdminDecideStrategy($strategy, $user)) {
        $this->addFlash('error', 'Seules les strategies en attente peuvent etre traitees par l administrateur.');
        return $this->redirectToStrategyReferer($request);
    }

    $status = trim((string) $request->request->get('status'));
    $allowedStatuses = [
        Strategie::STATUS_APPROVED,
        Strategie::STATUS_REJECTED,
    ];
    $justification = $this->normalizeStrategyDecisionJustification($request->request->get('justification'));

    if (!in_array($status, $allowedStatuses, true)) {
        $this->addFlash('error', 'Statut de decision administrateur invalide.');
        return $this->redirectToStrategyReferer($request);
    }

    if ($status === Strategie::STATUS_REJECTED) {
        $justificationError = $this->validateRejectedStrategyJustification($justification);
        if ($justificationError !== null) {
            $this->addFlash('error', $justificationError);
            return $this->redirectToStrategyReferer($request);
        }

        $strategy->setTranslatableLocale('fr');
        $this->saveRejectedJustificationWithTranslation($entityManager, $strategy, $justification);
    }

    $previousStatus = $strategy->getStatusStrategie();
    $strategy->setStatusStrategie($status);
    $this->syncLockedAtWithStatus($strategy, $previousStatus);

    $entityManager->flush();

    $this->addFlash(
        'success',
        $status === Strategie::STATUS_APPROVED
            ? 'Decision administrateur enregistree : strategie approuvee.'
            : 'Decision administrateur enregistree : strategie refusee.'
    );

    return $this->redirectToStrategyReferer($request);
}
    #[Route('/projects/strategies/{id}/decision', name: 'project_strategy_decision', methods: ['POST'])]
    public function updateStatus(Request $request, Strategie $strategy, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canClientDecideStrategy($strategy, $user)) {
            throw $this->createAccessDeniedException('Seul le client proprietaire du projet peut decider du statut de cette strategie.');
        }

        $status = trim((string) $request->request->get('status'));
        $allowedStatuses = [
            Strategie::STATUS_APPROVED,
            Strategie::STATUS_REJECTED,
        ];
        $justification = $this->normalizeStrategyDecisionJustification($request->request->get('justification'));

        if (!$this->isCsrfTokenValid('status' . $strategy->getIdStrategie(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide. Decision impossible.');

            return $this->redirectToStrategyReferer($request);
        }

        if ($this->isStrategyDecisionFinal($strategy)) {
            $this->addFlash('info', 'Votre reponse a deja ete enregistree pour cette strategie.');

            return $this->redirectToStrategyReferer($request);
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $this->addFlash('error', 'Statut invalide.');

            return $this->redirectToStrategyReferer($request);
        }

        if ($status === Strategie::STATUS_REJECTED) {
            $justificationError = $this->validateRejectedStrategyJustification($justification);
            if ($justificationError !== null) {
                $this->addFlash('error', $justificationError);

                return $this->redirectToStrategyReferer($request);
            }

            if ($status === Strategie::STATUS_REJECTED) {
    $justificationError = $this->validateRejectedStrategyJustification($justification);
    if ($justificationError !== null) {
        $this->addFlash('error', $justificationError);

        return $this->redirectToStrategyReferer($request);
    }

    $strategy->setTranslatableLocale('fr');
    $this->saveRejectedJustificationWithTranslation($entityManager, $strategy, $justification);
}
        }

        $previousStatus = $strategy->getStatusStrategie();
        $strategy->setStatusStrategie($status);
        $this->syncLockedAtWithStatus($strategy, $previousStatus);
        $entityManager->flush();

        $this->addFlash(
            'success',
            $status === Strategie::STATUS_APPROVED
                ? 'Strategie acceptee avec succes.'
                : 'Strategie refusee avec succes.'
        );

        return $this->redirectToStrategyReferer($request);
    }

    #[Route('/back/strategies/{id}/lock', name: 'app_back_strategies_lock', methods: ['POST'])]
    public function lock(Request $request, Strategie $strategy, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('lock' . $strategy->getIdStrategie(), $request->request->get('_token'))) {
            $this->syncLockedAtWithStatus($strategy, $strategy->getStatusStrategie());
            $entityManager->flush();
            $this->addFlash('success', 'Strategie verrouillee avec succes.');
        }

        return $this->redirectToStrategyReferer($request);
    }

    #[Route('/back/strategies/objectives/new', name: 'app_back_strategies_objective_new', methods: ['POST'])]
    public function createObjective(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        if (!$this->isCsrfTokenValid('create_objective', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide. Creation de l objectif impossible.');

            return $this->redirectToStrategyReferer($request);
        }

        $strategyId = (int) $request->request->get('strategyId');
        if ($strategyId <= 0) {
            $this->addFlash('error', 'Strategie cible invalide.');

            return $this->redirectToStrategyReferer($request);
        }

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

        if (!$this->isObjectivePriorityKeyValid($objectiveData['priority_key'])) {
            $this->addFlash('error', 'La priorite de l objectif est invalide.');

            return $this->redirectToStrategyReferer($request);
        }

        $objective = new Objective();
        $this->applyObjectiveData($objective, $objectiveData);
        $objective->setStrategie($strategy);

        $violations = $validator->validate($objective);
        if (count($violations) > 0) {
            $this->addObjectiveValidationErrors($violations);

            return $this->redirectToStrategyReferer($request);
        }

        $entityManager->persist($objective);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Objectif "%s" ajoute a la strategie "%s".', $objective->getNomObj(), $strategy->getNomStrategie()));

        return $this->redirectToStrategyReferer($request);
    }

    #[Route('/back/strategies/objectives/{id}/edit', name: 'app_back_strategies_objective_edit', methods: ['POST'])]
    public function updateObjective(Request $request, Objective $objective, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
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

        if (!$this->isObjectivePriorityKeyValid($objectiveData['priority_key'])) {
            $this->addFlash('error', 'La priorite de l objectif est invalide.');

            return $this->redirectToStrategyReferer($request);
        }

        $this->applyObjectiveData($objective, $objectiveData);

        $violations = $validator->validate($objective);
        if (count($violations) > 0) {
            $this->addObjectiveValidationErrors($violations);

            return $this->redirectToStrategyReferer($request);
        }

        $entityManager->flush();

        $this->addFlash('success', sprintf('Objectif "%s" mis a jour avec succes.', $objective->getNomObj()));

        return $this->redirectToStrategyReferer($request);
    }
    #[Route('/strategies/{id}/generate-pdf', name: 'strategy_generate_pdf', methods: ['POST'])]
    public function generatePdf(
        Request $request,
        Strategie $strategy,
        GeminiPdfContentGenerator $contentGenerator,
        PdfGeneratorService $pdfGenerator,
        StrategyPlaybookLocalizationService $playbookLocalizer
    ): JsonResponse {
        $language = $playbookLocalizer->normalizeLanguage((string) $request->request->get('lang', 'fr'));
        $labels = $playbookLocalizer->getLabels($language);
        $user = $this->getCurrentUser();

        if (!$this->canGenerateStrategyPdf($strategy, $user)) {
            return $this->json([
                'status' => 'failed',
                'error' => $labels['messages']['forbidden'],
            ], Response::HTTP_FORBIDDEN);
        }

        if ($strategy->getStatusStrategie() !== Strategie::STATUS_APPROVED) {
            return $this->json([
                'status' => 'failed',
                'error' => $labels['messages']['only_approved'],
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $project = $strategy->getProject();
            $content = $contentGenerator->generate($strategy, $project);
            $generationMeta = $contentGenerator->getLastGenerationMeta();
            $messages = [];

            if (($generationMeta['used_ai'] ?? false) !== true && is_string($generationMeta['warning'] ?? null)) {
                $warning = trim((string) $generationMeta['warning']);
                if ($warning !== '') {
                    $messages[] = $warning;
                }
            }

            $playbookViewModel = $playbookLocalizer->buildViewModel($language, $strategy, $project, $content, $messages);
            $messages = $playbookViewModel['messages'];
            $labels = $playbookViewModel['labels'];

            $html = $pdfGenerator->renderHtml('back/strategie/strategy_playbook.html.twig', [
                'strategy' => $strategy,
                'project' => $project,
                'content' => $playbookViewModel['content'],
                'document_language' => $playbookViewModel['language'],
                'labels' => $labels,
                'playbook_strategy' => $playbookViewModel['strategy'],
                'playbook_project' => $playbookViewModel['project'],
                'playbook_objectives' => $playbookViewModel['objectives'],
            ]);
            $baseFilename = sprintf('strategy_%d_%s_%s', (int) $strategy->getIdStrategie(), date('YmdHis'), $language);
            $pdfFilename = $baseFilename . '.pdf';

            if (!$pdfGenerator->supportsPdfGeneration()) {
                $htmlFilename = $baseFilename . '.html';
                $pdfGenerator->saveHtml($html, $htmlFilename, 'uploads/strategies');

                $messages[] = $labels['messages']['pdf_disabled'];

                $payload = [
                    'status' => 'completed',
                    'format' => 'html',
                    'url' => '/uploads/strategies/' . $htmlFilename,
                    'lang' => $language,
                ];

                if ($messages !== []) {
                    $payload['message'] = implode(' ', $messages);
                }

                return $this->json($payload);
            }

            try {
                $pdfGenerator->generate($html, $pdfFilename, 'uploads/strategies');

                $payload = [
                    'status' => 'completed',
                    'format' => 'pdf',
                    'url' => '/uploads/strategies/' . $pdfFilename,
                    'lang' => $language,
                ];

                if ($messages !== []) {
                    $payload['message'] = implode(' ', $messages);
                }

                return $this->json($payload);
            } catch (\Throwable $pdfException) {
                $htmlFilename = $baseFilename . '.html';
                $pdfGenerator->saveHtml($html, $htmlFilename, 'uploads/strategies');

                $messages[] = $labels['messages']['pdf_unavailable'];

                return $this->json([
                    'status' => 'completed',
                    'format' => 'html',
                    'url' => '/uploads/strategies/' . $htmlFilename,
                    'lang' => $language,
                    'message' => implode(' ', $messages),
                ]);
            }
        } catch (\Throwable $exception) {
            return $this->json([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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

    private function isObjectivePriorityKeyValid(string $priorityKey): bool
    {
        return array_key_exists($priorityKey, self::OBJECTIVE_PRIORITY_MAP);
    }

    private function addObjectiveValidationErrors(iterable $violations): void
    {
        $messages = [];

        foreach ($violations as $violation) {
            $message = trim((string) $violation->getMessage());
            if ($message === '' || in_array($message, $messages, true)) {
                continue;
            }

            $messages[] = $message;
        }

        foreach (array_slice($messages, 0, 3) as $message) {
            $this->addFlash('error', $message);
        }
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

    private function applyAutomaticStatusRules(Strategie $strategy, ?string $previousStatus = null, bool $forceReset = false): void
    {
        if (!$forceReset && $this->shouldPreserveFinalStrategyStatus($previousStatus ?? $strategy->getStatusStrategie())) {
            return;
        }

        if ($this->isStrategyAtRisk($strategy)) {
            $strategy->setStatusStrategie(Strategie::STATUS_PENDING);

            return;
        }

        if ($strategy->getProject() !== null) {
            $strategy->setStatusStrategie(Strategie::STATUS_IN_PROGRESS);

            return;
        }

        $strategy->setStatusStrategie(Strategie::STATUS_UNASSIGNED);
    }

    private function shouldPreserveFinalStrategyStatus(?string $status): bool
    {
        return in_array($status, [Strategie::STATUS_APPROVED, Strategie::STATUS_REJECTED], true);
    }

    private function hasStrategyProjectChanged(?Project $previousProject, ?Project $currentProject): bool
    {
        $previousProjectId = $previousProject?->getIdProj();
        $currentProjectId = $currentProject?->getIdProj();

        if ($previousProjectId !== null || $currentProjectId !== null) {
            return $previousProjectId !== $currentProjectId;
        }

        return $previousProject !== $currentProject;
    }

    private function isStrategyAtRisk(Strategie $strategy): bool
    {
        return $this->isEstimatedMonetaryGainBelowBudget($strategy) || $this->doesStrategyBudgetExceedProjectBudget($strategy);
    }

    private function isEstimatedMonetaryGainBelowBudget(Strategie $strategy): bool
    {
        $budget = $strategy->getBudgetTotal();
        $estimatedGainAmount = $this->calculateEstimatedGainAmount($strategy);

        if ($estimatedGainAmount === null || $budget === null) {
            return false;
        }

        return $estimatedGainAmount < $budget;
    }

    private function doesStrategyBudgetExceedProjectBudget(Strategie $strategy): bool
    {
        $project = $strategy->getProject();
        $strategyBudget = $strategy->getBudgetTotal();
        $projectBudget = $project?->getBudgetProj();

        if ($project === null || $strategyBudget === null || $projectBudget === null) {
            return false;
        }

        return $strategyBudget > $projectBudget;
    }

    private function calculateEstimatedGainAmount(Strategie $strategy): ?float
    {
        $budget = $strategy->getBudgetTotal();
        $estimatedGainRate = $strategy->getGainEstime();

        if ($budget === null || $estimatedGainRate === null) {
            return null;
        }

        return $budget * ($estimatedGainRate / 100);
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function isAdminUser(?User $user): bool
    {
        return $user instanceof User && $user->getRoleUser() === 'admin';
    }

    private function canAdminDecideStrategy(Strategie $strategy, ?User $user): bool
    {
        return $this->isAdminUser($user) && $strategy->getStatusStrategie() === Strategie::STATUS_PENDING;
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

    private function canGenerateStrategyPdf(Strategie $strategy, ?User $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        $project = $strategy->getProject();
        if ($project === null) {
            return false;
        }

        if (in_array($user->getRoleUser(), ['admin', 'gerant'], true)) {
            return true;
        }

        return $project->getUser()?->getIdUser() === $user->getIdUser();
    }

    private function isStrategyDecisionFinal(Strategie $strategy): bool
    {
        return in_array(
            $strategy->getStatusStrategie(),
            [Strategie::STATUS_APPROVED, Strategie::STATUS_REJECTED],
            true
        );
    }

    private function normalizeStrategyDecisionJustification(mixed $value): string
    {
        return trim((string) $value);
    }

    private function validateRejectedStrategyJustification(string $justification): ?string
    {
        if ($justification === '') {
            return 'Une justification est obligatoire pour refuser une strategie.';
        }

        $length = mb_strlen($justification);

        if ($length < 10) {
            return 'La justification du refus doit contenir au moins 10 caracteres.';
        }

        if ($length > 255) {
            return 'La justification du refus ne doit pas depasser 255 caracteres.';
        }

        return null;
    }


    

    private function redirectToStrategyReferer(Request $request): Response
    {
        $referer = (string) $request->headers->get('referer', '');

        if ($referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_back_strategies');
    }

    private function redirectToStrategyListReferer(Request $request): Response
    {
        $referer = (string) $request->headers->get('referer', '');
        $strategyListPath = $this->generateUrl('app_back_strategies');
        $refererPath = $referer !== '' ? (string) parse_url($referer, PHP_URL_PATH) : '';

        if ($referer !== '' && $refererPath === $strategyListPath) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_back_strategies');
    }

    private function getStrategySortLabels(): array
    {
        $labels = [];

        foreach (self::STRATEGY_SORT_OPTIONS as $key => $config) {
            $labels[$key] = $config['label'];
        }

        return $labels;
    }

    private function getStrategyStatusLabels(): array
    {
        $labels = [];

        foreach (self::STRATEGY_STATUS_OPTIONS as $key => $config) {
            $labels[$key] = $config['label'];
        }

        return $labels;
    }

    private function buildStrategyTypeOptions(array $types): array
    {
        $options = [];

        foreach ($types as $type) {
            $normalizedType = mb_strtolower(trim($type));
            if ($normalizedType === '') {
                continue;
            }

            $options[$normalizedType] = $this->formatStrategyTypeLabel($normalizedType);
        }

        return $options;
    }

    private function formatStrategyTypeLabel(string $type): string
    {
        $words = explode('_', $type);
        $formattedWords = array_map(
            static fn (string $word): string => ucfirst($word),
            array_filter($words, static fn (string $word): bool => $word !== '')
        );

        return $formattedWords !== [] ? implode(' ', $formattedWords) : 'Non defini';
    }

    private function buildActiveStrategyCriteria(
        string $searchQuery,
        string $statusKey,
        string $selectedType,
        array $statusOptions,
        array $typeOptions
    ): array {
        $criteria = [];

        if ($searchQuery !== '') {
            $criteria[] = sprintf('Recherche: "%s"', $searchQuery);
        }

        if ($statusKey !== 'all' && isset($statusOptions[$statusKey])) {
            $criteria[] = sprintf('Statut: %s', $statusOptions[$statusKey]);
        }

        if ($selectedType !== '' && isset($typeOptions[$selectedType])) {
            $criteria[] = sprintf('Type: %s', $typeOptions[$selectedType]);
        }

        return $criteria;
    }



private function syncEnglishStrategyTranslations(EntityManagerInterface $entityManager, Strategie $strategy): void
{
    $this->saveEnglishTranslation($entityManager, $strategy, 'nomStrategie', $strategy->getNomStrategie());
    $this->saveEnglishTranslation($entityManager, $strategy, 'justification', $strategy->getJustification());
    $this->saveEnglishTranslation($entityManager, $strategy, 'type', $strategy->getType());
}

private function saveEnglishTranslation(
    EntityManagerInterface $entityManager,
    Strategie $strategy,
    string $field,
    ?string $frenchValue
): void {
    $frenchValue = trim((string) $frenchValue);

    if ($frenchValue === '') {
        return;
    }

    /** @var \Gedmo\Translatable\Entity\Repository\TranslationRepository $translationRepo */
    $translationRepo = $entityManager->getRepository(Translation::class);

    try {
        $englishValue = $this->translator->translate($frenchValue, 'en', 'fr');
    } catch (\Throwable $e) {
        $englishValue = $frenchValue;
        $this->addFlash('warning', sprintf(
            'La traduction automatique du champ "%s" a échoué. La valeur française a été gardée en secours.',
            $field
        ));
    }

    $translationRepo->translate($strategy, $field, 'en', $englishValue);
}

private function saveRejectedJustificationWithTranslation(
    EntityManagerInterface $entityManager,
    Strategie $strategy,
    string $justification
): void {
    $strategy->setJustification($justification);
    $this->saveEnglishTranslation($entityManager, $strategy, 'justification', $justification);
}



 #[Route('/recommandation/project/{id}', name: 'strategie_recommandation')]
    public function recommander(
        int $id,
        ProjectRepository $projetRepository,
        PythonRecommendationService $pythonService,
        EntityManagerInterface $em
    ): Response {
        $projet = $projetRepository->find($id);

        if (!$projet) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $data = [
            'titleProj' => $projet->getTitleProj(),
            'descriptionProj' => $projet->getDescriptionProj(),
            'budgetProj' => $projet->getBudgetProj(),
            'typeProj' => $projet->getTypeProj(),
            'stateProj' => $projet->getStateProj(),
            'avancementProj' => $projet->getAvancementProj(),
        ];

        try {
            $recommendation = $pythonService->recommend($data);

            if (!$recommendation) {
                throw new \RuntimeException('Recommendation vide ou JSON invalide.');
            }

            if (($recommendation['error'] ?? false) === true) {
                $message = trim((string) ($recommendation['message'] ?? 'Erreur inconnue dans le moteur de recommandation.'));
                throw new \RuntimeException($message !== '' ? $message : 'Erreur inconnue dans le moteur de recommandation.');
            }

            $strategie = new Strategie();
            $strategie->setNomStrategie($recommendation['nomStrategie'] ?? 'Stratégie par défaut');
            $strategie->setType($recommendation['type'] ?? null);
            $strategie->setBudgetTotal($recommendation['budgetTotal'] ?? null);
            $strategie->setGainEstime($recommendation['gainEstime'] ?? null);
            $strategie->setDureeTerme($recommendation['DureeTerme'] ?? null);
            $strategie->setStatusStrategie($recommendation['statusStrategie'] ?? 'En_attente');
            $strategie->setCreatedAtS(new \DateTime());
            $strategie->setProject($projet);

            // si user connecté
            if ($this->getUser()) {
                $strategie->setUser($this->getUser());
            }

            $em->persist($strategie);
            $em->flush();

            return $this->render('back/strategie/recommendation.html.twig', [
                'projet' => $projet,
                'strategie' => $strategie,
                'recommendation' => $recommendation,
            ]);

        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            return $this->redirectToRoute('project_show', ['id' => $id]);
        }
    }


}
