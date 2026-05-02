<?php

namespace App\Controller;

use App\Entity\Cataloguefournisseur;
use App\Entity\User;
use App\Form\SupplierType;
use App\Repository\CataloguefournisseurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SupplierController extends AbstractController
{
    #[Route('/back/suppliers', name: 'back_supplier_index', methods: ['GET'])]
    public function index(Request $request, CataloguefournisseurRepository $supplierRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageSuppliers($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter la gestion des fournisseurs.');
        }

        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => trim((string) $request->query->get('status', '')),
        ];

        $suppliers = $supplierRepository->findBackOfficeSuppliers($filters);
        $totalProducts = array_sum(array_map(static fn (Cataloguefournisseur $supplier): int => (int) $supplier->getQuantite(), $suppliers));
        $activeSuppliers = count(array_filter($suppliers, static fn (Cataloguefournisseur $supplier): bool => $supplier->isActiveSupplier()));

        return $this->render('back/supplier/index.html.twig', [
            'suppliers' => $suppliers,
            'filters' => $filters,
            'status_choices' => [
                Cataloguefournisseur::STATUS_ACTIVE => 'Actif',
                Cataloguefournisseur::STATUS_EMPTY => 'Vide',
            ],
            'total_suppliers' => count($suppliers),
            'total_products' => $totalProducts,
            'active_suppliers' => $activeSuppliers,
        ]);
    }

    #[Route('/suppliers/new', name: 'supplier_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, CataloguefournisseurRepository $supplierRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageSuppliers($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas creer de fournisseur.');
        }

        $supplier = new Cataloguefournisseur();
        $supplier->setQuantite(0);

        $form = $this->createForm(SupplierType::class, $supplier, [
            'submit_label' => 'Ajouter le fournisseur',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeSupplierForPersistence($supplier);

            if ($supplierRepository->existsByName((string) $supplier->getNomFr())) {
                $form->get('nomFr')->addError(new FormError('Un fournisseur avec ce nom existe deja.'));
            } else {
                $entityManager->persist($supplier);
                $entityManager->flush();

                $this->addFlash('success', 'Le fournisseur a ete creee avec succes.');

                return $this->redirectToRoute('supplier_back_manage', ['id' => $supplier->getId()]);
            }
        }

        return $this->render('back/supplier/form.html.twig', [
            'supplier' => $supplier,
            'form' => $form->createView(),
            'page_title' => 'Ajouter un fournisseur',
            'page_badge' => 'Back office',
            'page_message' => 'Renseignez les informations du fournisseur. Les controles reprennent la logique du projet Java initial.',
            'back_route' => 'back_supplier_index',
        ]);
    }

    #[Route('/back/suppliers/{id}/manage', name: 'supplier_back_manage', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function manage(int $id, CataloguefournisseurRepository $supplierRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageSuppliers($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas gerer ce fournisseur.');
        }

        $supplier = $supplierRepository->findOneWithResources($id);
        if (!$supplier instanceof Cataloguefournisseur) {
            throw $this->createNotFoundException('Fournisseur introuvable.');
        }

        return $this->render('back/supplier/manage.html.twig', [
            'supplier' => $supplier,
            'linked_resources_count' => $supplier->getResources()->count(),
            'can_edit_supplier' => true,
            'can_delete_supplier' => true,
        ]);
    }

    #[Route('/suppliers/{id}/edit', name: 'supplier_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager, CataloguefournisseurRepository $supplierRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageSuppliers($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce fournisseur.');
        }

        $supplier = $supplierRepository->findOneWithResources($id);
        if (!$supplier instanceof Cataloguefournisseur) {
            throw $this->createNotFoundException('Fournisseur introuvable.');
        }

        $form = $this->createForm(SupplierType::class, $supplier, [
            'submit_label' => 'Mettre a jour le fournisseur',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeSupplierForPersistence($supplier);

            if ($supplierRepository->existsByName((string) $supplier->getNomFr(), $supplier->getId())) {
                $form->get('nomFr')->addError(new FormError('Un fournisseur avec ce nom existe deja.'));
            } else {
                $entityManager->flush();

                $this->addFlash('success', 'Le fournisseur a ete modifie avec succes.');

                return $this->redirectToRoute('supplier_back_manage', ['id' => $supplier->getId()]);
            }
        }

        return $this->render('back/supplier/form.html.twig', [
            'supplier' => $supplier,
            'form' => $form->createView(),
            'page_title' => 'Modifier un fournisseur',
            'page_badge' => 'Back office',
            'page_message' => 'Mettez a jour les informations du fournisseur et verifiez la coherence des donnees de contact.',
            'back_route' => 'supplier_back_manage',
        ]);
    }

    #[Route('/suppliers/{id}/delete', name: 'supplier_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager, CataloguefournisseurRepository $supplierRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageSuppliers($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce fournisseur.');
        }

        $supplier = $supplierRepository->findOneWithResources($id);
        if (!$supplier instanceof Cataloguefournisseur) {
            throw $this->createNotFoundException('Fournisseur introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_supplier_' . $supplier->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite de suppression est invalide.');

            return $this->redirectToRoute('supplier_back_manage', ['id' => $supplier->getId()]);
        }

        if (!$supplier->getResources()->isEmpty()) {
            $this->addFlash('error', 'Ce fournisseur ne peut pas etre supprime tant que des ressources lui sont rattachees.');

            return $this->redirectToRoute('supplier_back_manage', ['id' => $supplier->getId()]);
        }

        $entityManager->remove($supplier);
        $entityManager->flush();

        $this->addFlash('success', 'Le fournisseur a ete supprime avec succes.');

        return $this->redirectToRoute('back_supplier_index');
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function canManageSuppliers(?User $user): bool
    {
        return $user instanceof User && in_array($user->getRoleUser(), ['admin', 'gerant'], true);
    }

    private function normalizeSupplierForPersistence(Cataloguefournisseur $supplier): void
    {
        $supplier->setNomFr(trim((string) $supplier->getNomFr()));
        $supplier->setFournisseur(trim((string) $supplier->getFournisseur()));
        $supplier->setEmailFr(trim((string) $supplier->getEmailFr()));
        $supplier->setLocalisationFr(trim((string) $supplier->getLocalisationFr()));
        $supplier->setNumTelFr(trim((string) $supplier->getNumTelFr()));

        if ($supplier->getQuantite() < 0) {
            $supplier->setQuantite(0);
        }
    }
}
