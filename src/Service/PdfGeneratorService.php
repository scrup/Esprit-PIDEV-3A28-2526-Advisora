<?php

namespace App\Service;

use Twig\Environment;

class PdfGeneratorService
{
    private bool $gotenbergEnabled;
    private string $gotenbergUrl;

    public function __construct(
        private Environment $twig,
        ?string $gotenbergUrl = null,
        ?string $gotenbergEnabled = null
    ) {
        $this->gotenbergEnabled = $this->normalizeBoolean(
            $this->resolveFirstNonEmpty($gotenbergEnabled, $this->readEnv('GOTENBERG_ENABLED')),
            true
        );
        $this->gotenbergUrl = rtrim(
            $this->resolveFirstNonEmpty($gotenbergUrl, $this->readEnv('GOTENBERG_URL')) ?? 'http://127.0.0.1:3000',
            '/'
        );
    }

    public function renderHtml(string $template, array $context): string
    {
        return $this->twig->render($template, $context);
    }

    public function generate(string $html, string $filename, string $subDir = ''): string
    {
        if (!$this->supportsPdfGeneration()) {
            throw new \RuntimeException(
                'La generation PDF via Gotenberg est desactivee. Utilisez la version imprimable HTML.'
            );
        }

        $temporaryHtmlPath = tempnam(sys_get_temp_dir(), 'advisora_html_');
        if ($temporaryHtmlPath === false || file_put_contents($temporaryHtmlPath, $html) === false) {
            throw new \RuntimeException('Impossible de preparer le document HTML temporaire pour Gotenberg.');
        }

        $endpoint = $this->gotenbergUrl . '/forms/chromium/convert/html';
        $curl = curl_init($endpoint);
        if ($curl === false) {
            @unlink($temporaryHtmlPath);

            throw new \RuntimeException(sprintf(
                'Impossible d initialiser la requete HTTP vers Gotenberg (%s).',
                $endpoint
            ));
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => [
                'files' => new \CURLFile($temporaryHtmlPath, 'text/html', 'index.html'),
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $pdfContent = curl_exec($curl);

        if ($pdfContent === false) {
            $error = curl_error($curl);
            curl_close($curl);
            @unlink($temporaryHtmlPath);

            throw new \RuntimeException(sprintf(
                'La requete Gotenberg a echoue vers %s: %s. Verifiez que Gotenberg est demarre et que GOTENBERG_URL est correct.',
                $endpoint,
                $error !== '' ? $error : 'erreur inconnue'
            ));
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        @unlink($temporaryHtmlPath);

        $fullPath = $this->buildTargetPath($filename, $subDir);

        if ($statusCode >= 400) {
            throw new \RuntimeException(sprintf(
                'Gotenberg a echoue (%d): %s',
                $statusCode,
                trim((string) $pdfContent) !== '' ? trim((string) $pdfContent) : 'reponse vide'
            ));
        }

        if (file_put_contents($fullPath, $pdfContent) === false) {
            throw new \RuntimeException(sprintf('Impossible d ecrire le fichier PDF: %s', $fullPath));
        }

        return $fullPath;
    }

    public function supportsPdfGeneration(): bool
    {
        return $this->gotenbergEnabled && $this->gotenbergUrl !== '';
    }

    public function saveHtml(string $html, string $filename, string $subDir = ''): string
    {
        $fullPath = $this->buildTargetPath($filename, $subDir);

        if (file_put_contents($fullPath, $html) === false) {
            throw new \RuntimeException(sprintf('Impossible d ecrire le fichier HTML: %s', $fullPath));
        }

        return $fullPath;
    }

    private function buildTargetPath(string $filename, string $subDir = ''): string
    {
        $normalizedSubDir = trim(str_replace(['..', '\\'], ['', '/'], $subDir), '/');
        $targetDir = dirname(__DIR__, 2) . '/public' . ($normalizedSubDir !== '' ? '/' . $normalizedSubDir : '');

        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new \RuntimeException(sprintf('Impossible de creer le dossier de sortie PDF: %s', $targetDir));
        }

        return $targetDir . '/' . $filename;
    }

    private function resolveFirstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    private function readEnv(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) ? $value : null;
    }

    private function normalizeBoolean(?string $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        return !in_array(strtolower(trim($value)), ['0', 'false', 'off', 'no'], true);
    }
}
