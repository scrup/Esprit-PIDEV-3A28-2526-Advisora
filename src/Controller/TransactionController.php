<?php

namespace App\Controller;

use App\Entity\Investment;
use App\Entity\Transaction;
use App\Entity\User;
use App\Form\TransactionType;
use App\Repository\InvestmentRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TransactionController extends AbstractController
{
    #[Route('/transactions', name: 'transaction_index', methods: ['GET'])]
    public function index(Request $request, TransactionRepository $transactionRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut consulter ses transactions.');
        }

        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => trim((string) $request->query->get('status', '')),
        ];

        return $this->render('front/transaction/index.html.twig', [
            'transactions' => $transactionRepository->findClientTransactions($user, $filters),
            'filters' => $filters,
            'status_choices' => $this->getStatusChoices(),
        ]);
    }

    #[Route('/investments/{id}/transactions/new', name: 'transaction_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function new(
        int $id,
        Request $request,
        InvestmentRepository $investmentRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut creer une transaction.');
        }

        $investment = $investmentRepository->findOwnedDetailed($id, $user);
        if (!$investment instanceof Investment) {
            throw $this->createNotFoundException('Investissement introuvable.');
        }

        $existingTransaction = $investment->getLatestTransaction();
        if ($existingTransaction instanceof Transaction) {
            if ($existingTransaction->isPending()) {
                $this->addFlash('error', 'Une transaction en attente existe deja pour cet investissement.');

                return $this->redirectToRoute('transaction_edit', ['id' => $existingTransaction->getId()]);
            }

            $this->addFlash('error', 'Une transaction existe deja pour cet investissement. Il est en lecture seule.');

            return $this->redirectToRoute('investment_manage', ['id' => $investment->getId()]);
        }

        $transaction = new Transaction();
        $transaction->setInvestment($investment);
        $transaction->setDateTransac(new \DateTime('today'));
        $transaction->setMontantTransac((float) ($investment->getBud_minInv() ?? 0));
        $transaction->setType(Transaction::TYPE_INVESTMENT_PAYMENT);
        $transaction->setStatut(Transaction::STATUS_PENDING);

        $form = $this->createForm(TransactionType::class, $transaction, [
            'submit_label' => 'Ajouter la transaction',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeTransactionForPersistence($transaction, $investment);
            $this->validateTransactionAmount($form, $transaction);

            if ($form->isValid()) {
                $entityManager->persist($transaction);
                $entityManager->flush();

                $this->addFlash('success', 'La transaction a ete creee avec succes.');

                return $this->redirectToRoute('investment_manage', ['id' => $investment->getId()]);
            }
        }

        return $this->render('front/transaction/form.html.twig', [
            'form' => $form->createView(),
            'transaction' => $transaction,
            'investment' => $investment,
            'page_title' => 'Ajouter une transaction',
            'page_badge' => 'Transaction client',
            'page_message' => 'Le montant doit etre compris entre le minimum et le maximum de votre investissement.',
            'back_route' => 'investment_manage',
        ]);
    }

    #[Route('/transactions/{id}/edit', name: 'transaction_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        int $id,
        Request $request,
        TransactionRepository $transactionRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut modifier sa transaction.');
        }

        $transaction = $transactionRepository->findOwnedDetailed($id, $user);
        if (!$transaction instanceof Transaction) {
            throw $this->createNotFoundException('Transaction introuvable.');
        }

        if (!$this->canEditTransaction($transaction)) {
            $this->addFlash('error', 'Cette transaction ne peut plus etre modifiee.');

            return $this->redirectToRoute('transaction_index');
        }

        $form = $this->createForm(TransactionType::class, $transaction, [
            'submit_label' => 'Mettre a jour la transaction',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeTransactionForPersistence($transaction, $transaction->getInvestment());
            $this->validateTransactionAmount($form, $transaction);

            if ($form->isValid()) {
                $entityManager->flush();

                $this->addFlash('success', 'La transaction a ete modifiee avec succes.');

                return $this->redirectToRoute('investment_manage', ['id' => $transaction->getInvestment()?->getId()]);
            }
        }

        return $this->render('front/transaction/form.html.twig', [
            'form' => $form->createView(),
            'transaction' => $transaction,
            'investment' => $transaction->getInvestment(),
            'page_title' => 'Modifier ma transaction',
            'page_badge' => 'Transaction client',
            'page_message' => 'La transaction reste en attente tant qu elle n est pas traitee par le back office.',
            'back_route' => 'transaction_index',
        ]);
    }

    #[Route('/transactions/{id}/delete', name: 'transaction_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        int $id,
        Request $request,
        TransactionRepository $transactionRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut supprimer sa transaction.');
        }

        $transaction = $transactionRepository->findOwnedDetailed($id, $user);
        if (!$transaction instanceof Transaction) {
            throw $this->createNotFoundException('Transaction introuvable.');
        }

        if (!$this->canEditTransaction($transaction)) {
            $this->addFlash('error', 'Cette transaction ne peut plus etre supprimee.');

            return $this->redirectToRoute('transaction_index');
        }

        if (!$this->isCsrfTokenValid('delete_transaction_' . $transaction->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite de suppression est invalide.');

            return $this->redirectToRoute('transaction_index');
        }

        $investmentId = $transaction->getInvestment()?->getId();

        $entityManager->remove($transaction);
        $entityManager->flush();

        $this->addFlash('success', 'La transaction a ete supprimee avec succes.');

        return $investmentId !== null
            ? $this->redirectToRoute('investment_manage', ['id' => $investmentId])
            : $this->redirectToRoute('transaction_index');
    }

    #[Route('/back/transactions', name: 'back_transaction_index', methods: ['GET'])]
    public function backIndex(Request $request, TransactionRepository $transactionRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageTransactions($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter toutes les transactions.');
        }

        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'owner' => trim((string) $request->query->get('owner', '')),
        ];

        return $this->render('back/transaction/index.html.twig', [
            'transactions' => $transactionRepository->findBackOfficeTransactions($filters),
            'filters' => $filters,
            'status_choices' => $this->getStatusChoices(),
        ]);
    }

    #[Route('/back/transactions/{id}/manage', name: 'back_transaction_manage', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function backManage(int $id, TransactionRepository $transactionRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageTransactions($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas gerer cette transaction.');
        }

        $transaction = $transactionRepository->findDetailedById($id);
        if (!$transaction instanceof Transaction) {
            throw $this->createNotFoundException('Transaction introuvable.');
        }

        return $this->render('back/transaction/manage.html.twig', [
            'transaction' => $transaction,
        ]);
    }

    #[Route('/back/transactions/{id}/accept', name: 'back_transaction_accept', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function accept(
        int $id,
        Request $request,
        TransactionRepository $transactionRepository,
        EntityManagerInterface $entityManager
    ): Response {
        return $this->updateStatus($id, $request, $transactionRepository, $entityManager, Transaction::STATUS_SUCCESS, 'accept');
    }

    #[Route('/back/transactions/{id}/refuse', name: 'back_transaction_refuse', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function refuse(
        int $id,
        Request $request,
        TransactionRepository $transactionRepository,
        EntityManagerInterface $entityManager
    ): Response {
        return $this->updateStatus($id, $request, $transactionRepository, $entityManager, Transaction::STATUS_FAILED, 'refuse');
    }

    #[Route('/back/transactions/{id}/pending', name: 'back_transaction_pending', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function pending(
        int $id,
        Request $request,
        TransactionRepository $transactionRepository,
        EntityManagerInterface $entityManager
    ): Response {
        return $this->updateStatus($id, $request, $transactionRepository, $entityManager, Transaction::STATUS_PENDING, 'pending');
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

    private function canManageTransactions(?User $user): bool
    {
        return $user instanceof User && in_array($user->getRoleUser(), ['admin', 'gerant'], true);
    }

    private function canEditTransaction(Transaction $transaction): bool
    {
        if (!$transaction->isPending()) {
            return false;
        }

        $latestTransaction = $transaction->getInvestment()?->getLatestTransaction();

        return $latestTransaction instanceof Transaction && $latestTransaction->getId() === $transaction->getId();
    }

    private function normalizeTransactionForPersistence(Transaction $transaction, ?Investment $investment): void
    {
        if ($investment instanceof Investment) {
            $transaction->setInvestment($investment);
        }

        if ($transaction->getDateTransac() === null) {
            $transaction->setDateTransac(new \DateTime('today'));
        }

        $type = trim((string) $transaction->getType());
        $transaction->setType($type !== '' ? mb_strtoupper($type) : Transaction::TYPE_INVESTMENT_PAYMENT);

        if ($transaction->getStatut() === null || $transaction->getStatut() === '') {
            $transaction->setStatut(Transaction::STATUS_PENDING);
        }
    }

    private function validateTransactionAmount($form, Transaction $transaction): void
    {
        $investment = $transaction->getInvestment();
        if (!$investment instanceof Investment) {
            $form->addError(new FormError('La transaction doit etre rattachee a un investissement.'));

            return;
        }

        $amount = (float) ($transaction->getMontantTransac() ?? 0);
        $min = (float) ($investment->getBud_minInv() ?? 0);
        $max = (float) ($investment->getBud_maxInv() ?? 0);

        if ($amount < $min || $amount > $max) {
            $form->get('MontantTransac')->addError(new FormError(sprintf(
                'Le montant doit etre compris entre %s et %s %s.',
                number_format($min, 2, '.', ' '),
                number_format($max, 2, '.', ' '),
                $investment->getCurrencyInv() ?? 'TND'
            )));
        }
    }

    private function updateStatus(
        int $id,
        Request $request,
        TransactionRepository $transactionRepository,
        EntityManagerInterface $entityManager,
        string $targetStatus,
        string $tokenAction
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canManageTransactions($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas traiter cette transaction.');
        }

        $transaction = $transactionRepository->findDetailedById($id);
        if (!$transaction instanceof Transaction) {
            throw $this->createNotFoundException('Transaction introuvable.');
        }

        if (!$this->isCsrfTokenValid($tokenAction . '_transaction_' . $transaction->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite est invalide.');

            return $this->redirectToBackTransactionContext($request, $transaction);
        }

        $transaction->setStatut($targetStatus);
        $entityManager->flush();

        $this->addFlash('success', 'Le statut de la transaction a ete mis a jour.');

        return $this->redirectToBackTransactionContext($request, $transaction);
    }

    private function redirectToBackTransactionContext(Request $request, Transaction $transaction): Response
    {
        $referer = (string) $request->headers->get('referer', '');

        if (str_contains($referer, '/back/transactions/' . $transaction->getId() . '/manage')) {
            return $this->redirectToRoute('back_transaction_manage', ['id' => $transaction->getId()]);
        }

        return $this->redirectToRoute('back_transaction_index');
    }

    private function getStatusChoices(): array
    {
        return [
            Transaction::STATUS_PENDING => 'En attente',
            Transaction::STATUS_SUCCESS => 'Acceptee',
            Transaction::STATUS_FAILED => 'Refusee',
        ];
    }
}
