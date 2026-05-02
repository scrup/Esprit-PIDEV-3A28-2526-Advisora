<?php

namespace App\Controller;

use App\Service\GeminiTopProjectsService;
use App\Service\NewsApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GeminiProjectController extends AbstractController
{
    /**
     * Suggested project categories displayed as input hints.
     *
     * @var string[]
     */
    private const PROJECT_TYPES = [
        'IT / Technologie',
        'E-commerce',
        'Immobilier',
        'Finance',
        'Sante',
        'Education',
        'Energie',
        'Transport',
        'Agriculture',
        'Tourisme',
    ];

    #[Route('/projects/top10', name: 'project_top10', methods: ['GET'])]
    public function index(): Response
    {
        return $this->renderTop10Page(
            '',
            [],
            [],
            null,
            $this->buildEmptyGenerationMeta(),
            $this->buildEmptyNewsMeta()
        );
    }

    #[Route('/projects/top10/ranking', name: 'project_top10_generate_ranking', methods: ['POST'])]
    public function generateRanking(
        Request $request,
        GeminiTopProjectsService $geminiTopProjectsService
    ): Response
    {
        $selectedType = trim((string) $request->request->get('project_type', ''));
        $error = null;
        $projects = [];
        $generationMeta = $this->buildEmptyGenerationMeta();

        if ($selectedType === '') {
            $error = 'Merci de saisir un type de projet avant de generer le classement.';
        } else {
            try {
                $projects = $geminiTopProjectsService->generateTopProjects($selectedType);
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }

            $generationMeta = $geminiTopProjectsService->getLastGenerationMeta();
        }

        return $this->renderTop10Page(
            $selectedType,
            $projects,
            [],
            $error,
            $generationMeta,
            $this->buildEmptyNewsMeta()
        );
    }

    #[Route('/projects/top10/news', name: 'project_top10_generate_news', methods: ['POST'])]
    public function generateNews(
        Request $request,
        NewsApiService $newsApiService
    ): Response
    {
        $selectedType = trim((string) $request->request->get('project_type', ''));
        $error = null;
        $newsArticles = [];
        $newsMeta = $this->buildEmptyNewsMeta();

        if ($selectedType === '') {
            $error = 'Merci de saisir un type de projet avant de charger les actualites.';
        } else {
            $newsArticles = $newsApiService->searchProjectTypeNews($selectedType);
            $newsMeta = $newsApiService->getLastFetchMeta();
        }

        return $this->renderTop10Page(
            $selectedType,
            [],
            $newsArticles,
            $error,
            $this->buildEmptyGenerationMeta(),
            $newsMeta
        );
    }

    /**
     * @param array<int, array<string, mixed>> $projects
     * @param array<int, array<string, mixed>> $newsArticles
     * @param array{source: string|null, model: string|null, used_fallback: bool, used_stale_cache: bool, warning: string|null} $generationMeta
     * @param array{source: string|null, query: string|null, warning: string|null, used_stale_cache: bool} $newsMeta
     */
    private function renderTop10Page(
        string $selectedType,
        array $projects,
        array $newsArticles,
        ?string $error,
        array $generationMeta,
        array $newsMeta
    ): Response {
        return $this->render('front/project/top10.html.twig', [
            'project_types' => self::PROJECT_TYPES,
            'selected_type' => $selectedType,
            'projects' => $projects,
            'news_articles' => $newsArticles,
            'error' => $error,
            'generation_meta' => $generationMeta,
            'news_meta' => $newsMeta,
            'has_results' => $projects !== [],
        ]);
    }

    /**
     * @return array{source: string|null, model: string|null, used_fallback: bool, used_stale_cache: bool, warning: string|null}
     */
    private function buildEmptyGenerationMeta(): array
    {
        return [
            'source' => null,
            'model' => null,
            'used_fallback' => false,
            'used_stale_cache' => false,
            'warning' => null,
        ];
    }

    /**
     * @return array{source: string|null, query: string|null, warning: string|null, used_stale_cache: bool}
     */
    private function buildEmptyNewsMeta(): array
    {
        return [
            'source' => null,
            'query' => null,
            'warning' => null,
            'used_stale_cache' => false,
        ];
    }
}
