<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Event;
use App\Entity\User;
use App\Form\BookingType;
use App\Repository\BookingRepository;
use App\Repository\EventRepository;
use App\Service\BookingStatusStore;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BookingController extends AbstractController
{
    #[Route('/events/my-bookings', name: 'event_my_bookings', methods: ['GET'])]
    public function myBookings(BookingRepository $bookingRepository, BookingStatusStore $bookingStatusStore): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut consulter ses inscriptions.');
        }

        $bookings = $bookingRepository->findClientBookings($user);
        $bookingStatusStore->hydrateStatuses($bookings);

        return $this->render('front/event/bookings.html.twig', [
            'bookings' => $bookings,
        ]);
    }

    #[Route('/events/my-bookings/export-pdf', name: 'event_bookings_export_pdf', methods: ['GET'])]
    public function exportPdf(
        BookingRepository $bookingRepository,
        BookingStatusStore $bookingStatusStore,
        PdfService $pdfService
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut exporter ses inscriptions.');
        }

        $bookings = $bookingRepository->findClientBookings($user);
        $bookingStatusStore->hydrateStatuses($bookings);

        $pdf = $pdfService->generateFromTemplate('front/event/bookings_pdf.html.twig', [
            'bookings'     => $bookings,
            'user'         => $user,
            'generated_at' => new \DateTimeImmutable(),
        ]);

        $filename = sprintf('mes-inscriptions-%s.pdf', (new \DateTimeImmutable())->format('Y-m-d'));

        return $pdfService->downloadResponse($pdf, $filename);
    }

    #[Route('/events/{id}/book', name: 'event_book', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function book(
        int $id,
        Request $request,
        EventRepository $eventRepository,
        BookingRepository $bookingRepository,
        BookingStatusStore $bookingStatusStore,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut s inscrire a un evenement.');
        }

        $event = $eventRepository->findOneWithManagerAndBookings($id);
        if (!$event instanceof Event) {
            throw $this->createNotFoundException('Evenement introuvable.');
        }

        $existingBooking = $bookingRepository->findOneByUserAndEvent($user, $event);
        if ($existingBooking instanceof Booking) {
            $bookingStatusStore->hydrateStatuses([$existingBooking]);
            $this->addFlash('info', 'Vous etes deja inscrit a cet evenement. Vous pouvez modifier votre reservation.');

            return $this->redirectToRoute('event_booking_edit', ['id' => $existingBooking->getId()]);
        }

        $booking = new Booking();
        $booking->setUser($user);
        $booking->setEvent($event);
        $booking->setBookingDate(new \DateTime());
        $booking->setNumTicketBk(1);
        $booking->setTotalPrixBk(0.0);

        $form = $this->createForm(BookingType::class, $booking, [
            'submit_label' => 'Confirmer mon inscription',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $requestedTickets = max(1, $booking->getTicketCount());
            $remainingTickets = $event->getCapacity() - $bookingRepository->countReservedTicketsForEvent($event);

            if ($requestedTickets > $remainingTickets) {
                $form->get('numTicketBk')->addError(new FormError(sprintf(
                    'Il ne reste que %d ticket(s) disponible(s) pour cet evenement.',
                    max(0, $remainingTickets)
                )));
            } else {
                $this->normalizeBookingForPersistence($booking);
                $entityManager->persist($booking);
                $entityManager->flush();
                if ($booking->getId() !== null) {
                    $bookingStatusStore->initializePending($booking->getId(), $user?->getIdUser());
                    $booking->setWorkflowStatus(Booking::STATUS_PENDING);
                }

                $this->addFlash('success', 'Votre inscription a ete enregistree avec succes.');

                return $this->redirectToRoute('event_my_bookings');
            }
        }

        return $this->render('front/event/booking_form.html.twig', [
            'event' => $event,
            'booking' => $booking,
            'form' => $form->createView(),
            'page_title' => 'S inscrire a un evenement',
            'back_route' => 'event_show',
        ]);
    }

    #[Route('/events/bookings/{id}/edit', name: 'event_booking_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        int $id,
        Request $request,
        BookingRepository $bookingRepository,
        BookingStatusStore $bookingStatusStore,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut modifier son inscription.');
        }

        $booking = $bookingRepository->findOwnedBookingWithRelations($id, $user);
        if (!$booking instanceof Booking) {
            throw $this->createNotFoundException('Inscription introuvable.');
        }

        $bookingStatusStore->hydrateStatuses([$booking]);
        if (!$booking->isPending()) {
            $this->addFlash('error', 'Cette inscription ne peut plus etre modifiee car son statut est ' . strtolower($booking->getWorkflowStatusLabel()) . '.');

            return $this->redirectToRoute('event_my_bookings');
        }

        $form = $this->createForm(BookingType::class, $booking, [
            'submit_label' => 'Mettre a jour mon inscription',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = $booking->getEvent();
            if (!$event instanceof Event) {
                throw $this->createNotFoundException('Evenement introuvable.');
            }

            $requestedTickets = max(1, $booking->getTicketCount());
            $remainingTickets = $event->getCapacity() - $bookingRepository->countReservedTicketsForEvent($event, $booking);

            if ($requestedTickets > $remainingTickets) {
                $form->get('numTicketBk')->addError(new FormError(sprintf(
                    'Il ne reste que %d ticket(s) disponible(s) pour cet evenement.',
                    max(0, $remainingTickets)
                )));
            } else {
                $this->normalizeBookingForPersistence($booking);
                $entityManager->flush();

                $this->addFlash('success', 'Votre inscription a ete modifiee avec succes.');

                return $this->redirectToRoute('event_my_bookings');
            }
        }

        return $this->render('front/event/booking_form.html.twig', [
            'event' => $booking->getEvent(),
            'booking' => $booking,
            'form' => $form->createView(),
            'page_title' => 'Modifier mon inscription',
            'back_route' => 'event_my_bookings',
        ]);
    }

    #[Route('/events/bookings/{id}/delete', name: 'event_booking_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        int $id,
        Request $request,
        BookingRepository $bookingRepository,
        BookingStatusStore $bookingStatusStore,
        EntityManagerInterface $entityManager
    ): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            throw $this->createAccessDeniedException('Seul un client peut supprimer son inscription.');
        }

        $booking = $bookingRepository->findOwnedBookingWithRelations($id, $user);
        if (!$booking instanceof Booking) {
            throw $this->createNotFoundException('Inscription introuvable.');
        }

        $bookingStatusStore->hydrateStatuses([$booking]);
        if (!$booking->isPending()) {
            $this->addFlash('error', 'Seules les inscriptions en attente peuvent etre supprimees.');

            return $this->redirectToRoute('event_my_bookings');
        }

        if (!$this->isCsrfTokenValid('delete_booking_' . $booking->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite de suppression est invalide.');

            return $this->redirectToRoute('event_my_bookings');
        }

        $entityManager->remove($booking);
        $entityManager->flush();
        if ($booking->getId() !== null) {
            $bookingStatusStore->remove($booking->getId());
        }

        $this->addFlash('success', 'Votre inscription a ete supprimee avec succes.');

        return $this->redirectToRoute('event_my_bookings');
    }

    #[Route('/back/events/bookings', name: 'back_event_bookings', methods: ['GET'])]
    public function backIndex(
        Request $request,
        BookingRepository $bookingRepository,
        BookingStatusStore $bookingStatusStore
    ): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageBookings($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter toutes les inscriptions.');
        }

        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
        ];

        $bookings = $bookingRepository->findAllDetailed($filters);
        $bookingStatusStore->hydrateStatuses($bookings);

        return $this->render('back/event/bookings.html.twig', [
            'bookings' => $bookings,
            'filters' => $filters,
        ]);
    }

    #[Route('/back/events/bookings/{id}/accept', name: 'back_event_booking_accept', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function accept(
        int $id,
        Request $request,
        BookingRepository $bookingRepository,
        BookingStatusStore $bookingStatusStore
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canManageBookings($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas valider cette inscription.');
        }

        $booking = $bookingRepository->findDetailedById($id);
        if (!$booking instanceof Booking) {
            throw $this->createNotFoundException('Inscription introuvable.');
        }

        if (!$this->isCsrfTokenValid('accept_booking_' . $booking->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite d acceptation est invalide.');

            return $this->redirectToBackBookingContext($request, $booking);
        }

        $bookingStatusStore->hydrateStatuses([$booking]);
        if (!$booking->isPending()) {
            $this->addFlash('info', 'Cette inscription est deja traitee.');

            return $this->redirectToBackBookingContext($request, $booking);
        }

        $bookingStatusStore->markAccepted($booking->getId() ?? 0, $user?->getIdUser());
        $this->addFlash('success', 'L inscription a ete acceptee.');

        return $this->redirectToBackBookingContext($request, $booking);
    }

    #[Route('/back/events/bookings/{id}/refuse', name: 'back_event_booking_refuse', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function refuse(
        int $id,
        Request $request,
        BookingRepository $bookingRepository,
        BookingStatusStore $bookingStatusStore
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canManageBookings($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas refuser cette inscription.');
        }

        $booking = $bookingRepository->findDetailedById($id);
        if (!$booking instanceof Booking) {
            throw $this->createNotFoundException('Inscription introuvable.');
        }

        if (!$this->isCsrfTokenValid('refuse_booking_' . $booking->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite de refus est invalide.');

            return $this->redirectToBackBookingContext($request, $booking);
        }

        $bookingStatusStore->hydrateStatuses([$booking]);
        if (!$booking->isPending()) {
            $this->addFlash('info', 'Cette inscription est deja traitee.');

            return $this->redirectToBackBookingContext($request, $booking);
        }

        $bookingStatusStore->markRefused($booking->getId() ?? 0, $user?->getIdUser());
        $this->addFlash('success', 'L inscription a ete refusee.');

        return $this->redirectToBackBookingContext($request, $booking);
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

    private function canManageBookings(?User $user): bool
    {
        return $user instanceof User && in_array($user->getRoleUser(), ['admin', 'gerant'], true);
    }

    private function normalizeBookingForPersistence(Booking $booking): void
    {
        if ($booking->getBookingDate() === null) {
            $booking->setBookingDate(new \DateTime());
        }

        if ($booking->getTicketCount() < 1) {
            $booking->setNumTicketBk(1);
        }

        $booking->setTotalPrixBk(0.0);
    }

    private function redirectToBackBookingContext(Request $request, Booking $booking): Response
    {
        $referer = (string) $request->headers->get('referer', '');
        $eventId = $booking->getEvent()?->getId();

        if ($eventId !== null && str_contains($referer, '/back/events/' . $eventId . '/manage')) {
            return $this->redirectToRoute('event_back_manage', ['id' => $eventId]);
        }

        return $this->redirectToRoute('back_event_bookings');
    }
}
