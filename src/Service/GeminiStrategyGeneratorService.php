<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Strategie;

class GeminiStrategyGeneratorService
{
    private bool $lastGenerationUsedAi = false;
    private ?string $lastGenerationWarning = null;

    public function __construct(
        private ?string $apiKey = null,
        private ?string $model = null,
        private ?string $baseUrl = null
    ) {
        $this->apiKey = $this->resolveFirstNonEmpty(
            $this->apiKey,
            $this->readEnv('GEMINI_API_KEY'),
            $this->readEnv('GOOGLE_API_KEY')
        );

        $this->model = $this->resolveFirstNonEmpty($this->model, $this->readEnv('GEMINI_MODEL')) ?? 'gemini-2.5-flash';
        $this->baseUrl = rtrim(
            $this->resolveFirstNonEmpty($this->baseUrl, $this->readEnv('GEMINI_API_BASE_URL'))
                ?? 'https://generativelanguage.googleapis.com/v1beta',
            '/'
        );
    }

    public function generate(Project $project): array
    {
        $this->lastGenerationUsedAi = false;
        $this->lastGenerationWarning = null;

        if ($this->apiKey === null || $this->apiKey === '') {
            $this->lastGenerationWarning = 'Cle Gemini absente. Une generation de secours a ete appliquee.';

            return $this->buildFallbackRecommendation($project);
        }

        try {
            $rawPayload = $this->requestGeneratedRecommendation($this->buildPrompt($project));
            $normalized = $this->normalizeRecommendationPayload($rawPayload, $project);
            $this->lastGenerationUsedAi = true;

            return $normalized;
        } catch (\Throwable $exception) {
            $this->lastGenerationWarning = 'Generation Gemini indisponible, bascule sur une suggestion de secours.';

            return $this->buildFallbackRecommendation($project);
        }
    }

    public function getLastGenerationMeta(): array
    {
        return [
            'used_ai' => $this->lastGenerationUsedAi,
            'warning' => $this->lastGenerationWarning,
            'model' => $this->model,
            'configured' => $this->apiKey !== null && $this->apiKey !== '',
        ];
    }

