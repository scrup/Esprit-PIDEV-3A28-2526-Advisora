<?php

namespace App\Controller;

use App\Entity\User;
use App\Exception\InsufficientWalletException;
use App\Service\ClientMarketplaceService;
use App\Service\ClientMiniShopService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controleur HTTP du module shop.
 *
 * Roles:
 * - exposer les pages (catalogue, wallet, publication, checkout, produit),
 * - valider les intentions utilisateur (CSRF + role CLIENT),
 * - orchestrer les services metier et la session panier/pending checkout,
 * - centraliser les redirections et messages flash UX.
 */
final class ShopController extends AbstractController
{
    private const CART_SESSION_KEY = 'shop_cart';
    private const CART_DRAWER_FRAGMENT = 'shop-cart-drawer';
    private const PENDING_BUY_SESSION_KEY = 'shop_pending_market_buy';
    private const CHECKOUT_CART_SESSION_KEY = 'shop_checkout_cart';
    private const CHECKOUT_DRAFT_SESSION_KEY = 'shop_checkout_draft';

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

    #[Route('/boutique/market/publish', name: 'app_shop_market_publish', methods: ['POST'])]
    public function publishListing(Request $request, ClientMarketplaceService $marketplaceService): RedirectResponse
    {
        $client = $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_market_publish', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de publication est invalide.');

