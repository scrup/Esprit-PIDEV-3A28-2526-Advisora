<?php

namespace App\Controller;

use App\Entity\Cataloguefournisseur;
use App\Entity\Resource;
use App\Entity\ResourceMarketListing;
use App\Entity\ResourceMarketOrder;
use App\Entity\User;
use App\Form\ResourceType;
use App\Repository\CataloguefournisseurRepository;
use App\Repository\ProjectRepository;
use App\Repository\ResourceRepository;
use App\Service\ResourceActionAnalysisService;
use App\Service\ResourceAnalysisChatService;
use App\Service\ResourceAnalysisPdfReportService;
use App\Service\ResourceAnalysisResultStore;
use App\Service\ResourceReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class ResourceController extends AbstractController
{
    #[Route('/back/resources/dashboard', name: 'back_resource_dashboard', methods: ['GET'])]
    public function backDashboard(
        ResourceRepository $resourceRepository,
        CataloguefournisseurRepository $supplierRepository,
        EntityManagerInterface $entityManager,
        ResourceReservationService $reservationService,
        ChartBuilderInterface $chartBuilder
    ): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter le module back des ressources.');
        }

        $resources = $resourceRepository->findBackOfficeResources([]);
        $suppliers = $supplierRepository->findBackOfficeSuppliers([]);
        $metrics = $this->buildResourceModuleMetrics($resources, $suppliers, $entityManager, $reservationService);
        $linkedResources = array_values(array_filter(
            $resources,
            static fn (Resource $resource): bool => $resource->getProjects()->count() > 0
        ));

        usort(
            $linkedResources,
            static fn (Resource $left, Resource $right): int => $right->getProjects()->count() <=> $left->getProjects()->count()
        );

        $dashboardLinkedResources = array_slice($linkedResources, 0, 8);
        $stockSnapshots = $this->buildStockSnapshots($dashboardLinkedResources, $reservationService);

        return $this->render('back/resource/dashboard.html.twig', [
            'metrics' => $metrics,
            'linked_resources' => $dashboardLinkedResources,
            'stock_snapshots' => $stockSnapshots,
            'resource_charts' => [
                'stock_distribution' => $this->createStockDistributionChart($metrics, $chartBuilder)->createView(),
                'status_distribution' => $this->createResourceStatusChart($resources, $chartBuilder)->createView(),
                'project_links' => $this->createTopLinkedResourcesChart($dashboardLinkedResources, $chartBuilder)->createView(),
            ],
        ]);
    }

    #[Route('/resources', name: 'resource_index', methods: ['GET'])]
    public function index(Request $request, ResourceRepository $resourceRepository, CataloguefournisseurRepository $supplierRepository, ResourceReservationService $reservationService): Response
    {
        $user = $this->getCurrentUser();
        $filters = $this->extractFilters($request);
        $resources = $resourceRepository->findFrontResources($filters);

        return $this->render('front/resource/index.html.twig', [
            'resources' => $resources,
            'filters' => $filters,
            'status_choices' => $this->getAvailabilityChoices(),
            'suppliers' => $supplierRepository->findBy([], ['nomFr' => 'ASC']),
            'can_manage_resources' => $this->canManageResources($user),
            'can_reserve_resource' => $this->canReserveResources($user),
            'stock_snapshots' => $this->buildStockSnapshots($resources, $reservationService),
        ]);
    }

    #[Route('/back/resources', name: 'back_resource_index', methods: ['GET'])]
    public function backIndex(
        Request $request,
        ResourceRepository $resourceRepository,
        CataloguefournisseurRepository $supplierRepository,
        EntityManagerInterface $entityManager,
        ResourceReservationService $reservationService
    ): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter la gestion back des ressources.');
        }

        $filters = $this->extractFilters($request, false);
        $resources = $resourceRepository->findBackOfficeResources($filters);
        $metrics = $this->buildResourceModuleMetrics(
            $resourceRepository->findBackOfficeResources([]),
            $supplierRepository->findBackOfficeSuppliers([]),
            $entityManager,
            $reservationService
        );

        return $this->render('back/resource/index.html.twig', [
            'resources' => $resources,
            'filters' => $filters,
            'status_choices' => $this->getAvailabilityChoices(),
            'suppliers' => $supplierRepository->findBy([], ['nomFr' => 'ASC']),
            'can_edit_any_resource' => true,
            'can_delete_any_resource' => true,
            'metrics' => $metrics,
            'stock_snapshots' => $this->buildStockSnapshots($resources, $reservationService),
        ]);
    }

    #[Route('/back/resources/analysis', name: 'back_resource_analysis', methods: ['GET'])]
    public function backAnalysis(Request $request, ResourceAnalysisResultStore $analysisStore): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter l analyse ressources.');
        }

        // Le controller ne recalcule rien ici: il lit le dernier snapshot deja produit.
        $analysis = $analysisStore->loadLatest();
        $priorityFilter = $this->normalizeActionPriority((string) $request->query->get('priority', ''));
        $actionCodeFilter = strtoupper(trim((string) $request->query->get('action_code', '')));
        $actions = is_array($analysis['actions'] ?? null) ? $analysis['actions'] : [];
        $filteredActions = $this->filterActionsForView($actions, $priorityFilter, $actionCodeFilter);
        $availableCodes = $this->collectActionCodes($actions);

        $chatResult = null;
        if ($request->hasSession()) {
            $storedChat = $request->getSession()->get('resource_analysis_chat');
            if (is_array($storedChat)) {
                $chatResult = $storedChat;
            }
        }

        return $this->render('back/resource/analysis.html.twig', [
            'analysis' => $analysis,
            'is_running' => $analysisStore->isRunning(),
            'analysis_error' => $analysisStore->loadError(),
            'filtered_actions' => $filteredActions,
            'priority_filter' => $priorityFilter,
            'action_code_filter' => $actionCodeFilter,
            'available_action_codes' => $availableCodes,
            'chat_result' => $chatResult,
        ]);
    }

    #[Route('/back/resources/analysis/run', name: 'back_resource_analysis_run', methods: ['POST'])]
    public function runBackAnalysis(
        Request $request,
        ResourceAnalysisResultStore $analysisStore,
        ResourceActionAnalysisService $analysisService
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas lancer l analyse ressources.');
        }

        if (!$this->isCsrfTokenValid('run_resource_analysis', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite de lancement est invalide.');

            return $this->redirectToRoute('back_resource_analysis');
        }

        if ($analysisStore->isRunning()) {
            $this->addFlash('success', 'Une analyse est deja en cours. Actualisez la page dans quelques secondes.');

            return $this->redirectToRoute('back_resource_analysis');
        }

        // On efface l etat d erreur precedent avant un nouveau run.
        $analysisStore->clearError();
        $analysisStore->markRunning();

        try {
            // Lancement async via commande console pour ne pas bloquer l utilisateur.
            $projectDir = $this->getParameter('kernel.project_dir');
            if (!is_string($projectDir) || $projectDir === '') {
                throw new \RuntimeException('Le parametre kernel.project_dir est invalide.');
            }

            $process = new Process(
                [PHP_BINARY, 'bin/console', 'app:resource:analysis:run', '--no-interaction'],
                $projectDir
            );
            $process->disableOutput();
            $process->start();
            $this->addFlash('success', 'Analyse lancee en arriere-plan. Le resultat apparaitra automatiquement.');
        } catch (\Throwable) {
            // Fallback: si le process async n est pas possible, on calcule tout de suite.
            $analysisStore->saveResult($analysisService->analyze());
            $this->addFlash('success', 'Analyse calculee immediatement (mode fallback).');
        }

        return $this->redirectToRoute('back_resource_analysis');
    }

    #[Route('/back/resources/analysis/export.csv', name: 'back_resource_analysis_export_csv', methods: ['GET'])]
    public function exportBackAnalysisCsv(Request $request, ResourceAnalysisResultStore $analysisStore): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas exporter l analyse ressources.');
        }

        $analysis = $analysisStore->loadLatest();
        if (!is_array($analysis)) {
            $this->addFlash('error', 'Aucune analyse disponible a exporter.');

            return $this->redirectToRoute('back_resource_analysis');
        }

        $priorityFilter = $this->normalizeActionPriority((string) $request->query->get('priority', ''));
        $actionCodeFilter = strtoupper(trim((string) $request->query->get('action_code', '')));
        $actions = is_array($analysis['actions'] ?? null) ? $analysis['actions'] : [];
        $filteredActions = $this->filterActionsForView($actions, $priorityFilter, $actionCodeFilter);

        // Export uniquement des lignes visibles apres filtres pour coller a l UX.
        $filename = 'resource-analysis-' . (new \DateTimeImmutable())->format('Ymd-His') . '.csv';
        $csv = $this->buildActionsCsv($filteredActions);

        return new Response($csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/back/resources/analysis/export.pdf', name: 'back_resource_analysis_export_pdf', methods: ['GET'])]
    public function exportBackAnalysisPdf(
        Request $request,
        ResourceAnalysisResultStore $analysisStore,
        ResourceAnalysisPdfReportService $pdfReportService
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas exporter le rapport PDF ressources.');
        }

        $analysis = $analysisStore->loadLatest();
        if (!is_array($analysis)) {
            $this->addFlash('error', 'Aucune analyse disponible a exporter en PDF.');

            return $this->redirectToRoute('back_resource_analysis');
        }

        // On reapplique exactement les memes filtres que la vue CSV/UI
        // pour garantir la coherence des exports.
        $priorityFilter = $this->normalizeActionPriority((string) $request->query->get('priority', ''));
        $actionCodeFilter = strtoupper(trim((string) $request->query->get('action_code', '')));
        $actions = is_array($analysis['actions'] ?? null) ? $analysis['actions'] : [];
        $filteredActions = $this->filterActionsForView($actions, $priorityFilter, $actionCodeFilter);

        try {
            $report = $pdfReportService->generateReport($analysis, $filteredActions, $priorityFilter, $actionCodeFilter);
            if (($report['warning'] ?? null) !== null) {
                $this->addFlash('error', (string) $report['warning']);
            }

            return $this->file((string) $report['path'], (string) $report['download_name']);
        } catch (\Throwable $throwable) {
            $this->addFlash('error', 'Echec export PDF: ' . $throwable->getMessage());

            return $this->redirectToRoute('back_resource_analysis', $this->extractAnalysisFilterParams($request));
        }
    }

    #[Route('/back/resources/analysis/chat', name: 'back_resource_analysis_chat', methods: ['POST'])]
    public function backAnalysisChat(
        Request $request,
        ResourceAnalysisResultStore $analysisStore,
        ResourceAnalysisChatService $chatService
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas utiliser le chat d analyse ressources.');
        }

        if (!$this->isCsrfTokenValid('resource_analysis_chat', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite du chat est invalide.');

            return $this->redirectToRoute('back_resource_analysis', $this->extractAnalysisFilterParams($request));
        }

        $question = trim((string) $request->request->get('question', ''));
        if ($question === '') {
            $this->addFlash('error', 'La question du chat est vide.');

            return $this->redirectToRoute('back_resource_analysis', $this->extractAnalysisFilterParams($request));
        }

        $analysis = $analysisStore->loadLatest();
        if (!is_array($analysis)) {
            $this->addFlash('error', 'Aucune analyse disponible. Lancez d abord une analyse.');

            return $this->redirectToRoute('back_resource_analysis', $this->extractAnalysisFilterParams($request));
        }

        // Le chat ne remplace pas le moteur deterministic:
        // il explique les resultats deja calcules.
        $answer = $chatService->answer($analysis, $question);
        if ($request->hasSession()) {
            $request->getSession()->set('resource_analysis_chat', [
                'question' => $question,
                'answer' => $answer,
                'at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }

        return $this->redirectToRoute('back_resource_analysis', $this->extractAnalysisFilterParams($request));
    }

    #[Route('/resources/{id}', name: 'resource_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        int $id,
        ResourceRepository $resourceRepository,
        ProjectRepository $projectRepository,
        ResourceReservationService $reservationService
    ): Response
    {
        $resource = $resourceRepository->findOneWithSupplier($id);
        if (!$resource instanceof Resource) {
            throw $this->createNotFoundException('Ressource introuvable.');
        }

        $user = $this->getCurrentUser();
        $canReserveResource = $this->canReserveResources($user);

        return $this->render('front/resource/show.html.twig', [
            'resource' => $resource,
            'can_manage_resources' => $this->canManageResources($user),
            'can_reserve_resource' => $canReserveResource,
            'use_back_manage' => $this->isBackOfficeResourceUser($user),
            'client_projects' => $canReserveResource ? $projectRepository->findByOwnerOrdered($user) : [],
            'reserved_stock' => $reservationService->getReservedStock((int) $resource->getId()),
            'available_stock' => $reservationService->getAvailableStock($resource),
        ]);
    }

    #[Route('/resources/reservations', name: 'resource_reservations', methods: ['GET'])]
    public function reservations(ResourceReservationService $reservationService): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canReserveResources($user)) {
            throw $this->createAccessDeniedException('Seul un client peut consulter ses reservations.');
        }

        return $this->render('front/resource/reservations.html.twig', [
            'reservations' => $reservationService->getClientReservations($user),
        ]);
    }

    #[Route('/resources/reservations/{projectId}/{resourceId}/edit', name: 'resource_reservation_edit', methods: ['GET', 'POST'], requirements: ['projectId' => '\d+', 'resourceId' => '\d+'])]
    public function editReservation(
        int $projectId,
        int $resourceId,
        Request $request,
        ProjectRepository $projectRepository,
        ResourceReservationService $reservationService,
        ValidatorInterface $validator
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canReserveResources($user)) {
            throw $this->createAccessDeniedException('Seul un client peut modifier une reservation.');
        }

        $reservation = $reservationService->getClientReservation($user, $projectId, $resourceId);
        if ($reservation === null) {
            throw $this->createNotFoundException('Reservation introuvable.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_reservation_' . $projectId . '_' . $resourceId, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Le jeton de securite de modification est invalide.');

                return $this->redirectToRoute('resource_reservation_edit', ['projectId' => $projectId, 'resourceId' => $resourceId]);
            }

            ['quantity' => $quantity, 'projectId' => $targetProjectId, 'errors' => $errors] = $this->parseReservationPayload($request, $validator);

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }

                if ($targetProjectId !== null && $targetProjectId > 0) {
                    $reservation['project_id'] = $targetProjectId;
                }

                if ($quantity !== null) {
                    $reservation['reserved_qty'] = $quantity;
                }

                return $this->render('front/resource/reservation_edit.html.twig', [
                    'reservation' => $reservation,
                    'projects' => $projectRepository->findByOwnerOrdered($user),
                ]);
            }

            try {
                $reservationService->updateClientReservation($user, $projectId, $resourceId, $quantity, $targetProjectId);
                $this->addFlash('success', 'La reservation a ete modifiee avec succes.');

                return $this->redirectToRoute('resource_reservations');
            } catch (\Throwable $throwable) {
                $this->addFlash('error', $throwable->getMessage());
                if ($targetProjectId !== null && $targetProjectId > 0) {
                    $reservation['project_id'] = $targetProjectId;
                }
                $reservation['reserved_qty'] = $quantity;
            }
        }

        return $this->render('front/resource/reservation_edit.html.twig', [
            'reservation' => $reservation,
            'projects' => $projectRepository->findByOwnerOrdered($user),
        ]);
    }

    #[Route('/back/resources/reservations', name: 'back_resource_reservations', methods: ['GET'])]
    public function backReservations(ResourceReservationService $reservationService): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter l historique global des reservations.');
        }

        return $this->render('back/resource/reservations.html.twig', [
            'reservations' => $reservationService->getAllReservations(),
        ]);
    }

    #[Route('/resources/{id}/reserve', name: 'resource_reserve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reserve(
        int $id,
        Request $request,
        ResourceRepository $resourceRepository,
        ResourceReservationService $reservationService,
        ValidatorInterface $validator
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canReserveResources($user)) {
            throw $this->createAccessDeniedException('Seul un client peut reserver une ressource.');
        }

        $resource = $resourceRepository->findOneWithSupplier($id);
        if (!$resource instanceof Resource) {
            throw $this->createNotFoundException('Ressource introuvable.');
        }

        if (!$this->isCsrfTokenValid('reserve_resource_' . $resource->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite de reservation est invalide.');

            return $this->redirectToRoute('resource_show', ['id' => $resource->getId()]);
        }

        ['quantity' => $quantity, 'projectId' => $projectId, 'errors' => $errors] = $this->parseReservationPayload($request, $validator);

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

            return $this->redirectToRoute('resource_show', ['id' => $resource->getId()]);
        }

        try {
            $resolvedProjectId = $reservationService->reserveForClient($user, $resource, $quantity, $projectId);
            $this->addFlash(
                'success',
                sprintf(
                    'Reservation enregistree avec succes. Ressource liee au projet #%d pour une quantite de %d.',
                    $resolvedProjectId,
                    $quantity
                )
            );
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        return $this->redirectToRoute('resource_show', ['id' => $resource->getId()]);
    }

    #[Route('/resources/reservations/{projectId}/{resourceId}/delete', name: 'resource_reservation_delete', methods: ['POST'], requirements: ['projectId' => '\d+', 'resourceId' => '\d+'])]
    public function deleteReservation(
        int $projectId,
        int $resourceId,
        Request $request,
        ResourceReservationService $reservationService
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canReserveResources($user)) {
            throw $this->createAccessDeniedException('Seul un client peut supprimer une reservation.');
        }

        if (!$this->isCsrfTokenValid('delete_reservation_' . $projectId . '_' . $resourceId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite de suppression est invalide.');

            return $this->redirectToRoute('resource_reservations');
        }

        try {
            $reservationService->deleteClientReservation($user, $projectId, $resourceId);
            $this->addFlash('success', 'La reservation a ete supprimee avec succes.');
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        return $this->redirectToRoute('resource_reservations');
    }

    #[Route('/back/resources/reservations/{projectId}/{resourceId}/edit', name: 'back_resource_reservation_edit', methods: ['GET', 'POST'], requirements: ['projectId' => '\d+', 'resourceId' => '\d+'])]
    public function backEditReservation(
        int $projectId,
        int $resourceId,
        Request $request,
        ProjectRepository $projectRepository,
        ResourceReservationService $reservationService,
        ValidatorInterface $validator
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette reservation.');
        }

        $reservation = $reservationService->getReservationForManager($projectId, $resourceId);
        if ($reservation === null) {
            throw $this->createNotFoundException('Reservation introuvable.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_reservation_' . $projectId . '_' . $resourceId, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Le jeton de securite de modification est invalide.');

                return $this->redirectToRoute('back_resource_reservation_edit', ['projectId' => $projectId, 'resourceId' => $resourceId]);
            }

            ['quantity' => $quantity, 'projectId' => $targetProjectId, 'errors' => $errors] = $this->parseReservationPayload($request, $validator);

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }

                if ($targetProjectId !== null && $targetProjectId > 0) {
                    $reservation['project_id'] = $targetProjectId;
                }

                if ($quantity !== null) {
                    $reservation['reserved_qty'] = $quantity;
                }

                return $this->render('back/resource/reservation_edit.html.twig', [
                    'reservation' => $reservation,
                    'projects' => $projectRepository->findAllOrdered(),
                ]);
            }

            try {
                $reservationService->updateReservationForManager($projectId, $resourceId, $quantity, $targetProjectId);
                $this->addFlash('success', 'La reservation a ete modifiee avec succes.');

                return $this->redirectToRoute('back_resource_reservations');
            } catch (\Throwable $throwable) {
                $this->addFlash('error', $throwable->getMessage());
                if ($targetProjectId !== null && $targetProjectId > 0) {
                    $reservation['project_id'] = $targetProjectId;
                }
                $reservation['reserved_qty'] = $quantity;
            }
        }

        return $this->render('back/resource/reservation_edit.html.twig', [
            'reservation' => $reservation,
            'projects' => $projectRepository->findAllOrdered(),
        ]);
    }

    #[Route('/back/resources/reservations/{projectId}/{resourceId}/delete', name: 'back_resource_reservation_delete', methods: ['POST'], requirements: ['projectId' => '\d+', 'resourceId' => '\d+'])]
    public function backDeleteReservation(
        int $projectId,
        int $resourceId,
        Request $request,
        ResourceReservationService $reservationService
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette reservation.');
        }

        if (!$this->isCsrfTokenValid('delete_reservation_' . $projectId . '_' . $resourceId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite de suppression est invalide.');

            return $this->redirectToRoute('back_resource_reservations');
        }

        try {
            $reservationService->deleteReservationForManager($projectId, $resourceId);
            $this->addFlash('success', 'La reservation a ete supprimee avec succes.');
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        return $this->redirectToRoute('back_resource_reservations');
    }

    #[Route('/resources/new', name: 'resource_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        CataloguefournisseurRepository $supplierRepository,
        ResourceRepository $resourceRepository
    ): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas creer de ressource.');
        }

        $resource = new Resource();
        $resource->setQuantity(1);

        $supplierId = (int) $request->query->get('supplier_id', 0);
        if ($supplierId > 0) {
            $supplier = $supplierRepository->find($supplierId);
            if ($supplier !== null) {
                $resource->setCataloguefournisseur($supplier);
            }
        }

        $form = $this->createForm(ResourceType::class, $resource, [
            'submit_label' => 'Ajouter la ressource',
            'status_choices' => $this->getAvailabilityChoices($resource),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeResourceForPersistence($resource);

            if ($resourceRepository->existsByName((string) $resource->getNomRs())) {
                $form->get('nomRs')->addError(new FormError('Une ressource avec ce nom existe deja.'));
            } else {
                $entityManager->persist($resource);
                $entityManager->flush();

                $this->addFlash('success', 'La ressource a ete creee avec succes.');

                return $this->redirectToRoute($this->isBackOfficeResourceUser($user) ? 'resource_back_manage' : 'resource_manage', ['id' => $resource->getId()]);
            }
        }

        return $this->render($this->resolveResourceFormTemplate($user), [
            'resource' => $resource,
            'form' => $form->createView(),
            'page_title' => 'Ajouter une ressource',
            'page_badge' => $this->isBackOfficeResourceUser($user) ? 'Back office' : 'Nouvelle ressource',
            'page_message' => 'Renseignez les informations de la ressource. Les champs fournisseur, nom, prix, quantite et statut sont obligatoires.',
            'back_route' => 'back_resource_index',
        ]);
    }

    #[Route('/resources/{id}/manage', name: 'resource_manage', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function manage(int $id, ResourceRepository $resourceRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas gerer cette ressource.');
        }

        $resource = $resourceRepository->findOneWithSupplier($id);
        if (!$resource instanceof Resource) {
            throw $this->createNotFoundException('Ressource introuvable.');
        }

        return $this->redirectToRoute('resource_back_manage', ['id' => $resource->getId()]);
    }

    #[Route('/back/resources/{id}/manage', name: 'resource_back_manage', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function backManage(int $id, ResourceRepository $resourceRepository, ResourceReservationService $reservationService): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas gerer cette ressource.');
        }

        $resource = $resourceRepository->findOneWithSupplier($id);
        if (!$resource instanceof Resource) {
            throw $this->createNotFoundException('Ressource introuvable.');
        }

        return $this->render('back/resource/manage.html.twig', [
            'resource' => $resource,
            'can_edit_resource' => true,
            'can_delete_resource' => true,
            'assigned_projects_count' => $resource->getProjects()->count(),
            'reserved_stock' => $reservationService->getReservedStock((int) $resource->getId()),
            'available_stock' => $reservationService->getAvailableStock($resource),
        ]);
    }

    #[Route('/resources/{id}/edit', name: 'resource_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager, ResourceRepository $resourceRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette ressource.');
        }

        $resource = $resourceRepository->findOneWithSupplier($id);
        if (!$resource instanceof Resource) {
            throw $this->createNotFoundException('Ressource introuvable.');
        }

        $form = $this->createForm(ResourceType::class, $resource, [
            'submit_label' => 'Mettre a jour la ressource',
            'status_choices' => $this->getAvailabilityChoices($resource),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeResourceForPersistence($resource);

            if ($resourceRepository->existsByName((string) $resource->getNomRs(), $resource->getId())) {
                $form->get('nomRs')->addError(new FormError('Une ressource avec ce nom existe deja.'));
            } else {
                $entityManager->flush();

                $this->addFlash('success', 'La ressource a ete modifiee avec succes.');

                return $this->redirectToRoute($this->isBackOfficeResourceUser($user) ? 'resource_back_manage' : 'resource_manage', ['id' => $resource->getId()]);
            }
        }

        return $this->render($this->resolveResourceFormTemplate($user), [
            'resource' => $resource,
            'form' => $form->createView(),
            'page_title' => 'Modifier une ressource',
            'page_badge' => $this->isBackOfficeResourceUser($user) ? 'Back office' : 'Gestion ressource',
            'page_message' => 'Mettez a jour les informations de la ressource et verifiez la coherence du stock et du fournisseur.',
            'back_route' => $this->isBackOfficeResourceUser($user) ? 'resource_back_manage' : 'resource_manage',
        ]);
    }

    #[Route('/resources/{id}/delete', name: 'resource_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager, ResourceRepository $resourceRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageResources($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette ressource.');
        }

        $resource = $resourceRepository->findOneWithSupplier($id);
        if (!$resource instanceof Resource) {
            throw $this->createNotFoundException('Ressource introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_resource_' . $resource->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite de suppression est invalide.');

            return $this->redirectToRoute($this->getResourceManagementRoute($user), ['id' => $resource->getId()]);
        }

        if ($this->hasBlockingResourceDependencies($resource, $entityManager)) {
            $this->addFlash('error', 'Cette ressource ne peut pas etre supprimee tant qu elle est liee a des projets ou au mini-shop.');

            return $this->redirectToRoute($this->getResourceManagementRoute($user), ['id' => $resource->getId()]);
        }

        $entityManager->remove($resource);
        $entityManager->flush();

        $this->addFlash('success', 'La ressource a ete supprimee avec succes.');

        return $this->redirectToRoute('back_resource_index');
    }

    /**
     * @param array<int, mixed> $actions
     * @return array<int, array<string, mixed>>
     */
    private function filterActionsForView(array $actions, ?string $priorityFilter, string $actionCodeFilter): array
    {
        $normalizedCode = strtoupper(trim($actionCodeFilter));
        $filtered = [];

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $priority = strtolower(trim((string) ($action['priority'] ?? 'basse')));
            $actionCode = strtoupper(trim((string) ($action['action_code'] ?? '')));

            if ($priorityFilter !== null && $priority !== $priorityFilter) {
                continue;
            }
            if ($normalizedCode !== '' && $actionCode !== $normalizedCode) {
                continue;
            }

            $action['priority'] = $priority;
            $action['action_code'] = $actionCode;
            $filtered[] = $action;
        }

        return $filtered;
    }

    /**
     * @param array<int, mixed> $actions
     * @return array<int, string>
     */
    private function collectActionCodes(array $actions): array
    {
        $codes = [];
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $code = strtoupper(trim((string) ($action['action_code'] ?? '')));
            if ($code === '') {
                continue;
            }

            $codes[] = $code;
        }

        $codes = array_values(array_unique($codes));
        sort($codes);

        return $codes;
    }

    private function normalizeActionPriority(string $priority): ?string
    {
        $normalized = strtolower(trim($priority));

        return in_array($normalized, ['haute', 'moyenne', 'basse'], true) ? $normalized : null;
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     */
    private function buildActionsCsv(array $actions): string
    {
        $handle = fopen('php://temp', 'w+');
        if ($handle === false) {
            return '';
        }

        // BOM UTF-8 pour une ouverture propre sous Excel.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'priority',
            'code_action',
            'resource_id',
            'resource_name',
            'supplier_name',
            'impact_metier',
            'justification',
            'confidence_pct',
            'delay',
            'signal_type',
        ], ';');

        foreach ($actions as $action) {
            fputcsv($handle, [
                (string) ($action['priority'] ?? ''),
                (string) ($action['action_code'] ?? ''),
                (string) ($action['resource_id'] ?? ''),
                (string) ($action['resource_name'] ?? ''),
                (string) ($action['supplier_name'] ?? ''),
                (string) ($action['impact_metier'] ?? ''),
                (string) ($action['justification'] ?? ''),
                (string) ($action['confidence_pct'] ?? ''),
                (string) ($action['delay'] ?? ''),
                (string) ($action['signal_type'] ?? ''),
            ], ';');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : '';
    }

    /**
     * @return array<string, string>
     */
    private function extractAnalysisFilterParams(Request $request): array
    {
        $priorityRaw = (string) $request->request->get('priority', $request->query->get('priority', ''));
        $priority = $this->normalizeActionPriority($priorityRaw);
        $actionCode = strtoupper(trim((string) $request->request->get('action_code', $request->query->get('action_code', ''))));

        $params = [];
        if ($priority !== null) {
            $params['priority'] = $priority;
        }
        if ($actionCode !== '') {
            $params['action_code'] = $actionCode;
        }

        return $params;
    }

    /**
     * @return array{
     *     q: string,
     *     status: string,
     *     supplier_id: string,
     *     min_price: mixed,
     *     max_price: mixed
     * }
     */
    private function extractFilters(Request $request, bool $includePriceFilters = true): array
    {
        return [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'supplier_id' => trim((string) $request->query->get('supplier_id', '')),
            'min_price' => $includePriceFilters ? $request->query->get('min_price', null) : null,
            'max_price' => $includePriceFilters ? $request->query->get('max_price', null) : null,
        ];
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function canManageResources(?User $user): bool
    {
        return $user instanceof User && in_array($user->getRoleUser(), ['admin', 'gerant'], true);
    }

    private function canReserveResources(?User $user): bool
    {
        return $user instanceof User && strtolower((string) $user->getRoleUser()) === 'client';
    }

    private function isBackOfficeResourceUser(?User $user): bool
    {
        return $this->canManageResources($user);
    }

    private function resolveResourceFormTemplate(?User $user): string
    {
        return $this->isBackOfficeResourceUser($user)
            ? 'back/resource/form.html.twig'
            : 'front/resource/form.html.twig';
    }

    private function normalizeResourceForPersistence(Resource $resource): void
    {
        if (trim($resource->getName()) === '') {
            $resource->setName('Ressource sans nom');
        } else {
            $resource->setName(trim((string) $resource->getName()));
        }

        if ($resource->getImageUrlRs() !== null && trim($resource->getImageUrlRs()) === '') {
            $resource->setImageUrlRs(null);
        }

        if ($resource->getQuantity() <= 0) {
            $resource->setStatus(Resource::STATUS_UNAVAILABLE);

            return;
        }

        if (!array_key_exists((string) $resource->getStatus(), Resource::STATUSES)) {
            $resource->setStatus(Resource::STATUS_AVAILABLE);
        }
    }

    private function hasBlockingResourceDependencies(Resource $resource, EntityManagerInterface $entityManager): bool
    {
        if (!$resource->getProjects()->isEmpty()) {
            return true;
        }

        return $entityManager->getRepository(ResourceMarketListing::class)->count(['idRs' => $resource->getId()]) > 0
            || $entityManager->getRepository(ResourceMarketOrder::class)->count(['idRs' => $resource->getId()]) > 0;
    }

    private function getResourceManagementRoute(?User $user): string
    {
        return $this->isBackOfficeResourceUser($user) ? 'resource_back_manage' : 'resource_manage';
    }

    /**
     * @return array<string, string>
     */
    private function getAvailabilityChoices(?Resource $resource = null): array
    {
        $choices = Resource::STATUSES;
        $currentStatus = $resource?->getStatus();

        if ($currentStatus !== null && $currentStatus !== '' && !array_key_exists($currentStatus, $choices)) {
            $choices[$currentStatus] = ucfirst(str_replace('_', ' ', $currentStatus)) . ' (actuel)';
        }

        return $choices;
    }

    /**
     * @param array<string, int> $metrics
     */
    private function createStockDistributionChart(array $metrics, ChartBuilderInterface $chartBuilder): Chart
    {
        $availableStock = max(0, (int) ($metrics['stock_available'] ?? 0));
        $reservedStock = max(0, (int) ($metrics['stock_reserved'] ?? 0));

        $chart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        if ($availableStock === 0 && $reservedStock === 0) {
            $chart->setData([
                'labels' => ['Aucun stock enregistre'],
                'datasets' => [[
                    'data' => [1],
                    'backgroundColor' => ['#d9ccbb'],
                    'borderColor' => ['#f0e9de'],
                    'borderWidth' => 1,
                ]],
            ]);
        } else {
            $chart->setData([
                'labels' => ['Stock disponible', 'Stock reserve'],
                'datasets' => [[
                    'data' => [$availableStock, $reservedStock],
                    'backgroundColor' => ['#6f865b', '#c37d5d'],
                    'borderColor' => ['#edf4e7', '#f7ece6'],
                    'borderWidth' => 1,
                    'hoverOffset' => 4,
                ]],
            ]);
        }

        $chart->setOptions([
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 12,
                        'usePointStyle' => true,
                    ],
                ],
            ],
        ]);

        return $chart;
    }

    /**
     * @param Resource[] $resources
     */
    private function createResourceStatusChart(array $resources, ChartBuilderInterface $chartBuilder): Chart
    {
        $statusCounts = [
            Resource::STATUS_AVAILABLE => 0,
            Resource::STATUS_RESERVED => 0,
            Resource::STATUS_UNAVAILABLE => 0,
        ];

        foreach ($resources as $resource) {
            $status = $resource->getStatus();
            if (!array_key_exists($status, $statusCounts)) {
                $status = Resource::STATUS_UNAVAILABLE;
            }

            ++$statusCounts[$status];
        }

        $chart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => ['Disponibles', 'Reservees', 'Indisponibles'],
            'datasets' => [[
                'label' => 'Nombre de ressources',
                'data' => [
                    $statusCounts[Resource::STATUS_AVAILABLE],
                    $statusCounts[Resource::STATUS_RESERVED],
                    $statusCounts[Resource::STATUS_UNAVAILABLE],
                ],
                'backgroundColor' => ['#6f865b', '#c37d5d', '#9f5b47'],
                'borderRadius' => 8,
                'maxBarThickness' => 42,
            ]],
        ]);
        $chart->setOptions([
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                        'stepSize' => 1,
                    ],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
        ]);

        return $chart;
    }

    /**
     * @param Resource[] $linkedResources
     */
    private function createTopLinkedResourcesChart(array $linkedResources, ChartBuilderInterface $chartBuilder): Chart
    {
        $labels = [];
        $values = [];

        foreach ($linkedResources as $resource) {
            $name = trim((string) $resource->getName());
            if ($name === '') {
                $name = 'Ressource sans nom';
            }

            if (strlen($name) > 26) {
                $name = substr($name, 0, 23) . '...';
            }

            $labels[] = $name;
            $values[] = $resource->getProjects()->count();
        }

        if ($labels === []) {
            $labels[] = 'Aucune liaison';
            $values[] = 0;
        }

        $chart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Projets lies',
                'data' => $values,
                'backgroundColor' => '#7f6c57',
                'borderRadius' => 8,
                'barThickness' => 16,
            ]],
        ]);
        $chart->setOptions([
            'indexAxis' => 'y',
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                        'stepSize' => 1,
                    ],
                ],
                'y' => [
                    'grid' => ['display' => false],
                ],
            ],
        ]);

        return $chart;
    }

    /**
     * @param Resource[] $resources
     * @param array<int, mixed> $suppliers
     * @return array<string, int>
     */
    private function buildResourceModuleMetrics(
        array $resources,
        array $suppliers,
        EntityManagerInterface $entityManager,
        ResourceReservationService $reservationService
    ): array
    {
        $availableResources = count(array_filter(
            $resources,
            static fn (Resource $resource): bool => $resource->getQuantity() > 0 && $resource->getStatus() !== Resource::STATUS_UNAVAILABLE
        ));

        $activeSuppliers = count(array_filter(
            $suppliers,
            static fn (mixed $supplier): bool => $supplier instanceof Cataloguefournisseur && $supplier->isActiveSupplier()
        ));

        $stockTotal = array_sum(array_map(
            static fn (Resource $resource): int => (int) $resource->getQuantity(),
            $resources
        ));

        $reservedQuantity = array_sum(array_map(
            static fn (Resource $resource): int => $reservationService->getReservedStock((int) ($resource->getId() ?? 0)),
            $resources
        ));

        $totalReservations = (int) $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM project_resources');

        return [
            'total_resources' => count($resources),
            'available_resources' => $availableResources,
            'total_suppliers' => count($suppliers),
            'active_suppliers' => $activeSuppliers,
            'stock_total' => $stockTotal,
            'stock_reserved' => $reservedQuantity,
            'stock_available' => max(0, $stockTotal - $reservedQuantity),
            'total_reservations' => $totalReservations,
            'total_reserved_quantity' => $reservedQuantity,
        ];
    }

    /**
     * @param Resource[] $resources
     * @return array<int, array{reserved:int, available:int}>
     */
    private function buildStockSnapshots(array $resources, ResourceReservationService $reservationService): array
    {
        $snapshots = [];

        foreach ($resources as $resource) {
            $resourceId = (int) ($resource->getId() ?? 0);
            if ($resourceId <= 0) {
                continue;
            }

            $reserved = $reservationService->getReservedStock($resourceId);
            $snapshots[$resourceId] = [
                'reserved' => $reserved,
                'available' => $reservationService->getAvailableStock($resource),
            ];
        }

        return $snapshots;
    }

    /**
     * @return array{quantity:?int, projectId:?int, errors:array<int, string>}
     */
    private function parseReservationPayload(Request $request, ValidatorInterface $validator): array
    {
        $quantityRaw = trim((string) $request->request->get('quantity', ''));
        $projectIdRaw = trim((string) $request->request->get('project_id', ''));

        $violations = $validator->validate(
            [
                'quantity' => $quantityRaw,
                'project_id' => $projectIdRaw,
            ],
            new Assert\Collection([
                'allowExtraFields' => true,
                'fields' => [
                    'quantity' => [
                        new Assert\NotBlank(['message' => 'La quantite est obligatoire.']),
                        new Assert\Regex([
                            'pattern' => '/^\d+$/',
                            'message' => 'La quantite doit etre un entier positif.',
                        ]),
                        new Assert\GreaterThan([
                            'value' => 0,
                            'message' => 'La quantite doit etre strictement superieure a 0.',
                        ]),
                    ],
                    'project_id' => new Assert\Optional([
                        new Assert\Regex([
                            'pattern' => '/^\d+$/',
                            'message' => 'Le projet cible est invalide.',
                        ]),
                        new Assert\GreaterThan([
                            'value' => 0,
                            'message' => 'Le projet cible est invalide.',
                        ]),
                    ]),
                ],
            ])
        );

        $errors = [];
        foreach ($violations as $violation) {
            $message = trim((string) $violation->getMessage());
            if ($message === '' || in_array($message, $errors, true)) {
                continue;
            }

            $errors[] = $message;
        }

        return [
            'quantity' => $quantityRaw !== '' && ctype_digit($quantityRaw) ? (int) $quantityRaw : null,
            'projectId' => $projectIdRaw !== '' && ctype_digit($projectIdRaw) ? (int) $projectIdRaw : null,
            'errors' => $errors,
        ];
    }
}
