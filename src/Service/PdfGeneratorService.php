<?php

namespace App\Service;

use Twig\Environment;

class PdfGeneratorService
{
    private bool $gotenbergEnabled;
    private string $gotenbergUrl;

    /** @var list<string> */
    private array $localBrowserCandidates;

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

        $this->localBrowserCandidates = [
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            '/usr/bin/microsoft-edge',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/snap/bin/chromium',
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderHtml(string $template, array $context): string
    {
        return $this->twig->render($template, $context);
    }

    public function generate(string $html, string $filename, string $subDir = ''): string
    {
        if (!$this->supportsPdfGeneration()) {
            throw new \RuntimeException(
                'Aucun moteur PDF disponible. Utilisez la version imprimable HTML.'
            );
        }

        $temporaryHtmlPath = $this->buildTemporaryHtmlPath();

        if (file_put_contents($temporaryHtmlPath, $html) === false) {
            throw new \RuntimeException('Impossible de preparer le document HTML temporaire pour la generation PDF.');
        }

        $fullPath = $this->buildTargetPath($filename, $subDir);
        $errors = [];

        try {
            if ($this->gotenbergEnabled && $this->gotenbergUrl !== '') {
                return $this->generateWithGotenberg($temporaryHtmlPath, $fullPath);
            }
        } catch (\Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        try {
            if ($this->findLocalBrowserBinary() !== null) {
                return $this->generateWithLocalBrowser($temporaryHtmlPath, $fullPath);
            }
        } catch (\Throwable $exception) {
            $errors[] = $exception->getMessage();
        } finally {
            @unlink($temporaryHtmlPath);
        }

        throw new \RuntimeException($errors !== []
            ? implode(' ', $errors)
            : 'Impossible de generer le PDF avec les moteurs disponibles.');
    }

    public function supportsPdfGeneration(): bool
    {
        return ($this->gotenbergEnabled && $this->gotenbergUrl !== '') || $this->findLocalBrowserBinary() !== null;
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

    private function generateWithGotenberg(string $temporaryHtmlPath, string $fullPath): string
    {
        $endpoint = $this->gotenbergUrl . '/forms/chromium/convert/html';
        $curl = curl_init($endpoint);

        if ($curl === false) {
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

            throw new \RuntimeException(sprintf(
                'La requete Gotenberg a echoue vers %s: %s.',
                $endpoint,
                $error !== '' ? $error : 'erreur inconnue'
            ));
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

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

    private function generateWithLocalBrowser(string $temporaryHtmlPath, string $fullPath): string
    {
        $browserBinary = $this->findLocalBrowserBinary();

        if ($browserBinary === null) {
            throw new \RuntimeException('Aucun navigateur local compatible n a ete detecte pour generer le PDF.');
        }

        @unlink($fullPath);

        $command = sprintf(
            '%s --headless --disable-gpu --no-pdf-header-footer --print-to-pdf=%s %s',
            escapeshellarg($browserBinary),
            escapeshellarg($fullPath),
            escapeshellarg($this->toFileUrl($temporaryHtmlPath))
        );

        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, null, null, ['bypass_shell' => true]);

        if (!is_resource($process)) {
            throw new \RuntimeException('Impossible de lancer le navigateur local pour generer le PDF.');
        }

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        clearstatcache(true, $fullPath);

        if ($exitCode !== 0 || !is_file($fullPath) || filesize($fullPath) === 0) {
            throw new \RuntimeException(sprintf(
                'Le moteur PDF local a echoue (%d): %s %s',
                $exitCode,
                trim($stdout),
                trim($stderr)
            ));
        }

        return $fullPath;
    }

    private function buildTemporaryHtmlPath(): string
    {
        return sys_get_temp_dir() . '/advisora_html_' . bin2hex(random_bytes(8)) . '.html';
    }

    private function findLocalBrowserBinary(): ?string
    {
        foreach ($this->localBrowserCandidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function toFileUrl(string $path): string
    {
        $normalizedPath = str_replace(DIRECTORY_SEPARATOR, '/', $path);

        if (preg_match('/^[A-Za-z]:\//', $normalizedPath) === 1) {
            return 'file:///' . str_replace(' ', '%20', $normalizedPath);
        }

        return 'file://' . str_replace(' ', '%20', $normalizedPath);
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