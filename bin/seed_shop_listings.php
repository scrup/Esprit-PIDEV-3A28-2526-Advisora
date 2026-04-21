<?php

declare(strict_types=1);

use App\Entity\User;
use App\Kernel;
use App\ResourceShopBundle\Service\ClientMarketplaceService;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!isset($_SERVER['APP_ENV']) && !isset($_ENV['APP_ENV'])) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

$maxListings = isset($argv[1]) ? max(1, (int) $argv[1]) : 15;

$kernel = new Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$doctrine = $container->get('doctrine');
$entityManager = $doctrine->getManager();
$connection = $entityManager->getConnection();
$marketplaceService = $container->get(ClientMarketplaceService::class);

$clientRows = $connection->fetchAllAssociative(
    "SELECT idUser, CONCAT(COALESCE(PrenomUser,''), ' ', COALESCE(nomUser,'')) AS full_name
     FROM user
     WHERE LOWER(roleUser) = 'client'
     ORDER BY idUser ASC"
);

$created = 0;
$attempted = 0;

echo sprintf("Seed annonces test (max=%d)\n", $maxListings);

foreach ($clientRows as $clientRow) {
    if ($created >= $maxListings) {
        break;
    }

    $clientId = (int) ($clientRow['idUser'] ?? 0);
    if ($clientId <= 0) {
        continue;
    }

    /** @var User|null $client */
    $client = $entityManager->getRepository(User::class)->find($clientId);
    if (!$client instanceof User) {
        continue;
    }

    try {
        $data = $marketplaceService->buildPageData($client, '');
    } catch (\Throwable $exception) {
        echo sprintf("- client #%d: skip (%s)\n", $clientId, $exception->getMessage());
        continue;
    }

    $publishableReservations = is_array($data['publishable_reservations'] ?? null)
        ? $data['publishable_reservations']
        : [];

    if ($publishableReservations === []) {
        continue;
    }

    foreach ($publishableReservations as $reservation) {
        if ($created >= $maxListings) {
            break 2;
        }

        $projectId = (int) ($reservation['project_id'] ?? 0);
        $resourceId = (int) ($reservation['resource_id'] ?? 0);
        $publishableQty = (int) ($reservation['publishable_qty'] ?? 0);
        if ($projectId <= 0 || $resourceId <= 0 || $publishableQty <= 0) {
            continue;
        }

        $attempted++;

        $quantity = min(2, $publishableQty);
        if ($quantity <= 0) {
            continue;
        }

        $basePrice = (float) $connection->fetchOne(
            'SELECT COALESCE(prixRs, 0) FROM resources WHERE idRs = ?',
            [$resourceId]
        );
        $unitPrice = round(max(1.0, ($basePrice > 0 ? $basePrice * 0.55 : 50.0)), 3);
        $note = 'Annonce test seed shop';

        try {
            $result = $marketplaceService->publishListing(
                $client,
                $projectId,
                $resourceId,
                $quantity,
                $unitPrice,
                $note,
                null
            );

            $created++;
            echo sprintf(
                "+ listing #%d created | seller=%d | project=%d | resource=%d | qty=%d | price=%.3f\n",
                (int) ($result['idListing'] ?? 0),
                $clientId,
                $projectId,
                $resourceId,
                $quantity,
                $unitPrice
            );
        } catch (\Throwable $exception) {
            echo sprintf(
                "- publish fail | seller=%d | project=%d | resource=%d | reason=%s\n",
                $clientId,
                $projectId,
                $resourceId,
                $exception->getMessage()
            );
        }
    }
}

$listedCount = (int) $connection->fetchOne(
    'SELECT COUNT(*) FROM resource_market_listing WHERE status = ? AND qtyRemaining > 0',
    ['LISTED']
);

echo sprintf(
    "Done. attempted=%d created=%d open_listed_now=%d\n",
    $attempted,
    $created,
    $listedCount
);

$kernel->shutdown();
