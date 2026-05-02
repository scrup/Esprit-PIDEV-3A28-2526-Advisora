<?php

namespace App\Service;

use App\Entity\Event;
use App\Repository\EventRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class BasketService
{
    private RequestStack $requestStack;
    private EventRepository $eventRepository;

    public function __construct(RequestStack $requestStack, EventRepository $eventRepository)
    {
        $this->requestStack = $requestStack;
        $this->eventRepository = $eventRepository;
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getSession();
    }

    public function addToBasket(Event $event, int $quantity): void
    {
        $session = $this->getSession();

        /** @var array<int, int> $basket */
        $basket = $session->get('basket', []);

        $eventId = $event->getIdEv();

        if ($eventId === null) {
            return;
        }

        if (isset($basket[$eventId])) {
            $basket[$eventId] += $quantity;
        } else {
            $basket[$eventId] = $quantity;
        }

        $session->set('basket', $basket);
    }

    /**
     * @return array<int, array{event: Event, quantity: int, subtotal: float}>
     */
    public function getBasket(): array
    {
        /** @var array<int, int> $basket */
        $basket = $this->getSession()->get('basket', []);

        $items = [];

        foreach ($basket as $eventId => $quantity) {
            $event = $this->eventRepository->find((int) $eventId);

            if ($event instanceof Event) {
                $items[] = [
                    'event' => $event,
                    'quantity' => (int) $quantity,
                    'subtotal' => (float) ($event->getPrice() ?? 0) * (int) $quantity,
                ];
            }
        }

        return $items;
    }

    public function removeFromBasket(int $eventId): void
    {
        $session = $this->getSession();

        /** @var array<int, int> $basket */
        $basket = $session->get('basket', []);

        unset($basket[$eventId]);

        $session->set('basket', $basket);
    }

    public function updateQuantity(int $eventId, int $quantity): void
    {
        $session = $this->getSession();

        /** @var array<int, int> $basket */
        $basket = $session->get('basket', []);

        if ($quantity <= 0) {
            unset($basket[$eventId]);
        } else {
            $basket[$eventId] = $quantity;
        }

        $session->set('basket', $basket);
    }

    public function clearBasket(): void
    {
        $this->getSession()->remove('basket');
    }

    public function getTotal(): float
    {
        $total = 0.0;

        foreach ($this->getBasket() as $item) {
            $total += $item['subtotal'];
        }

        return $total;
    }

    public function getCount(): int
    {
        /** @var array<int, int> $basket */
        $basket = $this->getSession()->get('basket', []);

        return (int) array_sum($basket);
    }
}