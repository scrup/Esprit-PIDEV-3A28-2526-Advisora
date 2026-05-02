<?php

namespace App\Service;

use App\Repository\StrategieRepository;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PythonRecommendationService
{
    private string $pythonPath;
    private string $scriptPath;

    public function __construct(
        private string $projectDir,
        private StrategieRepository $strategieRepository,
    ) {
        $this->pythonPath = $this->resolvePreferredPythonPath();
        $this->scriptPath = $this->projectDir . '/python/recommend.py';
    }

    /**
     * @param array<string, mixed> $projectData
     *
     * @return array<string, mixed>|null
     */
    public function recommend(array $projectData): ?array
    {
        $projectData['dbStrategies'] = $this->strategieRepository->findRecommendationCandidates();

        $jsonData = json_encode($projectData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($jsonData === false) {
            throw new \RuntimeException('Impossible d\'encoder les données du projet en JSON.');
        }

        $lastException = null;

        foreach ($this->buildPythonCandidates($this->pythonPath) as $pythonCandidate) {
            try {
                $decoded = $this->runRecommendationProcess($pythonCandidate, $jsonData);
                $this->pythonPath = $pythonCandidate;

                return $decoded;
            } catch (\Throwable $exception) {
                $lastException = $exception;
            }
        }

        if ($lastException instanceof \Throwable) {
            throw new \RuntimeException($lastException->getMessage(), 0, $lastException);
        }

        throw new \RuntimeException('Aucun interpréteur Python disponible pour la recommandation.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function runRecommendationProcess(string $pythonPath, string $jsonData): ?array
    {
        $process = new Process(
            [
                $pythonPath,
                $this->scriptPath,
            ],
            null,
            $this->buildPythonEnv()
        );

        $process->setInput($jsonData);
        $process->setTimeout(25);
        $process->run();

        $output = trim($process->getOutput());
        $errorOutput = trim($process->getErrorOutput());

        if ($output === '' && $errorOutput !== '') {
            throw new \RuntimeException(
                sprintf(
                    'Erreur Python (%s) : %s',
                    $pythonPath,
                    $errorOutput
                )
            );
        }

        if ($output === '') {
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return null;
        }

        $decoded = json_decode($output, true);

        if (!is_array($decoded)) {
            $normalizedOutput = preg_replace('/^\xEF\xBB\xBF/', '', $output) ?? $output;
            $jsonStart = strpos($normalizedOutput, '{');
            $jsonEnd = strrpos($normalizedOutput, '}');

            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd >= $jsonStart) {
                $candidate = substr($normalizedOutput, $jsonStart, $jsonEnd - $jsonStart + 1);
                $decoded = json_decode($candidate, true);
            }
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException(
                sprintf(
                    'Réponse JSON invalide retournée par recommend.py (%s). Sortie brute: %s',
                    $pythonPath,
                    $output
                )
            );
        }

        if (($decoded['error'] ?? false) === true) {
            $message = trim((string) ($decoded['message'] ?? 'Erreur inconnue retournée par Python.'));

            throw new \RuntimeException(
                sprintf(
                    'Erreur Python (%s) : %s',
                    $pythonPath,
                    $message !== '' ? $message : 'Erreur inconnue retournée par Python.'
                )
            );
        }

        return $decoded;
    }

    /**
     * @return array<string, string>
     */
    private function buildPythonEnv(): array
    {
        $systemRoot = (string) (getenv('SYSTEMROOT') ?: getenv('WINDIR') ?: 'C:\\Windows');
        $winDir = (string) (getenv('WINDIR') ?: getenv('SYSTEMROOT') ?: $systemRoot);
        $temp = (string) (getenv('TEMP') ?: sys_get_temp_dir());
        $tmp = (string) (getenv('TMP') ?: $temp);
        $path = (string) (getenv('PATH') ?: '');

        return [
            'PYTHONIOENCODING' => 'utf-8',
            'PYTHONUTF8' => '1',
            'PYTHONNOUSERSITE' => '1',
            'PYTHONPATH' => '',
            'SYSTEMROOT' => $systemRoot,
            'WINDIR' => $winDir,
            'TEMP' => $temp,
            'TMP' => $tmp,
            'PATH' => $path,
        ];
    }

    /**
     * @return string[]
     */
    private function buildPythonCandidates(string $preferred): array
    {
        $candidates = [$preferred];
        $localAppData = rtrim((string) (getenv('LOCALAPPDATA') ?: ''), '\\/');

        if ($localAppData !== '') {
            $candidates[] = $localAppData . '\\Programs\\Python\\Python311\\python.exe';
            $candidates[] = $localAppData . '\\Programs\\Python\\Python312\\python.exe';
            $candidates[] = $localAppData . '\\Programs\\Python\\Python313\\python.exe';
        }

        $candidates[] = 'python';

        $resolved = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            $normalized = strtolower(trim($candidate));

            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;

            if ($candidate === 'python' || is_file($candidate)) {
                $resolved[] = $candidate;
            }
        }

        return $resolved;
    }

    private function resolvePreferredPythonPath(): string
    {
        $configuredPythonPath = trim((string) (
            $_ENV['PYTHON_EXECUTABLE']
            ?? $_SERVER['PYTHON_EXECUTABLE']
            ?? getenv('PYTHON_EXECUTABLE')
            ?: ''
        ));

        if ($configuredPythonPath !== '') {
            return $configuredPythonPath;
        }

        foreach ($this->buildPythonCandidates('python') as $candidate) {
            if ($candidate !== 'python') {
                return $candidate;
            }
        }

        return 'python';
    }
}