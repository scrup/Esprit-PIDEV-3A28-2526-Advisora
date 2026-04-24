<?php

// src/Service/PdfGeneratorService.php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Twig\Environment;

class PdfGeneratorService
{
    public function __construct(
        private Environment $twig,
        private HttpClientInterface $client,
        private string $gotenbergUrl = 'http://127.0.0.1:3000'
    ) {}

    public function renderHtml(string $template, array $context): string
    {
        return $this->twig->render($template, $context);
    }

    public function generate(string $html, string $filename, string $subDir = ''): string
    {
        $formData = new FormDataPart([
            'files' => new DataPart($html, 'index.html', 'text/html'),
        ]);

        $response = $this->client->request('POST', rtrim($this->gotenbergUrl, '/') . '/forms/chromium/convert/html', [
            'headers' => $formData->getPreparedHeaders()->toArray(),
            'body' => $formData->bodyToIterable(),
            'timeout' => 60,
        ]);

        $normalizedSubDir = trim(str_replace(['..', '\\'], ['', '/'], $subDir), '/');
        $targetDir = dirname(__DIR__, 2) . '/public' . ($normalizedSubDir !== '' ? '/' . $normalizedSubDir : '');

        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new \RuntimeException(sprintf('Impossible de creer le dossier de sortie PDF: %s', $targetDir));
        }

        $fullPath = $targetDir . '/' . $filename;
        $pdfContent = $response->getContent();

        if (file_put_contents($fullPath, $pdfContent) === false) {
            throw new \RuntimeException(sprintf('Impossible d ecrire le fichier PDF: %s', $fullPath));
        }

        return $fullPath;
    }
}
