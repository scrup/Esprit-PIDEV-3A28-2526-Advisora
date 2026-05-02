<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\InsufficientWalletException;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ClientMarketplaceService
{
    /**
     * Service metier principal du marketplace C2C.
     *
     * Responsabilites:
     * - publication/annulation d annonces,
     * - achat avec transfert stock reserve + transfert wallet,
     * - creation commande/livraison + sync Fiabilo,
     * - topup wallet + reprise checkout.
     */
    private const LISTING_LISTED = 'LISTED';
    private const LISTING_CANCELLED = 'CANCELLED';
    private const LISTING_SOLD_OUT = 'SOLD_OUT';

    private const ORDER_CONFIRMED = 'CONFIRMED';

    private const DELIVERY_PREPARING = 'EN_PREPARATION';
    private const DELIVERY_SENT = 'ENVOYEE';
    private const DELIVERY_DELIVERED = 'LIVREE';

    private const TOPUP_PENDING = 'PENDING';
    private const TOPUP_PAID = 'PAID';

    private const TXN_TOPUP = 'TOPUP';
    private const TXN_BUY = 'SHOP_BUY';
    private const TXN_SELL = 'SHOP_SELL';
    private const DEFAULT_COIN_RATE = 10.0;
    private const FIABILO_API_URL = '';
    private const FIABILO_API_TOKEN = '';
    private const STRIPE_SECRET_KEY = '';
    private const STRIPE_CURRENCY = 'eur';

    private ?string $quantityColumnCache = null;
    private bool $quantityColumnDetected = false;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShopListingImageService $listingImageService,
    )
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPageData(User $client, string $search = '', string $openSort = 'recent'): array
    {
        // Point d entree principal pour alimenter les pages Twig du shop.
        $this->assertClient($client);
        $connection = $this->entityManager->getConnection();
        $clientId = (int) $client->getIdUser();
        $normalizedOpenSort = $this->normalizeOpenSort($openSort);

        $projects = $connection->fetchAllAssociative(
            'SELECT idProj, titleProj FROM project WHERE idClient = ? ORDER BY idProj DESC',
            [$clientId]
        );

        $walletBalance = $this->getWalletBalance($connection, $clientId);
        $pendingTopups = $this->fetchPendingTopups($connection, $clientId);

        $publishableReservations = $this->fetchPublishableReservations($connection, $clientId);
        $myListings = $this->fetchMyListings($connection, $clientId);
        $openListings = $this->fetchOpenListings($connection, $clientId, $search, $normalizedOpenSort);
        $orders = $this->fetchOrders($connection, $clientId);

        return [
            'market_search' => $search,
            'open_sort' => $normalizedOpenSort,
            'wallet' => [
                'balance' => $walletBalance,
                'pending_topups' => $pendingTopups,
                'coin_rate' => $this->getCoinRate(),
            ],
            'projects' => $projects,
            'publishable_reservations' => $publishableReservations,
            'my_listings' => $myListings,
            'open_listings' => $openListings,
            'orders' => $orders,
            'stats' => [
                'publishable_count' => count($publishableReservations),
                'publishable_units' => array_sum(array_map(static fn (array $row): int => (int) $row['publishable_qty'], $publishableReservations)),
                'my_listings_count' => count($myListings),
                'open_count' => count($openListings),
                'open_units' => array_sum(array_map(static fn (array $row): int => (int) $row['qtyRemaining'], $openListings)),
                'orders_count' => count($orders),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getOpenListingProduct(User $client, int $listingId): array
    {
        $this->assertClient($client);
        $clientId = (int) $client->getIdUser();

        if ($listingId <= 0) {
            throw new \InvalidArgumentException('Annonce invalide.');
        }

        $connection = $this->entityManager->getConnection();
        $row = $connection->fetchAssociative(
            '
            SELECT
                l.idListing,
                l.sellerUserId,
                l.idProj,
                l.idRs,
                l.qtyInitial,
                l.qtyRemaining,
                l.unitPrice,
                l.note,
                l.status,
                l.createdAt,
                l.updatedAt,
                r.nomRs AS resource_name,
                r.QuantiteRs AS resource_total_qty,
                r.prixRs AS resource_base_price,
                r.availabilityStatusRs AS resource_status,
                COALESCE(NULLIF(TRIM(cf.fournisseur), \'\'), NULLIF(TRIM(cf.nomFr), \'\'), \'Non renseigne\') AS supplier_name,
                TRIM(CONCAT(COALESCE(u.PrenomUser, \'\'), \' \', COALESCE(u.nomUser, \'\'))) AS seller_name,
                NULLIF(TRIM(COALESCE(u.EmailUser, \'\')), \'\') AS seller_email,
                p.titleProj AS source_project_title,
                COALESCE(NULLIF(l.imageUrl, \'\'), NULLIF(r.imageUrlRs, \'\'), NULLIF(r.thumbnailUrlRs, \'\')) AS image_url,
                NULLIF(r.imageUrlRs, \'\') AS image_url_rs,
                NULLIF(r.thumbnailUrlRs, \'\') AS thumbnail_url_rs,
                COALESCE(rv_stats.review_count, 0) AS review_count,
                COALESCE(rv_stats.rating_avg, 0) AS rating_avg
            FROM resource_market_listing l
            LEFT JOIN resource r ON r.idRs = l.idRs
            LEFT JOIN cataloguefournisseur cf ON cf.idFr = r.idFr
            LEFT JOIN `user` u ON u.idUser = l.sellerUserId
            LEFT JOIN project p ON p.idProj = l.idProj
            LEFT JOIN (
                SELECT idListing, COUNT(*) AS review_count, ROUND(AVG(stars), 2) AS rating_avg
                FROM resource_market_review
                GROUP BY idListing
            ) rv_stats ON rv_stats.idListing = l.idListing
            WHERE l.idListing = ? AND l.status = ? AND l.qtyRemaining > 0 AND l.sellerUserId <> ?
            LIMIT 1
            ',
            [$listingId, self::LISTING_LISTED, $clientId]
        );

        if ($row === false) {
            throw new \InvalidArgumentException('Annonce introuvable ou indisponible.');
        }

        $images = [];
        foreach (['image_url', 'image_url_rs', 'thumbnail_url_rs'] as $key) {
            $value = is_string($row[$key] ?? null) ? trim((string) $row[$key]) : '';
            if ($value !== '' && !in_array($value, $images, true)) {
                $images[] = $value;
            }
        }

        if ($images === []) {
            $images[] = $this->listingImageService->generateResourceFallbackPath(
                (int) ($row['idRs'] ?? 0),
                (string) ($row['resource_name'] ?? '')
            );
        }

        $row['image_url'] = $images[0];
        $row['images'] = $images;

        return $this->hydrateAccountDisplayNames($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function publishListing(
        User $client,
        int $projectId,
        int $resourceId,
        int $quantity,
        float $unitPrice,
        ?string $note,
        ?string $imageUrl = null,
        ?UploadedFile $uploadedImage = null
    ): array {
        $this->assertClient($client);
        $clientId = (int) $client->getIdUser();

        if ($projectId <= 0 || $resourceId <= 0) {
            throw new \InvalidArgumentException('Reservation source invalide.');
        }
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantite de publication invalide.');
        }
        if ($unitPrice < 0) {
            throw new \InvalidArgumentException('Prix unitaire invalide.');
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $this->lockOwnedProjectForUpdate($connection, $projectId, $clientId, 'Le projet ne vous appartient pas.');

            $reservedQuantity = $this->currentReservedForProjectResource($connection, $projectId, $resourceId);
            if ($reservedQuantity <= 0) {
                throw new \InvalidArgumentException('Aucune quantite reservee sur cette ressource.');
            }

            $alreadyListed = $this->listedRemainingForProjectResource($connection, $clientId, $projectId, $resourceId, null);
            $maxPublishable = max(0, $reservedQuantity - $alreadyListed);
            if ($quantity > $maxPublishable) {
                throw new \InvalidArgumentException(sprintf('Quantite vendable insuffisante. Maximum: %d', $maxPublishable));
            }

            $resolvedImageUrl = $this->resolveListingImageUrl($connection, $resourceId, $imageUrl, $uploadedImage);

            $connection->insert('resource_market_listing', [
                'sellerUserId' => $clientId,
                'idProj' => $projectId,
                'idRs' => $resourceId,
                'qtyInitial' => $quantity,
                'qtyRemaining' => $quantity,
                'unitPrice' => round($unitPrice, 3),
                'note' => $this->sanitizeText($note, 280),
                'imageUrl' => $resolvedImageUrl,
                'status' => self::LISTING_LISTED,
                'createdAt' => $this->now(),
                'updatedAt' => $this->now(),
            ]);

            $listingId = (int) $connection->lastInsertId();
            $connection->commit();

            return [
                'idListing' => $listingId,
                'resource_id' => $resourceId,
                'quantity' => $quantity,
                'unit_price' => round($unitPrice, 3),
            ];
        } catch (\Throwable $throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    public function cancelListing(User $client, int $listingId): void
    {
        $this->assertClient($client);
        $clientId = (int) $client->getIdUser();

        if ($listingId <= 0) {
            throw new \InvalidArgumentException('Annonce invalide.');
        }

        $updated = $this->entityManager->getConnection()->executeStatement(
            "UPDATE resource_market_listing
             SET status = ?, updatedAt = ?
             WHERE idListing = ? AND sellerUserId = ? AND status = ?",
            [self::LISTING_CANCELLED, $this->now(), $listingId, $clientId, self::LISTING_LISTED]
        );

        if ($updated <= 0) {
            throw new \InvalidArgumentException('Annonce introuvable ou deja fermee.');
        }
    }

    /**
     * @param array<string, mixed> $deliveryPayload
     *
     * @return array<string, mixed>
     */
    public function buyListing(
        User $client,
        int $listingId,
        int $quantity,
        ?int $buyerProjectId,
        array $deliveryPayload = []
    ): array {
        // Transaction atomique:
        // 1) lock annonce + wallets
        // 2) verifier stock/solde
        // 3) transferer coins + quantites reservees
        // 4) creer commande/livraison
        // 5) sync Fiabilo
        $this->assertClient($client);
        $buyerUserId = (int) $client->getIdUser();

        if ($listingId <= 0) {
            throw new \InvalidArgumentException('Annonce invalide.');
        }
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantite achat invalide.');
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $listing = $connection->fetchAssociative(
                'SELECT * FROM resource_market_listing WHERE idListing = ? FOR UPDATE',
                [$listingId]
            );

            if ($listing === false) {
                throw new \InvalidArgumentException('Annonce introuvable.');
            }

            if ((int) $listing['sellerUserId'] === $buyerUserId) {
                throw new \InvalidArgumentException('Vous ne pouvez pas acheter votre propre annonce.');
            }

            if ((string) $listing['status'] !== self::LISTING_LISTED) {
                throw new \InvalidArgumentException('Annonce indisponible.');
            }

            $remaining = (int) $listing['qtyRemaining'];
            if ($quantity > $remaining) {
                throw new \InvalidArgumentException('Quantite demandee superieure au disponible.');
            }

            $sellerUserId = (int) $listing['sellerUserId'];
            $sellerProjectId = (int) $listing['idProj'];
            $resourceId = (int) $listing['idRs'];
            $unitPrice = (float) $listing['unitPrice'];
            $totalPrice = round($unitPrice * $quantity, 3);
            $resolvedBuyerProjectId = $this->resolveOrCreateBuyerProject($connection, $buyerUserId, $buyerProjectId);

            $this->lockProjectPairForUpdate(
                $connection,
                $sellerProjectId,
                $sellerUserId,
                $resolvedBuyerProjectId,
                $buyerUserId
            );

            $sellerReserved = $this->currentReservedForProjectResource($connection, $sellerProjectId, $resourceId);
            $otherOpenListed = $this->listedRemainingForProjectResource(
                $connection,
                $sellerUserId,
                $sellerProjectId,
                $resourceId,
                $listingId
            );
            $transferableQuantity = max(0, $sellerReserved - $otherOpenListed);

            if ($quantity > $transferableQuantity) {
                // Synchronise une annonce devenue incoherente avec le stock reserve reel du vendeur.
                $correctedRemaining = max(0, min($remaining, $transferableQuantity));
                $correctedStatus = $correctedRemaining > 0 ? self::LISTING_LISTED : self::LISTING_SOLD_OUT;
                $mustSyncListing = $correctedRemaining !== $remaining || (string) $listing['status'] !== $correctedStatus;

                if ($mustSyncListing) {
                    $connection->update('resource_market_listing', [
                        'qtyRemaining' => $correctedRemaining,
                        'status' => $correctedStatus,
                        'updatedAt' => $this->now(),
                    ], ['idListing' => $listingId]);
                    $connection->commit();
                }

                if ($correctedRemaining <= 0) {
                    throw new \InvalidArgumentException(
                        'Annonce desynchronisee: le vendeur n a plus de stock reserve. L annonce a ete fermee automatiquement.'
                    );
                }

                throw new \InvalidArgumentException(sprintf(
                    'Quantite ajustee selon le stock reserve vendeur. Maximum transferable: %d',
                    $correctedRemaining
                ));
            }

            $buyerReserved = $this->currentReservedForProjectResource($connection, $resolvedBuyerProjectId, $resourceId);

            $walletBalances = $this->lockWalletBalances($connection, $buyerUserId, $sellerUserId);
            $buyerBalance = $walletBalances['buyer'];
            if ($buyerBalance + 0.000001 < $totalPrice) {
                throw new InsufficientWalletException(
                    round($totalPrice - $buyerBalance, 3),
                    $totalPrice,
                    $buyerBalance
                );
            }

            $buyerBalanceAfter = $this->updateWalletBalance($connection, $buyerUserId, -$totalPrice);
            $sellerBalanceAfter = $this->updateWalletBalance($connection, $sellerUserId, $totalPrice);

            $orderReference = sprintf('ORDER#%d', $listingId);
            $this->insertWalletTxn($connection, $buyerUserId, self::TXN_BUY, -$totalPrice, $buyerBalanceAfter, $orderReference);
            $this->insertWalletTxn($connection, $sellerUserId, self::TXN_SELL, $totalPrice, $sellerBalanceAfter, $orderReference);

            $this->replaceReservationQuantity($connection, $sellerProjectId, $resourceId, $sellerReserved - $quantity);
            $this->replaceReservationQuantity($connection, $resolvedBuyerProjectId, $resourceId, $buyerReserved + $quantity);

            $newRemaining = max(0, $remaining - $quantity);
            $newStatus = $newRemaining === 0 ? self::LISTING_SOLD_OUT : self::LISTING_LISTED;
            $connection->update('resource_market_listing', [
                'qtyRemaining' => $newRemaining,
                'status' => $newStatus,
                'updatedAt' => $this->now(),
            ], ['idListing' => $listingId]);

            $connection->insert('resource_market_order', [
                'idListing' => $listingId,
                'buyerUserId' => $buyerUserId,
                'sellerUserId' => $sellerUserId,
                'buyerProjectId' => $resolvedBuyerProjectId,
                'idRs' => $resourceId,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'totalPrice' => $totalPrice,
                'status' => self::ORDER_CONFIRMED,
                'createdAt' => $this->now(),
            ]);
            $orderId = (int) $connection->lastInsertId();

            $resourceName = (string) $connection->fetchOne('SELECT nomRs FROM resource WHERE idRs = ?', [$resourceId]);
            $recipientName = $this->resolveRecipientName($client, $deliveryPayload);
            $city = $this->resolveDeliveryCity($deliveryPayload);
            $addressLine = $this->resolveDeliveryAddress($deliveryPayload);
            $phone = $this->resolveDeliveryPhone($client, $deliveryPayload, 'phone');
            $phone2 = $this->resolveDeliveryPhone($client, $deliveryPayload, 'phone2');
            $governorate = $this->resolveDeliveryGovernorate($deliveryPayload, $city);
            $postalCode = $this->resolveDeliveryPostalCode($deliveryPayload);

            $connection->insert('resource_market_delivery', [
                'idOrder' => $orderId,
                'buyerUserId' => $buyerUserId,
                'recipientName' => $recipientName,
                'city' => $city,
                'addressLine' => $addressLine,
                'phone' => $phone,
                'phone2' => $phone2,
                'resourceName' => $resourceName,
                'quantity' => $quantity,
                'totalPrice' => $totalPrice,
                'status' => self::DELIVERY_PREPARING,
                'provider' => 'FIABILO',
                'trackingCode' => null,
                'labelUrl' => null,
                'providerMessage' => 'En attente envoi Fiabilo',
                'createdAt' => $this->now(),
                'updatedAt' => $this->now(),
            ]);
            $deliveryId = (int) $connection->lastInsertId();

            $connection->commit();

            $deliverySync = $this->syncDeliveryWithFiabilo(
                $connection,
                $deliveryId,
                $orderId,
                [
                    'prix' => number_format($totalPrice, 4, '.', ''),
                    'nom' => $recipientName,
                    'gouvernerat' => $governorate,
                    'ville' => $city,
                    'adresse' => $addressLine,
                    'cp' => $postalCode ?? '',
                    'tel' => $phone ?? '',
                    'tel2' => $phone2 ?? '',
                    'designation' => $resourceName,
                    'nb_article' => (string) $quantity,
                    'msg' => $this->resolveDeliveryMessage($deliveryPayload, $orderId, $resourceName, $quantity),
                    'token' => self::FIABILO_API_TOKEN,
                ]
            );

            return [
                'idOrder' => $orderId,
                'idDelivery' => $deliveryId,
                'listing_id' => $listingId,
                'resource_id' => $resourceId,
                'quantity' => $quantity,
                'total_price' => $totalPrice,
                'buyer_balance_after' => $buyerBalanceAfter,
                'delivery_sync' => $deliverySync,
            ];
        } catch (\Throwable $throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function createTopup(User $client, float $moneyAmount, string $provider = 'MANUAL', array $context = []): array
    {
        $this->assertClient($client);
        $clientId = (int) $client->getIdUser();

        if ($moneyAmount <= 0) {
            throw new \InvalidArgumentException('Montant de recharge invalide.');
        }

        $coinRate = $this->getCoinRate();
        $coins = round($moneyAmount * $coinRate, 3);
        $resolvedProvider = $this->resolveTopupProvider($provider);
        $connection = $this->entityManager->getConnection();
        $email = $this->sanitizeText($client->getEmailUser(), 180);

        $connection->insert('resource_wallet_topup', [
            'idUser' => $clientId,
            'provider' => $resolvedProvider,
            'amountMoney' => round($moneyAmount, 3),
            'coinAmount' => $coins,
            'status' => self::TOPUP_PENDING,
            'externalRef' => null,
            'paymentUrl' => null,
            'note' => $this->buildTopupNote($resolvedProvider),
            'createdAt' => $this->now(),
            'confirmedAt' => null,
        ]);
        $topupId = (int) $connection->lastInsertId();

        $paymentUrl = null;
        $externalRef = null;

        if ($resolvedProvider === 'STRIPE') {
            $successUrl = $this->sanitizeText($context['success_url'] ?? null, 480);
            $cancelUrl = $this->sanitizeText($context['cancel_url'] ?? null, 480);
            if (is_string($successUrl)) {
                $successUrl = str_replace('__TOPUP__', (string) $topupId, $successUrl);
            }
            if (is_string($cancelUrl)) {
                $cancelUrl = str_replace('__TOPUP__', (string) $topupId, $cancelUrl);
            }

            if ($successUrl !== null && $successUrl !== '' && $cancelUrl !== null && $cancelUrl !== '') {
                try {
                    $stripeSession = $this->createStripeCheckoutSession(
                        $topupId,
                        $clientId,
                        round($moneyAmount, 3),
                        $coins,
                        $email,
                        $successUrl,
                        $cancelUrl
                    );
                    $paymentUrl = $stripeSession['url'] ?? null;
                    $externalRef = $stripeSession['id'];

                    $connection->update('resource_wallet_topup', [
                        'externalRef' => $externalRef,
                        'paymentUrl' => $paymentUrl,
                        'note' => 'Stripe checkout cree',
                    ], ['idTopup' => $topupId]);
                } catch (\Throwable $throwable) {
                    $connection->update('resource_wallet_topup', [
                        'note' => 'Stripe checkout error: ' . ($this->sanitizeText($throwable->getMessage(), 170) ?: 'inconnue'),
                    ], ['idTopup' => $topupId]);
                }
            }
        }

        return [
            'idTopup' => $topupId,
            'amountMoney' => round($moneyAmount, 3),
            'coinAmount' => $coins,
            'coinRate' => $coinRate,
            'provider' => $resolvedProvider,
            'paymentUrl' => $paymentUrl,
            'externalRef' => $externalRef,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmTopup(User $client, int $topupId): array
    {
        $this->assertClient($client);
        $clientId = (int) $client->getIdUser();

        if ($topupId <= 0) {
            throw new \InvalidArgumentException('Recharge invalide.');
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $topup = $connection->fetchAssociative(
                'SELECT * FROM resource_wallet_topup WHERE idTopup = ? FOR UPDATE',
                [$topupId]
            );

            if ($topup === false || (int) $topup['idUser'] !== $clientId) {
                throw new \InvalidArgumentException('Recharge introuvable.');
            }

            if ((string) $topup['status'] !== self::TOPUP_PENDING) {
                throw new \InvalidArgumentException('Cette recharge est deja traitee.');
            }

            $coins = round((float) $topup['coinAmount'], 3);
            $balanceAfter = $this->updateWalletBalance($connection, $clientId, $coins);

            $connection->update('resource_wallet_topup', [
                'status' => self::TOPUP_PAID,
                'confirmedAt' => $this->now(),
                'note' => 'Recharge confirmee depuis la boutique',
            ], ['idTopup' => $topupId]);

            $this->insertWalletTxn(
                $connection,
                $clientId,
                self::TXN_TOPUP,
                $coins,
                $balanceAfter,
                sprintf('TOPUP#%d', $topupId)
            );

            $connection->commit();

            return [
                'idTopup' => $topupId,
                'coinAmount' => $coins,
                'balanceAfter' => $balanceAfter,
            ];
        } catch (\Throwable $throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmStripeTopup(User $client, int $topupId, string $sessionId): array
    {
        $this->assertClient($client);
        $clientId = (int) $client->getIdUser();
        $resolvedSessionId = $this->sanitizeText($sessionId, 190);

        if ($topupId <= 0 || $resolvedSessionId === null || $resolvedSessionId === '') {
            throw new \InvalidArgumentException('Validation Stripe invalide.');
        }

        $connection = $this->entityManager->getConnection();
        $topup = $connection->fetchAssociative(
            'SELECT idTopup, idUser, provider, status, externalRef FROM resource_wallet_topup WHERE idTopup = ?',
            [$topupId]
        );

        if ($topup === false || (int) $topup['idUser'] !== $clientId) {
            throw new \InvalidArgumentException('Topup introuvable.');
        }

        if (strtoupper((string) $topup['provider']) !== 'STRIPE') {
            throw new \InvalidArgumentException('Ce topup n utilise pas Stripe.');
        }

        if ((string) $topup['status'] === self::TOPUP_PAID) {
            return [
                'idTopup' => $topupId,
                'coinAmount' => 0.0,
                'balanceAfter' => $this->getWalletBalance($connection, $clientId),
                'already_paid' => true,
            ];
        }

        if (is_string($topup['externalRef'] ?? null) && trim((string) $topup['externalRef']) !== '' && trim((string) $topup['externalRef']) !== $resolvedSessionId) {
            throw new \InvalidArgumentException('Session Stripe non associee a ce topup.');
        }

        $session = $this->retrieveStripeCheckoutSession($resolvedSessionId);
        $paymentStatus = strtolower((string) ($session['payment_status'] ?? ''));
        $status = strtolower((string) ($session['status'] ?? ''));

        if ($paymentStatus !== 'paid' || !in_array($status, ['complete', 'open'], true)) {
            throw new \InvalidArgumentException('Paiement Stripe non confirme.');
        }

        return $this->confirmTopup($client, $topupId);
    }

    public function confirmDelivery(User $client, int $orderId): void
    {
        $this->assertClient($client);
        $clientId = (int) $client->getIdUser();

        if ($orderId <= 0) {
            throw new \InvalidArgumentException('Commande invalide.');
        }

        $updated = $this->entityManager->getConnection()->executeStatement(
            'UPDATE resource_market_delivery d
             INNER JOIN resource_market_order o ON o.idOrder = d.idOrder
             SET d.status = ?, d.updatedAt = ?
             WHERE d.idOrder = ? AND o.buyerUserId = ? AND d.status <> ?',
            [self::DELIVERY_DELIVERED, $this->now(), $orderId, $clientId, self::DELIVERY_DELIVERED]
        );

        if ($updated <= 0) {
            throw new \InvalidArgumentException('Livraison introuvable ou deja confirmee.');
        }
    }

    public function getCoinRate(): float
    {
        $raw = $_ENV['SHOP_COIN_RATE'] ?? $_SERVER['SHOP_COIN_RATE'] ?? getenv('SHOP_COIN_RATE') ?: null;
        $parsed = is_numeric($raw) ? (float) $raw : self::DEFAULT_COIN_RATE;

        return $parsed > 0 ? round($parsed, 3) : self::DEFAULT_COIN_RATE;
    }

    public function coinsToMoney(float $coins): float
    {
        if ($coins <= 0) {
            return 0.0;
        }

        return round($coins / $this->getCoinRate(), 3);
    }

    public function submitReview(User $client, int $listingId, ?int $orderId, int $stars, ?string $comment): void
    {
        $this->assertClient($client);
        $clientId = (int) $client->getIdUser();

        if ($stars < 1 || $stars > 5) {
            throw new \InvalidArgumentException('La note doit etre comprise entre 1 et 5.');
        }

        $connection = $this->entityManager->getConnection();
        $resolvedListingId = $listingId > 0 ? $listingId : 0;
        $resolvedOrderId = null;

        if ($orderId !== null && $orderId > 0) {
            $order = $connection->fetchAssociative(
                'SELECT o.idOrder, o.idListing, d.status AS delivery_status
                 FROM resource_market_order o
                 LEFT JOIN resource_market_delivery d ON d.idOrder = o.idOrder
                 WHERE o.idOrder = ? AND o.buyerUserId = ?
                 LIMIT 1',
                [$orderId, $clientId]
            );

            if ($order === false) {
                throw new \InvalidArgumentException('Commande invalide.');
            }

            $orderListingId = (int) ($order['idListing'] ?? 0);
            if ($resolvedListingId <= 0) {
                $resolvedListingId = $orderListingId;
            }

            if ($resolvedListingId !== $orderListingId) {
                throw new \InvalidArgumentException('Annonce et commande incompatibles.');
            }

            if ((string) ($order['delivery_status'] ?? '') === self::DELIVERY_DELIVERED) {
                $resolvedOrderId = (int) $order['idOrder'];
            }
        }

        if ($resolvedListingId <= 0) {
            throw new \InvalidArgumentException('Annonce invalide.');
        }

        $listing = $connection->fetchAssociative(
            'SELECT idListing, sellerUserId FROM resource_market_listing WHERE idListing = ? LIMIT 1',
            [$resolvedListingId]
        );
        if ($listing === false) {
            throw new \InvalidArgumentException('Annonce introuvable.');
        }

        if ((int) ($listing['sellerUserId'] ?? 0) === $clientId) {
            throw new \InvalidArgumentException('Vous ne pouvez pas laisser un avis sur votre propre annonce.');
        }

        $alreadyReviewed = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM resource_market_review WHERE idListing = ? AND reviewerUserId = ?',
            [$resolvedListingId, $clientId]
        );
        if ($alreadyReviewed > 0) {
            throw new \InvalidArgumentException('Vous avez deja laisse un avis pour cette annonce.');
        }

        $connection->insert('resource_market_review', [
            'idListing' => $resolvedListingId,
            'stars' => $stars,
            'comment' => $this->sanitizeText($comment, 300),
            'createdAt' => $this->now(),
            'idOrder' => $resolvedOrderId,
            'reviewerUserId' => $clientId,
        ]);
    }

    /**
     * @return array{
     *     summary: array<string, mixed>,
     *     reviews: array<int, array<string, mixed>>,
     *     client_review: array<string, mixed>|null,
     *     reviewable_order: array<string, mixed>|null
     * }
     */
    public function getListingReviewData(User $client, int $listingId): array
    {
        $this->assertClient($client);
        $clientId = (int) $client->getIdUser();

        if ($listingId <= 0) {
            throw new \InvalidArgumentException('Annonce invalide.');
        }

        $connection = $this->entityManager->getConnection();

        $summaryRow = $connection->fetchAssociative(
            'SELECT COUNT(*) AS review_count, ROUND(AVG(stars), 2) AS rating_avg
             FROM resource_market_review
             WHERE idListing = ?',
            [$listingId]
        );

        $distributionRows = $connection->fetchAllAssociative(
            'SELECT stars, COUNT(*) AS total
             FROM resource_market_review
             WHERE idListing = ?
             GROUP BY stars',
            [$listingId]
        );

        $distribution = [
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
        ];
        foreach ($distributionRows as $row) {
            $stars = (int) ($row['stars'] ?? 0);
            if ($stars >= 1 && $stars <= 5) {
                $distribution[$stars] = (int) ($row['total'] ?? 0);
            }
        }

        $reviews = $connection->fetchAllAssociative(
            '
            SELECT
                rv.idReview,
                rv.idOrder,
                rv.reviewerUserId,
                rv.stars,
                rv.comment,
                rv.createdAt,
                TRIM(CONCAT(COALESCE(u.PrenomUser, \'\'), \' \', COALESCE(u.nomUser, \'\'))) AS reviewer_name,
                NULLIF(TRIM(COALESCE(u.EmailUser, \'\')), \'\') AS reviewer_email
            FROM resource_market_review rv
            LEFT JOIN `user` u ON u.idUser = rv.reviewerUserId
            WHERE rv.idListing = ?
            ORDER BY rv.createdAt DESC, rv.idReview DESC
            ',
            [$listingId]
        );

        foreach ($reviews as $index => $review) {
            $reviews[$index] = $this->hydrateAccountDisplayNames($review);
        }

        $clientReview = $connection->fetchAssociative(
            '
            SELECT
                rv.idReview,
                rv.idOrder,
                rv.reviewerUserId,
                rv.stars,
                rv.comment,
                rv.createdAt,
                TRIM(CONCAT(COALESCE(u.PrenomUser, \'\'), \' \', COALESCE(u.nomUser, \'\'))) AS reviewer_name,
                NULLIF(TRIM(COALESCE(u.EmailUser, \'\')), \'\') AS reviewer_email
            FROM resource_market_review rv
            LEFT JOIN `user` u ON u.idUser = rv.reviewerUserId
            WHERE rv.idListing = ? AND rv.reviewerUserId = ?
            ORDER BY rv.createdAt DESC, rv.idReview DESC
            LIMIT 1
            ',
            [$listingId, $clientId]
        );

        $reviewableOrder = $connection->fetchAssociative(
            '
            SELECT o.idOrder, o.createdAt
            FROM resource_market_order o
            INNER JOIN resource_market_delivery d ON d.idOrder = o.idOrder
            LEFT JOIN resource_market_review rv ON rv.idListing = o.idListing AND rv.reviewerUserId = ?
            WHERE o.idListing = ?
              AND o.buyerUserId = ?
              AND d.status = ?
              AND rv.idReview IS NULL
            ORDER BY o.createdAt DESC, o.idOrder DESC
            LIMIT 1
            ',
            [$clientId, $listingId, $clientId, self::DELIVERY_DELIVERED]
        );

        return [
            'summary' => [
                'review_count' => (int) ($summaryRow['review_count'] ?? 0),
                'rating_avg' => round((float) ($summaryRow['rating_avg'] ?? 0), 2),
                'distribution' => $distribution,
            ],
            'reviews' => $reviews,
            'client_review' => $clientReview !== false ? $this->hydrateAccountDisplayNames($clientReview) : null,
            'reviewable_order' => $reviewableOrder !== false ? $reviewableOrder : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPendingTopups(Connection $connection, int $clientId): array
    {
        return $connection->fetchAllAssociative(
            'SELECT idTopup, provider, amountMoney, coinAmount, status, externalRef, paymentUrl, note, createdAt
             FROM resource_wallet_topup
             WHERE idUser = ? AND status = ?
             ORDER BY createdAt DESC, idTopup DESC
             LIMIT 20',
            [$clientId, self::TOPUP_PENDING]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPublishableReservations(Connection $connection, int $clientId): array
    {
        $quantityExpression = $this->quantityAggregateSql($connection, 'pr');

        $reservations = $connection->fetchAllAssociative(
            '
            SELECT
                p.idProj AS project_id,
                p.titleProj AS project_title,
                r.idRs AS resource_id,
                r.nomRs AS resource_name,
                COALESCE(NULLIF(TRIM(cf.fournisseur), \'\'), NULLIF(TRIM(cf.nomFr), \'\'), \'Non renseigne\') AS supplier_name,
                COALESCE(NULLIF(r.imageUrlRs, \'\'), NULLIF(r.thumbnailUrlRs, \'\')) AS image_url,
                ' . $quantityExpression . ' AS reserved_qty
            FROM project p
            INNER JOIN project_resources pr ON pr.project_id = p.idProj
            INNER JOIN resource r ON r.idRs = pr.resource_id
            LEFT JOIN cataloguefournisseur cf ON cf.idFr = r.idFr
            WHERE p.idClient = ?
            GROUP BY p.idProj, p.titleProj, r.idRs, r.nomRs, cf.fournisseur, cf.nomFr, r.imageUrlRs, r.thumbnailUrlRs
            ORDER BY p.idProj DESC, r.idRs DESC
            ',
            [$clientId]
        );

        $listedRows = $connection->fetchAllAssociative(
            'SELECT idProj, idRs, COALESCE(SUM(qtyRemaining), 0) AS listed_qty
             FROM resource_market_listing
             WHERE sellerUserId = ? AND status = ?
             GROUP BY idProj, idRs',
            [$clientId, self::LISTING_LISTED]
        );

        $listedByReservation = [];
        foreach ($listedRows as $row) {
            $listedByReservation[$row['idProj'] . ':' . $row['idRs']] = (int) $row['listed_qty'];
        }

        $publishable = [];
        foreach ($reservations as $reservation) {
            $key = $reservation['project_id'] . ':' . $reservation['resource_id'];
            $listedQty = $listedByReservation[$key] ?? 0;
            $reservedQty = (int) $reservation['reserved_qty'];
            $publishableQty = max(0, $reservedQty - $listedQty);

            if ($publishableQty <= 0) {
                continue;
            }

            $reservation['listed_qty'] = $listedQty;
            $reservation['publishable_qty'] = $publishableQty;
            $publishable[] = $this->ensureResolvedImageUrl($reservation, 'resource_id', 'resource_name');
        }

        return $publishable;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchOpenListings(Connection $connection, int $clientId, string $search, string $sort): array
    {
        $sql = '
            SELECT
                l.idListing,
                l.sellerUserId,
                l.idProj,
                l.idRs,
                l.qtyInitial,
                l.qtyRemaining,
                l.unitPrice,
                l.note,
                l.status,
                l.createdAt,
                l.updatedAt,
                r.nomRs AS resource_name,
                COALESCE(NULLIF(TRIM(cf.fournisseur), \'\'), NULLIF(TRIM(cf.nomFr), \'\'), \'Non renseigne\') AS supplier_name,
                TRIM(CONCAT(COALESCE(u.PrenomUser, \'\'), \' \', COALESCE(u.nomUser, \'\'))) AS seller_name,
                NULLIF(TRIM(COALESCE(u.EmailUser, \'\')), \'\') AS seller_email,
                COALESCE(NULLIF(l.imageUrl, \'\'), NULLIF(r.imageUrlRs, \'\'), NULLIF(r.thumbnailUrlRs, \'\')) AS image_url,
                COALESCE(rv_stats.review_count, 0) AS review_count,
                COALESCE(rv_stats.rating_avg, 0) AS rating_avg
            FROM resource_market_listing l
            LEFT JOIN resource r ON r.idRs = l.idRs
            LEFT JOIN cataloguefournisseur cf ON cf.idFr = r.idFr
            LEFT JOIN `user` u ON u.idUser = l.sellerUserId
            LEFT JOIN (
                SELECT idListing, COUNT(*) AS review_count, ROUND(AVG(stars), 2) AS rating_avg
                FROM resource_market_review
                GROUP BY idListing
            ) rv_stats ON rv_stats.idListing = l.idListing
            WHERE l.status = ? AND l.qtyRemaining > 0 AND l.sellerUserId <> ?
        ';
        $parameters = [self::LISTING_LISTED, $clientId];

        $normalizedSearch = trim($search);
        if ($normalizedSearch !== '') {
            $like = '%' . strtolower($normalizedSearch) . '%';
            $sql .= '
                AND (
                    LOWER(COALESCE(r.nomRs, \'\')) LIKE ?
                    OR LOWER(COALESCE(cf.fournisseur, cf.nomFr, \'\')) LIKE ?
                    OR LOWER(TRIM(CONCAT(COALESCE(u.PrenomUser, \'\'), \' \', COALESCE(u.nomUser, \'\')))) LIKE ?
                    OR LOWER(COALESCE(l.status, \'\')) LIKE ?
                )
            ';
            $parameters[] = $like;
            $parameters[] = $like;
            $parameters[] = $like;
            $parameters[] = $like;
        }

        $sql .= match ($sort) {
            'price_asc' => ' ORDER BY l.unitPrice ASC, l.createdAt DESC, l.idListing DESC',
            'price_desc' => ' ORDER BY l.unitPrice DESC, l.createdAt DESC, l.idListing DESC',
            'qty_desc' => ' ORDER BY l.qtyRemaining DESC, l.createdAt DESC, l.idListing DESC',
            default => ' ORDER BY l.createdAt DESC, l.idListing DESC',
        };

        return array_map(
            fn (array $row): array => $this->ensureResolvedImageUrl(
                $this->hydrateAccountDisplayNames($row),
                'idRs',
                'resource_name'
            ),
            $connection->fetchAllAssociative($sql, $parameters)
        );
    }

    private function normalizeOpenSort(string $sort): string
    {
        $normalized = strtolower(trim($sort));

        return in_array($normalized, ['recent', 'price_asc', 'price_desc', 'qty_desc'], true)
            ? $normalized
            : 'recent';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchMyListings(Connection $connection, int $clientId): array
    {
        return array_map(
            fn (array $row): array => $this->ensureResolvedImageUrl($row, 'idRs', 'resource_name'),
            $connection->fetchAllAssociative(
            '
            SELECT
                l.idListing,
                l.idProj,
                l.idRs,
                l.qtyInitial,
                l.qtyRemaining,
                l.unitPrice,
                l.note,
                l.status,
                l.createdAt,
                l.updatedAt,
                r.nomRs AS resource_name,
                COALESCE(NULLIF(TRIM(cf.fournisseur), \'\'), NULLIF(TRIM(cf.nomFr), \'\'), \'Non renseigne\') AS supplier_name,
                COALESCE(NULLIF(l.imageUrl, \'\'), NULLIF(r.imageUrlRs, \'\'), NULLIF(r.thumbnailUrlRs, \'\')) AS image_url
            FROM resource_market_listing l
            LEFT JOIN resource r ON r.idRs = l.idRs
            LEFT JOIN cataloguefournisseur cf ON cf.idFr = r.idFr
            WHERE l.sellerUserId = ?
            ORDER BY l.updatedAt DESC, l.idListing DESC
            ',
            [$clientId]
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchOrders(Connection $connection, int $clientId): array
    {
        $rows = $connection->fetchAllAssociative(
            '
            SELECT
                o.idOrder,
                o.idListing,
                o.buyerUserId,
                o.sellerUserId,
                o.buyerProjectId,
                o.idRs,
                o.quantity,
                o.unitPrice,
                o.totalPrice,
                o.status AS order_status,
                o.createdAt AS order_created_at,
                l.status AS listing_status,
                r.nomRs AS resource_name,
                COALESCE(NULLIF(TRIM(cf.fournisseur), \'\'), NULLIF(TRIM(cf.nomFr), \'\'), \'Non renseigne\') AS supplier_name,
                TRIM(CONCAT(COALESCE(b.PrenomUser, \'\'), \' \', COALESCE(b.nomUser, \'\'))) AS buyer_name,
                NULLIF(TRIM(COALESCE(b.EmailUser, \'\')), \'\') AS buyer_email,
                TRIM(CONCAT(COALESCE(s.PrenomUser, \'\'), \' \', COALESCE(s.nomUser, \'\'))) AS seller_name,
                NULLIF(TRIM(COALESCE(s.EmailUser, \'\')), \'\') AS seller_email,
                p.titleProj AS buyer_project_title,
                d.idDelivery,
                d.status AS delivery_status,
                d.provider,
                d.trackingCode,
                d.recipientName,
                d.city,
                d.addressLine,
                d.phone,
                d.updatedAt AS delivery_updated_at,
                rv.idReview,
                rv.stars,
                rv.comment,
                rv.createdAt AS review_created_at,
                COALESCE(NULLIF(l.imageUrl, \'\'), NULLIF(r.imageUrlRs, \'\'), NULLIF(r.thumbnailUrlRs, \'\')) AS image_url,
                CASE WHEN o.buyerUserId = ? THEN \'BUYER\' ELSE \'SELLER\' END AS order_role
            FROM resource_market_order o
            LEFT JOIN resource_market_listing l ON l.idListing = o.idListing
            LEFT JOIN resource r ON r.idRs = o.idRs
            LEFT JOIN cataloguefournisseur cf ON cf.idFr = r.idFr
            LEFT JOIN project p ON p.idProj = o.buyerProjectId
            LEFT JOIN `user` b ON b.idUser = o.buyerUserId
            LEFT JOIN `user` s ON s.idUser = o.sellerUserId
            LEFT JOIN resource_market_delivery d ON d.idOrder = o.idOrder
            LEFT JOIN resource_market_review rv ON rv.idReview = (
                SELECT rv2.idReview
                FROM resource_market_review rv2
                WHERE rv2.idListing = o.idListing AND rv2.reviewerUserId = ?
                ORDER BY rv2.createdAt DESC, rv2.idReview DESC
                LIMIT 1
            )
            WHERE o.buyerUserId = ? OR o.sellerUserId = ?
            ORDER BY o.createdAt DESC, o.idOrder DESC
            LIMIT 40
            ',
            [$clientId, $clientId, $clientId, $clientId]
        );

        foreach ($rows as $index => $row) {
            $isBuyer = (string) $row['order_role'] === 'BUYER';
            $isDelivered = (string) ($row['delivery_status'] ?? '') === self::DELIVERY_DELIVERED;
            $rows[$index] = $this->hydrateAccountDisplayNames($row);
            $rows[$index]['can_confirm_delivery'] = $isBuyer && !$isDelivered;
            $rows[$index]['can_review'] = $isBuyer && $isDelivered && empty($row['idReview']);
            $rows[$index]['has_review'] = !empty($row['idReview']);
        }

        return $rows;
    }

    private function assertClient(User $client): void
    {
        if (strtolower((string) $client->getRoleUser()) !== 'client') {
            throw new \InvalidArgumentException('Le mini-shop est reserve au role CLIENT.');
        }
    }

    private function assertProjectBelongsToClient(Connection $connection, int $projectId, int $clientId): void
    {
        $resolvedProjectId = (int) $connection->fetchOne(
            'SELECT idProj FROM project WHERE idProj = ? AND idClient = ?',
            [$projectId, $clientId]
        );

        if ($resolvedProjectId <= 0) {
            throw new \InvalidArgumentException('Le projet ne vous appartient pas.');
        }
    }

    private function lockOwnedProjectForUpdate(
        Connection $connection,
        int $projectId,
        int $clientId,
        string $errorMessage
    ): void {
        $resolvedProjectId = (int) $connection->fetchOne(
            'SELECT idProj FROM project WHERE idProj = ? AND idClient = ? FOR UPDATE',
            [$projectId, $clientId]
        );

        if ($resolvedProjectId <= 0) {
            throw new \InvalidArgumentException($errorMessage);
        }
    }

    private function lockProjectPairForUpdate(
        Connection $connection,
        int $sellerProjectId,
        int $sellerClientId,
        int $buyerProjectId,
        int $buyerClientId
    ): void {
        $projectLocks = [
            [
                'project_id' => $sellerProjectId,
                'client_id' => $sellerClientId,
                'error_message' => 'Le projet vendeur est introuvable.',
            ],
            [
                'project_id' => $buyerProjectId,
                'client_id' => $buyerClientId,
                'error_message' => 'Le projet acheteur est introuvable.',
            ],
        ];

        usort($projectLocks, static fn (array $left, array $right): int => $left['project_id'] <=> $right['project_id']);

        foreach ($projectLocks as $index => $projectLock) {
            if ($index > 0 && $projectLock['project_id'] === $projectLocks[$index - 1]['project_id']) {
                if ($projectLock['client_id'] !== $projectLocks[$index - 1]['client_id']) {
                    throw new \InvalidArgumentException('Conflit detecte entre les projets vendeur et acheteur.');
                }

                continue;
            }

            $this->lockOwnedProjectForUpdate(
                $connection,
                (int) $projectLock['project_id'],
                (int) $projectLock['client_id'],
                (string) $projectLock['error_message']
            );
        }
    }

    private function resolveOrCreateBuyerProject(Connection $connection, int $clientId, ?int $projectId): int
    {
        if ($projectId !== null && $projectId > 0) {
            $this->assertProjectBelongsToClient($connection, $projectId, $clientId);

            return $projectId;
        }

        $latestProjectId = (int) $connection->fetchOne(
            'SELECT idProj FROM project WHERE idClient = ? ORDER BY idProj DESC LIMIT 1',
            [$clientId]
        );

        if ($latestProjectId > 0) {
            return $latestProjectId;
        }

        $now = $this->now();
        $connection->insert('project', [
            'titleProj' => 'Marketplace Ressources',
            'descriptionProj' => 'Projet cree automatiquement pour achat marketplace',
            'budgetProj' => 0.0,
            'typeProj' => 'RESOURCE_MARKET',
            'stateProj' => 'PENDING',
            'createdAtProj' => $now,
            'updatedAtProj' => $now,
            'avancementProj' => 0.0,
            'idClient' => $clientId,
        ]);

        return (int) $connection->lastInsertId();
    }

    private function currentReservedForProjectResource(Connection $connection, int $projectId, int $resourceId): int
    {
        $quantityColumn = $this->detectQuantityColumn($connection);
        $sql = $quantityColumn === null
            ? 'SELECT COUNT(*) FROM project_resources WHERE project_id = ? AND resource_id = ?'
            : 'SELECT COALESCE(SUM(' . $quantityColumn . '), 0) FROM project_resources WHERE project_id = ? AND resource_id = ?';

        return (int) $connection->fetchOne($sql, [$projectId, $resourceId]);
    }

    private function listedRemainingForProjectResource(
        Connection $connection,
        int $sellerUserId,
        int $projectId,
        int $resourceId,
        ?int $excludedListingId
    ): int {
        $sql = 'SELECT COALESCE(SUM(qtyRemaining), 0)
                FROM resource_market_listing
                WHERE sellerUserId = ?
                  AND idProj = ?
                  AND idRs = ?
                  AND status = ?';
        $parameters = [$sellerUserId, $projectId, $resourceId, self::LISTING_LISTED];

        if ($excludedListingId !== null) {
            $sql .= ' AND idListing <> ?';
            $parameters[] = $excludedListingId;
        }

        return (int) $connection->fetchOne($sql, $parameters);
    }

    private function replaceReservationQuantity(Connection $connection, int $projectId, int $resourceId, int $newQuantity): void
    {
        $connection->executeStatement(
            'DELETE FROM project_resources WHERE project_id = ? AND resource_id = ?',
            [$projectId, $resourceId]
        );

        if ($newQuantity <= 0) {
            return;
        }

        $quantityColumn = $this->detectQuantityColumn($connection);
        if ($quantityColumn === null) {
            for ($index = 0; $index < $newQuantity; $index++) {
                $connection->insert('project_resources', [
                    'project_id' => $projectId,
                    'resource_id' => $resourceId,
                ]);
            }

            return;
        }

        $connection->insert('project_resources', [
            'project_id' => $projectId,
            'resource_id' => $resourceId,
            $quantityColumn => $newQuantity,
        ]);
    }

    private function getWalletBalance(Connection $connection, int $userId): float
    {
        $balance = $connection->fetchOne(
            'SELECT balanceCoins FROM resource_wallet_account WHERE idUser = ?',
            [$userId]
        );

        return $balance === false ? 0.0 : round((float) $balance, 3);
    }

    private function lockWalletBalance(Connection $connection, int $userId): float
    {
        $balance = $connection->fetchOne(
            'SELECT balanceCoins FROM resource_wallet_account WHERE idUser = ? FOR UPDATE',
            [$userId]
        );

        if ($balance !== false) {
            return round((float) $balance, 3);
        }

        $connection->insert('resource_wallet_account', [
            'idUser' => $userId,
            'balanceCoins' => 0.0,
            'updatedAt' => $this->now(),
        ]);

        return 0.0;
    }

    /**
     * @return array{buyer: float, seller: float}
     */
    private function lockWalletBalances(Connection $connection, int $buyerUserId, int $sellerUserId): array
    {
        $orderedUserIds = [$buyerUserId, $sellerUserId];
        sort($orderedUserIds);

        $balancesByUser = [];
        foreach ($orderedUserIds as $userId) {
            if (array_key_exists($userId, $balancesByUser)) {
                continue;
            }

            $balancesByUser[$userId] = $this->lockWalletBalance($connection, $userId);
        }

        return [
            'buyer' => round((float) ($balancesByUser[$buyerUserId] ?? 0.0), 3),
            'seller' => round((float) ($balancesByUser[$sellerUserId] ?? 0.0), 3),
        ];
    }

    private function updateWalletBalance(Connection $connection, int $userId, float $deltaCoins): float
    {
        $current = $this->lockWalletBalance($connection, $userId);
        $next = round($current + $deltaCoins, 3);

        if ($next < -0.000001) {
            throw new \RuntimeException('Le wallet ne peut pas devenir negatif.');
        }

        $connection->update('resource_wallet_account', [
            'balanceCoins' => max(0.0, $next),
            'updatedAt' => $this->now(),
        ], ['idUser' => $userId]);

        return max(0.0, $next);
    }

    private function insertWalletTxn(
        Connection $connection,
        int $userId,
        string $txnType,
        float $amountCoins,
        float $balanceAfter,
        ?string $ref
    ): void {
        $connection->insert('resource_wallet_txn', [
            'idUser' => $userId,
            'txnType' => $txnType,
            'amountCoins' => round($amountCoins, 3),
            'balanceAfter' => round($balanceAfter, 3),
            'ref' => $this->sanitizeText($ref, 100),
            'createdAt' => $this->now(),
        ]);
    }

    private function quantityAggregateSql(Connection $connection, string $alias): string
    {
        $quantityColumn = $this->detectQuantityColumn($connection);

        if ($quantityColumn !== null) {
            return 'COALESCE(SUM(' . $alias . '.' . $quantityColumn . '), 0)';
        }

        return 'COUNT(*)';
    }

    private function detectQuantityColumn(Connection $connection): ?string
    {
        if ($this->quantityColumnDetected) {
            return $this->quantityColumnCache;
        }

        $this->quantityColumnDetected = true;
        $columns = $connection->fetchFirstColumn(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_resources'"
        );

        foreach ($columns as $column) {
            $normalized = strtolower((string) $column);
            if (in_array($normalized, ['quantite', 'quantity', 'qty', 'qtyallocated'], true)) {
                return $this->quantityColumnCache = (string) $column;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $deliveryPayload
     */
    private function resolveRecipientName(User $buyer, array $deliveryPayload): string
    {
        $value = $this->sanitizeText($deliveryPayload['recipient_name'] ?? null, 120);
        if ($value !== null && $value !== '') {
            return $value;
        }

        $fullName = trim((string) $buyer->getPrenomUser() . ' ' . (string) $buyer->getNomUser());

        return $fullName !== '' ? $fullName : 'Client';
    }

    /**
     * @param array<string, mixed> $deliveryPayload
     */
    private function resolveDeliveryCity(array $deliveryPayload): string
    {
        return $this->sanitizeText($deliveryPayload['city'] ?? null, 120) ?: 'Tunis';
    }

    /**
     * @param array<string, mixed> $deliveryPayload
     */
    private function resolveDeliveryAddress(array $deliveryPayload): string
    {
        return $this->sanitizeText($deliveryPayload['address_line'] ?? null, 220) ?: 'Adresse a confirmer';
    }

    /**
     * @param array<string, mixed> $deliveryPayload
     */
    private function resolveDeliveryGovernorate(array $deliveryPayload, string $fallbackCity): string
    {
        $governorate = $this->sanitizeText($deliveryPayload['governorate'] ?? null, 120);

        if (is_string($governorate) && $governorate !== '') {
            return $governorate;
        }

        return $fallbackCity !== '' ? $fallbackCity : 'Tunis';
    }

    /**
     * @param array<string, mixed> $deliveryPayload
     */
    private function resolveDeliveryPostalCode(array $deliveryPayload): ?string
    {
        $postalCode = $this->sanitizeText($deliveryPayload['postal_code'] ?? null, 20);

        return $postalCode !== '' ? $postalCode : null;
    }

    /**
     * @param array<string, mixed> $deliveryPayload
     */
    private function resolveDeliveryMessage(array $deliveryPayload, int $orderId, string $resourceName, int $quantity): string
    {
        $customMessage = $this->sanitizeText($deliveryPayload['delivery_note'] ?? null, 220);
        if (is_string($customMessage) && $customMessage !== '') {
            return $customMessage;
        }

        return sprintf('Commande #%d - %s x%d', $orderId, $resourceName !== '' ? $resourceName : 'Ressource', $quantity);
    }

    /**
     * @param array<string, mixed> $deliveryPayload
     */
    private function resolveDeliveryPhone(User $buyer, array $deliveryPayload, string $field): ?string
    {
        $resolved = $this->sanitizeText($deliveryPayload[$field] ?? null, 40);

        if ($resolved !== null && $resolved !== '') {
            return $resolved;
        }

        if ($field === 'phone') {
            return $this->sanitizeText($buyer->getNumTelUser(), 40);
        }

        return null;
    }

    /**
     * @return array{id:string,url:?string}
     */
    private function createStripeCheckoutSession(
        int $topupId,
        int $clientId,
        float $amountMoney,
        float $coinAmount,
        ?string $email,
        string $successUrl,
        string $cancelUrl
    ): array {
        if (!class_exists(\Stripe\Stripe::class)) {
            throw new \RuntimeException('Stripe SDK introuvable.');
        }

        $amountCents = max(1, (int) round($amountMoney * 100));

        \Stripe\Stripe::setApiKey(self::STRIPE_SECRET_KEY);
        $session = \Stripe\Checkout\Session::create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'success_url' => $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'customer_email' => $email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => self::STRIPE_CURRENCY,
                    'unit_amount' => $amountCents,
                    'product_data' => [
                        'name' => sprintf('Topup Wallet #%d', $topupId),
                        'description' => sprintf('Credit %.3f coins', $coinAmount),
                    ],
                ],
            ]],
            'metadata' => [
                'shop_topup_id' => (string) $topupId,
                'shop_client_id' => (string) $clientId,
                'shop_coin_amount' => (string) $coinAmount,
            ],
        ]);

        return [
            'id' => (string) $session->id,
            'url' => isset($session->url) ? (string) $session->url : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function retrieveStripeCheckoutSession(string $sessionId): array
    {
        if (!class_exists(\Stripe\Stripe::class)) {
            throw new \RuntimeException('Stripe SDK introuvable.');
        }

        \Stripe\Stripe::setApiKey(self::STRIPE_SECRET_KEY);
        $session = \Stripe\Checkout\Session::retrieve($sessionId);

        return $session->toArray();
    }

    private function resolveTopupProvider(string $provider): string
    {
        $normalized = strtoupper($this->sanitizeText($provider, 32) ?: 'MANUAL');
        $allowed = ['STRIPE', 'FLOUCI', 'D17', 'MANUAL', 'AUTO_CHECKOUT'];

        return in_array($normalized, $allowed, true) ? $normalized : 'MANUAL';
    }

    private function buildTopupNote(string $provider): string
    {
        return match ($provider) {
            'STRIPE' => 'Stripe checkout cree',
            'FLOUCI' => 'Mode simple (config API manquante).',
            'D17' => 'D17 checkout cree',
            'AUTO_CHECKOUT' => 'Recharge automatique apres echec checkout',
            default => 'Recharge initiee depuis la boutique',
        };
    }

    /**
     * @param array<string, string> $fiabiloPayload
     *
     * @return array{success:bool,message:string,tracking_code:?string,label_url:?string,raw_response:?string}
     */
    private function syncDeliveryWithFiabilo(Connection $connection, int $deliveryId, int $orderId, array $fiabiloPayload): array
    {
        try {
            $fiabiloResponse = $this->callFiabiloApi($fiabiloPayload);
        } catch (\Throwable $throwable) {
            $fiabiloResponse = [
                'success' => false,
                'message' => 'Erreur transport Fiabilo: ' . $throwable->getMessage(),
                'tracking_code' => null,
                'label_url' => null,
                'raw_response' => null,
            ];
        }

        $message = $this->sanitizeText($fiabiloResponse['message'], 250) ?: 'Erreur Fiabilo';
        $trackingCode = $this->sanitizeText($fiabiloResponse['tracking_code'], 120);
        $labelUrl = $this->sanitizeText($fiabiloResponse['label_url'], 480);

        if ($fiabiloResponse['success']) {
            if ($trackingCode === null || $trackingCode === '') {
                $trackingCode = sprintf('FIABILO-%d-%d', $orderId, $deliveryId);
            }

            $connection->update('resource_market_delivery', [
                'status' => self::DELIVERY_SENT,
                'trackingCode' => $trackingCode,
                'labelUrl' => $labelUrl,
                'providerMessage' => $message,
                'updatedAt' => $this->now(),
            ], ['idDelivery' => $deliveryId]);
        } else {
            $connection->update('resource_market_delivery', [
                'providerMessage' => $message,
                'updatedAt' => $this->now(),
            ], ['idDelivery' => $deliveryId]);
        }

        return [
            'success' => $fiabiloResponse['success'],
            'message' => $message,
            'tracking_code' => $trackingCode,
            'label_url' => $labelUrl,
            'raw_response' => $fiabiloResponse['raw_response'],
        ];
    }

    /**
     * @param array<string, string> $payload
     *
     * @return array{success:bool,message:string,tracking_code:?string,label_url:?string,raw_response:?string}
     */
    private function callFiabiloApi(array $payload): array
    {
        $encoded = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
        $responseBody = null;
        $statusCode = null;

        if (function_exists('curl_init')) {
            $curl = curl_init(self::FIABILO_API_URL);
            if ($curl === false) {
                throw new \RuntimeException('Initialisation cURL impossible.');
            }

            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $encoded,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json, text/plain, */*',
                ],
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 20,
            ]);

            $raw = curl_exec($curl);
            if ($raw === false) {
                $error = curl_error($curl);
                curl_close($curl);
                throw new \RuntimeException('Echec appel Fiabilo: ' . $error);
            }

            $responseBody = (string) $raw;
            $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json, text/plain, */*\r\n",
                    'content' => $encoded,
                    'timeout' => 20,
                ],
            ]);

            $raw = @file_get_contents(self::FIABILO_API_URL, false, $context);
            $responseBody = is_string($raw) ? $raw : '';

            $statusCode = 0;
            $headers = $http_response_header;
            foreach ($headers as $headerLine) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d{3})/i', (string) $headerLine, $matches) === 1) {
                    $statusCode = (int) $matches[1];
                    break;
                }
            }
        }

        $decoded = $this->decodeFiabiloResponse($responseBody);
        $isSuccess = $decoded['success'];
        $message = $decoded['message'];
        $trackingCode = $decoded['tracking_code'];
        $labelUrl = $decoded['label_url'];

        if ($statusCode >= 400) {
            $isSuccess = false;
            $message = 'HTTP ' . $statusCode . ' - ' . ($message !== '' ? $message : 'Reponse Fiabilo invalide');
        }

        return [
            'success' => $isSuccess,
            'message' => $message !== '' ? $message : ($isSuccess ? 'Fiabilo accepte la livraison.' : 'Fiabilo refuse la livraison.'),
            'tracking_code' => $trackingCode,
            'label_url' => $labelUrl,
            'raw_response' => $responseBody,
        ];
    }

    /**
     * @return array{success:bool,message:string,tracking_code:?string,label_url:?string}
     */
    private function decodeFiabiloResponse(?string $responseBody): array
    {
        $body = is_string($responseBody) ? trim($responseBody) : '';
        if ($body === '') {
            return [
                'success' => false,
                'message' => 'Reponse Fiabilo vide.',
                'tracking_code' => null,
                'label_url' => null,
            ];
        }

        $decodedJson = json_decode($body, true);
        if (is_array($decodedJson)) {
            return $this->extractFiabiloFields($decodedJson);
        }

        $asQuery = [];
        parse_str(str_replace([';', "\n"], ['&', '&'], $body), $asQuery);

        $normalizedQuery = [];
        foreach ($asQuery as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $normalizedQuery[$key] = $value;
        }

        if ($normalizedQuery !== []) {
            return $this->extractFiabiloFields($normalizedQuery);
        }

        if (preg_match('/status\s*[:=]\s*1/i', $body) === 1) {
            return [
                'success' => true,
                'message' => 'Fiabilo accepte la livraison.',
                'tracking_code' => null,
                'label_url' => null,
            ];
        }

        return [
            'success' => false,
            'message' => $this->sanitizeText($body, 230) ?: 'Reponse Fiabilo non exploitable.',
            'tracking_code' => null,
            'label_url' => null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{success:bool,message:string,tracking_code:?string,label_url:?string}
     */
    private function extractFiabiloFields(array $data): array
    {
        $statusRaw = $data['status'] ?? $data['success'] ?? $data['ok'] ?? null;
        $statusNormalized = strtolower(trim((string) $statusRaw));
        $isSuccess = in_array($statusNormalized, ['1', 'true', 'ok', 'success', 'yes'], true);

        $message = (string) ($data['message'] ?? $data['msg'] ?? $data['detail'] ?? $data['error'] ?? '');
        $trackingCode = (string) ($data['tracking'] ?? $data['tracking_code'] ?? $data['trackingCode'] ?? $data['code_suivi'] ?? '');
        $labelUrl = (string) ($data['label_url'] ?? $data['labelUrl'] ?? $data['tracking_url'] ?? $data['url'] ?? $data['lien'] ?? '');

        return [
            'success' => $isSuccess,
            'message' => $this->sanitizeText($message, 230) ?: ($isSuccess ? 'Fiabilo accepte la livraison.' : 'Fiabilo refuse la livraison.'),
            'tracking_code' => $this->sanitizeText($trackingCode, 120),
            'label_url' => $this->sanitizeText($labelUrl, 480),
        ];
    }

    private function resolveListingImageUrl(
        Connection $connection,
        int $resourceId,
        ?string $customImageUrl,
        ?UploadedFile $uploadedImage = null
    ): ?string
    {
        $resource = $connection->fetchAssociative(
            '
            SELECT
                TRIM(COALESCE(nomRs, \'\')) AS resource_name
            FROM resource
            WHERE idRs = ?
            LIMIT 1
            ',
            [$resourceId]
        ) ?: [];

        $resourceName = trim((string) ($resource['resource_name'] ?? ''));
        if ($resourceName === '') {
            $resourceName = sprintf('Ressource %d', $resourceId);
        }

        if ($uploadedImage instanceof UploadedFile) {
            return $this->listingImageService->storeUploadedImage($uploadedImage, $resourceName);
        }

        $custom = $this->sanitizeText($customImageUrl, 480);
        if ($this->isAcceptedImageUrl($custom)) {
            return $custom;
        }

        return $this->listingImageService->generateResourceFallbackPath($resourceId, $resourceName);
    }

    private function isAcceptedImageUrl(?string $value): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        if (str_starts_with($value, '/')) {
            return true;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function ensureResolvedImageUrl(array $row, string $resourceIdKey, string $resourceNameKey): array
    {
        $resourceId = (int) ($row[$resourceIdKey] ?? 0);
        if ($resourceId <= 0) {
            return $row;
        }

        $currentImageUrl = is_string($row['image_url'] ?? null) ? trim((string) $row['image_url']) : null;
        if ($this->isAcceptedImageUrl($currentImageUrl)) {
            return $row;
        }

        $resourceName = trim((string) ($row[$resourceNameKey] ?? ''));
        if ($resourceName === '') {
            $resourceName = sprintf('Ressource %d', $resourceId);
        }

        $row['image_url'] = $this->listingImageService->generateResourceFallbackPath($resourceId, $resourceName);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function hydrateAccountDisplayNames(array $row): array
    {
        if (array_key_exists('seller_name', $row) || array_key_exists('seller_email', $row) || array_key_exists('sellerUserId', $row)) {
            $row['seller_name'] = $this->resolveAccountDisplayName(
                $row['seller_name'] ?? null,
                $row['seller_email'] ?? null,
                isset($row['sellerUserId']) ? (int) $row['sellerUserId'] : null
            );
        }

        if (array_key_exists('buyer_name', $row) || array_key_exists('buyer_email', $row) || array_key_exists('buyerUserId', $row)) {
            $row['buyer_name'] = $this->resolveAccountDisplayName(
                $row['buyer_name'] ?? null,
                $row['buyer_email'] ?? null,
                isset($row['buyerUserId']) ? (int) $row['buyerUserId'] : null
            );
        }

        if (array_key_exists('reviewer_name', $row) || array_key_exists('reviewer_email', $row) || array_key_exists('reviewerUserId', $row)) {
            $row['reviewer_name'] = $this->resolveAccountDisplayName(
                $row['reviewer_name'] ?? null,
                $row['reviewer_email'] ?? null,
                isset($row['reviewerUserId']) ? (int) $row['reviewerUserId'] : null
            );
        }

        return $row;
    }

    private function resolveAccountDisplayName(mixed $fullName, mixed $email, ?int $userId): string
    {
        $normalizedFullName = trim((string) $fullName);
        if ($this->isMeaningfulAccountDisplayName($normalizedFullName)) {
            return $normalizedFullName;
        }

        $emailAlias = $this->extractEmailAccountAlias(is_string($email) ? $email : null);
        if ($emailAlias !== null) {
            return $emailAlias;
        }

        return $userId !== null && $userId > 0 ? sprintf('Compte #%d', $userId) : 'Client';
    }

    private function isMeaningfulAccountDisplayName(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, ['client', 'test client', 'user local', 'local user'], true)) {
            return false;
        }

        return preg_match('/^(one|two|three|four|five|six|seven|eight|nine|ten)\s+client$/', $normalized) !== 1;
    }

    private function extractEmailAccountAlias(?string $email): ?string
    {
        if (!is_string($email)) {
            return null;
        }

        $normalizedEmail = trim($email);
        if ($normalizedEmail === '' || !str_contains($normalizedEmail, '@')) {
            return null;
        }

        $localPart = trim((string) strstr($normalizedEmail, '@', true));
        if (strlen($localPart) < 2) {
            return null;
        }

        $readable = trim(preg_replace('/\s+/', ' ', preg_replace('/[._-]+/', ' ', $localPart) ?? '') ?? '');
        if ($readable === '') {
            return null;
        }

        return ucwords($readable);
    }

    private function sanitizeText(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        return substr($trimmed, 0, $maxLength);
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
