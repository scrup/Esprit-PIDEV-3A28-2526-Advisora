<?php

namespace App\Controller;

use App\Entity\Objective;
use App\Entity\Project;
use App\Entity\Strategie;
use App\Entity\SwotItem;
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
use App\Service\StrategyPlaybookLocalizationService;
use App\Service\GeminiStrategyGeneratorService;
use App\Service\PythonRecommendationService;
use App\Service\NotificationService;
use Knp\Component\Pager\PaginatorInterface;
use App\Service\AutoTranslator;
use App\Service\FrenchSpellCorrector;
use App\Service\GeminiTowsObjectiveGeneratorService;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Gedmo\Translatable\Entity\Translation;


final class StrategyController extends AbstractController
{
    private const RECOMMENDATION_SESSION_KEY = 'strategy_pending_recommendations';

    private const OBJECTIVE_PRIORITY_MAP = [
        'low' => Objective::PRIORITY_LOW,
        'medium' => Objective::PRIORITY_MEDIUM,
        'high' => Objective::PRIORITY_HIGH,
        'urgent' => Objective::PRIORITY_URGENT,
    ];

    private const SWOT_TYPE_OPTIONS = [
        'strength' => 'Forces',
        'weakness' => 'Faiblesses',
        'opportunity' => 'Opportunites',
        'threat' => 'Menaces',
    ];

    private const SWOT_TYPE_ALIASES = [
        'strength' => 'strength',
        'strengths' => 'strength',
        'force' => 'strength',
        'forces' => 'strength',
        'weakness' => 'weakness',
        'weaknesses' => 'weakness',
        'faiblesse' => 'weakness',
        'faiblesses' => 'weakness',
        'opportunity' => 'opportunity',
        'opportunities' => 'opportunity',
        'opportunite' => 'opportunity',
        'opportunites' => 'opportunity',
        'threat' => 'threat',
        'threats' => 'threat',
        'menace' => 'threat',
        'menaces' => 'threat',
    ];

    private const SWOT_WEIGHT_MIN = 1;
    private const SWOT_WEIGHT_MAX = 10;

    private const TOWS_TYPE_OPTIONS = [
        Objective::TOWS_TYPE_SO => 'SO',
        Objective::TOWS_TYPE_WO => 'WO',
        Objective::TOWS_TYPE_ST => 'ST',
        Objective::TOWS_TYPE_WT => 'WT',
    ];

