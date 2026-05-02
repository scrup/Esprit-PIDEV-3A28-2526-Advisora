<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Service\ClientMiniShopService;
use App\Service\ClientMarketplaceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:shop:seed-listings',
    description: 'Publie automatiquement des annonces test via la logique metier du shop.'
)]
final class ShopSeedListingsCommand extends Command
{
    private const LISTING_STATUS_LISTED = 'LISTED';
    private const SEED_PROJECT_TITLE = 'Seed Boutique Test';
    private const SEED_PROJECT_DESCRIPTION = 'Projet seed auto pour publier des annonces de test';
    private const SEED_PROJECT_TYPE = 'RESOURCE';
    private const SEED_PROJECT_STATE = 'PENDING';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClientMarketplaceService $marketplaceService,
        private readonly ClientMiniShopService $miniShopService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('max', InputArgument::OPTIONAL, 'Nombre max d annonces a creer', 15);
        $this->addOption('per-client', null, InputOption::VALUE_REQUIRED, 'Nombre max d annonces par client', '2');
        $this->addOption(
            'ensure-reservations',
            null,
            InputOption::VALUE_NONE,
            'Cree des reservations seed (logique mini-shop) si un client n a rien de publiable'
        );
        $this->addOption(
            'reserve-lines',
            null,
            InputOption::VALUE_REQUIRED,
            'Nombre max de lignes de reservation a creer par client lorsque --ensure-reservations est active',
            '2'
        );
        $this->addOption(
            'reserve-qty',
            null,
            InputOption::VALUE_REQUIRED,
            'Quantite reservee par ligne de reservation seed',
            '2'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $maxListings = max(1, (int) $input->getArgument('max'));
        $maxPerClient = max(1, (int) $input->getOption('per-client'));
        $ensureReservations = (bool) $input->getOption('ensure-reservations');
        $reserveLines = max(1, (int) $input->getOption('reserve-lines'));
        $reserveQty = max(1, (int) $input->getOption('reserve-qty'));
        $connection = $this->entityManager->getConnection();

        $clientRows = $connection->fetchAllAssociative(
            "SELECT idUser, CONCAT(COALESCE(PrenomUser,''), ' ', COALESCE(nomUser,'')) AS full_name
             FROM user
             WHERE LOWER(roleUser) = 'client'
             ORDER BY idUser ASC"
        );

        $created = 0;
        $attempted = 0;
        $io->title(sprintf(
            'Seed annonces test (max=%d | per-client=%d | ensure-reservations=%s)',
            $maxListings,
            $maxPerClient,
            $ensureReservations ? 'yes' : 'no'
        ));

        foreach ($clientRows as $clientRow) {
            if ($created >= $maxListings) {
                break;
            }

            $clientId = (int) ($clientRow['idUser'] ?? 0);
            if ($clientId <= 0) {
                continue;
            }

            /** @var User|null $client */
            $client = $this->entityManager->getRepository(User::class)->find($clientId);
            if (!$client instanceof User) {
                continue;
            }

            $publishableReservations = $this->loadPublishableReservations($client, $io);

            if ($publishableReservations === [] && $ensureReservations) {
                $seedProjectId = $this->getOrCreateSeedProjectId($clientId);
                $reservedLines = $this->seedReservationsForClient(
                    $client,
                    $seedProjectId,
                    $reserveLines,
                    $reserveQty,
                    $io
                );

                if ($reservedLines > 0) {
                    $publishableReservations = $this->loadPublishableReservations($client, $io);
                }
            }

            if ($publishableReservations === []) {
                $io->text(sprintf('- client #%d: aucune reservation publiable', $clientId));
                continue;
            }

            $createdForClient = 0;

            foreach ($publishableReservations as $reservation) {
                if ($created >= $maxListings) {
                    break 2;
                }
                if ($createdForClient >= $maxPerClient) {
                    break;
                }

                $projectId = (int) ($reservation['project_id'] ?? 0);
                $resourceId = (int) ($reservation['resource_id'] ?? 0);
                $publishableQty = (int) ($reservation['publishable_qty'] ?? 0);
                if ($projectId <= 0 || $resourceId <= 0 || $publishableQty <= 0) {
                    continue;
                }

                $hasOpenListing = (int) $connection->fetchOne(
                    'SELECT COUNT(*) FROM resource_market_listing
                     WHERE sellerUserId = ? AND idProj = ? AND idRs = ? AND status = ? AND qtyRemaining > 0',
                    [$clientId, $projectId, $resourceId, self::LISTING_STATUS_LISTED]
                ) > 0;

                if ($hasOpenListing) {
                    $io->text(sprintf(
                        '- skip duplicate open listing | seller=%d | project=%d | resource=%d',
                        $clientId,
                        $projectId,
                        $resourceId
                    ));
                    continue;
                }

                $attempted++;
                $quantity = min(2, $publishableQty);
                $basePrice = (float) $connection->fetchOne(
                    'SELECT COALESCE(prixRs, 0) FROM resource WHERE idRs = ?',
                    [$resourceId]
                );
                $unitPrice = round(max(1.0, ($basePrice > 0 ? $basePrice * 0.55 : 50.0)), 3);

                try {
                    $result = $this->marketplaceService->publishListing(
                        $client,
                        $projectId,
                        $resourceId,
                        $quantity,
                        $unitPrice,
                        'Annonce test seed shop',
                        null
                    );

                    $created++;
                    $createdForClient++;
                    $io->text(sprintf(
                        '+ listing #%d | seller=%d | project=%d | resource=%d | qty=%d | price=%.3f',
                        (int) ($result['idListing'] ?? 0),
                        $clientId,
                        $projectId,
                        $resourceId,
                        $quantity,
                        $unitPrice
                    ));
                } catch (\Throwable $exception) {
                    $io->text(sprintf(
                        '- publish fail | seller=%d | project=%d | resource=%d | reason=%s',
                        $clientId,
                        $projectId,
                        $resourceId,
                        $exception->getMessage()
                    ));
                }
            }
        }

        $openListedCount = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM resource_market_listing WHERE status = ? AND qtyRemaining > 0',
            [self::LISTING_STATUS_LISTED]
        );