            return $this->redirectAfterPublishAction($request, 'shop-marketplace');
        }

        try {
            $uploadedImage = $request->files->get('image_file');
            $marketplaceService->publishListing(
                $client,
                (int) $request->request->get('project_id'),
                (int) $request->request->get('resource_id'),
                (int) $request->request->get('quantity'),
                (float) str_replace(',', '.', (string) $request->request->get('unit_price')),
                (string) $request->request->get('note'),
                (string) $request->request->get('image_url'),
                $uploadedImage instanceof UploadedFile ? $uploadedImage : null
            );

            $this->addFlash('success', 'Annonce publiee avec succes.');
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        return $this->redirectAfterPublishAction($request, 'shop-marketplace');
    }

    #[Route('/boutique/market/cancel', name: 'app_shop_market_cancel', methods: ['POST'])]
    public function cancelListing(Request $request, ClientMarketplaceService $marketplaceService): RedirectResponse
    {
        $client = $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_market_cancel', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton annulation est invalide.');

            return $this->redirectAfterPublishAction($request, 'shop-marketplace');
        }

        try {
            $marketplaceService->cancelListing($client, (int) $request->request->get('listing_id'));
            $this->addFlash('success', 'Annonce annulee avec succes.');
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        return $this->redirectAfterPublishAction($request, 'shop-marketplace');
    }

    #[Route('/boutique/market/buy', name: 'app_shop_market_buy', methods: ['POST'])]
    public function buyListing(Request $request, ClientMarketplaceService $marketplaceService): RedirectResponse
    {
        $client = $this->requireClient();
        $listingId = (int) $request->request->get('listing_id');
        $quantity = (int) $request->request->get('quantity');

        if (!$this->isCsrfTokenValid('shop_market_buy', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton achat est invalide.');

            return $this->redirectAfterBuyError($request, $listingId, $quantity);
        }

        $projectId = $request->request->get('project_id') !== '' ? (int) $request->request->get('project_id') : null;
        $delivery = $this->extractDeliveryPayload($request);
        $topupProvider = strtoupper((string) $request->request->get('topup_provider', 'STRIPE'));

        try {
            // Cas nominal: solde suffisant, achat direct et creation commande+livraison.
            $result = $marketplaceService->buyListing($client, $listingId, $quantity, $projectId, $delivery);
            $this->clearPendingBuy($request);
            $this->clearCheckoutDraft($request);

            $this->pushCheckoutFeedback($result);
            $this->addFlash('info', 'Vous pouvez laisser votre avis dans la section Avis clients.');
        } catch (InsufficientWalletException $exception) {
            // Cas insuffisant: on sauvegarde le contexte d achat pour reprise automatique
            // apres recharge, puis on tente de lancer une recharge STRIPE/FLOUCI/D17.
            $this->savePendingBuy($request, [
                'listing_id' => $listingId,
                'quantity' => $quantity,
                'project_id' => $projectId,
                'delivery' => $delivery,
                'missing_coins' => $exception->getMissingCoins(),
                'required_coins' => $exception->getRequiredCoins(),
                'current_balance' => $exception->getCurrentBalance(),
                'coin_rate' => $marketplaceService->getCoinRate(),
                'missing_money' => $marketplaceService->coinsToMoney($exception->getMissingCoins()),
                'topup_provider' => $topupProvider,
            ]);

            $autoTopupAmount = max(0.001, $marketplaceService->coinsToMoney($exception->getMissingCoins()));
            try {
                $stripeCallbackUrls = $this->buildStripeTopupCallbackUrls();
                $topup = $marketplaceService->createTopup($client, $autoTopupAmount, $topupProvider, [
                    'success_url' => $stripeCallbackUrls['success_url'],
                    'cancel_url' => $stripeCallbackUrls['cancel_url'],
                ]);
                $this->addFlash('error', sprintf(
                    'Solde insuffisant: %.3f coins manquants. Recharge auto #%d creee via %s (%.3f money => %.3f coins, taux %.3f).',
                    $exception->getMissingCoins(),
                    (int) $topup['idTopup'],
                    (string) ($topup['provider'] ?? $topupProvider),
                    (float) ($topup['amountMoney'] ?? $autoTopupAmount),
                    (float) $topup['coinAmount'],
                    (float) ($topup['coinRate'] ?? $marketplaceService->getCoinRate())
                ));

                if (($topup['provider'] ?? '') === 'STRIPE' && is_string($topup['paymentUrl'] ?? null) && $topup['paymentUrl'] !== '') {
                    return new RedirectResponse((string) $topup['paymentUrl']);
                }

                return $this->redirectToRoute('app_shop_wallet');
            } catch (\Throwable $topupException) {
                $this->addFlash('error', sprintf(
                    'Solde insuffisant (%.3f coins manquants). Echec creation recharge auto: %s',
                    $exception->getMissingCoins(),
                    $topupException->getMessage()
                ));

                return $this->redirectAfterBuyError($request, $listingId, $quantity);
            }
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());

            return $this->redirectAfterBuyError($request, $listingId, $quantity);
        }

        return $this->redirectToListingReviewPage($listingId);
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
            $reviewData['reviews'] ?? [],
            max(1, (int) $request->query->get('review_page', 1)),
            5,
            ['pageParameterName' => 'review_page']
        );

        return $this->render('front/shop/product.html.twig', [
            'product' => $product,
            'checkout_cart_quantity' => $checkoutCartQuantity,
            'review_summary' => $reviewData['summary'] ?? ['review_count' => 0, 'rating_avg' => 0.0, 'distribution' => []],
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
        $checkoutItems = is_array($checkoutCartState['items'] ?? null) ? $checkoutCartState['items'] : [];

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

        return $this->render('front/shop/checkout_cart.html.twig', [
            'checkout_cart_items' => $checkoutItems,
            'checkout_cart_stats' => $checkoutCartState['stats'] ?? ['items_count' => 0, 'units_count' => 0, 'subtotal' => 0.0],
            'projects' => $pageData['projects'] ?? [],
            'wallet' => $pageData['wallet'] ?? ['balance' => 0.0, 'pending_topups' => [], 'coin_rate' => $marketplaceService->getCoinRate()],
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
            $reviewData['reviews'] ?? [],
            max(1, (int) $request->query->get('review_page', 1)),
            4,
            ['pageParameterName' => 'review_page']
        );

        $pageData = $marketplaceService->buildPageData($client, '');

        return $this->render('front/shop/checkout.html.twig', [
            'product' => $product,
            'projects' => $pageData['projects'] ?? [],
            'wallet' => $pageData['wallet'] ?? ['balance' => 0, 'pending_topups' => []],
            'quantity' => $quantity,
            'review_summary' => $reviewData['summary'] ?? ['review_count' => 0, 'rating_avg' => 0.0, 'distribution' => []],
            'client_review' => $reviewData['client_review'] ?? null,
            'reviewable_order' => $reviewData['reviewable_order'] ?? null,
            'reviews_page' => $reviewsPage,
        ]);
    }

    #[Route('/boutique/checkout/buy-cart', name: 'app_shop_market_buy_cart', methods: ['POST'])]
    public function buyCheckoutCart(Request $request, ClientMarketplaceService $marketplaceService): RedirectResponse
    {
        $client = $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_market_buy_cart', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton checkout panier est invalide.');

            return $this->redirectToRoute('app_shop_checkout');
        }

        $projectId = $request->request->get('project_id') !== '' ? (int) $request->request->get('project_id') : null;
        $delivery = $this->extractDeliveryPayload($request);
        $topupProvider = strtoupper((string) $request->request->get('topup_provider', 'STRIPE'));

        // Le "checkout panier" execute les annonces du panier C2C en sequence.
        // Chaque ligne cree sa propre commande/livraison cote metier.
        $checkoutCartState = $this->buildCheckoutCartState($request, $marketplaceService, $client);
        $checkoutItems = is_array($checkoutCartState['items'] ?? null) ? $checkoutCartState['items'] : [];
        if ($checkoutItems === []) {
            $this->addFlash('error', 'Votre panier C2C est vide.');

            return $this->redirectToRoute('app_shop');
        }

        $checkoutCart = [];
        foreach ($checkoutItems as $item) {
            $listingId = (int) ($item['listing_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($listingId > 0 && $quantity > 0) {
                $checkoutCart[$listingId] = $quantity;
            }
        }

        if ($checkoutCart === []) {
            $this->addFlash('error', 'Aucune ligne valide dans le panier C2C.');

            return $this->redirectToRoute('app_shop');
        }

        $walletData = $marketplaceService->buildPageData($client, '')['wallet'] ?? [];
        $walletBalance = round((float) ($walletData['balance'] ?? 0.0), 3);
        $requiredCoins = round((float) (($checkoutCartState['stats']['subtotal'] ?? 0.0)), 3);
        $missingCoins = round(max(0.0, $requiredCoins - $walletBalance), 3);

        if ($missingCoins > 0.000001) {
            $this->savePendingBuy($request, $this->buildPendingCartPayload(
                $marketplaceService,
                $checkoutCart,
                $projectId,
                $delivery,
                $missingCoins,
                $requiredCoins,
                $walletBalance,
                $topupProvider
            ));

            $autoTopupAmount = max(0.001, $marketplaceService->coinsToMoney($missingCoins));
            try {
                $stripeCallbackUrls = $this->buildStripeTopupCallbackUrls();
                $topup = $marketplaceService->createTopup($client, $autoTopupAmount, $topupProvider, [
                    'success_url' => $stripeCallbackUrls['success_url'],
                    'cancel_url' => $stripeCallbackUrls['cancel_url'],
                ]);
                $this->addFlash('error', sprintf(
                    'Solde insuffisant pour tout le panier: %.3f coins manquants. Recharge auto #%d creee via %s.',
                    $missingCoins,
                    (int) $topup['idTopup'],
                    (string) ($topup['provider'] ?? $topupProvider)
                ));

                if (($topup['provider'] ?? '') === 'STRIPE' && is_string($topup['paymentUrl'] ?? null) && $topup['paymentUrl'] !== '') {
                    return new RedirectResponse((string) $topup['paymentUrl']);
                }

                return $this->redirectToRoute('app_shop_wallet');
            } catch (\Throwable $topupException) {
                $this->addFlash('error', sprintf(
                    'Solde insuffisant (%.3f coins manquants). Echec creation recharge auto: %s',
                    $missingCoins,
                    $topupException->getMessage()
                ));

                return $this->redirectToRoute('app_shop_checkout');
            }
        }

        $batch = $this->executeCheckoutCartBatch($client, $marketplaceService, $checkoutCart, $projectId, $delivery);
        $results = is_array($batch['results'] ?? null) ? $batch['results'] : [];
        $reviewListingId = 0;
        $remainingCart = $this->normalizeCheckoutCart($batch['remaining_cart'] ?? []);

        $this->saveCheckoutCart($request, $remainingCart);

        if ($results !== []) {
            $this->pushBatchCheckoutFeedback(
                $results,
                $remainingCart === [] ? 'Checkout panier confirme.' : 'Achat partiel confirme.'
            );
            foreach ($results as $result) {
                $candidateListingId = (int) ($result['listing_id'] ?? 0);
                if ($candidateListingId > 0) {
                    $reviewListingId = $candidateListingId;

                    break;
                }
            }
        }

        $insufficient = $batch['insufficient'] ?? null;
        if ($insufficient instanceof InsufficientWalletException) {
            $requiredRemainingCoins = $this->estimateCheckoutCartSubtotal($marketplaceService, $client, $remainingCart);
            $currentBalance = $insufficient->getCurrentBalance();
            $missingRemainingCoins = round(max(
                $insufficient->getMissingCoins(),
                max(0.0, $requiredRemainingCoins - $currentBalance)
            ), 3);

            $this->savePendingBuy($request, $this->buildPendingCartPayload(
                $marketplaceService,
                $remainingCart,
                $projectId,
                $delivery,
                $missingRemainingCoins,
                $requiredRemainingCoins,
                $currentBalance,
                $topupProvider
            ));

            $autoTopupAmount = max(0.001, $marketplaceService->coinsToMoney($missingRemainingCoins));
            try {
                $stripeCallbackUrls = $this->buildStripeTopupCallbackUrls();
                $topup = $marketplaceService->createTopup($client, $autoTopupAmount, $topupProvider, [
                    'success_url' => $stripeCallbackUrls['success_url'],
                    'cancel_url' => $stripeCallbackUrls['cancel_url'],
                ]);
                $this->addFlash('error', sprintf(
                    'Solde insuffisant pendant checkout panier: %.3f coins manquants. Recharge auto #%d creee via %s.',
                    $missingRemainingCoins,
                    (int) $topup['idTopup'],
                    (string) ($topup['provider'] ?? $topupProvider)
                ));

                if (($topup['provider'] ?? '') === 'STRIPE' && is_string($topup['paymentUrl'] ?? null) && $topup['paymentUrl'] !== '') {
                    return new RedirectResponse((string) $topup['paymentUrl']);
                }

                return $this->redirectToRoute('app_shop_wallet');
            } catch (\Throwable $topupException) {
                $this->addFlash('error', sprintf(
                    'Recharge auto panier impossible: %s',
                    $topupException->getMessage()
                ));
            }

            return $this->redirectToRoute('app_shop_checkout');
        }

        $error = $batch['error'] ?? null;
        if ($error instanceof \Throwable) {
            $this->addFlash('error', 'Checkout panier interrompu: ' . $error->getMessage());

            return $this->redirectToRoute('app_shop_checkout');
        }

        $this->clearPendingBuy($request);
        $this->addFlash('success', 'Merci pour votre commande.');

        if ($reviewListingId > 0) {
            $this->addFlash('info', 'Vous pouvez laisser votre avis dans la section Avis clients.');

            return $this->redirectToListingReviewPage($reviewListingId);
        }

        return $this->redirectToShop($request, 'shop-orders');
    }

    #[Route('/boutique/checkout/add', name: 'app_shop_market_cart_add', methods: ['POST'])]
    public function addToCheckoutCart(Request $request, ClientMarketplaceService $marketplaceService): RedirectResponse
    {
        $client = $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_market_cart_add', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton add to cart est invalide.');

            return $this->redirectToRoute('app_shop');
        }

        $listingId = (int) $request->request->get('listing_id');
        $requestedQuantity = max(1, (int) $request->request->get('quantity'));

        try {
            $product = $marketplaceService->getOpenListingProduct($client, $listingId);
            $quantity = min($requestedQuantity, max(1, (int) $product['qtyRemaining']));
            $checkoutCart = $this->getCheckoutCart($request);
            $existingQuantity = (int) ($checkoutCart[(int) $product['idListing']] ?? 0);
            $checkoutCart[(int) $product['idListing']] = min(
                max(1, $existingQuantity + $quantity),
                max(1, (int) $product['qtyRemaining'])
            );
            $this->saveCheckoutCart($request, $checkoutCart);

            $this->addFlash('success', 'Produit ajoute au panier checkout.');
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        $redirectPage = strtolower((string) $request->request->get('_redirect_page', ''));
        if ($redirectPage === 'shop') {
            $fragment = trim((string) $request->request->get('_redirect_fragment', 'shop-marketplace'));
            if ($fragment === '') {
                $fragment = 'shop-marketplace';
            }

            return $this->redirectToShop($request, $fragment);
        }

        return $this->redirectToRoute('app_shop_market_product', ['listingId' => $listingId]);
    }

    #[Route('/boutique/checkout/remove', name: 'app_shop_market_cart_clear', methods: ['POST'])]
    public function clearCheckoutCart(Request $request): RedirectResponse
    {
        $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_market_cart_clear', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton suppression panier checkout est invalide.');

            return $this->redirectToShop($request, 'shop-cart-drawer');
        }

        $listingId = (int) $request->request->get('listing_id');
        if ($listingId > 0) {
            $checkoutCart = $this->getCheckoutCart($request);
            unset($checkoutCart[$listingId]);
            $this->saveCheckoutCart($request, $checkoutCart);
            $this->addFlash('info', 'Annonce retiree du panier checkout.');
        } else {
            $this->clearCheckoutDraft($request);
            $this->addFlash('info', 'Panier checkout vide.');
        }

        return $this->redirectToShop($request, 'shop-cart-drawer');
    }

    #[Route('/boutique/wallet/topup', name: 'app_shop_wallet_topup', methods: ['POST'])]
    public function createWalletTopup(Request $request, ClientMarketplaceService $marketplaceService): RedirectResponse
    {
        $client = $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_wallet_topup', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton recharge est invalide.');

            return $this->redirectAfterWalletAction($request, 'shop-wallet');
        }

        try {
            $amount = (float) str_replace(',', '.', (string) $request->request->get('amount_money'));
            $provider = (string) $request->request->get('provider');
            $stripeCallbackUrls = $this->buildStripeTopupCallbackUrls();
            $topup = $marketplaceService->createTopup($client, $amount, $provider, [
                'success_url' => $stripeCallbackUrls['success_url'],
                'cancel_url' => $stripeCallbackUrls['cancel_url'],
            ]);

            $this->addFlash('success', sprintf(
                'Recharge initiee (#%d) via %s. Montant: %.3f, coins a crediter: %.3f (taux %.3f).',
                (int) $topup['idTopup'],
                (string) ($topup['provider'] ?? strtoupper($provider)),
                (float) ($topup['amountMoney'] ?? $amount),
                (float) $topup['coinAmount'],
                (float) ($topup['coinRate'] ?? $marketplaceService->getCoinRate())
            ));

            if (($topup['provider'] ?? '') === 'STRIPE' && is_string($topup['paymentUrl'] ?? null) && $topup['paymentUrl'] !== '') {
                return new RedirectResponse((string) $topup['paymentUrl']);
            }
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        return $this->redirectAfterWalletAction($request, 'shop-wallet');
    }

    #[Route('/boutique/wallet/topup/stripe/success/{topupId}', name: 'app_shop_wallet_topup_stripe_success', methods: ['GET'], requirements: ['topupId' => '\d+'])]
    public function stripeTopupSuccess(int $topupId, Request $request, ClientMarketplaceService $marketplaceService): RedirectResponse
    {
        $client = $this->requireClient();
        $sessionId = (string) $request->query->get('session_id', '');
        $reviewListingIdAfterResume = 0;

        try {
            // 1) Validation paiement Stripe.
            $confirmed = $marketplaceService->confirmStripeTopup($client, $topupId, $sessionId);
            if (($confirmed['already_paid'] ?? false) === true) {
                $this->addFlash('success', 'Paiement Stripe deja confirme.');
            } else {
                $this->addFlash('success', sprintf(
                    'Paiement Stripe confirme. Nouveau solde: %.3f coins.',
                    (float) ($confirmed['balanceAfter'] ?? 0.0)
                ));
            }

            // 2) Reprise automatique du checkout en attente (single ou panier).
            $pending = $this->getPendingBuy($request);
            if (is_array($pending) && $this->isPendingCartPayload($pending)) {
                $pendingCart = $this->normalizeCheckoutCart($pending['cart_items'] ?? []);
                if ($pendingCart === []) {
                    $this->clearPendingBuy($request);
                    $this->clearCheckoutDraft($request);
                } else {
                    $projectId = isset($pending['project_id']) && is_numeric($pending['project_id']) ? (int) $pending['project_id'] : null;
                    $delivery = is_array($pending['delivery'] ?? null) ? $pending['delivery'] : [];
                    $topupProvider = strtoupper((string) ($pending['topup_provider'] ?? 'STRIPE'));

                    $batch = $this->executeCheckoutCartBatch($client, $marketplaceService, $pendingCart, $projectId, $delivery);
                    $results = is_array($batch['results'] ?? null) ? $batch['results'] : [];
                    $remainingCart = $this->normalizeCheckoutCart($batch['remaining_cart'] ?? []);
                    $this->saveCheckoutCart($request, $remainingCart);

                    if ($results !== []) {
                        $this->pushBatchCheckoutFeedback($results, 'Checkout panier repris apres paiement Stripe.');
                        foreach ($results as $result) {
                            $candidateListingId = (int) ($result['listing_id'] ?? 0);
                            if ($candidateListingId > 0) {
                                $reviewListingIdAfterResume = $candidateListingId;

                                break;
                            }
                        }
                    }

                    $insufficient = $batch['insufficient'] ?? null;
                    if ($insufficient instanceof InsufficientWalletException) {
                        $requiredRemainingCoins = $this->estimateCheckoutCartSubtotal($marketplaceService, $client, $remainingCart);
                        $currentBalance = $insufficient->getCurrentBalance();
                        $missingRemainingCoins = round(max(
                            $insufficient->getMissingCoins(),
                            max(0.0, $requiredRemainingCoins - $currentBalance)
                        ), 3);
                        $this->savePendingBuy($request, $this->buildPendingCartPayload(
                            $marketplaceService,
                            $remainingCart,
                            $projectId,
                            $delivery,
                            $missingRemainingCoins,
                            $requiredRemainingCoins,
                            $currentBalance,
                            $topupProvider
                        ));
                        $this->addFlash('error', 'Paiement confirme mais solde encore insuffisant pour finaliser tout le panier.');
                    } else {
                        $error = $batch['error'] ?? null;
                        if ($error instanceof \Throwable) {
                            $this->savePendingBuy($request, $this->buildPendingCartPayload(
                                $marketplaceService,
                                $remainingCart,
                                $projectId,
                                $delivery,
                                0.0,
                                $this->estimateCheckoutCartSubtotal($marketplaceService, $client, $remainingCart),
                                (float) ($pending['current_balance'] ?? 0.0),
                                $topupProvider
                            ));
                            $this->addFlash('error', 'Paiement fait mais checkout panier non finalise: ' . $error->getMessage());
                        } else {
                            $this->clearPendingBuy($request);
                        }
                    }
                }
            } elseif (is_array($pending) && isset($pending['listing_id'], $pending['quantity'])) {
                try {
                    $result = $marketplaceService->buyListing(
                        $client,
                        (int) $pending['listing_id'],
                        (int) $pending['quantity'],
                        isset($pending['project_id']) && is_numeric($pending['project_id']) ? (int) $pending['project_id'] : null,
                        is_array($pending['delivery'] ?? null) ? $pending['delivery'] : []
                    );
                    $this->clearPendingBuy($request);
                    $this->pushCheckoutFeedback($result, 'Checkout repris apres paiement Stripe.');
                    $reviewListingIdAfterResume = (int) ($result['listing_id'] ?? 0);
                } catch (InsufficientWalletException $exception) {
                    $pending['missing_coins'] = $exception->getMissingCoins();
                    $pending['required_coins'] = $exception->getRequiredCoins();
                    $pending['current_balance'] = $exception->getCurrentBalance();
                    $pending['coin_rate'] = $marketplaceService->getCoinRate();
                    $pending['missing_money'] = $marketplaceService->coinsToMoney($exception->getMissingCoins());
                    $this->savePendingBuy($request, $pending);
                    $this->addFlash('error', 'Paiement confirme mais solde encore insuffisant pour finaliser le checkout.');
                } catch (\Throwable $throwable) {
                    $this->addFlash('error', 'Paiement fait mais checkout non finalise: ' . $throwable->getMessage());
                }
            }
        } catch (\Throwable $throwable) {
            $this->addFlash('error', 'Paiement Stripe non valide: ' . $throwable->getMessage());
        }

        if ($reviewListingIdAfterResume > 0) {
            $this->addFlash('info', 'Vous pouvez laisser votre avis dans la section Avis clients.');

            return $this->redirectToListingReviewPage($reviewListingIdAfterResume);
        }

        return $this->redirectToRoute('app_shop_wallet');
    }

    #[Route('/boutique/wallet/topup/stripe/cancel/{topupId}', name: 'app_shop_wallet_topup_stripe_cancel', methods: ['GET'], requirements: ['topupId' => '\d+'])]
    public function stripeTopupCancel(int $topupId): RedirectResponse
    {
        $this->requireClient();
        $this->addFlash('error', sprintf('Paiement Stripe annule pour le topup #%d.', $topupId));

        return $this->redirectToRoute('app_shop_wallet');
    }

    #[Route('/boutique/wallet/topup/confirm', name: 'app_shop_wallet_topup_confirm', methods: ['POST'])]
    public function confirmWalletTopup(Request $request, ClientMarketplaceService $marketplaceService): RedirectResponse
    {
        $client = $this->requireClient();
        $reviewListingIdAfterResume = 0;

        if (!$this->isCsrfTokenValid('shop_wallet_topup_confirm', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton confirmation recharge est invalide.');

            return $this->redirectAfterTopupAction($request, 'shop-wallet');
        }

        try {
            $topupId = (int) $request->request->get('topup_id');
            // Confirmation manuelle d une recharge puis reprise de checkout en attente.
            $confirmed = $marketplaceService->confirmTopup($client, $topupId);
            $this->addFlash('success', sprintf(
                'Recharge confirmee. Nouveau solde: %.3f coins.',
                (float) $confirmed['balanceAfter']
            ));

            $pending = $this->getPendingBuy($request);
            if (is_array($pending) && $this->isPendingCartPayload($pending)) {
                $pendingCart = $this->normalizeCheckoutCart($pending['cart_items'] ?? []);
                if ($pendingCart === []) {
                    $this->clearPendingBuy($request);
                    $this->clearCheckoutDraft($request);
                } else {
                    $projectId = isset($pending['project_id']) && is_numeric($pending['project_id']) ? (int) $pending['project_id'] : null;
                    $delivery = is_array($pending['delivery'] ?? null) ? $pending['delivery'] : [];
                    $topupProvider = strtoupper((string) ($pending['topup_provider'] ?? 'STRIPE'));

                    $batch = $this->executeCheckoutCartBatch($client, $marketplaceService, $pendingCart, $projectId, $delivery);
                    $results = is_array($batch['results'] ?? null) ? $batch['results'] : [];
                    $remainingCart = $this->normalizeCheckoutCart($batch['remaining_cart'] ?? []);
                    $this->saveCheckoutCart($request, $remainingCart);

                    if ($results !== []) {
                        $this->pushBatchCheckoutFeedback($results, 'Checkout panier repris avec succes.');
                        foreach ($results as $result) {
                            $candidateListingId = (int) ($result['listing_id'] ?? 0);
                            if ($candidateListingId > 0) {
                                $reviewListingIdAfterResume = $candidateListingId;

                                break;
                            }
                        }
                    }

                    $insufficient = $batch['insufficient'] ?? null;
                    if ($insufficient instanceof InsufficientWalletException) {
                        $requiredRemainingCoins = $this->estimateCheckoutCartSubtotal($marketplaceService, $client, $remainingCart);
                        $currentBalance = $insufficient->getCurrentBalance();
                        $missingRemainingCoins = round(max(
                            $insufficient->getMissingCoins(),
                            max(0.0, $requiredRemainingCoins - $currentBalance)
                        ), 3);
                        $this->savePendingBuy($request, $this->buildPendingCartPayload(
                            $marketplaceService,
                            $remainingCart,
                            $projectId,
                            $delivery,
                            $missingRemainingCoins,
                            $requiredRemainingCoins,
                            $currentBalance,
                            $topupProvider
                        ));

                        $this->addFlash('error', 'Recharge encore insuffisante pour finaliser tout le panier.');
                    } else {
                        $error = $batch['error'] ?? null;
                        if ($error instanceof \Throwable) {
                            $this->savePendingBuy($request, $this->buildPendingCartPayload(
                                $marketplaceService,
                                $remainingCart,
                                $projectId,
                                $delivery,
                                0.0,
                                $this->estimateCheckoutCartSubtotal($marketplaceService, $client, $remainingCart),
                                (float) ($pending['current_balance'] ?? 0.0),
                                $topupProvider
                            ));
                            $this->addFlash('error', 'Recharge faite mais checkout panier non finalise: ' . $error->getMessage());
                        } else {
                            $this->clearPendingBuy($request);
                        }
                    }
                }
            } elseif (is_array($pending) && isset($pending['listing_id'], $pending['quantity'])) {
                try {
                    $result = $marketplaceService->buyListing(
                        $client,
                        (int) $pending['listing_id'],
                        (int) $pending['quantity'],
                        isset($pending['project_id']) && is_numeric($pending['project_id']) ? (int) $pending['project_id'] : null,
                        is_array($pending['delivery'] ?? null) ? $pending['delivery'] : []
                    );
                    $this->clearPendingBuy($request);
                    $this->pushCheckoutFeedback($result, 'Checkout repris avec succes.');
                    $reviewListingIdAfterResume = (int) ($result['listing_id'] ?? 0);
                } catch (InsufficientWalletException $exception) {
                    $pending['missing_coins'] = $exception->getMissingCoins();
                    $pending['required_coins'] = $exception->getRequiredCoins();
                    $pending['current_balance'] = $exception->getCurrentBalance();
                    $pending['coin_rate'] = $marketplaceService->getCoinRate();
                    $pending['missing_money'] = $marketplaceService->coinsToMoney($exception->getMissingCoins());
                    $this->savePendingBuy($request, $pending);

                    $this->addFlash('error', 'Recharge encore insuffisante pour finaliser ce checkout.');
                } catch (\Throwable $throwable) {
                    $this->addFlash('error', 'Recharge faite mais checkout non finalise: ' . $throwable->getMessage());
                }
            }
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        if ($reviewListingIdAfterResume > 0) {
            $this->addFlash('info', 'Vous pouvez laisser votre avis dans la section Avis clients.');

            return $this->redirectToListingReviewPage($reviewListingIdAfterResume);
        }

        return $this->redirectAfterTopupAction($request, 'shop-orders');
    }

    #[Route('/boutique/delivery/confirm', name: 'app_shop_delivery_confirm', methods: ['POST'])]
    public function confirmDelivery(Request $request, ClientMarketplaceService $marketplaceService): RedirectResponse
    {
        $client = $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_delivery_confirm', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton confirmation livraison est invalide.');

            return $this->redirectToShop($request, 'shop-orders');
        }

        try {
            $marketplaceService->confirmDelivery($client, (int) $request->request->get('order_id'));
            $this->addFlash('success', 'Livraison confirmee. Vous pouvez maintenant laisser un avis.');
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        return $this->redirectToShop($request, 'shop-orders');
    }

    #[Route('/boutique/review/submit', name: 'app_shop_review_submit', methods: ['POST'])]
    public function submitReview(Request $request, ClientMarketplaceService $marketplaceService): RedirectResponse
    {
        $client = $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_review_submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton avis est invalide.');

            return $this->redirectAfterReviewAction($request, 'shop-orders');
        }

        try {
            $marketplaceService->submitReview(
                $client,
                (int) $request->request->get('listing_id'),
                (int) $request->request->get('order_id'),
                (int) $request->request->get('stars'),
                (string) $request->request->get('comment')
            );
            $this->addFlash('success', 'Avis enregistre avec succes.');
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        return $this->redirectAfterReviewAction($request, 'shop-orders');
    }

    #[Route('/boutique/reserve', name: 'app_shop_reserve', methods: ['POST'])]
    public function reserve(Request $request, ClientMiniShopService $miniShopService): RedirectResponse
    {
        $client = $this->requireClient();
        $csrfToken = (string) ($request->request->get('_token') ?: $request->request->get('_reserve_token'));

        if (!$this->isCsrfTokenValid('shop_reserve', $csrfToken)) {
            $this->addFlash('error', 'Le jeton de reservation est invalide.');

            return $this->redirectToShop($request, self::CART_DRAWER_FRAGMENT);
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

            return $this->redirectToShop($request, self::CART_DRAWER_FRAGMENT);
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

        return $this->redirectToShop($request, self::CART_DRAWER_FRAGMENT);
    }

    #[Route('/boutique/panier/update', name: 'app_shop_cart_update', methods: ['POST'])]
    public function updateCart(Request $request, ClientMiniShopService $miniShopService): RedirectResponse
    {
        $client = $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_cart_update', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton du panier est invalide.');

            return $this->redirectToShop($request, self::CART_DRAWER_FRAGMENT);
        }

        $resourceId = (int) $request->request->get('resource_id');
        $quantity = (int) $request->request->get('quantity');
        $cart = $this->getCart($request);

        if ($quantity <= 0) {
            unset($cart[$resourceId]);
            $this->saveCart($request, $cart);
            $this->addFlash('info', 'Article retire du panier.');

            return $this->redirectToShop($request, self::CART_DRAWER_FRAGMENT);
        }

        $resource = $miniShopService->getResourceSnapshot($client, $resourceId);
        if ($resource === null) {
            unset($cart[$resourceId]);
            $this->saveCart($request, $cart);
            $this->addFlash('error', 'Ressource introuvable.');

            return $this->redirectToShop($request, self::CART_DRAWER_FRAGMENT);
        }

        $availableStock = (int) $resource['available_stock'];
        if ($quantity > max(0, $availableStock)) {
            $this->addFlash('error', sprintf('Stock insuffisant pour %s. Disponible: %d', (string) $resource['nomRs'], $availableStock));

            return $this->redirectToShop($request);
        }

        $cart[$resourceId] = $quantity;
        $this->saveCart($request, $cart);
        $this->addFlash('success', 'Quantite du panier mise a jour.');

        return $this->redirectToShop($request, self::CART_DRAWER_FRAGMENT);
    }

    #[Route('/boutique/panier/remove', name: 'app_shop_cart_remove', methods: ['POST'])]
    public function removeFromCart(Request $request): RedirectResponse
    {
        $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_cart_remove', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton du panier est invalide.');

            return $this->redirectToShop($request, self::CART_DRAWER_FRAGMENT);
        }

        $resourceId = (int) $request->request->get('resource_id');
        $cart = $this->getCart($request);
        unset($cart[$resourceId]);
        $this->saveCart($request, $cart);
        $this->addFlash('info', 'Article retire du panier.');

        return $this->redirectToShop($request, self::CART_DRAWER_FRAGMENT);
    }

    #[Route('/boutique/panier/clear', name: 'app_shop_cart_clear', methods: ['POST'])]
    public function clearCart(Request $request): RedirectResponse
    {
        $this->requireClient();

        if (!$this->isCsrfTokenValid('shop_cart_clear', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton du panier est invalide.');

            return $this->redirectToShop($request, self::CART_DRAWER_FRAGMENT);
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

            return $this->redirectToShop($request, self::CART_DRAWER_FRAGMENT);
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

        return $this->redirectToShop($request, self::CART_DRAWER_FRAGMENT);
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
        $projectId = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
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
    private function extractDeliveryPayload(Request $request): array
    {
        return [
            'recipient_name' => (string) $request->request->get('recipient_name'),
            'governorate' => (string) $request->request->get('governorate'),
            'city' => (string) $request->request->get('city'),
            'address_line' => (string) $request->request->get('address_line'),
            'postal_code' => (string) $request->request->get('postal_code'),
            'phone' => (string) $request->request->get('phone'),
            'phone2' => (string) $request->request->get('phone2'),
            'delivery_note' => (string) $request->request->get('delivery_note'),
        ];
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
