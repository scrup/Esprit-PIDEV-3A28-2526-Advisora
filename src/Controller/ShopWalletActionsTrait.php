<?php
namespace App\Controller;
use App\Exception\InsufficientWalletException;
use App\Service\ClientMarketplaceService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
/**
 * Actions de recharge wallet du shop.
 *
 * On garde ensemble la creation de topups, les callbacks Stripe et la reprise
 * des checkouts en attente apres credit du portefeuille.
 */
trait ShopWalletActionsTrait
{
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
                    $results = $batch['results'];
                    $remainingCart = $this->normalizeCheckoutCart($batch['remaining_cart']);
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
                    $results = $batch['results'];
                    $remainingCart = $this->normalizeCheckoutCart($batch['remaining_cart']);
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

}
