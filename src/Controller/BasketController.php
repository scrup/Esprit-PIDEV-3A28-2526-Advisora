<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Event;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\EventRepository;
use App\Service\BasketService;
use App\Service\BookingStatusStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BasketController extends AbstractController
{
    // ── Add to cart ──────────────────────────────────────────────────────────

    #[Route('/events/{id}/add-to-cart', name: 'event_add_to_cart', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addToCart(
        int $id,
        Request $request,
        EventRepository $eventRepository,
        BookingRepository $bookingRepository,
        BasketService $basket
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            $this->addFlash('error', 'Vous devez etre connecte en tant que client pour ajouter un evenement au panier.');

            return $this->redirectToRoute('event_show', ['id' => $id]);
        }

        $event = $eventRepository->find($id);
        if (!$event instanceof Event) {
            throw $this->createNotFoundException('Evenement introuvable.');
        }

        // Already has a confirmed booking
        $existing = $bookingRepository->findOneByUserAndEvent($user, $event);
        if ($existing instanceof Booking) {
            $this->addFlash('info', 'Vous etes deja inscrit a cet evenement.');

            return $this->redirectToRoute('event_show', ['id' => $id]);
        }

        $qty = max(1, (int) $request->request->get('quantity', 1));

        // Cap at remaining tickets
        $remaining = $event->getRemainingTickets();
        if ($remaining <= 0) {
            $this->addFlash('error', 'Cet evenement est complet.');

            return $this->redirectToRoute('event_show', ['id' => $id]);
        }

        $qty = min($qty, $remaining);
        $basket->addToBasket($event, $qty);

        $this->addFlash('success', sprintf('"%s" ajoute au panier (%d ticket%s).', $event->getTitle(), $qty, $qty > 1 ? 's' : ''));

        $redirect = $request->request->get('redirect', 'events');
        if ($redirect === 'cart') {
            return $this->redirectToRoute('event_cart');
        }

        return $this->redirectToRoute('event_index');
    }

    // ── Cart view ─────────────────────────────────────────────────────────────

    #[Route('/events/cart', name: 'event_cart', methods: ['GET'])]
    public function cart(BasketService $basket): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            return $this->redirectToRoute('app_login');
        }

        $items = $basket->getBasket();
        $total = $basket->getTotal();

        return $this->render('front/event/cart.html.twig', [
            'items' => $items,
            'total' => $total,
            'paypal_client_id' => $_ENV['PAYPAL_CLIENT_ID'] ?? '',
        ]);
    }

    // ── Update quantity ───────────────────────────────────────────────────────

    #[Route('/events/cart/update/{id}', name: 'event_cart_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateQuantity(
        int $id,
        Request $request,
        EventRepository $eventRepository,
        BasketService $basket
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            return $this->redirectToRoute('app_login');
        }

        $event = $eventRepository->find($id);
        if ($event instanceof Event) {
            $qty = max(0, (int) $request->request->get('quantity', 1));
            if ($qty > 0) {
                $qty = min($qty, $event->getRemainingTickets());
            }

            $basket->updateQuantity($id, $qty);
        }

        return $this->redirectToRoute('event_cart');
    }

    // ── Remove from cart ──────────────────────────────────────────────────────

    #[Route('/events/cart/remove/{id}', name: 'event_cart_remove', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function removeFromCart(int $id, BasketService $basket): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            return $this->redirectToRoute('app_login');
        }

        $basket->removeFromBasket($id);
        $this->addFlash('success', 'Article retire du panier.');

        return $this->redirectToRoute('event_cart');
    }

    // ── PayPal: create order (called by JS SDK) ───────────────────────────────

    #[Route('/events/cart/paypal/create-order', name: 'event_paypal_create_order', methods: ['POST'])]
    public function paypalCreateOrder(BasketService $basket): JsonResponse
    {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $items = $basket->getBasket();
        if (empty($items)) {
            return $this->json(['error' => 'Panier vide'], 400);
        }

        $total = $basket->getTotal();
        if ($total <= 0) {
            // Free events — skip PayPal, confirm directly
            return $this->json(['free' => true]);
        }

        $clientId = $_ENV['PAYPAL_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? '';
        $baseUrl = ($_ENV['PAYPAL_MODE'] ?? 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        // Get access token
        $tokenResponse = $this->paypalRequest('POST', $baseUrl . '/v1/oauth2/token', $clientId, $clientSecret, [
            'grant_type' => 'client_credentials',
        ], 'form');

        if (!isset($tokenResponse['access_token'])) {
            return $this->json(['error' => 'PayPal auth failed'], 500);
        }

        $accessToken = (string) $tokenResponse['access_token'];

        // Build order items
        $orderItems = [];
        foreach ($items as $item) {
            $unitPrice = number_format((float) ($item['event']->getPrice() ?? 0), 2, '.', '');
            $orderItems[] = [
                'name' => $item['event']->getTitle() ?? 'Ticket',
                'quantity' => (string) $item['quantity'],
                'unit_amount' => [
                    'currency_code' => 'USD',
                    'value' => $unitPrice,
                ],
            ];
        }

        $orderPayload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => number_format($total, 2, '.', ''),
                        'breakdown' => [
                            'item_total' => [
                                'currency_code' => 'USD',
                                'value' => number_format($total, 2, '.', ''),
                            ],
                        ],
                    ],
                    'items' => $orderItems,
                ],
            ],
        ];

        $order = $this->paypalRequest('POST', $baseUrl . '/v2/checkout/orders', $clientId, $clientSecret, $orderPayload, 'json', $accessToken);

        if (!isset($order['id'])) {
            return $this->json(['error' => 'Could not create PayPal order'], 500);
        }

        return $this->json(['orderID' => $order['id']]);
    }

    // ── PayPal: capture order & confirm bookings ──────────────────────────────

    #[Route('/events/cart/paypal/capture-order', name: 'event_paypal_capture_order', methods: ['POST'])]
    public function paypalCaptureOrder(
        Request $request,
        BasketService $basket,
        BookingRepository $bookingRepository,
        EntityManagerInterface $entityManager,
        BookingStatusStore $bookingStatusStore
    ): JsonResponse {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = [];
        }

        $orderId = $data['orderID'] ?? '';

        if (empty($orderId)) {
            return $this->json(['error' => 'Missing orderID'], 400);
        }

        $clientId = $_ENV['PAYPAL_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? '';
        $baseUrl = ($_ENV['PAYPAL_MODE'] ?? 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        // Get access token
        $tokenResponse = $this->paypalRequest('POST', $baseUrl . '/v1/oauth2/token', $clientId, $clientSecret, [
            'grant_type' => 'client_credentials',
        ], 'form');

        if (!isset($tokenResponse['access_token'])) {
            return $this->json(['error' => 'PayPal auth failed'], 500);
        }

        $accessToken = (string) $tokenResponse['access_token'];

        // Capture the order
        $capture = $this->paypalRequest(
            'POST',
            $baseUrl . '/v2/checkout/orders/' . $orderId . '/capture',
            $clientId,
            $clientSecret,
            [],
            'json',
            $accessToken
        );

        if (($capture['status'] ?? '') !== 'COMPLETED') {
            return $this->json(['error' => 'Payment not completed', 'details' => $capture], 400);
        }

        // Create bookings
        $this->confirmBookingsFromBasket($basket, $bookingRepository, $entityManager, $bookingStatusStore, $user);

        return $this->json(['success' => true, 'redirect' => $this->generateUrl('event_cart_confirmed')]);
    }

    // ── Confirm free events (no payment needed) ───────────────────────────────

    #[Route('/events/cart/confirm-free', name: 'event_cart_confirm_free', methods: ['POST'])]
    public function confirmFree(
        BasketService $basket,
        BookingRepository $bookingRepository,
        EntityManagerInterface $entityManager,
        BookingStatusStore $bookingStatusStore
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            return $this->redirectToRoute('app_login');
        }

        $items = $basket->getBasket();
        if (empty($items)) {
            return $this->redirectToRoute('event_cart');
        }

        $this->confirmBookingsFromBasket($basket, $bookingRepository, $entityManager, $bookingStatusStore, $user);

        return $this->redirectToRoute('event_cart_confirmed');
    }

    // ── Confirmation page ─────────────────────────────────────────────────────

    #[Route('/events/cart/confirmed', name: 'event_cart_confirmed', methods: ['GET'])]
    public function confirmed(): Response
    {
        $user = $this->getCurrentUser();
        if (!$this->isClient($user)) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/event/cart_confirmed.html.twig');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function confirmBookingsFromBasket(
        BasketService $basket,
        BookingRepository $bookingRepository,
        EntityManagerInterface $entityManager,
        BookingStatusStore $bookingStatusStore,
        User $user
    ): void {
        $items = $basket->getBasket();

        foreach ($items as $item) {
            $event = $item['event'];
            $qty = (int) $item['quantity'];

            // Skip if already booked
            if ($bookingRepository->findOneByUserAndEvent($user, $event) instanceof Booking) {
                continue;
            }

            $booking = new Booking();
            $booking->setUser($user);
            $booking->setEvent($event);
            $booking->setBookingDate(new \DateTime());
            $booking->setNumTicketBk($qty);
            $booking->setTotalPrixBk((float) $item['subtotal']);

            $entityManager->persist($booking);
            $entityManager->flush();

            if ($booking->getId() !== null) {
                $bookingStatusStore->initializePending($booking->getId(), $user->getIdUser());
            }
        }

        $basket->clearBasket();
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function paypalRequest(
        string $method,
        string $url,
        string $clientId,
        string $clientSecret,
        array $body,
        string $bodyType = 'json',
        ?string $accessToken = null
    ): array {
        $headers = ['Accept: application/json'];

        if ($accessToken !== null) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        } else {
            $headers[] = 'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret);
        }

        $method = strtoupper(trim($method));
        if ($method === '') {
            return [];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return [];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($bodyType === 'form') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
        } else {
            $encodedBody = json_encode($body);
            if ($encodedBody === false) {
                curl_close($ch);

                return [];
            }

            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedBody);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!is_string($response)) {
            return [];
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : [];
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
}