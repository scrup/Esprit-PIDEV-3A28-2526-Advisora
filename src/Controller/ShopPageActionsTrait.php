<?php
namespace App\Controller;
use App\Service\ClientMarketplaceService;
use App\Service\ClientMiniShopService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
/**
 * Ecrans GET du module boutique.
 *
 * On regroupe ici les pages de lecture et de navigation pour garder une vue
 * claire de l'experience utilisateur cote catalogue, wallet et checkout.
 */
trait ShopPageActionsTrait
{
    #[Route('/boutique', name: 'app_shop', methods: ['GET'])]
    public function index(
        Request $request,
        ClientMiniShopService $miniShopService,
        ClientMarketplaceService $marketplaceService,
        PaginatorInterface $paginator,
    ): Response
    {
        // Cette page assemble 2 univers:
        // 1) mini shop "catalogue fournisseur" (reservations internes)
        // 2) marketplace C2C entre clients (annonces LISTED)
        $manageMode = strtolower((string) $request->query->get('manage', ''));
        if (in_array($manageMode, ['1', 'true', 'yes'], true)) {
            return $this->redirectToRoute('app_shop_publish');
        }

        $walletMode = strtolower((string) $request->query->get('wallet', ''));
        if (in_array($walletMode, ['1', 'true', 'yes'], true)) {
            return $this->redirectToRoute('app_shop_wallet');
        }

        $client = $this->requireClient();
        $supplierId = $request->query->getInt('supplier_id') ?: null;
        $search = trim((string) $request->query->get('q', ''));
        $marketSearch = trim((string) $request->query->get('market_q', ''));
        $marketSort = strtolower(trim((string) $request->query->get('c2c_sort', 'recent')));
        $marketPage = max(1, (int) $request->query->get('c2c_page', 1));
        $cart = $this->getCart($request);
        $marketplace = $marketplaceService->buildPageData($client, $marketSearch, $marketSort);
        $marketplace['open_listings_page'] = $paginator->paginate(
            $marketplace['open_listings'] ?? [],
            $marketPage,
            9,
            ['pageParameterName' => 'c2c_page']
        );
        $checkoutCartState = $this->buildCheckoutCartState($request, $marketplaceService, $client);

        return $this->render('front/shop/index.html.twig', array_merge(
            $miniShopService->buildPageData($client, $supplierId, $search, $cart),
            [
                'marketplace' => $marketplace,
                'pending_buy' => $this->getPendingBuy($request),
                'checkout_cart_items' => $checkoutCartState['items'],
                'checkout_cart_stats' => $checkoutCartState['stats'],
            ]
        ));
    }

    #[Route('/boutique/publier', name: 'app_shop_publish', methods: ['GET'])]
    public function publishPage(
        Request $request,
        ClientMarketplaceService $marketplaceService,
    ): Response
    {
        $client = $this->requireClient();

        return $this->render('front/shop/publish.html.twig', [
            'marketplace' => $marketplaceService->buildPageData($client, ''),
            'pending_buy' => $this->getPendingBuy($request),
        ]);
    }

    #[Route('/boutique/wallet', name: 'app_shop_wallet', methods: ['GET'])]
    public function walletPage(
        Request $request,
        ClientMarketplaceService $marketplaceService,
    ): Response
    {
        $client = $this->requireClient();

        return $this->render('front/shop/wallet.html.twig', [
            'marketplace' => $marketplaceService->buildPageData($client, ''),
            'pending_buy' => $this->getPendingBuy($request),
        ]);
    }

    #[Route('/boutique/mes-annonces', name: 'app_shop_my_listings', methods: ['GET'])]
    public function myListingsPage(
        Request $request,
        ClientMarketplaceService $marketplaceService,
    ): Response
    {
        $client = $this->requireClient();

        return $this->render('front/shop/my_listings.html.twig', [
            'marketplace' => $marketplaceService->buildPageData($client, ''),
            'pending_buy' => $this->getPendingBuy($request),
        ]);
    }

    #[Route('/boutique/recharges', name: 'app_shop_topups', methods: ['GET'])]
    public function topups(
        Request $request,
    ): RedirectResponse
    {
        $this->requireClient();
        $this->addFlash('info', 'La page "recharges en attente" est desactivee.');

        return $this->redirectToRoute('app_shop_wallet');
    }

    #[Route('/boutique/produit/{listingId}', name: 'app_shop_market_product', methods: ['GET'], requirements: ['listingId' => '\d+'])]
    public function productPage(
        int $listingId,
        Request $request,
        ClientMarketplaceService $marketplaceService,
        PaginatorInterface $paginator,
    ): Response
    {
        $client = $this->requireClient();

        try {
            $product = $marketplaceService->getOpenListingProduct($client, $listingId);
            $reviewData = $marketplaceService->getListingReviewData($client, (int) $product['idListing']);
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());

            return $this->redirectToRoute('app_shop');
        }

