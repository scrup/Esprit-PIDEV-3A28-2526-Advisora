<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Service\ClientMarketplaceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:shop:seed-listings',
    description: 'Publie automatiquement des annonces test via la logique metier du shop.'
)]
final class ShopSeedListingsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClientMarketplaceService $marketplaceService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('max', InputArgument::OPTIONAL, 'Nombre max d annonces a creer', 15);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $maxListings = max(1, (int) $input->getArgument('max'));
        $connection = $this->entityManager->getConnection();

        $clientRows = $connection->fetchAllAssociative(
            "SELECT idUser, CONCAT(COALESCE(PrenomUser,''), ' ', COALESCE(nomUser,'')) AS full_name
             FROM user
             WHERE LOWER(roleUser) = 'client'
             ORDER BY idUser ASC"
        );

        $created = 0;
        $attempted = 0;
        $io->title(sprintf('Seed annonces test (max=%d)', $maxListings));

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

            try {
                $data = $this->marketplaceService->buildPageData($client, '');
            } catch (\Throwable $exception) {
                $io->warning(sprintf('client #%d skip: %s', $clientId, $exception->getMessage()));
                continue;
            }

            $publishableReservations = is_array($data['publishable_reservations'] ?? null)
                ? $data['publishable_reservations']
                : [];

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

                $hasOpenListing = (int) $connection->fetchOne(
                    'SELECT COUNT(*) FROM resource_market_listing
                     WHERE sellerUserId = ? AND idProj = ? AND idRs = ? AND status = ? AND qtyRemaining > 0',
                    [$clientId, $projectId, $resourceId, 'LISTED']
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
                    'SELECT COALESCE(prixRs, 0) FROM resources WHERE idRs = ?',
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
            ['LISTED']
        );

        $io->success(sprintf(
            'Done. attempted=%d created=%d open_listed_now=%d',
            $attempted,
            $created,
            $openListedCount
        ));

        return Command::SUCCESS;
    }
}