        $io->success(sprintf(
            'Done. attempted=%d created=%d open_listed_now=%d',
            $attempted,
            $created,
            $openListedCount
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadPublishableReservations(User $client, SymfonyStyle $io): array
    {
        $clientId = (int) $client->getIdUser();

        try {
            $data = $this->marketplaceService->buildPageData($client, '');
        } catch (\Throwable $exception) {
            $io->warning(sprintf('client #%d skip: %s', $clientId, $exception->getMessage()));

            return [];
        }

        return is_array($data['publishable_reservations'] ?? null)
            ? $data['publishable_reservations']
            : [];
    }

    private function getOrCreateSeedProjectId(int $clientId): int
    {
        $connection = $this->entityManager->getConnection();

        $existing = (int) $connection->fetchOne(
            'SELECT idProj FROM project WHERE idClient = ? AND titleProj = ? ORDER BY idProj DESC LIMIT 1',
            [$clientId, self::SEED_PROJECT_TITLE]
        );
        if ($existing > 0) {
            return $existing;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $connection->insert('project', [
            'titleProj' => self::SEED_PROJECT_TITLE,
            'descriptionProj' => self::SEED_PROJECT_DESCRIPTION,
            'budgetProj' => 0.0,
            'typeProj' => self::SEED_PROJECT_TYPE,
            'stateProj' => self::SEED_PROJECT_STATE,
            'createdAtProj' => $now,
            'updatedAtProj' => $now,
            'avancementProj' => 0.0,
            'idClient' => $clientId,
        ]);

        return (int) $connection->lastInsertId();
    }

    private function seedReservationsForClient(
        User $client,
        int $seedProjectId,
        int $maxLines,
        int $reserveQty,
        SymfonyStyle $io
    ): int {
        $clientId = (int) $client->getIdUser();

        try {
            $data = $this->miniShopService->buildPageData($client, null, '', []);
        } catch (\Throwable $exception) {
            $io->text(sprintf('- reserve skip | seller=%d | reason=%s', $clientId, $exception->getMessage()));

            return 0;
        }

        $resources = is_array($data['resources'] ?? null) ? $data['resources'] : [];
        if ($resources === []) {
            return 0;
        }

        usort($resources, static function (array $left, array $right): int {
            $leftAvailable = (int) ($left['available_stock'] ?? 0);
            $rightAvailable = (int) ($right['available_stock'] ?? 0);
            if ($leftAvailable !== $rightAvailable) {
                return $rightAvailable <=> $leftAvailable;
            }

            return (int) ($left['idRs'] ?? 0) <=> (int) ($right['idRs'] ?? 0);
        });

        $reservedLines = 0;
        $connection = $this->entityManager->getConnection();

        foreach ($resources as $resource) {
            if ($reservedLines >= $maxLines) {
                break;
            }

            $resourceId = (int) ($resource['idRs'] ?? 0);
            $availableStock = (int) ($resource['available_stock'] ?? 0);
            if ($resourceId <= 0 || $availableStock <= 0) {
                continue;
            }

            $hasOpenListingOnPair = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM resource_market_listing
                 WHERE sellerUserId = ? AND idProj = ? AND idRs = ? AND status = ? AND qtyRemaining > 0',
                [$clientId, $seedProjectId, $resourceId, self::LISTING_STATUS_LISTED]
            ) > 0;
            if ($hasOpenListingOnPair) {
                continue;
            }

            $quantity = min($reserveQty, $availableStock);
            if ($quantity <= 0) {
                continue;
            }

            try {
                $this->miniShopService->reserve($client, $resourceId, $quantity, $seedProjectId);
                $reservedLines++;
                $io->text(sprintf(
                    '+ reserve seed | seller=%d | project=%d | resource=%d | qty=%d',
                    $clientId,
                    $seedProjectId,
                    $resourceId,
                    $quantity
                ));
            } catch (\Throwable $exception) {
                $io->text(sprintf(
                    '- reserve fail | seller=%d | project=%d | resource=%d | reason=%s',
                    $clientId,
                    $seedProjectId,
                    $resourceId,
                    $exception->getMessage()
                ));
            }
        }

        return $reservedLines;
    }
}
