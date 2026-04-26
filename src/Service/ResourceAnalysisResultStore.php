<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stockage local des analyses ressources.
 *
 * On conserve les snapshots dans var/resource-analysis pour:
 * - afficher le dernier resultat en back office,
 * - separer le calcul (commande) de l'affichage (controller),
 * - permettre un lancement "arriere-plan" robuste.
 */
class ResourceAnalysisResultStore
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function markRunning(): void
    {
        $this->writeJson($this->runningPath(), [
            'started_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }

    public function isRunning(): bool
    {
        $path = $this->runningPath();
        if (!is_file($path)) {
            return false;
        }

        // Securite anti blocage: si un lock depasse 20 min, on le purge.
        $maxAgeSeconds = 20 * 60;
        $mtime = @filemtime($path);
        if (!is_int($mtime)) {
            return true;
        }

        if ((time() - $mtime) > $maxAgeSeconds) {
            @unlink($path);

            return false;
        }

        return true;
    }

    public function clearRunning(): void
    {
        @unlink($this->runningPath());
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveResult(array $payload): void
    {
        $this->writeJson($this->latestPath(), $payload);
        @unlink($this->errorPath());
        $this->clearRunning();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadLatest(): ?array
    {
        return $this->readJson($this->latestPath());
    }

    public function saveError(string $message): void
    {
        $this->writeJson($this->errorPath(), [
            'message' => trim($message) !== '' ? trim($message) : 'Erreur inconnue',
            'at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);
        $this->clearRunning();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadError(): ?array
    {
        return $this->readJson($this->errorPath());
    }

    public function clearError(): void
    {
        @unlink($this->errorPath());
    }

    private function ensureDirectory(): string
    {
        $dir = $this->projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'resource-analysis';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function latestPath(): string
    {
        return $this->ensureDirectory() . DIRECTORY_SEPARATOR . 'latest.json';
    }

    private function runningPath(): string
    {
        return $this->ensureDirectory() . DIRECTORY_SEPARATOR . 'running.lock';
    }

    private function errorPath(): string
    {
        return $this->ensureDirectory() . DIRECTORY_SEPARATOR . 'error.json';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return;
        }

        @file_put_contents($path, $encoded);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}