        $checkoutCart = $this->getCheckoutCart($request);
        $checkoutCartQuantity = min(
            max(1, (int) ($checkoutCart[(int) ($product['idListing'] ?? 0)] ?? 1)),
            max(1, (int) ($product['qtyRemaining'] ?? 1))
        );
        $reviewsPage = $paginator->paginate(
            $reviewData['reviews'],
            max(1, (int) $request->query->get('review_page', 1)),
            5,
            ['pageParameterName' => 'review_page']
        );

        return $this->render('front/shop/product.html.twig', [
            'product' => $product,
            'checkout_cart_quantity' => $checkoutCartQuantity,
            'review_summary' => $reviewData['summary'],
            'client_review' => $reviewData['client_review'] ?? null,
            'reviewable_order' => $reviewData['reviewable_order'] ?? null,
            'reviews_page' => $reviewsPage,
        ]);
    }

    #[Route('/boutique/checkout', name: 'app_shop_checkout', methods: ['GET'])]
    public function checkoutFromDraft(Request $request, ClientMarketplaceService $marketplaceService): Response
    {
        $client = $this->requireClient();
        $checkoutCartState = $this->buildCheckoutCartState($request, $marketplaceService, $client);
        $checkoutItems = $checkoutCartState['items'];

        if ($checkoutItems === []) {
            $this->addFlash('error', 'Votre panier checkout est vide.');

            return $this->redirectToRoute('app_shop');
        }

        if (count($checkoutItems) === 1) {
            $single = $checkoutItems[0];
            $listingId = (int) ($single['listing_id'] ?? 0);
            $quantity = (int) ($single['quantity'] ?? 1);

            if ($listingId > 0) {
                $parameters = ['listingId' => $listingId];
                if ($quantity > 0) {
                    $parameters['quantity'] = $quantity;
                }

                return $this->redirectToRoute('app_shop_market_checkout', $parameters);
            }
        }

        $pageData = $marketplaceService->buildPageData($client, '');
        $projects = is_array($pageData['projects'] ?? null) ? $pageData['projects'] : [];
        $checkoutForm = $this->createCheckoutForm(
            $projects,
            false,
            1,
            [
                'topup_provider' => 'STRIPE',
            ],
            'shop_market_buy_cart'
        );

        return $this->render('front/shop/checkout_cart.html.twig', [
            'checkout_cart_items' => $checkoutItems,
            'checkout_cart_stats' => $checkoutCartState['stats'],
            'projects' => $projects,
            'wallet' => $pageData['wallet'] ?? ['balance' => 0.0, 'pending_topups' => [], 'coin_rate' => $marketplaceService->getCoinRate()],
            'checkout_form' => $checkoutForm->createView(),
        ]);
    }

    #[Route('/boutique/checkout/{listingId}', name: 'app_shop_market_checkout', methods: ['GET'], requirements: ['listingId' => '\d+'])]
    public function checkoutPage(
        int $listingId,
        Request $request,
        ClientMarketplaceService $marketplaceService,
        PaginatorInterface $paginator,
    ): Response {
        $client = $this->requireClient();

        try {
            $product = $marketplaceService->getOpenListingProduct($client, $listingId);
            $reviewData = $marketplaceService->getListingReviewData($client, (int) $product['idListing']);
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());

            return $this->redirectToRoute('app_shop');
        }

        $requestedQuantity = max(1, (int) $request->query->get('quantity', 1));
        $maxQuantity = max(1, (int) $product['qtyRemaining']);
        $quantity = min($requestedQuantity, $maxQuantity);
        $reviewsPage = $paginator->paginate(
            $reviewData['reviews'],
            max(1, (int) $request->query->get('review_page', 1)),
            4,
            ['pageParameterName' => 'review_page']
        );

        $pageData = $marketplaceService->buildPageData($client, '');
        $projects = is_array($pageData['projects'] ?? null) ? $pageData['projects'] : [];
        $checkoutForm = $this->createCheckoutForm(
            $projects,
            true,
            $maxQuantity,
            [
                'quantity' => $quantity,
                'topup_provider' => 'STRIPE',
            ],
            'shop_market_buy'
        );

        return $this->render('front/shop/checkout.html.twig', [
            'product' => $product,
            'projects' => $projects,
            'wallet' => $pageData['wallet'] ?? ['balance' => 0, 'pending_topups' => []],
            'quantity' => $quantity,
            'review_summary' => $reviewData['summary'],
            'client_review' => $reviewData['client_review'] ?? null,
            'reviewable_order' => $reviewData['reviewable_order'] ?? null,
            'reviews_page' => $reviewsPage,
            'checkout_form' => $checkoutForm->createView(),
        ]);
    }

}
