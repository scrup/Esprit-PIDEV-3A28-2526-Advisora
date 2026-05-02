<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Event;
use App\Entity\User;
use App\Form\EventType;
use App\Repository\BookingRepository;
use App\Repository\EventRepository;
use App\Service\BookingStatusStore;
use App\Service\EventRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EventController extends AbstractController
{
    #[Route('/events', name: 'event_index', methods: ['GET'])]
    public function index(
        Request $request,
        EventRepository $eventRepository,
        BookingRepository $bookingRepository,
        BookingStatusStore $bookingStatusStore,
        EventRecommendationService $recommendationService
    ): Response {
        $user = $this->getCurrentUser();
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'location' => trim((string) $request->query->get('location', '')),
        ];

        $events = $eventRepository->findFrontEvents($filters);
        $existingBookings = [];

        if ($this->isClient($user)) {
            $clientBookings = $bookingRepository->findClientBookings($user);
            $bookingStatusStore->hydrateStatuses($clientBookings);

            foreach ($clientBookings as $booking) {
                if ($booking->getEvent()?->getId() !== null) {
                    $existingBookings[$booking->getEvent()->getId()] = $booking;
                }
            }
        }

        $recommendations = [];
        if ($user && $this->isClient($user)) {
            $recommendations = $recommendationService->getRecommendationsForUser($user, 5);
        }

        return $this->render('front/event/index.html.twig', [
            'events' => $events,
            'filters' => $filters,
            'can_manage_events' => $this->canManageEvents($user),
            'can_book_events' => $this->isClient($user),
            'existing_bookings' => $existingBookings,
            'recommendations' => $recommendations,
        ]);
    }

    #[Route('/events/calendar-data', name: 'event_calendar_data', methods: ['GET'])]
    public function calendarData(EventRepository $eventRepository): Response
    {
        $events = $eventRepository->findAll();
        $data = [];

        foreach ($events as $event) {
            $data[] = [
                'id' => $event->getIdEv(),
                'title' => $event->getTitleEv(),
                'start' => $event->getStartDateEv()->format('Y-m-d\TH:i:s'),
                'end' => $event->getEndDateEv()->format('Y-m-d\TH:i:s'),
                'url' => $this->generateUrl('event_show', ['id' => $event->getIdEv()]),
            ];
        }

        return $this->json($data);
    }

    #[Route('/events/{id}', name: 'event_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        int $id,
        EventRepository $eventRepository,
        BookingRepository $bookingRepository,
        BookingStatusStore $bookingStatusStore
    ): Response {
        $event = $eventRepository->findOneWithManagerAndBookings($id);
        if (!$event instanceof Event) {
            throw $this->createNotFoundException('Evenement introuvable.');
        }

        $user = $this->getCurrentUser();
        $existingBooking = null;

        if ($this->isClient($user)) {
            $existingBooking = $bookingRepository->findOneByUserAndEvent($user, $event);
            if ($existingBooking instanceof Booking) {
                $bookingStatusStore->hydrateStatuses([$existingBooking]);
            }
        }

        return $this->render('front/event/show.html.twig', [
            'event' => $event,
            'can_manage_events' => $this->canManageEvents($user),
            'can_book_events' => $this->isClient($user),
            'existing_booking' => $existingBooking,
        ]);
    }

    #[Route('/back/events', name: 'back_event_index', methods: ['GET'])]
    public function backIndex(Request $request, EventRepository $eventRepository): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageEvents($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter la gestion des evenements.');
        }

        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'manager' => trim((string) $request->query->get('manager', '')),
        ];

        return $this->render('back/event/index.html.twig', [
            'events' => $eventRepository->findBackOfficeEvents($filters),
            'filters' => $filters,
        ]);
    }

    #[Route('/events/new', name: 'event_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageEvents($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas creer d evenement.');
        }

        $event = new Event();
        $event->setUser($user);
        $event->setStartDateEv(new \DateTimeImmutable('+1 day 09:00'));
        $event->setEndDateEv(new \DateTimeImmutable('+1 day 17:00'));
        $event->setCapaciteEvnt(50);

        $form = $this->createForm(EventType::class, $event, [
            'submit_label' => 'Ajouter l evenement',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeEventForPersistence($event, $user);
            $entityManager->persist($event);
            $entityManager->flush();

            $this->addFlash('success', 'L evenement a ete cree avec succes.');

            return $this->redirectToRoute('event_back_manage', ['id' => $event->getId()]);
        }

        return $this->render('back/event/form.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
            'page_title' => 'Ajouter un evenement',
            'page_badge' => 'Back office',
            'page_message' => 'Le gerant createur est associe automatiquement a l utilisateur connecte.',
            'back_route' => 'back_event_index',
        ]);
    }

    #[Route('/back/events/{id}/manage', name: 'event_back_manage', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function backManage(int $id, EventRepository $eventRepository, BookingStatusStore $bookingStatusStore): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageEvents($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas gerer cet evenement.');
        }

        $event = $eventRepository->findOneWithManagerAndBookings($id);
        if (!$event instanceof Event) {
            throw $this->createNotFoundException('Evenement introuvable.');
        }

        $eventBookings = $this->sortBookingsByDate($event->getBookings()->toArray());
        $bookingStatusStore->hydrateStatuses($eventBookings);

        return $this->render('back/event/manage.html.twig', [
            'event' => $event,
            'event_bookings' => $eventBookings,
        ]);
    }

    #[Route('/events/{id}/edit', name: 'event_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, EventRepository $eventRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->canManageEvents($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cet evenement.');
        }

        $event = $eventRepository->findOneWithManagerAndBookings($id);
        if (!$event instanceof Event) {
            throw $this->createNotFoundException('Evenement introuvable.');
        }

        $form = $this->createForm(EventType::class, $event, [
            'submit_label' => 'Mettre a jour l evenement',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeEventForPersistence($event, $event->getUser() instanceof User ? $event->getUser() : $user);
            $entityManager->flush();

            $this->addFlash('success', 'L evenement a ete modifie avec succes.');

            return $this->redirectToRoute('event_back_manage', ['id' => $event->getId()]);
        }

        return $this->render('back/event/form.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
            'page_title' => 'Modifier un evenement',
            'page_badge' => 'Back office',
            'page_message' => 'Mettez a jour les informations publiees et la capacite disponible.',
            'back_route' => 'event_back_manage',
        ]);
    }

    #[Route('/events/{id}/delete', name: 'event_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        int $id,
        Request $request,
        EventRepository $eventRepository,
        BookingRepository $bookingRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canManageEvents($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cet evenement.');
        }

        $event = $eventRepository->findOneWithManagerAndBookings($id);
        if (!$event instanceof Event) {
            throw $this->createNotFoundException('Evenement introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_event_' . $event->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de securite de suppression est invalide.');

            return $this->redirectToRoute('event_back_manage', ['id' => $event->getId()]);
        }

        if ($bookingRepository->countEventBookings($event) > 0) {
            $this->addFlash('error', 'Cet evenement ne peut pas etre supprime tant qu il possede des inscriptions.');

            return $this->redirectToRoute('event_back_manage', ['id' => $event->getId()]);
        }

        $entityManager->remove($event);
        $entityManager->flush();

        $this->addFlash('success', 'L evenement a ete supprime avec succes.');

        return $this->redirectToRoute('back_event_index');
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function canManageEvents(?User $user): bool
    {
        return $user instanceof User && in_array($user->getRoleUser(), ['admin', 'gerant'], true);
    }

    private function isClient(?User $user): bool
    {
        return $user instanceof User && $user->getRoleUser() === 'client';
    }

    private function normalizeEventForPersistence(Event $event, ?User $manager): void
    {
        $event->setTitleEv(trim((string) $event->getTitleEv()));
        $event->setOrganisateurName(trim((string) $event->getOrganisateurName()));
        $event->setLocalisationEv(trim((string) $event->getLocalisationEv()));

        if ($event->getDescriptionEv() !== null) {
            $description = trim($event->getDescriptionEv());
            $event->setDescriptionEv($description === '' ? null : $description);
        }

        if ($event->getCapaciteEvnt() < 1) {
            $event->setCapaciteEvnt(1);
        }

        if ($manager instanceof User) {
            $event->setUser($manager);
        }
    }

    /**
     * @param array<int, Booking> $bookings
     *
     * @return array<int, Booking>
     */
    private function sortBookingsByDate(array $bookings): array
    {
        usort($bookings, static function (Booking $left, Booking $right): int {
            $leftDate = $left->getBookingDate()->getTimestamp();
            $rightDate = $right->getBookingDate()->getTimestamp();

            return $rightDate <=> $leftDate;
        });

        return $bookings;
    }
}
