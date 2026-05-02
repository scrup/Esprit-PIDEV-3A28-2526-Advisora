<?php
namespace App\Controller;
use App\Service\ClientMiniShopService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
/**
 * Actions panier/reservations du mini-shop fournisseur.
 *
 * Cette partie gere uniquement le panier interne, les reservations et leurs
 * mises a jour afin de separer clairement le flux catalogue du flux C2C.
 */
trait ShopCartActionsTrait
{
    #[Route('/boutique/reserve', name: 'app_shop_reserve', methods: ['POST'])]
    public function reserve(Request $request, ClientMiniShopService $miniShopService): RedirectResponse
    {
        $client = $this->requireClient();
        $csrfToken = (string) ($request->request->get('_token') ?: $request->request->get('_reserve_token'));

        if (!$this->isCsrfTokenValid('shop_reserve', $csrfToken)) {
            $this->addFlash('error', 'Le jeton de reservation est invalide.');

            return $this->redirectToShop($request, 'shop-cart-drawer');
        }

        try {
            $miniShopService->reserve(
                $client,
                (int) $request->request->get('resource_id'),
                (int) $request->request->get('quantity'),
                $request->request->get('project_id') !== '' ? (int) $request->request->get('project_id') : null
            );

            $this->addFlash('success', 'Reservation enregistree avec succes.');
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        return $this->redirectToShop($request);
    }

    #[Route('/boutique/panier/add', name: 'app_shop_cart_add', methods: ['POST'])]
    public function addToCart(Request $request, ClientMiniShopService $miniShopService): RedirectResponse
    {
        $client = $this->requireClient();
        $csrfToken = (string) ($request->request->get('_token') ?: $request->request->get('_cart_token'));

        if (!$this->isCsrfTokenValid('shop_cart_add', $csrfToken)) {
            $this->addFlash('error', 'Le jeton du panier est invalide.');

            return $this->redirectToShop($request, 'shop-cart-drawer');
        }

        $resourceId = (int) $request->request->get('resource_id');
        $quantity = max(1, (int) $request->request->get('quantity'));
        $resource = $miniShopService->getResourceSnapshot($client, $resourceId);

        if ($resource === null) {
            $this->addFlash('error', 'Ressource introuvable.');

            return $this->redirectToShop($request);
        }

        $cart = $this->getCart($request);
        $existingQuantity = $cart[$resourceId] ?? 0;
        $nextQuantity = $existingQuantity + $quantity;
        $availableStock = (int) $resource['available_stock'];

        if ($nextQuantity > max(0, $availableStock)) {
            $this->addFlash('error', sprintf('Stock insuffisant pour %s. Disponible: %d', (string) $resource['nomRs'], $availableStock));

            return $this->redirectToShop($request);
        }

        $cart[$resourceId] = $nextQuantity;
        $this->saveCart($request, $cart);
        $this->addFlash('success', sprintf('%s ajoute au panier.', (string) $resource['nomRs']));

        return $this->redirectToShop($request, 'shop-cart-drawer');
    }

    #[Route('/boutique/panier/update', name: 'app_shop_cart_update', methods: ['POST'])]
    public function updateCart(Request $request, ClientMiniShopService $miniShopService): RedirectResponse
    {
        $client = $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_cart_update', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton du panier est invalide.');

            return $this->redirectToShop($request, 'shop-cart-drawer');
        }

        $resourceId = (int) $request->request->get('resource_id');
        $quantity = (int) $request->request->get('quantity');
        $cart = $this->getCart($request);

        if ($quantity <= 0) {
            unset($cart[$resourceId]);
            $this->saveCart($request, $cart);
            $this->addFlash('info', 'Article retire du panier.');

            return $this->redirectToShop($request, 'shop-cart-drawer');
        }

        $resource = $miniShopService->getResourceSnapshot($client, $resourceId);
        if ($resource === null) {
            unset($cart[$resourceId]);
            $this->saveCart($request, $cart);
            $this->addFlash('error', 'Ressource introuvable.');

            return $this->redirectToShop($request, 'shop-cart-drawer');
        }

