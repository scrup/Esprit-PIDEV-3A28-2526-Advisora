<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\BookingRepository;

class EventRecommendationService
{
    private EventRepository $eventRepository;
    private BookingRepository $bookingRepository;

    public function __construct(
        EventRepository $eventRepository,
        BookingRepository $bookingRepository
    ) {
        $this->eventRepository = $eventRepository;
        $this->bookingRepository = $bookingRepository;
    }

    public function getRecommendationsForUser(User $user, int $limit = 5): array
    {
        // Get user's past bookings
        $bookings = $this->bookingRepository->findBy(['user' => $user]);
        $bookedEventIds = array_map(fn($booking) => $booking->getEvent()?->getIdEv() ?? null, $bookings);
        $bookedEventIds = array_filter($bookedEventIds);

        // Get user's interests
        $interests = $user->getInterests() ?? [];

        // Get all events not booked by user
        $allEvents = $this->eventRepository->findAll();
        $availableEvents = array_filter($allEvents, fn($event) => !in_array($event->getIdEv(), $bookedEventIds));

        // If no interests or bookings, return random events
        if (empty($interests) && empty($bookings)) {
            $availableEvents = array_values($availableEvents);
            shuffle($availableEvents);
            return array_slice($availableEvents, 0, $limit);
        }

        // Score events based on interest matching
        $scored = $this->scoreEventsByInterests($availableEvents, $interests);

        return array_slice($scored, 0, $limit);
    }

    private function scoreEventsByInterests(array $events, array $interests): array
    {
        $scored = [];

        foreach ($events as $event) {
            $score = 0;
            $eventText = strtolower(
                ($event->getTitleEv() ?? '') . ' ' .
                ($event->getDescriptionEv() ?? '') . ' ' .
                ($event->getLocalisationEv() ?? '')
            );

            // Score based on interest matches
            foreach ($interests as $interest) {
                $interestLower = strtolower((string) $interest);
                if (strpos($eventText, $interestLower) !== false) {
                    $score += 10;
                }
            }

            // Slight boost for upcoming events
            if ($event->getStartDateEv() && $event->getStartDateEv() > new \DateTime()) {
                $score += 1;
            }

            $scored[] = ['event' => $event, 'score' => $score];
        }

        // Sort by score descending
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        // Return only events
        return array_map(fn($item) => $item['event'], $scored);
    }
}

