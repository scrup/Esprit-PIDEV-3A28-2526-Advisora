<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\EventRepository;

class EventRecommendationService
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly BookingRepository $bookingRepository
    ) {
    }

    public function getRecommendationsForUser(User $user, int $limit = 5): array
    {
        $bookings = $this->bookingRepository->findClientBookings($user);
        $bookedEventIds = array_values(array_filter(array_map(
            static fn ($booking) => $booking->getEvent()?->getIdEv(),
            $bookings
        )));

        $interestKeywords = $this->extractInterestKeywords($user, $bookings);
        $availableEvents = array_values(array_filter(
            $this->eventRepository->findFrontEvents(),
            static fn ($event) => !in_array($event->getIdEv(), $bookedEventIds, true)
        ));

        if ($availableEvents === []) {
            return [];
        }

        if ($interestKeywords === []) {
            usort($availableEvents, static fn ($left, $right) => ($left->getStartDateEv()?->getTimestamp() ?? PHP_INT_MAX) <=> ($right->getStartDateEv()?->getTimestamp() ?? PHP_INT_MAX));

            return array_slice($availableEvents, 0, $limit);
        }

        $scored = [];
        foreach ($availableEvents as $event) {
            $score = $this->scoreEvent($event, $interestKeywords);
            if ($event->getStartDateEv() instanceof \DateTimeInterface && $event->getStartDateEv() > new \DateTimeImmutable()) {
                $score += 1;
            }

            $scored[] = ['event' => $event, 'score' => $score];
        }

        usort($scored, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        return array_slice(array_map(static fn (array $item) => $item['event'], $scored), 0, $limit);
    }

    private function extractInterestKeywords(User $user, array $bookings): array
    {
        $keywords = [];

        $expertise = trim((string) $user->getExpertiseAreaUser());
        if ($expertise !== '') {
            $keywords = array_merge($keywords, preg_split('/[\s,;|]+/', mb_strtolower($expertise)) ?: []);
        }

        foreach ($bookings as $booking) {
            $event = $booking->getEvent();
            if ($event === null) {
                continue;
            }

            $keywords = array_merge(
                $keywords,
                preg_split('/[\s,;|]+/', mb_strtolower(trim((string) $event->getTitleEv()))) ?: [],
                preg_split('/[\s,;|]+/', mb_strtolower(trim((string) $event->getLocalisationEv()))) ?: []
            );
        }

        $keywords = array_values(array_unique(array_filter($keywords, static fn (string $keyword): bool => mb_strlen($keyword) >= 3)));

        return $keywords;
    }

    private function scoreEvent($event, array $interestKeywords): int
    {
        $score = 0;
        $haystack = mb_strtolower(trim(implode(' ', [
            (string) $event->getTitleEv(),
            (string) $event->getDescriptionEv(),
            (string) $event->getLocalisationEv(),
            (string) $event->getOrganisateurName(),
        ])));

        foreach ($interestKeywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                $score += 10;
            }
        }

        return $score;
    }
}

