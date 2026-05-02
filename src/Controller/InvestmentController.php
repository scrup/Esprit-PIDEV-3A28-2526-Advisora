<?php

namespace App\Controller;

use App\Entity\Investment;
use App\Entity\Project;
use App\Entity\Transaction;
use App\Entity\User;
use App\Form\InvestmentType;
use App\Repository\InvestmentRepository;
use App\Repository\ProjectRepository;
use App\Service\InvestmentPredictionService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InvestmentController extends AbstractController
{
    #[Route('/investments', name: 'investment_index', methods: ['GET'])]
    public function index(Request $request, InvestmentRepository $investmentRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut consulter ses investissements.');
        }

        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => trim((string) $request->query->get('status', '')),
        ];

        return $this->render('front/investment/index.html.twig', [
            'investments' => $investmentRepository->findClientInvestments($user, $filters),
            'filters' => $filters,
            'status_choices' => $this->getStatusChoices(true),
        ]);
    }

    #[Route('/investments/new', name: 'investment_create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        ProjectRepository $projectRepository,
        EntityManagerInterface $entityManager,
        InvestmentPredictionService $investmentPredictionService
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut creer un investissement.');
        }

        $investment = new Investment();
        $investment->setUser($user);
        $investment->setCurrencyInv('TND');

        return $this->handleCreateForm($request, $investment, $projectRepository, $entityManager, $investmentPredictionService, null, $user);
    }

    #[Route('/projects/{id}/investments/new', name: 'investment_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function new(
        int $id,
        Request $request,
        ProjectRepository $projectRepository,
        EntityManagerInterface $entityManager,
        InvestmentPredictionService $investmentPredictionService
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut creer un investissement.');
        }

        $project = $projectRepository->find($id);
        if (!$project instanceof Project) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $investment = new Investment();
        $investment->setUser($user);
        $investment->setCurrencyInv('TND');
        $investment->setProject($project);

        return $this->handleCreateForm($request, $investment, $projectRepository, $entityManager, $investmentPredictionService, $project, $user);
    }

    #[Route('/investments/{id}/manage', name: 'investment_manage', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function manage(
        int $id,
        InvestmentRepository $investmentRepository,
        InvestmentPredictionService $investmentPredictionService
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut gerer son investissement.');
        }

        $investment = $investmentRepository->findOwnedDetailed($id, $user);
        if (!$investment instanceof Investment) {
            throw $this->createNotFoundException('Investissement introuvable.');
        }

        $latestTransaction = $investment->getLatestTransaction();

        $totalActive = $investment->getTotalActiveTransactionsAmount();
        $budMax = (float) $investment->getBud_maxInv();
        $prediction = null;
        $predictionError = null;

        try {
            $prediction = $investmentPredictionService->predictForInvestment($investment);
        } catch (\Throwable) {
            $predictionError = 'La prediction d investissement est temporairement indisponible.';
        }

        return $this->render('front/investment/manage.html.twig', [
            'investment' => $investment,
            'latest_transaction' => $latestTransaction,
            'can_edit_investment' => $investment->isEditableByClient(),
            'can_delete_investment' => $investment->isEditableByClient(),
            'can_create_transaction' => $totalActive < $budMax,
            'prediction' => $prediction,
            'prediction_error' => $predictionError,
        ]);
    }

    #[Route('/investments/{id}/edit', name: 'investment_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        int $id,
        Request $request,
        InvestmentRepository $investmentRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut modifier son investissement.');
        }

        $investment = $investmentRepository->findOwnedDetailed($id, $user);
        if (!$investment instanceof Investment) {
            throw $this->createNotFoundException('Investissement introuvable.');
        }

        if (!$investment->isEditableByClient()) {
            $this->addFlash('error', 'Cet investissement est verrouille par une transaction traitee.');

            return $this->redirectToRoute('investment_manage', ['id' => $investment->getId()]);
        }

        $form = $this->createForm(InvestmentType::class, $investment, [
            'submit_label' => 'Mettre a jour l investissement',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeInvestmentForPersistence($investment, $investment->getProject(), $user);
            $this->validateInvestmentRange($form, $investment);
            $this->validatePendingTransactionStillFits($form, $investment);

            if ($form->isValid()) {
                $entityManager->flush();

                $this->addFlash('success', 'L investissement a ete modifie avec succes.');

                return $this->redirectToRoute('investment_manage', ['id' => $investment->getId()]);
            }
        }

        return $this->render('front/investment/form.html.twig', [
            'form' => $form->createView(),
            'investment' => $investment,
            'project' => $investment->getProject(),
            'page_title' => 'Modifier mon investissement',
            'page_badge' => 'Investissement client',
            'page_message' => 'La transaction en attente doit rester comprise dans la fourchette si elle existe deja.',
            'back_route' => 'investment_manage',
        ]);
    }

    #[Route('/investments/{id}/delete', name: 'investment_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        int $id,
        Request $request,
        InvestmentRepository $investmentRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut supprimer son investissement.');
        }

        $investment = $investmentRepository->findOwnedDetailed($id, $user);
        if (!$investment instanceof Investment) {
            throw $this->createNotFoundException('Investissement introuvable.');
        }

        if (!$investment->isEditableByClient()) {
            $this->addFlash('error', 'Cet investissement ne peut plus etre supprime.');

            return $this->redirectToRoute('investment_manage', ['id' => $investment->getId()]);
        }

        if (!$this->isCsrfTokenValid('delete_investment_' . $investment->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite de suppression est invalide.');

            return $this->redirectToRoute('investment_manage', ['id' => $investment->getId()]);
        }

        $entityManager->remove($investment);
        $entityManager->flush();

        $this->addFlash('success', 'L investissement a ete supprime avec succes.');

        return $this->redirectToRoute('investment_index');
    }

    #[Route('/back/investments', name: 'back_investment_index', methods: ['GET'])]
    public function backIndex(Request $request, InvestmentRepository $investmentRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageInvestments($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter tous les investissements.');
        }

        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'owner' => trim((string) $request->query->get('owner', '')),
        ];

        return $this->render('back/investment/index.html.twig', [
            'investments' => $investmentRepository->findBackOfficeInvestments($filters),
            'filters' => $filters,
            'status_choices' => $this->getStatusChoices(false),
        ]);
    }

    #[Route('/back/investments/{id}/manage', name: 'back_investment_manage', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function backManage(int $id, InvestmentRepository $investmentRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageInvestments($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter cet investissement.');
        }

        $investment = $investmentRepository->findDetailedById($id);
        if (!$investment instanceof Investment) {
            throw $this->createNotFoundException('Investissement introuvable.');
        }

        return $this->render('back/investment/manage.html.twig', [
            'investment' => $investment,
            'latest_transaction' => $investment->getLatestTransaction(),
        ]);
    }

    #[Route('/investments/history', name: 'investment_history', methods: ['GET'])]
    public function history(Request $request, InvestmentRepository $investmentRepository, PaginatorInterface $paginator): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut consulter ses investissements.');
        }

        $allInvestments = $investmentRepository->findClientInvestments($user, []);

        $pagination = $paginator->paginate(
            $allInvestments,
            $request->query->getInt('page', 1),
            6
        );

        return $this->render('front/investment/history.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/investments/history/pdf', name: 'investment_history_pdf', methods: ['GET'])]
    public function historyPdf(Request $request, InvestmentRepository $investmentRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut consulter ses investissements.');
        }

        $investments = $investmentRepository->findClientInvestments($user, []);

        $pdfOptions = new \Dompdf\Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->setIsRemoteEnabled(true);

        $dompdf = new \Dompdf\Dompdf($pdfOptions);

        $html = $this->renderView('front/investment/history_pdf.html.twig', [
            'investments' => $investments,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="historique_investissements.pdf"',
        ]);
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function isClient(?User $user): bool
    {
        return $user instanceof User && $user->getRoleUser() === 'client';
    }

    private function canManageInvestments(?User $user): bool
    {
        return $user instanceof User && in_array($user->getRoleUser(), ['admin', 'gerant'], true);
    }

    private function handleCreateForm(
        Request $request,
        Investment $investment,
        ProjectRepository $projectRepository,
        EntityManagerInterface $entityManager,
        InvestmentPredictionService $investmentPredictionService,
        ?Project $prefilledProject,
        User $user
    ): Response {
        $allProjects = array_values($projectRepository->findAllOrdered());
        $investmentRecommendations = [];
        $investmentRecommendationsError = null;

        try {
            $investmentRecommendations = $investmentPredictionService->getTopProjectRecommendations($allProjects, 5);
        } catch (\Throwable) {
            $investmentRecommendationsError = 'Le classement des projets recommandes est temporairement indisponible.';
        }

        $form = $this->createForm(InvestmentType::class, $investment, [
            'submit_label' => 'Ajouter l investissement',
            'include_project' => true,
            'project_choices' => $allProjects,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedProject = $investment->getProject();
            if (!$selectedProject instanceof Project || $selectedProject->getId() === null) {
                $form->get('project')->addError(new FormError('Le projet selectionne est invalide.'));
            } else {
                $this->normalizeInvestmentForPersistence($investment, $selectedProject, $user);
                $this->validateInvestmentRange($form, $investment);
            }

            if ($form->isValid()) {
                $entityManager->persist($investment);
                $entityManager->flush();

                $this->addFlash('success', 'L investissement a ete cree avec succes.');

                return $this->redirectToRoute('investment_manage', ['id' => $investment->getId()]);
            }
        }

        return $this->render('front/investment/form.html.twig', [
            'form' => $form->createView(),
            'investment' => $investment,
            'project' => $investment->getProject() ?? $prefilledProject,
            'projects_count' => count($allProjects),
            'investment_recommendations' => $investmentRecommendations,
            'investment_recommendations_error' => $investmentRecommendationsError,
            'recommendation_macro' => $investmentRecommendations !== []
                ? $investmentRecommendations[0]['prediction']->getMacroAnalysis()
                : null,
            'selected_project_id' => $investment->getProject()?->getId(),
            'page_title' => 'Ajouter un investissement',
            'page_badge' => 'Investissement client',
            'page_message' => 'Choisissez librement un projet existant puis saisissez une fourchette min/max compatible avec votre futur transfert.',
            'back_route' => 'investment_index',
        ]);
    }

    private function normalizeInvestmentForPersistence(Investment $investment, ?Project $project, ?User $user): void
    {
        if ($project instanceof Project) {
            $investment->setProject($project);
        }

        if ($user instanceof User) {
            $investment->setUser($user);
        }

        $comment = $investment->getCommentaireInv();
        if ($comment !== null) {
            $comment = trim($comment);
            $investment->setCommentaireInv($comment !== '' ? $comment : null);
        }

        $currency = trim((string) $investment->getCurrencyInv());
        $investment->setCurrencyInv($currency !== '' ? mb_strtoupper($currency) : 'TND');

        if ($investment->getDurationEstimateLabel() !== null) {
            $investment->setDureeInv(null);
        }
    }

    /**
     * @param FormInterface<Investment> $form
     */
    private function validateInvestmentRange(FormInterface $form, Investment $investment): void
    {
        if ($investment->getBud_minInv() > $investment->getBud_maxInv()) {
            $form->get('bud_maxInv')->addError(new FormError('Le montant maximum doit etre superieur ou egal au montant minimum.'));
        }
    }

    /**
     * @param FormInterface<Investment> $form
     */
    private function validatePendingTransactionStillFits(FormInterface $form, Investment $investment): void
    {
        $totalActive = $investment->getTotalActiveTransactionsAmount();

        if ($totalActive > (float) $investment->getBud_maxInv()) {
            $form->addError(new FormError('Le total des transactions existantes depasse le nouveau montant maximum de l investissement.'));
        }
    }

    /**
     * @return array<string, string>
     */
    private function getStatusChoices(bool $clientScope): array
    {
        $choices = [
            Transaction::STATUS_PENDING => 'Transaction en attente',
            Transaction::STATUS_SUCCESS => 'Transaction acceptee',
            Transaction::STATUS_FAILED => 'Transaction refusee',
        ];

        if (!$clientScope) {
            $choices = ['NO_TRANSACTION' => 'Sans transaction'] + $choices;
        }

        return $choices;
    }
}