        $availableStock = (int) $resource['available_stock'];
        if ($quantity > max(0, $availableStock)) {
            $this->addFlash('error', sprintf('Stock insuffisant pour %s. Disponible: %d', (string) $resource['nomRs'], $availableStock));

            return $this->redirectToShop($request);
        }

        $cart[$resourceId] = $quantity;
        $this->saveCart($request, $cart);
        $this->addFlash('success', 'Quantite du panier mise a jour.');

        return $this->redirectToShop($request, 'shop-cart-drawer');
    }

    #[Route('/boutique/panier/remove', name: 'app_shop_cart_remove', methods: ['POST'])]
    public function removeFromCart(Request $request): RedirectResponse
    {
        $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_cart_remove', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton du panier est invalide.');

            return $this->redirectToShop($request, 'shop-cart-drawer');
        }

        $resourceId = (int) $request->request->get('resource_id');
        $cart = $this->getCart($request);
        unset($cart[$resourceId]);
        $this->saveCart($request, $cart);
        $this->addFlash('info', 'Article retire du panier.');

        return $this->redirectToShop($request, 'shop-cart-drawer');
    }

    #[Route('/boutique/panier/clear', name: 'app_shop_cart_clear', methods: ['POST'])]
    public function clearCart(Request $request): RedirectResponse
    {
        $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_cart_clear', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton du panier est invalide.');

            return $this->redirectToShop($request, 'shop-cart-drawer');
        }

        $this->saveCart($request, []);
        $this->addFlash('info', 'Panier vide.');

        return $this->redirectToShop($request);
    }

    #[Route('/boutique/panier/reserve', name: 'app_shop_cart_reserve', methods: ['POST'])]
    public function reserveCart(Request $request, ClientMiniShopService $miniShopService): RedirectResponse
    {
        $client = $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_cart_reserve', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de reservation du panier est invalide.');

            return $this->redirectToShop($request, 'shop-cart-drawer');
        }

        $cart = $this->getCart($request);
        if ($cart === []) {
            $this->addFlash('error', 'Le panier est vide.');

            return $this->redirectToShop($request);
        }

        $cartItems = $miniShopService->getCartItems($client, $cart);
        foreach ($cartItems as $item) {
            if (!$item['is_stock_valid']) {
                $this->addFlash('error', sprintf('Stock insuffisant pour %s.', (string) $item['resource_name']));

                return $this->redirectToShop($request);
            }
        }

        try {
            $projectId = $request->request->get('project_id') !== '' ? (int) $request->request->get('project_id') : null;

            foreach ($cartItems as $item) {
                $miniShopService->reserve(
                    $client,
                    (int) $item['resource_id'],
                    (int) $item['quantity'],
                    $projectId
                );
            }

            $this->saveCart($request, []);
            $this->addFlash('success', 'Le panier a ete reserve avec succes.');
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        return $this->redirectToShop($request, 'shop-cart-drawer');
    }

    #[Route('/boutique/reservations/update', name: 'app_shop_reservation_update', methods: ['POST'])]
    public function updateReservation(Request $request, ClientMiniShopService $miniShopService): RedirectResponse
    {
        $client = $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_update', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de modification est invalide.');

            return $this->redirectToShop($request);
        }

        try {
            [$projectId, $resourceId] = $this->parseReservationKey((string) $request->request->get('reservation_key'));

            $miniShopService->updateReservation(
                $client,
                $projectId,
                $resourceId,
                (int) $request->request->get('new_quantity'),
                $request->request->get('target_project_id') !== '' ? (int) $request->request->get('target_project_id') : null
            );

            $this->addFlash('success', 'Reservation mise a jour avec succes.');
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        return $this->redirectToShop($request);
    }

    #[Route('/boutique/reservations/delete', name: 'app_shop_reservation_delete', methods: ['POST'])]
    public function deleteReservation(Request $request, ClientMiniShopService $miniShopService): RedirectResponse
    {
        $client = $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_delete', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de suppression est invalide.');

            return $this->redirectToShop($request);
        }

        try {
            [$projectId, $resourceId] = $this->parseReservationKey((string) $request->request->get('reservation_key'));
            $miniShopService->deleteReservation($client, $projectId, $resourceId);

            $this->addFlash('success', 'Reservation supprimee avec succes.');
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        return $this->redirectToShop($request);
    }

}

