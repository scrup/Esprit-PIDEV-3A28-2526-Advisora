<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class AutoTranslator
{
    private const WINDOWS_STORE_PYTHON_MARKER = '\\appdata\\local\\microsoft\\windowsapps\\python.exe';

    private string $pythonPath;
    private string $scriptPath;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
        string $pythonPath = 'python'
    ) {
        $this->pythonPath = $this->resolvePreferredPythonPath($pythonPath);
        $this->scriptPath = $projectDir . '/python/translate.py';
    }

    public function translate(string $text, string $source = 'fr', string $target = 'en'): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $lastException = null;
        $usableCandidates = [];

        foreach ($this->buildPythonCandidates($this->pythonPath) as $pythonCandidate) {
            if (!$this->isUsablePythonCandidate($pythonCandidate)) {
                continue;
            }

            $usableCandidates[] = $pythonCandidate;
            $process = new Process(
                [
                    $pythonCandidate,
                    $this->scriptPath,
                    $source,
                    $target,
                    $text,
                ],
                null,
                $this->buildPythonEnv()
            );

            $process->setTimeout(20);
            $process->run();

            if ($process->isSuccessful()) {
                $this->pythonPath = $pythonCandidate;

                return trim($process->getOutput());
            }

            $lastException = new ProcessFailedException($process);
        }

        if ($usableCandidates === []) {
            throw new \RuntimeException('La traduction automatique est indisponible : Python n est pas installe ou accessible sur ce serveur.');
        }

        if ($lastException instanceof \Throwable) {
            throw $lastException;
        }

        throw new \RuntimeException('Aucun interpreteur Python disponible pour la traduction automatique.');
    }

    public function translateNullable(?string $text, string $source = 'fr', string $target = 'en'): ?string
    {
        if ($text === null || trim($text) === '') {
            return $text;
        }

        return $this->translate($text, $source, $target);
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

        $candidates[] = 'python3';
        $candidates[] = 'python';
        $candidates[] = 'py';

        $resolved = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            $normalized = strtolower(trim($candidate));

            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;

            if (in_array($candidate, ['python', 'python3', 'py'], true) || is_file($candidate)) {
                $resolved[] = $candidate;
            }
        }

        return $resolved;
    }

    private function isUsablePythonCandidate(string $candidate): bool
    {
        $normalizedCandidate = strtolower(str_replace('/', '\\', trim($candidate)));

        if ($normalizedCandidate === '') {
            return false;
        }

        if (str_contains($normalizedCandidate, self::WINDOWS_STORE_PYTHON_MARKER)) {
            return false;
        }

        $versionProcess = new Process([$candidate, '--version'], null, $this->buildPythonEnv());
        $versionProcess->setTimeout(5);
        $versionProcess->run();

        if (!$versionProcess->isSuccessful()) {
            return false;
        }

        $versionOutput = strtolower(trim($versionProcess->getOutput() . ' ' . $versionProcess->getErrorOutput()));

        return str_starts_with($versionOutput, 'python ');
    }

    private function resolvePreferredPythonPath(string $fallback): string
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

        return $fallback;
    }
}
