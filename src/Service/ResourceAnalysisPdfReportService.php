<?php

namespace App\Service;

/**
 * Service de generation de rapport metier "Analyse Ressources".
 *
 * Principe:
 * - genere un HTML detaille via Twig,
 * - tente la conversion PDF via Gotenberg (PdfGeneratorService),
 * - fallback propre en HTML telechargeable si le moteur PDF est indisponible.
 */
class ResourceAnalysisPdfReportService
{
    public function __construct(
        private readonly PdfGeneratorService $pdfGeneratorService,
    ) {
    }

    /**
     * @param array<string, mixed> $analysis
     * @param array<int, array<string, mixed>> $filteredActions
     * @return array{format:string,path:string,download_name:string,warning:?string}
     */
    public function generateReport(array $analysis, array $filteredActions, ?string $priorityFilter, string $actionCodeFilter): array
    {
        $timestamp = (new \DateTimeImmutable())->format('Ymd-His');
        $baseName = 'resource-analysis-report-' . $timestamp;
        $subDir = 'uploads/resource-analysis/reports';

        $html = $this->pdfGeneratorService->renderHtml('back/resource/analysis_pdf.html.twig', [
            'analysis' => $analysis,
            'actions' => $filteredActions,
            'priority_filter' => $priorityFilter,
            'action_code_filter' => $actionCodeFilter,
            'generated_at' => new \DateTimeImmutable(),
        ]);

        if ($this->pdfGeneratorService->supportsPdfGeneration()) {
            try {
                $pdfPath = $this->pdfGeneratorService->generate($html, $baseName . '.pdf', $subDir);

                return [
                    'format' => 'pdf',
                    'path' => $pdfPath,
                    'download_name' => $baseName . '.pdf',
                    'warning' => null,
                ];
            } catch (\Throwable $throwable) {
                $htmlPath = $this->pdfGeneratorService->saveHtml($html, $baseName . '.html', $subDir);

                return [
                    'format' => 'html',
                    'path' => $htmlPath,
                    'download_name' => $baseName . '.html',
                    'warning' => 'PDF indisponible, fallback HTML genere: ' . $throwable->getMessage(),
                ];
            }
        }

        $htmlPath = $this->pdfGeneratorService->saveHtml($html, $baseName . '.html', $subDir);

        return [
            'format' => 'html',
            'path' => $htmlPath,
            'download_name' => $baseName . '.html',
            'warning' => 'Gotenberg desactive: export HTML imprime genere.',
        ];
    }
}

