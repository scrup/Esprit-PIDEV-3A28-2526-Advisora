<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ResourceActionAnalysisService;
use App\Service\ResourceAnalysisResultStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:resource:analysis:run',
    description: 'Calcule et stocke l analyse prioritaire des ressources back office.'
)]
final class RunResourceAnalysisCommand extends Command
{
    public function __construct(
        private readonly ResourceActionAnalysisService $analysisService,
        private readonly ResourceAnalysisResultStore $resultStore,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->resultStore->markRunning();
        $this->resultStore->clearError();

        try {
            $analysis = $this->analysisService->analyze();
            $this->resultStore->saveResult($analysis);
            $io->success('Analyse ressources calculee et enregistree.');

            return Command::SUCCESS;
        } catch (\Throwable $throwable) {
            $this->resultStore->saveError($throwable->getMessage());
            $io->error('Echec analyse ressources: ' . $throwable->getMessage());

            return Command::FAILURE;
        }
    }
}

