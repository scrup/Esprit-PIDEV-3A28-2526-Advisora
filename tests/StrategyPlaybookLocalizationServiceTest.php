<?php

namespace App\Tests;

use App\Entity\Objective;
use App\Entity\Project;
use App\Entity\Strategie;
use App\Service\LibreTranslateService;
use App\Service\StrategyPlaybookLocalizationService;
use PHPUnit\Framework\TestCase;

class StrategyPlaybookLocalizationServiceTest extends TestCase
{
    public function testBuildViewModelLocalizesPlaybookToEnglish(): void
    {
        $service = new StrategyPlaybookLocalizationService(new FakeLibreTranslateService());
        $strategy = (new Strategie())
            ->setNomStrategie('Strategie croissance')
            ->setStatusStrategie(Strategie::STATUS_APPROVED)
            ->setType('Expansion')
            ->setJustification('Justification strategique')
            ->setNews('Actualite produit')
            ->setDureeTerme(12)
            ->setBudgetTotal(4500)
            ->setGainEstime(120);
        $objective = (new Objective())
            ->setNomObj('Lancer le canal B2B')
            ->setDescriptionOb('Structurer une offre dediee aux PME.')
            ->setPriorityOb(Objective::PRIORITY_HIGH);
        $strategy->addObjective($objective);

        $project = (new Project())
            ->setTitleProj('Projet Atlas')
            ->setDescriptionProj('Plateforme de croissance commerciale.')
            ->setStateProj(Project::STATUS_ACCEPTED)
            ->setBudgetProj(9800)
            ->setAvancementProj(55);

        $content = [
            'executive_summary' => 'Resume executif',
            'strategic_diagnosis' => 'Diagnostic de depart',
            'highlights' => ['Point 1', 'Point 2'],
            'strategic_priorities' => ['Priorite 1'],
            'opportunities' => ['Opportunite 1'],
            'expected_outcome_summary' => 'Projection globale',
            'expected_outcome_chart' => [
                'aria_label' => 'Projection d outcome',
                'polyline_points' => '0,0 1,1',
                'area_points' => '0,0 1,1',
                'points' => [
                    [
                        'period' => 'Demarrage',
                        'value_label' => '12 %',
                        'x' => 10,
                        'y' => 20,
                        'value_y' => 18,
                    ],
                ],
                'y_ticks' => [
                    [
                        'label' => '0 %',
                        'y' => 0,
                    ],
                ],
                'start_value_label' => '12 %',
                'final_value_label' => '120 %',
            ],
            'execution_phases' => [
                [
                    'title' => 'Phase 1',
                    'horizon' => 'Debut',
                    'focus' => 'Valider la traction',
                ],
            ],
            'risks' => ['Risque 1'],
            'mitigation_actions' => ['Action 1'],
            'actions' => ['Tache 1'],
            'kpis' => [
                [
                    'name' => 'KPI 1',
                    'target' => '10 rendez-vous',
                    'cadence' => 'Hebdomadaire',
                ],
            ],
        ];

        $viewModel = $service->buildViewModel('en', $strategy, $project, $content, ['Message test']);

        self::assertSame('en', $viewModel['language']);
        self::assertSame('Strategy Playbook', $viewModel['labels']['document_title']);
        self::assertSame('[en] Strategie croissance', $viewModel['strategy']['name']);
        self::assertSame('Approved', $viewModel['strategy']['status_label']);
        self::assertSame('[en] Projet Atlas', $viewModel['project']['title']);
        self::assertSame('Accepted', $viewModel['project']['status_label']);
        self::assertSame('Priority high', $viewModel['objectives'][0]['priority_badge']);
        self::assertSame('[en] Resume executif', $viewModel['content']['executive_summary']);
        self::assertSame('[en] Point 1', $viewModel['content']['highlights'][0]);
        self::assertSame('[en] Demarrage', $viewModel['content']['expected_outcome_chart']['points'][0]['period']);
        self::assertSame('0,0 1,1', $viewModel['content']['expected_outcome_chart']['polyline_points']);
        self::assertSame('[en] Message test', $viewModel['messages'][0]);
    }

    public function testNormalizeLanguageFallsBackToFrench(): void
    {
        $service = new StrategyPlaybookLocalizationService(new FakeLibreTranslateService());

        self::assertSame('fr', $service->normalizeLanguage('de'));
        self::assertSame('fr', $service->normalizeLanguage(null));
    }
}

class FakeLibreTranslateService extends LibreTranslateService
{
    public function __construct()
    {
    }

    public function translate(string $text, string $targetLang, ?string $sourceLang = 'auto'): string
    {
        return sprintf('[%s] %s', $targetLang, trim($text));
    }

    public function translateBatch(array $texts, string $targetLang, ?string $sourceLang = 'auto'): array
    {
        return array_map(
            static fn (string $text): string => sprintf('[%s] %s', $targetLang, trim($text)),
            array_values($texts)
        );
    }
}