    private const TOWS_TYPE_SOURCE_TARGET = [
        Objective::TOWS_TYPE_SO => ['source' => 'strength', 'target' => 'opportunity'],
        Objective::TOWS_TYPE_WO => ['source' => 'weakness', 'target' => 'opportunity'],
        Objective::TOWS_TYPE_ST => ['source' => 'strength', 'target' => 'threat'],
        Objective::TOWS_TYPE_WT => ['source' => 'weakness', 'target' => 'threat'],
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
            'label' => 'Acceptees',
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
    public function index(
        Request $request,
        StrategieRepository $strategieRepository,
        PaginatorInterface $paginator
    ): Response
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
        $pagination = $paginator->paginate(
            $strategies,
            $request->query->getInt('page', 1),
            5
        );

        $activeCriteria = $this->buildActiveStrategyCriteria(
            $searchQuery,
            $statusKey,
            $selectedType,
            $statusOptions,
            $typeOptions
        );

        return $this->render('back/strategie/strategie.html.twig', [
            'strategies' => $strategies,
            'pagination' => $pagination,
            'typeDistribution' => $this->buildStrategyTypeDistribution($strategies),
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

    #[Route('/back/strategies/spellcheck', name: 'app_back_strategies_spellcheck', methods: ['POST'])]
    public function spellcheck(Request $request, FrenchSpellCorrector $frenchSpellCorrector): JsonResponse
    {
        $field = trim((string) $request->request->get('field', ''));
        $text = (string) $request->request->get('text', '');
        $language = trim((string) $request->request->get('language', 'fr'));

        if (!in_array($field, ['nomStrategie', 'justification'], true)) {
            return $this->json([
                'status' => 'error',
                'message' => 'Champ non supporte pour la correction.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($text === '') {
            return $this->json([
                'status' => 'ok',
                'field' => $field,
                'original' => $text,
                'corrected' => $text,
                'changed' => false,
            ]);
        }

        try {
            $result = $frenchSpellCorrector->correctWithStatus($text, $language !== '' ? $language : 'fr');
            $status = $result['status'];
            $corrected = $result['corrected'];
            $changed = $result['changed'];
            $errorMessage = (string) ($result['error'] ?? '');

            if ($status !== 'ok') {
                return $this->json([
                    'status' => 'error',
                    'field' => $field,
                    'original' => $text,
                    'corrected' => $text,
                    'changed' => false,
                    'message' => $errorMessage !== '' ? $errorMessage : 'LanguageTool indisponible.',
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            return $this->json([
                'status' => 'ok',
                'field' => $field,
                'original' => $text,
                'corrected' => $corrected,
                'changed' => $changed,
            ]);
        } catch (\Throwable $exception) {
            return $this->json([
                'status' => 'error',
                'field' => $field,
                'original' => $text,
                'corrected' => $text,
                'changed' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

   #[Route('/back/strategies/nouvelle', name: 'app_back_strategies_new', methods: ['GET', 'POST'])]
public function new(
    Request $request,
    EntityManagerInterface $entityManager,
    AutoTranslator $autoTranslator,
    FrenchSpellCorrector $frenchSpellCorrector
): Response {
    $strategy = new Strategie();
    $currentUser = $this->getCurrentUser();

    if ($currentUser instanceof User) {
        $strategy->setUser($currentUser);
    }

    $strategy->setStatusStrategie(Strategie::STATUS_UNASSIGNED);
    $form = $this->createForm(StrategyType::class, $strategy);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $this->applyAutomaticStatusRules($strategy);
        $this->syncLockedAtWithStatus($strategy);
        $correctedFields = $this->applyFrenchSpellCorrections($strategy, $frenchSpellCorrector);
        if ($correctedFields !== []) {
            $this->addFlash('info', sprintf(
                'Correction orthographique automatique appliquee (FR) sur: %s.',
                implode(', ', $correctedFields)
            ));
        }

        /** @var StrategieRepository $strategieRepository */
        $strategieRepository = $entityManager->getRepository(Strategie::class);
        $duplicate = $strategieRepository->findDuplicateByNameForProject(
            (string) $strategy->getNomStrategie(),
            $strategy->getProject()
        );

        if ($duplicate instanceof Strategie) {
            $this->addFlash(
                'info',
                sprintf(
                    'Une strategie avec le meme nom existe deja pour ce projet (ID #%d).',
                    (int) $duplicate->getIdStrategie()
                )
            );

            return $this->redirectToRoute('app_back_strategies_edit', ['id' => $duplicate->getIdStrategie()]);
        }

        $entityManager->persist($strategy);
        $entityManager->flush();

        try {
            $this->autoTranslateStrategyFields($strategy, $entityManager, $autoTranslator, 'fr');
            $entityManager->flush();
        } catch (\Throwable $e) {
            $this->addFlash(
                'info',
                'Strategie creee, mais la traduction automatique a echoue : ' . $e->getMessage()
            );
        }

        $this->addFlash('success', 'Strategie creee avec succes.');

        return $this->redirectToRoute('app_back_strategies');
    }

    return $this->render('back/strategie/strategy-form.html.twig', [
        'form' => $form->createView(),
        'strategy' => $strategy,
    ]);
}

#[Route('/back/strategies/{id}/edit', name: 'app_back_strategies_edit', methods: ['GET', 'POST'])]
public function edit(
    Request $request,
    Strategie $strategy,
    EntityManagerInterface $entityManager,
    AutoTranslator $autoTranslator,
    FrenchSpellCorrector $frenchSpellCorrector
): Response {
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
        $correctedFields = $this->applyFrenchSpellCorrections($strategy, $frenchSpellCorrector);
        if ($correctedFields !== []) {
            $this->addFlash('info', sprintf(
                'Correction orthographique automatique appliquee (FR) sur: %s.',
                implode(', ', $correctedFields)
            ));
        }

        $duplicate = $entityManager
            ->getRepository(Strategie::class)
            ->findDuplicateByNameForProject(
                (string) $strategy->getNomStrategie(),
                $strategy->getProject(),
                $strategy->getIdStrategie()
            );

        if ($duplicate instanceof Strategie) {
            $this->addFlash(
                'error',
                sprintf(
                    'Nom deja utilise pour ce projet par la strategie #%d. Choisissez un autre nom.',
                    (int) $duplicate->getIdStrategie()
                )
            );

            return $this->redirectToRoute('app_back_strategies_edit', ['id' => $strategy->getIdStrategie()]);
        }

        $entityManager->flush();

        try {
            $this->autoTranslateStrategyFields($strategy, $entityManager, $autoTranslator, 'fr');
            $entityManager->flush();
        } catch (\Throwable $e) {
            $this->addFlash(
                'info',
                'Strategie modifiee, mais la traduction automatique a echoue : ' . $e->getMessage()
            );
        }

        $this->addFlash('success', 'Strategie modifiee avec succes.');

        return $this->redirectToRoute('app_back_strategies');
    }

    return $this->render('back/strategie/strategy-form.html.twig', [
        'form' => $form->createView(),
        'strategy' => $strategy,
    ]);
}

#[Route('/back/strategies/objectives/new', name: 'app_back_strategies_objective_new', methods: ['POST'])]
public function createObjective(
    Request $request,
    EntityManagerInterface $entityManager,
    ValidatorInterface $validator,
    AutoTranslator $autoTranslator
): Response {
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

    try {
        $this->autoTranslateObjectiveFields($objective, $entityManager, $autoTranslator, 'fr');
        $entityManager->flush();
    } catch (\Throwable $e) {
        $this->addFlash(
            'info',
            'Objectif ajoute, mais la traduction automatique a echoue : ' . $e->getMessage()
        );
    }

    $this->addFlash('success', sprintf(
        'Objectif "%s" ajoute a la strategie "%s".',
        $objective->getNomObj(),
        $strategy->getNomStrategie()
    ));

    return $this->redirectToStrategyReferer($request);
}

#[Route('/back/strategies/objectives/{id}/edit', name: 'app_back_strategies_objective_edit', methods: ['POST'])]
public function updateObjective(
    Request $request,
    Objective $objective,
    EntityManagerInterface $entityManager,
    ValidatorInterface $validator,
    AutoTranslator $autoTranslator
): Response {
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

    try {
        $this->autoTranslateObjectiveFields($objective, $entityManager, $autoTranslator, 'fr');
        $entityManager->flush();
    } catch (\Throwable $e) {
        $this->addFlash(
            'info',
            'Objectif mis a jour, mais la traduction automatique a echoue : ' . $e->getMessage()
        );
    }

    $this->addFlash('success', sprintf(
        'Objectif "%s" mis a jour avec succes.',
        $objective->getNomObj()
    ));

    return $this->redirectToStrategyReferer($request);
}

    #[Route('/back/strategies/{id}/delete', name: 'app_back_strategies_delete', methods: ['POST'])]
    public function delete(Request $request, Strategie $strategy, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $strategy->getIdStrategie(), (string) $request->request->get('_token'))) {
            $entityManager->remove($strategy);
            $entityManager->flush();
            $this->addFlash('success', 'Strategie supprimee avec succes.');
        } else {
            $this->addFlash('error', 'Token invalide. Suppression impossible.');
        }

        return $this->redirectToStrategyListReferer($request);
    }

#[Route('/back/strategies/{id}/show', name: 'app_back_strategies_show', methods: ['GET'])]
public function show(Strategie $strategy): Response
{
    return $this->render('back/strategie/show.html.twig', [
        'strategy' => $strategy,
    ]);
}

#[Route('/back/strategies/{id}/swot-matrix', name: 'app_back_strategies_swot_matrix', methods: ['GET'])]
public function swotMatrix(Strategie $strategy): Response
{
    $swotBuckets = $this->buildStrategySwotBuckets($strategy);

    return $this->render('back/strategie/swot-matrix.html.twig', [
        'strategy' => $strategy,
        'swotBuckets' => $swotBuckets,
        'swotTypeOptions' => self::SWOT_TYPE_OPTIONS,
        'swotWeightMin' => self::SWOT_WEIGHT_MIN,
        'swotWeightMax' => self::SWOT_WEIGHT_MAX,
        'towsTypeOptions' => self::TOWS_TYPE_OPTIONS,
        'towsPairingViewModel' => $this->buildTowsPairingViewModel($swotBuckets),
    ]);
}

#[Route('/back/strategies/{id}/swot-items/new', name: 'app_back_strategies_swot_item_new', methods: ['POST'])]
public function createSwotItem(Request $request, Strategie $strategy, EntityManagerInterface $entityManager): Response
{
    $strategyId = $strategy->getIdStrategie();
    if ($strategyId === null) {
        $this->addFlash('error', 'Strategie invalide.');

        return $this->redirectToRoute('app_back_strategies');
    }

    if (!$this->isCsrfTokenValid('create_swot_item_' . $strategyId, (string) $request->request->get('_token'))) {
        $this->addFlash('error', 'Token invalide. Creation SWOT impossible.');

        return $this->redirectToSwotMatrix($strategy);
    }

    $swotData = $this->extractSwotItemData($request);
    $validationError = $this->validateSwotItemData($swotData);
    if ($validationError !== null) {
        $this->addFlash('error', $validationError);

        return $this->redirectToSwotMatrix($strategy);
    }

    $swotItem = new SwotItem();
    $swotItem->setStrategie($strategy);
    $swotItem->setType($swotData['type']);
    $swotItem->setDescription($swotData['description']);
    $swotItem->setWeight($swotData['weight']);

    $entityManager->persist($swotItem);
    $entityManager->flush();

    $typeLabel = self::SWOT_TYPE_OPTIONS[$swotData['type']] ?? 'SWOT';
    $this->addFlash('success', sprintf('%s ajoute avec succes.', $typeLabel));

    return $this->redirectToSwotMatrix($strategy);
}

#[Route('/back/strategies/swot-items/{id}/edit', name: 'app_back_strategies_swot_item_edit', methods: ['POST'])]
public function updateSwotItem(Request $request, SwotItem $swotItem, EntityManagerInterface $entityManager): Response
{
    if (!$this->isCsrfTokenValid('edit_swot_item_' . $swotItem->getId(), (string) $request->request->get('_token'))) {
        $this->addFlash('error', 'Token invalide. Modification SWOT impossible.');

        return $this->redirectToStrategyReferer($request);
    }

    $strategy = $swotItem->getStrategie();
    if (!$strategy instanceof Strategie) {
        $this->addFlash('error', 'Strategie associee introuvable.');

        return $this->redirectToRoute('app_back_strategies');
    }

    $swotData = $this->extractSwotItemData($request);
    $validationError = $this->validateSwotItemData($swotData);
    if ($validationError !== null) {
        $this->addFlash('error', $validationError);

        return $this->redirectToSwotMatrix($strategy);
    }

    $swotItem->setType($swotData['type']);
    $swotItem->setDescription($swotData['description']);
    $swotItem->setWeight($swotData['weight']);
    $entityManager->flush();

    $this->addFlash('success', 'Element SWOT mis a jour avec succes.');

    return $this->redirectToSwotMatrix($strategy);
}

#[Route('/back/strategies/swot-items/{id}/delete', name: 'app_back_strategies_swot_item_delete', methods: ['POST'])]
public function deleteSwotItem(Request $request, SwotItem $swotItem, EntityManagerInterface $entityManager): Response
{
    if (!$this->isCsrfTokenValid('delete_swot_item_' . $swotItem->getId(), (string) $request->request->get('_token'))) {
        $this->addFlash('error', 'Token invalide. Suppression SWOT impossible.');

        return $this->redirectToStrategyReferer($request);
    }

    $strategy = $swotItem->getStrategie();
    $entityManager->remove($swotItem);
    $entityManager->flush();

    $this->addFlash('success', 'Element SWOT supprime avec succes.');

    if (!$strategy instanceof Strategie) {
        return $this->redirectToRoute('app_back_strategies');
    }

    return $this->redirectToSwotMatrix($strategy);
}

#[Route('/back/strategies/{id}/swot-items/bulk', name: 'app_back_strategies_swot_item_bulk', methods: ['POST'])]
public function bulkImportSwotItems(Request $request, Strategie $strategy, EntityManagerInterface $entityManager): Response
{
    $strategyId = $strategy->getIdStrategie();
    if ($strategyId === null) {
        $this->addFlash('error', 'Strategie invalide.');

        return $this->redirectToRoute('app_back_strategies');
    }

    if (!$this->isCsrfTokenValid('bulk_swot_item_' . $strategyId, (string) $request->request->get('_token'))) {
        $this->addFlash('error', 'Token invalide. Import SWOT impossible.');

        return $this->redirectToSwotMatrix($strategy);
    }

    $type = $this->normalizeSwotType((string) $request->request->get('swotType'));
    if (!$this->isSwotTypeValid($type)) {
        $this->addFlash('error', 'Type SWOT invalide pour l import.');

        return $this->redirectToSwotMatrix($strategy);
    }

    $defaultWeightRaw = trim((string) $request->request->get('swotBulkWeight', ''));
    $defaultWeight = $this->parseSwotWeight($defaultWeightRaw);
    if ($defaultWeightRaw !== '' && $defaultWeight === null) {
        $this->addFlash('error', 'Le poids par defaut de l import doit etre un entier valide.');

        return $this->redirectToSwotMatrix($strategy);
    }

    if ($defaultWeight !== null && ($defaultWeight < self::SWOT_WEIGHT_MIN || $defaultWeight > self::SWOT_WEIGHT_MAX)) {
        $this->addFlash('error', sprintf('Le poids par defaut doit etre entre %d et %d.', self::SWOT_WEIGHT_MIN, self::SWOT_WEIGHT_MAX));

        return $this->redirectToSwotMatrix($strategy);
    }

    $bulkText = (string) $request->request->get('swotBulkText', '');
    $descriptions = $this->normalizeBulkSwotDescriptions($bulkText);

    if ($descriptions === []) {
        $this->addFlash('error', 'Aucun element valide detecte dans votre import.');

        return $this->redirectToSwotMatrix($strategy);
    }

    $createdCount = 0;

    foreach ($descriptions as $description) {
        $swotItem = new SwotItem();
        $swotItem->setStrategie($strategy);
        $swotItem->setType($type);
        $swotItem->setDescription($description);
        $swotItem->setWeight($defaultWeight);
        $entityManager->persist($swotItem);
        ++$createdCount;
    }

    $entityManager->flush();

    $typeLabel = self::SWOT_TYPE_OPTIONS[$type] ?? 'SWOT';
    $this->addFlash('success', sprintf('%d element(s) importes dans %s.', $createdCount, $typeLabel));

    return $this->redirectToSwotMatrix($strategy);
}

#[Route('/back/strategies/{id}/tows-objective/new', name: 'app_back_strategies_tows_objective_new', methods: ['POST'])]
public function createTowsObjective(
    Request $request,
    Strategie $strategy,
    EntityManagerInterface $entityManager,
    GeminiTowsObjectiveGeneratorService $objectiveGenerator,
    AutoTranslator $autoTranslator
): Response {
    $strategyId = $strategy->getIdStrategie();
    if ($strategyId === null) {
        $this->addFlash('error', 'Strategie invalide.');

        return $this->redirectToRoute('app_back_strategies');
    }

    if (!$this->isCsrfTokenValid('create_tows_objective_' . $strategyId, (string) $request->request->get('_token'))) {
        $this->addFlash('error', 'Token invalide. Creation de l objectif TOWS impossible.');

        return $this->redirectToSwotMatrix($strategy);
    }

    $towsType = $this->normalizeTowsType((string) $request->request->get('towsType'));
    if (!array_key_exists($towsType, self::TOWS_TYPE_OPTIONS)) {
        $this->addFlash('error', 'Type TOWS invalide.');

        return $this->redirectToSwotMatrix($strategy);
    }

    $sourceItemId = (int) $request->request->get('sourceSwotItemId', 0);
    $targetItemId = (int) $request->request->get('targetSwotItemId', 0);

    if ($sourceItemId <= 0 || $targetItemId <= 0) {
        $this->addFlash('error', 'Selection SWOT incomplete. Choisissez une source et une cible.');

        return $this->redirectToSwotMatrix($strategy);
    }

    /** @var SwotItem|null $sourceItem */
    $sourceItem = $entityManager->getRepository(SwotItem::class)->find($sourceItemId);
    /** @var SwotItem|null $targetItem */
    $targetItem = $entityManager->getRepository(SwotItem::class)->find($targetItemId);

    $pairValidationError = $this->validateTowsPairForStrategy($strategy, $towsType, $sourceItem, $targetItem);
    if ($pairValidationError !== null) {
        $this->addFlash('error', $pairValidationError);

        return $this->redirectToSwotMatrix($strategy);
    }

    $generated = $objectiveGenerator->generate($strategy, $sourceItem, $targetItem, $towsType);
    $priorityValue = self::OBJECTIVE_PRIORITY_MAP[$generated['priority_key']] ?? Objective::PRIORITY_MEDIUM;

    $objective = new Objective();
    $objective->setStrategie($strategy);
    $objective->setNomObj($this->normalizeObjectiveText((string) $generated['name'], 80));
    $objective->setDescriptionOb($this->normalizeObjectiveText((string) $generated['description'], Objective::MAX_TEXT_LENGTH));
    $objective->setPriorityOb($priorityValue);
    $objective->setTowsType($towsType);
    $objective->setTowsSourceSwotItem($sourceItem);
    $objective->setTowsTargetSwotItem($targetItem);

    $entityManager->persist($objective);
    $entityManager->flush();

    try {
        $this->autoTranslateObjectiveFields($objective, $entityManager, $autoTranslator, 'fr');
        $entityManager->flush();
    } catch (\Throwable $exception) {
        $this->addFlash('info', 'Objectif TOWS cree, mais la traduction automatique a echoue : ' . $exception->getMessage());
    }

    $this->addFlash(
        'success',
        sprintf(
            'Objectif TOWS %s cree avec priorite %s.',
            $towsType,
            $objective->getPriorityLabel()
        )
    );

    if (!empty($generated['warning']) && is_string($generated['warning'])) {
        $this->addFlash('info', $generated['warning']);
    }

    return $this->redirectToSwotMatrix($strategy);
}


#[Route('/back/strategies/{id}/decision', name: 'app_back_strategies_decision', methods: ['POST'])]
public function adminDecision(
    Request $request,
    Strategie $strategy,
    EntityManagerInterface $entityManager,
    NotificationService $notificationService
): Response
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

        $this->saveRejectedJustification($strategy, $justification);
    }

    $previousStatus = $strategy->getStatusStrategie();
    $strategy->setStatusStrategie($status);
    $this->syncLockedAtWithStatus($strategy, $previousStatus);
    $notificationService->notifyAdminStrategyDecision($strategy, $status, $user);

    $entityManager->flush();

    $this->addFlash(
        'success',
        $status === Strategie::STATUS_APPROVED
            ? 'Decision administrateur enregistree : strategie acceptee.'
            : 'Decision administrateur enregistree : strategie refusee.'
    );

    return $this->redirectToStrategyReferer($request);
}
    #[Route('/projects/strategies/{id}/decision', name: 'project_strategy_decision', methods: ['POST'])]
    public function updateStatus(
        Request $request,
        Strategie $strategy,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService
    ): Response
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

        if (!$this->isCsrfTokenValid('status' . $strategy->getIdStrategie(), (string) $request->request->get('_token'))) {
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

            $this->saveRejectedJustification($strategy, $justification);
        }

        $previousStatus = $strategy->getStatusStrategie();
        $strategy->setStatusStrategie($status);
        $this->syncLockedAtWithStatus($strategy, $previousStatus);
        $notificationService->notifyClientStrategyDecision($strategy, $status);
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
        if ($this->isCsrfTokenValid('lock' . $strategy->getIdStrategie(), (string) $request->request->get('_token'))) {
            $this->syncLockedAtWithStatus($strategy, $strategy->getStatusStrategie());
            $entityManager->flush();
            $this->addFlash('success', 'Strategie verrouillee avec succes.');
        }

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

            if ($generationMeta['used_ai'] !== true && $generationMeta['warning'] !== null) {
                $warning = trim($generationMeta['warning']);
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

                $payload['message'] = implode(' ', $messages);

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

    /**
     * @return array{name: string, description: string, priority_key: string}
     */
    private function extractObjectiveData(Request $request): array
    {
        return [
            'name' => trim((string) $request->request->get('objectifName')),
            'description' => trim((string) $request->request->get('objectifDescription')),
            'priority_key' => trim((string) $request->request->get('objectifPriority', 'medium')),
        ];
    }

    /**
     * @param array{name: string, description: string, priority_key: string} $objectiveData
     */
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

    /**
     * @return array{type: string, description: string, weight: int|null, weight_raw: string}
     */
    private function extractSwotItemData(Request $request): array
    {
        $weightValue = trim((string) $request->request->get('swotWeight', ''));

        return [
            'type' => $this->normalizeSwotType((string) $request->request->get('swotType')),
            'description' => trim((string) $request->request->get('swotDescription')),
            'weight' => $this->parseSwotWeight($weightValue),
            'weight_raw' => $weightValue,
        ];
    }

    /**
     * @param array{type: string, description: string, weight: int|null, weight_raw: string} $swotData
     */
    private function validateSwotItemData(array $swotData): ?string
    {
        if (!$this->isSwotTypeValid($swotData['type'])) {
            return 'Type SWOT invalide.';
        }

        if ($swotData['description'] === '') {
            return 'La description SWOT est obligatoire.';
        }

        if (mb_strlen($swotData['description']) < 3) {
            return 'La description SWOT doit contenir au moins 3 caracteres.';
        }

        if (mb_strlen($swotData['description']) > 1000) {
            return 'La description SWOT ne doit pas depasser 1000 caracteres.';
        }

        if ($swotData['weight_raw'] !== '' && $swotData['weight'] === null) {
            return 'Le poids SWOT doit etre un nombre entier valide.';
        }

        if ($swotData['weight'] !== null && ($swotData['weight'] < self::SWOT_WEIGHT_MIN || $swotData['weight'] > self::SWOT_WEIGHT_MAX)) {
            return sprintf(
                'Le poids SWOT doit etre compris entre %d et %d.',
                self::SWOT_WEIGHT_MIN,
                self::SWOT_WEIGHT_MAX
            );
        }

        return null;
    }

    private function isSwotTypeValid(string $type): bool
    {
        return array_key_exists($type, self::SWOT_TYPE_OPTIONS);
    }

    private function normalizeSwotType(string $type): string
    {
        $normalized = mb_strtolower(trim($type));

        return self::SWOT_TYPE_ALIASES[$normalized] ?? $normalized;
    }

    private function parseSwotWeight(string $weightValue): ?int
    {
        if ($weightValue === '') {
            return null;
        }

        if (!is_numeric($weightValue)) {
            return null;
        }

        return (int) round((float) $weightValue);
    }

    /**
     * @return array{
     *     strength: array<int, SwotItem>,
     *     weakness: array<int, SwotItem>,
     *     opportunity: array<int, SwotItem>,
     *     threat: array<int, SwotItem>
     * }
     */
    private function buildStrategySwotBuckets(Strategie $strategy): array
    {
        $buckets = [
            'strength' => [],
            'weakness' => [],
            'opportunity' => [],
            'threat' => [],
        ];

        foreach ($strategy->getSwotItems() as $swotItem) {
            $normalizedType = $this->normalizeSwotType($swotItem->getType());

            if (!array_key_exists($normalizedType, $buckets)) {
                continue;
            }

            $buckets[$normalizedType][] = $swotItem;
        }

        foreach ($buckets as $type => $items) {
            usort(
                $items,
                static function (SwotItem $left, SwotItem $right): int {
                    $leftWeight = $left->getWeight() ?? -1;
                    $rightWeight = $right->getWeight() ?? -1;

                    if ($leftWeight !== $rightWeight) {
                        return $rightWeight <=> $leftWeight;
                    }

                    return ($left->getId() ?? 0) <=> ($right->getId() ?? 0);
                }
            );

            $buckets[$type] = $items;
        }

        return $buckets;
    }

    private function normalizeTowsType(string $towsType): string
    {
        return strtoupper(trim($towsType));
    }

    private function normalizeObjectiveText(string $text, int $maxLength): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) <= $maxLength) {
            return $normalized;
        }

        return rtrim(mb_substr($normalized, 0, max(0, $maxLength - 3))) . '...';
    }

    private function validateTowsPairForStrategy(
        Strategie $strategy,
        string $towsType,
        ?SwotItem $sourceItem,
        ?SwotItem $targetItem
    ): ?string {
        if (!$sourceItem instanceof SwotItem || !$targetItem instanceof SwotItem) {
            return 'Items SWOT source/cible introuvables.';
        }

        $strategyId = $strategy->getIdStrategie();
        if ($strategyId === null) {
            return 'Strategie invalide.';
        }

        if ($sourceItem->getStrategie()?->getIdStrategie() !== $strategyId || $targetItem->getStrategie()?->getIdStrategie() !== $strategyId) {
            return 'Les items SWOT selectionnes doivent appartenir a cette strategie.';
        }

        $rule = self::TOWS_TYPE_SOURCE_TARGET[$towsType] ?? null;
        if (!is_array($rule)) {
            return 'Type TOWS non pris en charge.';
        }

        $sourceType = $this->normalizeSwotType($sourceItem->getType());
        $targetType = $this->normalizeSwotType($targetItem->getType());

        if ($sourceType !== $rule['source'] || $targetType !== $rule['target']) {
            return sprintf(
                'Combinaison invalide pour %s. Attendu: %s + %s.',
                $towsType,
                self::SWOT_TYPE_OPTIONS[$rule['source']] ?? strtoupper($rule['source']),
                self::SWOT_TYPE_OPTIONS[$rule['target']] ?? strtoupper($rule['target'])
            );
        }

        return null;
    }

    /**
     * @param array{
     *     strength: array<int, SwotItem>,
     *     weakness: array<int, SwotItem>,
     *     opportunity: array<int, SwotItem>,
     *     threat: array<int, SwotItem>
     * } $swotBuckets
     *
     * @return array<string, array{
     *     type: string,
     *     source_label: string,
     *     target_label: string,
     *     source_items: array<int, array{id: int, description: string, weight: int|null}>,
     *     target_items: array<int, array{id: int, description: string, weight: int|null}>
     * }>
     */
    private function buildTowsPairingViewModel(array $swotBuckets): array
    {
        $viewModel = [];

        foreach (self::TOWS_TYPE_SOURCE_TARGET as $type => $rule) {
            $sourceType = (string) $rule['source'];
            $targetType = (string) $rule['target'];
            $sourceItems = $swotBuckets[$sourceType] ?? [];
            $targetItems = $swotBuckets[$targetType] ?? [];

            $viewModel[$type] = [
                'type' => $type,
                'source_label' => self::SWOT_TYPE_OPTIONS[$sourceType] ?? ucfirst($sourceType),
                'target_label' => self::SWOT_TYPE_OPTIONS[$targetType] ?? ucfirst($targetType),
                'source_items' => array_map(static fn (SwotItem $item): array => [
                    'id' => (int) $item->getId(),
                    'description' => $item->getDescription(),
                    'weight' => $item->getWeight(),
                ], $sourceItems),
                'target_items' => array_map(static fn (SwotItem $item): array => [
                    'id' => (int) $item->getId(),
                    'description' => $item->getDescription(),
                    'weight' => $item->getWeight(),
                ], $targetItems),
            ];
        }

        return $viewModel;
    }

    /**
     * @return string[]
     */
    private function normalizeBulkSwotDescriptions(string $bulkText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $bulkText) ?: [];
        $descriptions = [];

        foreach ($lines as $line) {
            $normalized = trim((string) $line);

            if ($normalized === '' || mb_strlen($normalized) < 3) {
                continue;
            }

            if (mb_strlen($normalized) > 1000) {
                $normalized = mb_substr($normalized, 0, 1000);
            }

            if (in_array($normalized, $descriptions, true)) {
                continue;
            }

            $descriptions[] = $normalized;
        }

        return $descriptions;
    }

    /**
     * @param iterable<\Symfony\Component\Validator\ConstraintViolationInterface> $violations
     */
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
        return $this->isEstimatedMonetaryGainBelowBudget($strategy)
            || $this->doesStrategyBudgetExceedProjectBudget($strategy);
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
        $estimatedGainPercent = $strategy->getGainEstime();

        if ($budget === null || $estimatedGainPercent === null) {
            return null;
        }

        return ($budget * $estimatedGainPercent) / 100;
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

        return $project->getUser()->getIdUser() === $user->getIdUser();
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

    private function redirectToSwotMatrix(Strategie $strategy): Response
    {
        $strategyId = $strategy->getIdStrategie();
        if ($strategyId === null) {
            return $this->redirectToRoute('app_back_strategies');
        }

        return $this->redirectToRoute('app_back_strategies_swot_matrix', ['id' => $strategyId]);
    }

    /**
     * @return array<string, string>
     */
    private function getStrategySortLabels(): array
    {
        $labels = [];

        foreach (self::STRATEGY_SORT_OPTIONS as $key => $config) {
            $labels[$key] = $config['label'];
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    private function getStrategyStatusLabels(): array
    {
        $labels = [];

        foreach (self::STRATEGY_STATUS_OPTIONS as $key => $config) {
            $labels[$key] = $config['label'];
        }

        return $labels;
    }

    /**
     * @param array<int, string> $types
     *
     * @return array<string, string>
     */
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

    /**
     * @param array<int, mixed> $strategies
     *
     * @return array<string, int>
     */
    private function buildStrategyTypeDistribution(array $strategies): array
    {
        $distribution = [];

        foreach ($strategies as $strategy) {
            if (!$strategy instanceof Strategie) {
                continue;
            }

            $type = mb_strtolower(trim((string) $strategy->getType()));
            if ($type === '') {
                $type = 'non-defini';
            }

            $distribution[$type] = ($distribution[$type] ?? 0) + 1;
        }

        arsort($distribution);

        return $distribution;
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

    /**
     * @param array<string, string> $statusOptions
     * @param array<string, string> $typeOptions
     *
     * @return array<int, string>
     */
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
private function saveRejectedJustification(Strategie $strategy, string $justification): void
{
    $strategy->setJustification($justification);
}



 #[Route('/recommandation/project/{id}', name: 'strategie_recommandation')]
    public function recommander(
        int $id,
        Request $request,
        ProjectRepository $projetRepository,
        PythonRecommendationService $pythonService
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canBackOfficeRecommendStrategy($user)) {
            throw $this->createAccessDeniedException('Seul un gerant ou un administrateur peut recommander une strategie.');
        }

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

            $normalizedRecommendation = $this->normalizeRecommendationPayload($recommendation);
            $this->storePendingRecommendation($request, $projet->getIdProj(), $user, $normalizedRecommendation);
            $strategie = $this->createStrategyFromRecommendation($normalizedRecommendation, $projet, $user);

            return $this->render('back/strategie/recommendation.html.twig', [
                'projet' => $projet,
                'strategie' => $strategie,
                'recommendation' => [
                    'top_3' => $normalizedRecommendation['top_3'],
                ],
                'is_preview' => true,
                'can_recommendation_decide' => true,
            ]);

        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            return $this->redirectToRoute('project_show', ['id' => $id]);
        }
    }

    #[Route('/recommandation/project/{id}/decision', name: 'strategie_recommandation_decision', methods: ['POST'])]
    public function recommendationDecision(
        int $id,
        Request $request,
        ProjectRepository $projetRepository,
        StrategieRepository $strategieRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canBackOfficeRecommendStrategy($user)) {
            throw $this->createAccessDeniedException('Seul un gerant ou un administrateur peut valider cette recommandation.');
        }

        $projet = $projetRepository->find($id);
        if (!$projet) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        if (!$this->isCsrfTokenValid('recommendation_decision_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide. Decision impossible.');

            return $this->redirectToRoute('strategie_recommandation', ['id' => $id]);
        }

        $decision = trim((string) $request->request->get('decision'));
        if (!in_array($decision, ['accept', 'reject'], true)) {
            $this->addFlash('error', 'Decision invalide.');

            return $this->redirectToRoute('strategie_recommandation', ['id' => $id]);
        }

        $pendingRecommendation = $this->getPendingRecommendation($request, $id, $user);
        if ($pendingRecommendation === null) {
            $this->addFlash('error', 'Aucune recommandation en attente de confirmation pour ce projet.');

            return $this->redirectToRoute('strategie_recommandation', ['id' => $id]);
        }

        if ($decision === 'reject') {
            $this->clearPendingRecommendation($request, $id, $user);
            $this->addFlash('info', 'Recommandation refusee. Aucune strategie n a ete enregistree.');

            return $this->redirectToRoute('project_back_manage', ['id' => $id]);
        }

        $recommendedStrategyId = $this->extractRecommendationStrategyId($pendingRecommendation);
        if ($recommendedStrategyId !== null) {
            $strategie = $strategieRepository->find($recommendedStrategyId);
            if (!$strategie instanceof Strategie) {
                $this->addFlash('error', 'La strategie recommandee est introuvable en base.');

                return $this->redirectToRoute('strategie_recommandation', ['id' => $id]);
            }

            $assignedProject = $strategie->getProject();
            if ($assignedProject !== null && $assignedProject->getIdProj() !== $projet->getIdProj()) {
                $this->addFlash('error', 'Cette strategie est deja attribuee a un autre projet.');

                return $this->redirectToRoute('strategie_recommandation', ['id' => $id]);
            }

            $nameConflict = $strategieRepository->findAssignedDuplicateByName(
                (string) $strategie->getNomStrategie(),
                $strategie->getIdStrategie()
            );
            if ($nameConflict instanceof Strategie) {
                $conflictProjectId = $nameConflict->getProject()?->getIdProj();
                if ($conflictProjectId !== null && $conflictProjectId !== $projet->getIdProj()) {
                    $this->addFlash('error', 'Cette strategie est deja verrouillee sur un autre projet et ne peut pas etre reutilisee.');

                    return $this->redirectToRoute('strategie_recommandation', ['id' => $id]);
                }
            }

            $this->applyRecommendationToExistingStrategy($strategie, $projet, $user);
            $entityManager->flush();

            $this->clearPendingRecommendation($request, $id, $user);
            $this->addFlash('success', 'Strategie attribuee au projet avec statut automatique.');

            return $this->redirectToRoute('project_back_manage', ['id' => $id]);
        }

        $nameConflict = $strategieRepository->findAssignedDuplicateByName((string) ($pendingRecommendation['nomStrategie'] ?? ''));
        if ($nameConflict instanceof Strategie) {
            $conflictProjectId = $nameConflict->getProject()?->getIdProj();
            if ($conflictProjectId === $projet->getIdProj()) {
                $this->applyRecommendationToExistingStrategy($nameConflict, $projet, $user);
                $entityManager->flush();

                $this->clearPendingRecommendation($request, $id, $user);
                $this->addFlash('success', 'Cette strategie est deja liee a ce projet et son statut a ete recalcule automatiquement.');

                return $this->redirectToRoute('project_back_manage', ['id' => $id]);
            }

            $this->addFlash('error', 'Cette strategie est deja verrouillee sur un autre projet et ne peut pas etre reutilisee.');

            return $this->redirectToRoute('strategie_recommandation', ['id' => $id]);
        }

        $strategie = $this->createStrategyFromRecommendation($pendingRecommendation, $projet, $user);

        $duplicate = $strategieRepository->findDuplicateByNameForProject(
            (string) $strategie->getNomStrategie(),
            $strategie->getProject()
        );
        if ($duplicate instanceof Strategie) {
            $this->applyRecommendationToExistingStrategy($duplicate, $projet, $user);
            $entityManager->flush();

            $this->clearPendingRecommendation($request, $id, $user);
            $this->addFlash('info', 'Une strategie du meme nom existe deja pour ce projet. La strategie existante a ete reutilisee.');

            return $this->redirectToRoute('project_back_manage', ['id' => $id]);
        }

        $this->applyAutomaticStatusRules($strategie, null, true);
        $this->syncLockedAtWithStatus($strategie);
        $entityManager->persist($strategie);
        $entityManager->flush();

        $this->clearPendingRecommendation($request, $id, $user);
        $this->addFlash('success', 'Strategie enregistree avec statut automatique.');

        return $this->redirectToRoute('project_back_manage', ['id' => $id]);
    }

    #[Route('/recommandation/project/{id}/auto-generate', name: 'strategie_recommandation_auto_generate', methods: ['POST'])]
    public function autoGenerateRecommendation(
        int $id,
        Request $request,
        ProjectRepository $projetRepository,
        StrategieRepository $strategieRepository,
        GeminiStrategyGeneratorService $geminiStrategyGenerator
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canBackOfficeRecommendStrategy($user)) {
            throw $this->createAccessDeniedException('Seul un gerant ou un administrateur peut generer une strategie IA.');
        }

        $projet = $projetRepository->find($id);
        if (!$projet) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        if (!$this->isCsrfTokenValid('recommendation_auto_generate_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide. Generation automatique impossible.');

            return $this->redirectToRoute('strategie_recommandation', ['id' => $id]);
        }

        try {
            $generatedPayload = $geminiStrategyGenerator->generate($projet);
            $normalizedRecommendation = $this->normalizeRecommendationPayload($generatedPayload);
            $normalizedRecommendation['idStrategie'] = null;
            $normalizedRecommendation['statusStrategie'] = Strategie::STATUS_PENDING;
            $normalizedRecommendation['nomStrategie'] = $this->buildUniqueGeneratedStrategyName(
                $normalizedRecommendation['nomStrategie'],
                $projet,
                $strategieRepository
            );

            $strategie = $this->createStrategyFromRecommendation($normalizedRecommendation, $projet, $user);
            $strategie->setStatusStrategie(Strategie::STATUS_PENDING);
            $strategie->setLockedAt(null);
            $this->storePendingRecommendation($request, $projet->getIdProj(), $user, $normalizedRecommendation);

            $generationMeta = $geminiStrategyGenerator->getLastGenerationMeta();
            if ($generationMeta['used_ai'] === true) {
                $this->addFlash('success', 'Nouvelle strategie generee par Gemini. Verifiez l apercu puis confirmez pour l enregistrer.');
            } else {
                $this->addFlash('success', 'Nouvelle strategie generee (mode de secours). Verifiez l apercu puis confirmez pour l enregistrer.');
            }

            $warning = trim((string) $generationMeta['warning']);
            if ($warning !== '') {
                $this->addFlash('info', $warning);
            }

            return $this->render('back/strategie/recommendation.html.twig', [
                'projet' => $projet,
                'strategie' => $strategie,
                'recommendation' => [
                    'top_3' => [],
                ],
                'is_preview' => true,
                'can_recommendation_decide' => true,
            ]);
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Generation automatique echouee: ' . $exception->getMessage());

            return $this->redirectToRoute('strategie_recommandation', ['id' => $id]);
        }
    }

    private function canBackOfficeRecommendStrategy(?User $user): bool
    {
        return $user instanceof User && in_array($user->getRoleUser(), ['admin', 'gerant'], true);
    }

    /**
     * @param array<string, mixed> $recommendation
     *
     * @return array{
     *     idStrategie: int|null,
     *     nomStrategie: string,
     *     type: string|null,
     *     budgetTotal: float|null,
     *     gainEstime: float|null,
     *     DureeTerme: int|null,
     *     statusStrategie: string,
     *     justification: string|null,
     *     top_3: array<int, mixed>
     * }
     */
    private function normalizeRecommendationPayload(array $recommendation): array
    {
        $allowedStatuses = [
            Strategie::STATUS_PENDING,
            Strategie::STATUS_IN_PROGRESS,
            Strategie::STATUS_APPROVED,
            Strategie::STATUS_REJECTED,
            Strategie::STATUS_UNASSIGNED,
        ];

        $status = trim((string) ($recommendation['statusStrategie'] ?? Strategie::STATUS_PENDING));
        if (!in_array($status, $allowedStatuses, true)) {
            $status = Strategie::STATUS_PENDING;
        }

        $name = trim((string) ($recommendation['nomStrategie'] ?? ''));
        $name = $name !== '' ? $name : 'Strategie recommandee';

        $type = trim((string) ($recommendation['type'] ?? ''));
        $justification = trim((string) ($recommendation['justification'] ?? ''));
        $topCandidates = $recommendation['top_3'] ?? [];

        return [
            'idStrategie' => $this->extractRecommendationStrategyId($recommendation),
            'nomStrategie' => $name,
            'type' => $type !== '' ? $type : null,
            'budgetTotal' => is_numeric($recommendation['budgetTotal'] ?? null) ? (float) $recommendation['budgetTotal'] : null,
            'gainEstime' => is_numeric($recommendation['gainEstime'] ?? null) ? (float) $recommendation['gainEstime'] : null,
            'DureeTerme' => is_numeric($recommendation['DureeTerme'] ?? null) ? (int) $recommendation['DureeTerme'] : null,
            'statusStrategie' => $status,
            'justification' => $justification !== '' ? $justification : null,
            'top_3' => is_array($topCandidates) ? $topCandidates : [],
        ];
    }

    /**
     * @param array<string, mixed> $recommendation
     */
    private function createStrategyFromRecommendation(array $recommendation, Project $projet, ?User $user): Strategie
    {
        $payload = $this->normalizeRecommendationPayload($recommendation);

        $strategie = new Strategie();
        $strategie->setNomStrategie($payload['nomStrategie']);
        $strategie->setType($payload['type']);
        $strategie->setBudgetTotal($payload['budgetTotal']);
        $strategie->setGainEstime($payload['gainEstime']);
        $strategie->setDureeTerme($payload['DureeTerme']);
        $strategie->setJustification($payload['justification'] ?? null);
        $strategie->setStatusStrategie($payload['statusStrategie']);
        $strategie->setProject($projet);

        if ($user instanceof User) {
            $strategie->setUser($user);
        }

        return $strategie;
    }

    /**
     * @param array<string, mixed> $recommendation
     */
    private function extractRecommendationStrategyId(array $recommendation): ?int
    {
        $strategyId = $recommendation['idStrategie'] ?? null;
        if (!is_numeric($strategyId)) {
            return null;
        }

        $normalizedId = (int) $strategyId;

        return $normalizedId > 0 ? $normalizedId : null;
    }

    private function applyRecommendationToExistingStrategy(Strategie $strategie, Project $projet, ?User $user): void
    {
        $strategie->setProject($projet);
        $this->applyAutomaticStatusRules($strategie, null, true);
        $this->syncLockedAtWithStatus($strategie);

        if ($strategie->getUser() === null && $user instanceof User) {
            $strategie->setUser($user);
        }
    }

    private function buildUniqueGeneratedStrategyName(
        string $proposedName,
        Project $project,
        StrategieRepository $strategieRepository
    ): string {
        $baseName = trim($proposedName);
        if ($baseName !== '') {
            $baseName = preg_replace('/^strategie\s+ia\s*[-:â€“]?\s*/iu', '', $baseName) ?? $baseName;
            $baseName = trim($baseName);
        }
        if ($baseName === '') {
            $projectTitle = trim((string) $project->getTitleProj());
            $baseName = $projectTitle !== '' ? 'Strategie ' . $projectTitle : 'Strategie recommandee';
        }

        $candidate = $baseName;
        $suffix = 1;

        while (true) {
            $conflict = $strategieRepository->findAssignedDuplicateByName($candidate);
            if (!$conflict instanceof Strategie) {
                return $candidate;
            }

            $conflictProjectId = $conflict->getProject()?->getIdProj();
            if ($conflictProjectId === null || $conflictProjectId === $project->getIdProj()) {
                return $candidate;
            }

            ++$suffix;
            $projectId = $project->getIdProj() ?? 0;
            $candidate = sprintf('%s - P%d-%d', $baseName, $projectId, $suffix);
        }
    }

    /**
     * @param array<string, mixed> $recommendation
     */
    private function storePendingRecommendation(Request $request, int $projectId, User $user, array $recommendation): void
    {
        $session = $request->getSession();
        $pendingRecommendations = (array) $session->get(self::RECOMMENDATION_SESSION_KEY, []);
        $pendingRecommendations[$this->getRecommendationSessionKey($projectId, $user->getIdUser())] = $this->normalizeRecommendationPayload($recommendation);

        $session->set(self::RECOMMENDATION_SESSION_KEY, $pendingRecommendations);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPendingRecommendation(Request $request, int $projectId, User $user): ?array
    {
        $session = $request->getSession();
        $pendingRecommendations = (array) $session->get(self::RECOMMENDATION_SESSION_KEY, []);
        $pendingRecommendation = $pendingRecommendations[$this->getRecommendationSessionKey($projectId, $user->getIdUser())] ?? null;

        return is_array($pendingRecommendation) ? $pendingRecommendation : null;
    }

    private function clearPendingRecommendation(Request $request, int $projectId, User $user): void
    {
        $session = $request->getSession();
        $pendingRecommendations = (array) $session->get(self::RECOMMENDATION_SESSION_KEY, []);

        unset($pendingRecommendations[$this->getRecommendationSessionKey($projectId, $user->getIdUser())]);
        $session->set(self::RECOMMENDATION_SESSION_KEY, $pendingRecommendations);
    }

    private function getRecommendationSessionKey(int $projectId, int $userId): string
    {
        return sprintf('%d:%d', $projectId, $userId);
    }

    /**
     * @return string[]
     */
    private function applyFrenchSpellCorrections(Strategie $strategy, FrenchSpellCorrector $frenchSpellCorrector): array
    {
        $correctedFields = [];

        $currentName = (string) $strategy->getNomStrategie();
        $correctedName = $frenchSpellCorrector->correct($currentName, 'fr');
        if ($correctedName !== $currentName) {
            $strategy->setNomStrategie($correctedName);
            $correctedFields[] = 'nom';
        }

        $currentJustification = $strategy->getJustification();
        $correctedJustification = $frenchSpellCorrector->correctNullable($currentJustification, 'fr');
        if ($correctedJustification !== $currentJustification) {
            $strategy->setJustification($correctedJustification);
            $correctedFields[] = 'justification';
        }

        return $correctedFields;
    }



    private function autoTranslateStrategyFields(
    Strategie $strategy,
    EntityManagerInterface $entityManager,
    AutoTranslator $autoTranslator,
    string $source = 'fr'
): void {
    /** @var TranslationRepository $translationRepo */
    $translationRepo = $entityManager->getRepository(Translation::class);

    foreach (['en', 'ar'] as $target) {
        $translatedName = $autoTranslator->translateNullable($strategy->getNomStrategie(), $source, $target);
        $translatedType = $autoTranslator->translateNullable($strategy->getType(), $source, $target);
        $translatedJustification = $autoTranslator->translateNullable($strategy->getJustification(), $source, $target);

        if ($translatedName !== null && trim($translatedName) !== '') {
            $translationRepo->translate($strategy, 'nomStrategie', $target, $translatedName);
        }

        if ($translatedType !== null && trim($translatedType) !== '') {
            $translationRepo->translate($strategy, 'type', $target, $translatedType);
        }

        if ($translatedJustification !== null && trim($translatedJustification) !== '') {
            $translationRepo->translate($strategy, 'justification', $target, $translatedJustification);
        }
    }
}

private function autoTranslateObjectiveFields(
    Objective $objective,
    EntityManagerInterface $entityManager,
    AutoTranslator $autoTranslator,
    string $source = 'fr'
): void {
    /** @var TranslationRepository $translationRepo */
    $translationRepo = $entityManager->getRepository(Translation::class);

    foreach (['en', 'ar'] as $target) {
        $translatedName = $autoTranslator->translateNullable($objective->getNomObj(), $source, $target);
        $translatedDescription = $autoTranslator->translateNullable($objective->getDescriptionOb(), $source, $target);

        if ($translatedName !== null && trim($translatedName) !== '') {
            $translationRepo->translate($objective, 'nomObj', $target, $translatedName);
        }

        if ($translatedDescription !== null && trim($translatedDescription) !== '') {
            $translationRepo->translate($objective, 'descriptionOb', $target, $translatedDescription);
        }
    }
}

}