    private function requestGeneratedRecommendation(string $prompt): array
    {
        $url = sprintf('%s/models/%s:generateContent', $this->baseUrl, rawurlencode((string) $this->model));
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'responseMimeType' => 'application/json',
            ],
        ];

        $response = $this->sendJsonRequest($url, [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $this->apiKey,
        ], $payload);

        if ((int) ($response['status'] ?? 0) >= 400) {
            throw new \RuntimeException('Erreur API Gemini.');
        }

        $decoded = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Reponse Gemini invalide.');
        }

        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            throw new \RuntimeException('Reponse Gemini vide.');
        }

        $json = json_decode($this->extractJsonDocument($text), true);
        if (!is_array($json)) {
            throw new \RuntimeException('JSON Gemini invalide.');
        }

        return $json;
    }

    private function sendJsonRequest(string $url, array $headers, array $payload): array
    {
        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $curl = curl_init($url);

        if ($curl === false) {
            throw new \RuntimeException('Impossible d initialiser la requete Gemini.');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $encodedPayload,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $body = curl_exec($curl);
        if ($body === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException($error !== '' ? $error : 'Requete Gemini echouee.');
        }

        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return [
            'status' => $status,
            'body' => (string) $body,
        ];
    }

    private function normalizeRecommendationPayload(array $payload, Project $project): array
    {
        $fallback = $this->buildFallbackRecommendation($project);
        $name = trim((string) ($payload['nomStrategie'] ?? ''));
        $type = trim((string) ($payload['type'] ?? ''));

        $budget = is_numeric($payload['budgetTotal'] ?? null) ? (float) $payload['budgetTotal'] : $fallback['budgetTotal'];
        $gain = is_numeric($payload['gainEstime'] ?? null) ? (float) $payload['gainEstime'] : $fallback['gainEstime'];
        $duration = is_numeric($payload['DureeTerme'] ?? null) ? (int) $payload['DureeTerme'] : $fallback['DureeTerme'];

        if ($budget !== null && $budget < 0) {
            $budget = $fallback['budgetTotal'];
        }

        if ($gain !== null && $gain < -100) {
            $gain = $fallback['gainEstime'];
        }

        if ($duration !== null && $duration < 1) {
            $duration = $fallback['DureeTerme'];
        }

        if ($name !== '') {
            $name = preg_replace('/^strategie\s+ia\s*[-:–]?\s*/iu', '', $name) ?? $name;
            $name = trim($name);
        }

        return [
            'nomStrategie' => $name !== '' ? $name : $fallback['nomStrategie'],
            'type' => $type !== '' ? mb_strtolower($type) : $fallback['type'],
            'budgetTotal' => $budget,
            'gainEstime' => $gain,
            'DureeTerme' => $duration,
            'statusStrategie' => Strategie::STATUS_IN_PROGRESS,
            'top_3' => [],
        ];
    }

    private function buildFallbackRecommendation(Project $project): array
    {
        $projectTitle = trim((string) $project->getTitleProj());
        $projectType = mb_strtolower(trim((string) $project->getTypeProj()));
        $projectBudget = $project->getBudgetProj();
        $projectProgress = $project->getAvancementProj();

        $name = $projectTitle !== '' ? 'Strategie ' . $projectTitle : 'Strategie personnalisee';
        $type = $projectType !== '' ? str_replace(' ', '_', $projectType) : 'optimisation';

        $budget = null;
        if ($projectBudget !== null && $projectBudget > 0) {
            $budget = round($projectBudget * 0.35, 2);
        }

        $duration = 6;
        if ($projectProgress !== null) {
            if ($projectProgress >= 70) {
                $duration = 4;
            } elseif ($projectProgress <= 20) {
                $duration = 9;
            }
        }

        return [
            'nomStrategie' => $name,
            'type' => $type,
            'budgetTotal' => $budget,
            'gainEstime' => $budget !== null ? round($budget * 0.18, 2) : null,
            'DureeTerme' => $duration,
            'statusStrategie' => Strategie::STATUS_IN_PROGRESS,
            'top_3' => [],
        ];
    }

    private function buildPrompt(Project $project): string
    {
        $title = $project->getTitleProj() ?: 'Projet sans titre';
        $description = $project->getDescriptionProj() ?: 'Description non fournie';
        $type = $project->getTypeProj() ?: 'non_defini';
        $status = $project->getStateProj() ?: 'PENDING';
        $budget = $project->getBudgetProj();
        $progress = $project->getAvancementProj();

        return implode("\n", [
            'Tu es un expert en strategie business.',
            'Genere une strategie alternative pour le projet suivant.',
            'Retourne uniquement un JSON valide avec les cles:',
            'nomStrategie, type, budgetTotal, gainEstime, DureeTerme',
            '',
            'Contraintes:',
            '- nomStrategie: court, specifique au projet',
            '- nomStrategie ne doit pas commencer par "Strategie IA"',
            '- type: MARKETING, FINANCIERE, OPERATIONNELLE, DIGITALE, RH, CROISSANCE, COMMERCIALE, JURIDIQUE pas de type invente',
            '- budgetTotal: nombre decimal >= 0 et <= budget du projet ou null si budget du projet non defini',
            '- gainEstime: montant de gain attendu en TND (nombre decimal >= 0)',
            '- DureeTerme: entier en mois (1 a 36)'
            ,
            '',
            'Contexte projet:',
            sprintf('- titleProj: %s', $title),
            sprintf('- descriptionProj: %s', $description),
            sprintf('- typeProj: %s', $type),
            sprintf('- stateProj: %s', $status),
            sprintf('- budgetProj: %s', $budget !== null ? (string) $budget : 'null'),
            sprintf('- avancementProj: %s', $progress !== null ? (string) $progress : 'null'),
        ]);
    }

    private function extractJsonDocument(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed !== '' && $trimmed[0] === '{') {
            return $trimmed;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start === false || $end === false || $end < $start) {
            return $trimmed;
        }

        return substr($trimmed, $start, $end - $start + 1);
    }

    private function readEnv(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function resolveFirstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
}
