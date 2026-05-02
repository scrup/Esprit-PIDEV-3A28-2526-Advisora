<?php
namespace App\Controller;
use App\Exception\InsufficientWalletException;
use App\Service\ClientMarketplaceService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
/**
 * Actions marketplace C2C du module boutique.
 *
 * Cette zone couvre la publication d'annonces, le checkout C2C, les avis et
 * les confirmations de livraison sans modifier la logique metier existante.
 */
trait ShopMarketplaceActionsTrait
{
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
    public function buyListing(
        Request $request,
        ClientMarketplaceService $marketplaceService,
        PaginatorInterface $paginator,
    ): Response {
        $client = $this->requireClient();
        $listingId = (int) $request->request->get('listing_id');
        if ($listingId <= 0) {
            $this->addFlash('error', 'Annonce checkout invalide.');

            return $this->redirectToRoute('app_shop');
        }

        try {
            $product = $marketplaceService->getOpenListingProduct($client, $listingId);
            $reviewData = $marketplaceService->getListingReviewData($client, $listingId);
        } catch (\Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());

            return $this->redirectToRoute('app_shop');
        }

        $maxQuantity = max(1, (int) ($product['qtyRemaining'] ?? 1));
        $pageData = $marketplaceService->buildPageData($client, '');
        $projects = is_array($pageData['projects'] ?? null) ? $pageData['projects'] : [];
        $requestedQuantity = max(1, (int) $request->request->get('quantity', 1));
        $defaultQuantity = min($requestedQuantity, $maxQuantity);

        $checkoutForm = $this->createCheckoutForm(
            $projects,
            true,
            $maxQuantity,
            [
                'quantity' => $defaultQuantity,
                'topup_provider' => strtoupper((string) $request->request->get('topup_provider', 'STRIPE')),
            ],
            'shop_market_buy'
        );
        $checkoutForm->handleRequest($request);

        if (!$checkoutForm->isSubmitted() || !$checkoutForm->isValid()) {
            $quantity = min(
                max(1, (int) ($checkoutForm->get('quantity')->getData() ?? $defaultQuantity)),
                $maxQuantity
            );
            $reviewsPage = $paginator->paginate(
                $reviewData['reviews'],
                max(1, (int) $request->query->get('review_page', 1)),
                4,
                ['pageParameterName' => 'review_page']
            );

            $this->addFlash('error', 'Veuillez corriger les champs du checkout.');

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

        $formData = $checkoutForm->getData();
        $quantity = min(max(1, (int) ($formData['quantity'] ?? $defaultQuantity)), $maxQuantity);
        $projectId = $formData['project_id'] ?? null;
        $projectId = is_numeric($projectId) ? (int) $projectId : null;
        if ($projectId !== null && $projectId <= 0) {
            $projectId = null;
        }
        $delivery = $this->extractDeliveryPayloadFromFormData($formData);
        $topupProvider = strtoupper((string) ($formData['topup_provider'] ?? 'STRIPE'));

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

    #[Route('/boutique/checkout/buy-cart', name: 'app_shop_market_buy_cart', methods: ['POST'])]
    public function buyCheckoutCart(Request $request, ClientMarketplaceService $marketplaceService): Response
    {
        $client = $this->requireClient();

        // Le "checkout panier" execute les annonces du panier C2C en sequence.
        // Chaque ligne cree sa propre commande/livraison cote metier.
        $checkoutCartState = $this->buildCheckoutCartState($request, $marketplaceService, $client);
        $checkoutItems = $checkoutCartState['items'];
        $pageData = $marketplaceService->buildPageData($client, '');
        $projects = is_array($pageData['projects'] ?? null) ? $pageData['projects'] : [];

        $checkoutForm = $this->createCheckoutForm(
            $projects,
            false,
            1,
            [
                'topup_provider' => strtoupper((string) $request->request->get('topup_provider', 'STRIPE')),
            ],
            'shop_market_buy_cart'
        );
        $checkoutForm->handleRequest($request);

        if (!$checkoutForm->isSubmitted() || !$checkoutForm->isValid()) {
            $this->addFlash('error', 'Veuillez corriger les champs du checkout panier.');

            return $this->render('front/shop/checkout_cart.html.twig', [
                'checkout_cart_items' => $checkoutItems,
                'checkout_cart_stats' => $checkoutCartState['stats'],
                'projects' => $projects,
                'wallet' => $pageData['wallet'] ?? ['balance' => 0.0, 'pending_topups' => [], 'coin_rate' => $marketplaceService->getCoinRate()],
                'checkout_form' => $checkoutForm->createView(),
            ]);
        }

        $formData = $checkoutForm->getData();
        $projectId = $formData['project_id'] ?? null;
        $projectId = is_numeric($projectId) ? (int) $projectId : null;
        if ($projectId !== null && $projectId <= 0) {
            $projectId = null;
        }
        $delivery = $this->extractDeliveryPayloadFromFormData($formData);
        $topupProvider = strtoupper((string) ($formData['topup_provider'] ?? 'STRIPE'));

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

        $walletData = $pageData['wallet'] ?? [];
        $walletBalance = round((float) ($walletData['balance'] ?? 0.0), 3);
        $requiredCoins = round((float) $checkoutCartState['stats']['subtotal'], 3);
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
        $results = $batch['results'];
        $reviewListingId = 0;
        $remainingCart = $this->normalizeCheckoutCart($batch['remaining_cart']);

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

}
