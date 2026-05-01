<?php
namespace App\Controller;
use App\Entity\User;
use App\Exception\InsufficientWalletException;
use App\Form\ShopCheckoutType;
use App\Service\ClientMarketplaceService;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
/**
 * Helpers transverses du module boutique.
 *
 * On centralise ici les verifications CLIENT, la gestion de session, la
 * construction des formulaires checkout et les redirections communes.
 */
trait ShopSupportTrait
{
    private function requireClient(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Connectez-vous pour acceder au mini-shop.');
        }

        // La regle metier impose un acces strictement reserve au role CLIENT.
        if (strtolower((string) $user->getRoleUser()) !== 'client') {
            throw $this->createAccessDeniedException('Le mini-shop est reserve au role CLIENT.');
        }

        return $user;
    }

    /**
     * La page est unique, donc apres chaque action on revient au meme ecran.
     */
    private function redirectToShop(Request $request, ?string $defaultFragment = null): RedirectResponse
    {
        $supplierId = $request->request->get('supplier_id', $request->query->get('supplier_id'));
        $search = trim((string) $request->request->get('q', $request->query->get('q', '')));
        $marketSearch = trim((string) $request->request->get('market_q', $request->query->get('market_q', '')));
        $marketSort = strtolower(trim((string) $request->request->get('c2c_sort', $request->query->get('c2c_sort', ''))));
        $manageMode = (string) $request->request->get('manage', (string) $request->query->get('manage', ''));
        $ordersMode = (string) $request->request->get('orders', (string) $request->query->get('orders', ''));
        $walletMode = (string) $request->request->get('wallet', (string) $request->query->get('wallet', ''));
        $parameters = [];
        $fragment = $request->request->get('_redirect_fragment', $request->query->get('_redirect_fragment', $defaultFragment));

        if (is_string($fragment) && $fragment === 'shop-topups') {
            return $this->redirectToRoute('app_shop_wallet');
        }

        if (is_string($fragment) && $fragment === 'shop-wallet') {
            return $this->redirectToRoute('app_shop_wallet');
        }

        if ($supplierId !== null && $supplierId !== '' && ctype_digit((string) $supplierId)) {
            $parameters['supplier_id'] = (int) $supplierId;
        }

        if ($search !== '') {
            $parameters['q'] = $search;
        }

        if ($marketSearch !== '') {
            $parameters['market_q'] = $marketSearch;
        }

        if (in_array($marketSort, ['recent', 'price_asc', 'price_desc', 'qty_desc'], true) && $marketSort !== 'recent') {
            $parameters['c2c_sort'] = $marketSort;
        }

        if (in_array(strtolower($manageMode), ['1', 'true', 'yes'], true)) {
            $parameters['manage'] = 1;
        }

        if (in_array(strtolower($ordersMode), ['1', 'true', 'yes'], true)) {
            $parameters['orders'] = 1;
        }

        if (in_array(strtolower($walletMode), ['1', 'true', 'yes'], true)) {
            return $this->redirectToRoute('app_shop_wallet');
        }

        if (is_string($fragment) && $fragment === 'shop-orders') {
            $parameters['orders'] = 1;
        }

        $url = $this->generateUrl('app_shop', $parameters);

        if (is_string($fragment) && preg_match('/^[A-Za-z0-9\-_]+$/', $fragment) === 1) {
            $url .= '#' . $fragment;
        }

        return new RedirectResponse($url);
    }

    private function redirectAfterTopupAction(Request $request, ?string $defaultFragment = null): RedirectResponse
    {
        $redirectPage = strtolower((string) $request->request->get('_redirect_page', $request->query->get('_redirect_page', '')));
        if ($redirectPage === 'topups') {
            return $this->redirectToRoute('app_shop_wallet');
        }

        return $this->redirectToShop($request, $defaultFragment);
    }

    private function redirectAfterWalletAction(Request $request, ?string $defaultFragment = null): RedirectResponse
    {
        $redirectPage = strtolower((string) $request->request->get('_redirect_page', $request->query->get('_redirect_page', '')));
        if ($redirectPage === 'wallet') {
            return $this->redirectToRoute('app_shop_wallet');
        }

        return $this->redirectToShop($request, $defaultFragment);
    }

    private function redirectAfterPublishAction(Request $request, ?string $defaultFragment = null): RedirectResponse
    {
        $redirectPage = strtolower((string) $request->request->get('_redirect_page', $request->query->get('_redirect_page', '')));
        if ($redirectPage === 'publish') {
            return $this->redirectToRoute('app_shop_publish');
        }

        if ($redirectPage === 'my_listings') {
            return $this->redirectToRoute('app_shop_my_listings');
        }

        return $this->redirectToShop($request, $defaultFragment);
    }

    private function redirectAfterReviewAction(Request $request, ?string $defaultFragment = null): RedirectResponse
    {
        $redirectPage = strtolower((string) $request->request->get('_redirect_page', $request->query->get('_redirect_page', '')));
        $listingId = (int) $request->request->get('listing_id', $request->query->get('listing_id', 0));

        if ($redirectPage === 'product' && $listingId > 0) {
            return $this->redirectToRoute('app_shop_market_product', ['listingId' => $listingId]);
        }

        if ($redirectPage === 'checkout' && $listingId > 0) {
            $checkoutQuantity = (int) $request->request->get('checkout_quantity', $request->query->get('checkout_quantity', 1));
            $parameters = ['listingId' => $listingId];
            if ($checkoutQuantity > 0) {
                $parameters['quantity'] = $checkoutQuantity;
            }

            return $this->redirectToRoute('app_shop_market_checkout', $parameters);
        }

        return $this->redirectToShop($request, $defaultFragment);
    }

    private function redirectAfterBuyError(Request $request, int $listingId, int $quantity): RedirectResponse
    {
        $redirectPage = strtolower((string) $request->request->get('_redirect_page', $request->query->get('_redirect_page', '')));
        if ($redirectPage === 'checkout_cart') {
            return $this->redirectToRoute('app_shop_checkout');
        }

        if ($redirectPage === 'checkout' && $listingId > 0) {
            $parameters = ['listingId' => $listingId];
            if ($quantity > 0) {
                $parameters['quantity'] = $quantity;
            }

            return $this->redirectToRoute('app_shop_market_checkout', $parameters);
        }

        return $this->redirectToShop($request, 'shop-wallet');
    }

    /**
     * Redirige l acheteur vers la fiche produit, directement sur la zone des avis clients.
     */
    private function redirectToListingReviewPage(int $listingId): RedirectResponse
    {
        if ($listingId <= 0) {
            return $this->redirectToRoute('app_shop');
        }

        return new RedirectResponse(
            $this->generateUrl('app_shop_market_product', ['listingId' => $listingId]) . '#shop-reviews'
        );
    }

    /**
     * @param array<string, mixed> $result
     */
    private function pushCheckoutFeedback(array $result, string $prefix = 'Checkout confirme.'): void
    {
        $this->addFlash('success', sprintf(
            '%s Commande #%d creee, livraison #%d creee.',
            $prefix,
            (int) ($result['idOrder'] ?? 0),
            (int) ($result['idDelivery'] ?? 0)
        ));

        $deliverySync = is_array($result['delivery_sync'] ?? null) ? $result['delivery_sync'] : [];
        $synced = (bool) ($deliverySync['success'] ?? false);
        if ($synced) {
            $tracking = (string) ($deliverySync['tracking_code'] ?? '');
            $message = (string) ($deliverySync['message'] ?? 'Livraison envoyee vers Fiabilo.');
            $this->addFlash('success', sprintf(
                'Fiabilo accepte la livraison.%s %s',
                $tracking !== '' ? ' Tracking: ' . $tracking . '.' : '',
                $message
            ));
            $this->addFlash('success', 'Merci pour votre commande.');

            return;
        }

        $errorMessage = (string) ($deliverySync['message'] ?? 'Erreur de synchronisation Fiabilo.');
        $this->addFlash('error', 'Commande creee, livraison reste EN_PREPARATION: ' . $errorMessage);
        $this->addFlash('success', 'Merci pour votre commande.');
    }

    /**
     * @param array<string, mixed> $pending
     */
    private function isPendingCartPayload(array $pending): bool
    {
        if (strtolower((string) ($pending['mode'] ?? '')) === 'cart') {
            return true;
        }

        return is_array($pending['cart_items'] ?? null);
    }

    /**
     * @param array<int, int> $checkoutCart
     * @param array<string, mixed> $delivery
     *
     * @return array<string, mixed>
     */
    private function buildPendingCartPayload(
        ClientMarketplaceService $marketplaceService,
        array $checkoutCart,
        ?int $projectId,
        array $delivery,
        float $missingCoins,
        float $requiredCoins,
        float $currentBalance,
        string $topupProvider
    ): array {
        $normalizedCart = $this->normalizeCheckoutCart($checkoutCart);
        $normalizedMissing = round(max(0.0, $missingCoins), 3);

        return [
            'mode' => 'cart',
            'cart_items' => $normalizedCart,
            'cart_lines' => count($normalizedCart),
            'cart_units' => array_sum($normalizedCart),
            'project_id' => $projectId,
            'delivery' => $delivery,
            'missing_coins' => $normalizedMissing,
            'required_coins' => round(max(0.0, $requiredCoins), 3),
            'current_balance' => round(max(0.0, $currentBalance), 3),
            'coin_rate' => $marketplaceService->getCoinRate(),
            'missing_money' => $marketplaceService->coinsToMoney($normalizedMissing),
            'topup_provider' => strtoupper($topupProvider),
        ];
    }

    /**
     * @param array<int, int> $checkoutCart
     * @param array<string, mixed> $delivery
     *
     * @return array{
     *     results: array<int, array<string, mixed>>,
     *     remaining_cart: array<int, int>,
     *     insufficient: InsufficientWalletException|null,
     *     error: \Throwable|null
     * }
     */
    private function executeCheckoutCartBatch(
        User $client,
        ClientMarketplaceService $marketplaceService,
        array $checkoutCart,
        ?int $projectId,
        array $delivery
    ): array {
        $remainingCart = $this->normalizeCheckoutCart($checkoutCart);
        $results = [];
        $insufficient = null;
        $error = null;

        foreach ($remainingCart as $listingId => $quantity) {
            try {
                $results[] = $marketplaceService->buyListing($client, $listingId, $quantity, $projectId, $delivery);
                unset($remainingCart[$listingId]);
            } catch (InsufficientWalletException $exception) {
                $insufficient = $exception;

                break;
            } catch (\Throwable $throwable) {
                $error = $throwable;

                break;
            }
        }

        return [
            'results' => $results,
            'remaining_cart' => $remainingCart,
            'insufficient' => $insufficient,
            'error' => $error,
        ];
    }

    /**
     * @param array<int, int> $checkoutCart
     */
    private function estimateCheckoutCartSubtotal(
        ClientMarketplaceService $marketplaceService,
        User $client,
        array $checkoutCart
    ): float {
        $subtotal = 0.0;
        $normalizedCart = $this->normalizeCheckoutCart($checkoutCart);

        foreach ($normalizedCart as $listingId => $requestedQuantity) {
            try {
                $product = $marketplaceService->getOpenListingProduct($client, $listingId);
            } catch (\Throwable) {
                continue;
            }

            $available = max(1, (int) ($product['qtyRemaining'] ?? 1));
            $quantity = min(max(1, $requestedQuantity), $available);
            $subtotal += round((float) ($product['unitPrice'] ?? 0.0) * $quantity, 3);
        }

        return round($subtotal, 3);
    }

    /**
     * @param array<int, array<string, mixed>> $results
     */
    private function pushBatchCheckoutFeedback(array $results, string $prefix = 'Checkout panier confirme.'): void
    {
        if ($results === []) {
            return;
        }

        $ordersCount = 0;
        $deliveriesCount = 0;
        $totalCoins = 0.0;
        $syncedCount = 0;
        $syncErrorCount = 0;

        foreach ($results as $result) {
            $ordersCount++;
            if ((int) ($result['idDelivery'] ?? 0) > 0) {
                $deliveriesCount++;
            }
            $totalCoins += (float) ($result['total_price'] ?? 0.0);

            $deliverySync = is_array($result['delivery_sync'] ?? null) ? $result['delivery_sync'] : [];
            if ((bool) ($deliverySync['success'] ?? false)) {
                $syncedCount++;
            } else {
                $syncErrorCount++;
            }
        }

        $this->addFlash('success', sprintf(
            '%s %d commande(s) creee(s), %d livraison(s), total %.3f coins.',
            $prefix,
            $ordersCount,
            $deliveriesCount,
            round($totalCoins, 3)
        ));

        if ($syncedCount > 0) {
            $this->addFlash('success', sprintf('Fiabilo a accepte %d livraison(s).', $syncedCount));
        }

        if ($syncErrorCount > 0) {
            $this->addFlash('error', sprintf(
                '%d livraison(s) restent EN_PREPARATION suite a une erreur de synchronisation.',
                $syncErrorCount
            ));
        }

        $this->addFlash('success', 'Merci pour votre commande.');
    }

    /**
     * La cle combine project + resource pour rester sur une seule page sans URL dynamique.
     *
     * @return array{0:int,1:int}
     */
    private function parseReservationKey(string $reservationKey): array
    {
        $parts = explode(':', $reservationKey, 2);
        $projectId = ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        $resourceId = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : 0;

        if ($projectId <= 0 || $resourceId <= 0) {
            throw new \InvalidArgumentException('Reservation invalide.');
        }

        return [$projectId, $resourceId];
    }

    /**
     * @return array<int, int>
     */
    private function getCart(Request $request): array
    {
        $session = $request->getSession();
        $rawCart = $session->get(self::CART_SESSION_KEY, []);

        if (!is_array($rawCart)) {
            return [];
        }

        $cart = [];
        foreach ($rawCart as $resourceId => $quantity) {
            $resolvedResourceId = (int) $resourceId;
            $resolvedQuantity = (int) $quantity;

            if ($resolvedResourceId > 0 && $resolvedQuantity > 0) {
                $cart[$resolvedResourceId] = $resolvedQuantity;
            }
        }

        return $cart;
    }

    /**
     * @param array<int, int> $cart
     */
    private function saveCart(Request $request, array $cart): void
    {
        $request->getSession()->set(self::CART_SESSION_KEY, $cart);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPendingBuy(Request $request): ?array
    {
        $pending = $request->getSession()->get(self::PENDING_BUY_SESSION_KEY);

        return is_array($pending) ? $pending : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function savePendingBuy(Request $request, array $payload): void
    {
        $request->getSession()->set(self::PENDING_BUY_SESSION_KEY, $payload);
    }

    private function clearPendingBuy(Request $request): void
    {
        $request->getSession()->remove(self::PENDING_BUY_SESSION_KEY);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getCheckoutDraft(Request $request): ?array
    {
        $draft = $request->getSession()->get(self::CHECKOUT_DRAFT_SESSION_KEY);

        return is_array($draft) ? $draft : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function saveCheckoutDraft(Request $request, array $payload): void
    {
        $listingId = isset($payload['listing_id']) ? (int) $payload['listing_id'] : 0;
        $quantity = isset($payload['quantity']) ? (int) $payload['quantity'] : 0;

        if ($listingId > 0 && $quantity > 0) {
            $this->saveCheckoutCart($request, [$listingId => $quantity]);

            return;
        }

        $this->clearCheckoutDraft($request);
    }

    private function clearCheckoutDraft(Request $request): void
    {
        $request->getSession()->remove(self::CHECKOUT_DRAFT_SESSION_KEY);
        $request->getSession()->remove(self::CHECKOUT_CART_SESSION_KEY);
    }

    /**
     * @return array<int, int>
     */
    private function getCheckoutCart(Request $request): array
    {
        $session = $request->getSession();
        $rawCart = $session->get(self::CHECKOUT_CART_SESSION_KEY, []);
        $cart = $this->normalizeCheckoutCart($rawCart);
        if ($cart !== []) {
            return $cart;
        }

        $legacyDraft = $session->get(self::CHECKOUT_DRAFT_SESSION_KEY);
        if (!is_array($legacyDraft)) {
            return [];
        }

        $listingId = isset($legacyDraft['listing_id']) ? (int) $legacyDraft['listing_id'] : 0;
        $quantity = isset($legacyDraft['quantity']) ? (int) $legacyDraft['quantity'] : 0;

        if ($listingId <= 0 || $quantity <= 0) {
            return [];
        }

        return [$listingId => $quantity];
    }

    /**
     * @param array<int, int> $cart
     */
    private function saveCheckoutCart(Request $request, array $cart): void
    {
        $normalizedCart = $this->normalizeCheckoutCart($cart);
        $session = $request->getSession();

        if ($normalizedCart === []) {
            $session->remove(self::CHECKOUT_CART_SESSION_KEY);
            $session->remove(self::CHECKOUT_DRAFT_SESSION_KEY);

            return;
        }

        $session->set(self::CHECKOUT_CART_SESSION_KEY, $normalizedCart);
        $firstListingId = (int) array_key_first($normalizedCart);
        $session->set(self::CHECKOUT_DRAFT_SESSION_KEY, [
            'listing_id' => $firstListingId,
            'quantity' => (int) $normalizedCart[$firstListingId],
        ]);
    }

    /**
     * @param mixed $rawCart
     *
     * @return array<int, int>
     */
    private function normalizeCheckoutCart(mixed $rawCart): array
    {
        if (!is_array($rawCart)) {
            return [];
        }

        $cart = [];
        foreach ($rawCart as $listingId => $quantity) {
            $resolvedListingId = (int) $listingId;
            $resolvedQuantity = (int) $quantity;
            if ($resolvedListingId > 0 && $resolvedQuantity > 0) {
                $cart[$resolvedListingId] = $resolvedQuantity;
            }
        }

        ksort($cart);

        return $cart;
    }

    /**
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     stats: array{items_count:int,units_count:int,subtotal:float}
     * }
     */
    private function buildCheckoutCartState(
        Request $request,
        ClientMarketplaceService $marketplaceService,
        User $client
    ): array {
        // On relit le panier session puis on revalide chaque ligne contre l annonce
        // encore ouverte pour eviter un checkout avec quantite ou prix obsoletes.
        $checkoutCart = $this->getCheckoutCart($request);
        if ($checkoutCart === []) {
            return [
                'items' => [],
                'stats' => [
                    'items_count' => 0,
                    'units_count' => 0,
                    'subtotal' => 0.0,
                ],
            ];
        }

        $resolvedCart = [];
        $items = [];

        foreach ($checkoutCart as $listingId => $requestedQuantity) {
            try {
                $product = $marketplaceService->getOpenListingProduct($client, $listingId);
            } catch (\Throwable) {
                continue;
            }

            $available = max(1, (int) ($product['qtyRemaining'] ?? 1));
            $quantity = min(max(1, $requestedQuantity), $available);
            $resolvedCart[(int) $product['idListing']] = $quantity;
            $items[] = [
                'listing_id' => (int) ($product['idListing'] ?? 0),
                'resource_name' => (string) ($product['resource_name'] ?? 'Ressource'),
                'supplier_name' => (string) ($product['supplier_name'] ?? 'Non renseigne'),
                'seller_name' => (string) ($product['seller_name'] ?? 'Client'),
                'unit_price' => round((float) ($product['unitPrice'] ?? 0), 3),
                'quantity' => $quantity,
                'line_total' => round((float) ($product['unitPrice'] ?? 0) * $quantity, 3),
                'available' => (int) ($product['qtyRemaining'] ?? 0),
                'image_url' => is_array($product['images'] ?? null) ? (string) (($product['images'][0] ?? '') ?: '') : '',
            ];
        }

        if ($resolvedCart !== $checkoutCart) {
            $this->saveCheckoutCart($request, $resolvedCart);
        }

        return [
            'items' => $items,
            'stats' => [
                'items_count' => count($items),
                'units_count' => array_sum(array_map(static fn (array $item): int => (int) $item['quantity'], $items)),
                'subtotal' => round(array_sum(array_map(static fn (array $item): float => (float) $item['line_total'], $items)), 3),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractDeliveryPayloadFromFormData(array $formData): array
    {
        return [
            'recipient_name' => trim((string) ($formData['recipient_name'] ?? '')),
            'governorate' => trim((string) ($formData['governorate'] ?? '')),
            'city' => trim((string) ($formData['city'] ?? '')),
            'address_line' => trim((string) ($formData['address_line'] ?? '')),
            'postal_code' => trim((string) ($formData['postal_code'] ?? '')),
            'phone' => trim((string) ($formData['phone'] ?? '')),
            'phone2' => trim((string) ($formData['phone2'] ?? '')),
            'delivery_note' => trim((string) ($formData['delivery_note'] ?? '')),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $projects
     * @param array<string, mixed> $initialData
     */
    private function createCheckoutForm(
        array $projects,
        bool $includeQuantity,
        int $maxQuantity,
        array $initialData,
        string $csrfTokenId
    ): FormInterface {
        // Un seul helper garde les memes regles Symfony entre checkout simple et checkout panier.
        return $this->createForm(
            ShopCheckoutType::class,
            $initialData,
            [
                'project_choices' => $this->buildProjectChoices($projects),
                'include_quantity' => $includeQuantity,
                'max_quantity' => max(1, $maxQuantity),
                'csrf_token_id' => $csrfTokenId,
            ]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $projects
     *
     * @return array<string, int>
     */
    private function buildProjectChoices(array $projects): array
    {
        $choices = [];

        foreach ($projects as $project) {
            $projectId = (int) ($project['idProj'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            $title = trim((string) ($project['titleProj'] ?? ''));
            $label = $title !== ''
                ? sprintf('%s (#%d)', $title, $projectId)
                : sprintf('Projet #%d', $projectId);
            $choices[$label] = $projectId;
        }

        return $choices;
    }

    /**
     * Symfony exige un entier pour {topupId}; on genere d'abord une URL valide puis on remplace le segment final.
     *
     * @return array{success_url:string,cancel_url:string}
     */
    private function buildStripeTopupCallbackUrls(): array
    {
        $placeholderId = 999999999;
        $replacementPattern = '/999999999';
        $replacementValue = '/__TOPUP__';

        $successUrl = $this->generateUrl(
            'app_shop_wallet_topup_stripe_success',
            ['topupId' => $placeholderId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $cancelUrl = $this->generateUrl(
            'app_shop_wallet_topup_stripe_cancel',
            ['topupId' => $placeholderId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return [
            'success_url' => $this->replacePathSegment($successUrl, $replacementPattern, $replacementValue),
            'cancel_url' => $this->replacePathSegment($cancelUrl, $replacementPattern, $replacementValue),
        ];
    }

    private function replacePathSegment(string $url, string $searchSegment, string $replacementSegment): string
    {
        $escapedSearch = preg_quote($searchSegment, '/');
        $updatedUrl = preg_replace('/' . $escapedSearch . '(?=$|[?#])/', $replacementSegment, $url, 1);

        if (!is_string($updatedUrl) || $updatedUrl === '') {
            return $url;
        }

        return $updatedUrl;
    }
}
