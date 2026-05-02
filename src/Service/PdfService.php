<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Wraps Dompdf as a Symfony service.
 *
 * Usage:
 *   $pdf = $this->pdfService->generateFromTemplate('my/template.html.twig', $data);
 *   return $this->pdfService->streamResponse($pdf, 'filename.pdf');
 */
class PdfService
{
    public function __construct(private readonly Environment $twig)
    {
    }

    /**
     * Render a Twig template and convert it to a PDF binary string.
     *
     * @param string               $template  Twig template path
     * @param array<string, mixed> $context   Variables passed to the template
     * @param string               $paper     Paper size (A4, Letter, …)
     * @param string               $orientation portrait | landscape
     */
    public function generateFromTemplate(
        string $template,
        array $context = [],
        string $paper = 'A4',
        string $orientation = 'portrait'
    ): string {
        $html = $this->twig->render($template, $context);

        return $this->generateFromHtml($html, $paper, $orientation);
    }

    /**
     * Convert a raw HTML string to a PDF binary string.
     */
    public function generateFromHtml(
        string $html,
        string $paper = 'A4',
        string $orientation = 'portrait'
    ): string {
        $dompdf = $this->buildDompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Build a Symfony Response that triggers a PDF download.
     */
    public function downloadResponse(string $pdfContent, string $filename): Response
    {
        return new Response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Build a Symfony Response that renders the PDF inline in the browser.
     */
    public function inlineResponse(string $pdfContent, string $filename): Response
    {
        return new Response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function buildDompdf(): Dompdf
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);

        return new Dompdf($options);
    }
}
