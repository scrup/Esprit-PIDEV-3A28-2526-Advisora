<?php

namespace App\Service;

use App\Entity\Objective;
use App\Entity\Strategie;
use App\Entity\SwotItem;

class GeminiTowsObjectiveGeneratorService
{
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

    /**
     * @return array{
     *     name: string,
     *     description: string,
     *     priority_key: string,
     *     used_ai: bool,
     *     warning: string|null
     * }
     */
    public function generate(
        Strategie $strategy,
        SwotItem $sourceItem,
        SwotItem $targetItem,
        string $towsType
    ): array {
        $fallback = $this->buildFallback($strategy, $sourceItem, $targetItem, $towsType);

        if ($this->apiKey === null || $this->apiKey === '') {
            $fallback['warning'] = 'Cle Gemini absente. Objectif TOWS genere en mode secours.';

            return $fallback;
        }

        try {
            $generated = $this->requestGeneratedObjective($strategy, $sourceItem, $targetItem, $towsType);

            return [
                'name' => $this->normalizeText($generated['name'] ?? null, $fallback['name']),
                'description' => $this->normalizeText($generated['description'] ?? null, $fallback['description']),
                'priority_key' => $this->normalizePriorityKey($generated['priority_key'] ?? null, $fallback['priority_key']),
                'used_ai' => true,
                'warning' => null,
            ];
        } catch (\Throwable) {
            $fallback['warning'] = 'Generation Gemini indisponible. Objectif TOWS cree avec le modele de secours.';

            return $fallback;
        }
    }

    /**
     * @return array{name: string, description: string, priority_key: string}
     */
    private function requestGeneratedObjective(
        Strategie $strategy,
        SwotItem $sourceItem,
        SwotItem $targetItem,
        string $towsType
    ): array {
        $url = sprintf('%s/models/%s:generateContent', $this->baseUrl, rawurlencode((string) $this->model));
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $this->buildPrompt($strategy, $sourceItem, $targetItem, $towsType)],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.5,
                'responseMimeType' => 'application/json',
            ],
        ];

        $response = $this->sendJsonRequest($url, [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $this->apiKey,
        ], $payload);

        if ($response['status'] >= 400) {
            throw new \RuntimeException('Gemini API error.');
        }

        $decoded = json_decode($response['body'], true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid Gemini response.');
        }

        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!is_string($text) || trim($text) === '') {
            throw new \RuntimeException('Empty Gemini response.');
        }

        $parsed = json_decode($this->extractJsonDocument($text), true);
        if (!is_array($parsed)) {
            throw new \RuntimeException('Invalid Gemini JSON.');
        }

        return [
            'name' => (string) ($parsed['objective_name'] ?? ''),
            'description' => (string) ($parsed['objective_description'] ?? ''),
            'priority_key' => (string) ($parsed['priority_key'] ?? ''),
        ];
    }

    private function buildPrompt(
        Strategie $strategy,
        SwotItem $sourceItem,
        SwotItem $targetItem,
        string $towsType
    ): string {
        $strategyName = trim((string) $strategy->getNomStrategie());
        $strategyName = $strategyName !== '' ? $strategyName : 'Strategie sans nom';
        $source = trim($sourceItem->getDescription());
        $target = trim($targetItem->getDescription());
        $sourceWeight = $sourceItem->getWeight() ?? 0;
        $targetWeight = $targetItem->getWeight() ?? 0;

        return implode("\n", [
            'Tu es un consultant en strategie TOWS.',
            'Cree un seul objectif actionnable, tres concret, base sur le croisement fourni.',
            'Reponds uniquement en JSON valide avec EXACTEMENT ces cles:',
            'objective_name, objective_description, priority_key',
            '',
            'Contraintes:',
            '- objective_name: phrase courte (max 80 caracteres).',
            '- objective_description: phrase executable (max 220 caracteres).',
            '- priority_key: low, medium, high ou urgent.',
            '- Rester coherent avec le type TOWS et les poids.',
            '',
            sprintf('Strategie: %s', $strategyName),
            sprintf('Type TOWS: %s', strtoupper(trim($towsType))),
            sprintf('Source SWOT (poids %d): %s', $sourceWeight, $source),
            sprintf('Cible SWOT (poids %d): %s', $targetWeight, $target),
        ]);
    }

    /**
     * @return array{
     *     name: string,
     *     description: string,
     *     priority_key: string,
     *     used_ai: bool,
     *     warning: string|null
     * }
     */
    private function buildFallback(
        Strategie $strategy,
        SwotItem $sourceItem,
        SwotItem $targetItem,
        string $towsType
    ): array {
        $source = trim($sourceItem->getDescription());
        $target = trim($targetItem->getDescription());
        $prefix = match (strtoupper(trim($towsType))) {
            Objective::TOWS_TYPE_SO => 'Exploiter',
            Objective::TOWS_TYPE_WO => 'Corriger',
            Objective::TOWS_TYPE_ST => 'Mobiliser',
            Objective::TOWS_TYPE_WT => 'Reduire',
            default => 'Activer',
        };

        $name = sprintf(
            '%s %s pour %s',
            $prefix,
            $this->truncateText($source, 40),
            $this->truncateText($target, 35)
        );

        $description = match (strtoupper(trim($towsType))) {
            Objective::TOWS_TYPE_SO => sprintf('Utiliser "%s" pour capter "%s".', $source, $target),
            Objective::TOWS_TYPE_WO => sprintf('Reduire "%s" afin de mieux exploiter "%s".', $source, $target),
            Objective::TOWS_TYPE_ST => sprintf('S appuyer sur "%s" pour limiter "%s".', $source, $target),
            Objective::TOWS_TYPE_WT => sprintf('Diminuer "%s" pour eviter l impact de "%s".', $source, $target),
            default => sprintf('Relier "%s" a "%s".', $source, $target),
        };

        $priorityKey = $this->guessPriorityKeyFromWeights($sourceItem->getWeight(), $targetItem->getWeight());

        return [
            'name' => $this->truncateText($name, 80),
            'description' => $this->truncateText($description, 220),
            'priority_key' => $priorityKey,
            'used_ai' => false,
            'warning' => null,
        ];
    }

    private function guessPriorityKeyFromWeights(?int $sourceWeight, ?int $targetWeight): string
    {
        $score = ($sourceWeight ?? 0) + ($targetWeight ?? 0);

        return match (true) {
            $score >= 16 => 'urgent',
            $score >= 12 => 'high',
            $score >= 7 => 'medium',
            default => 'low',
        };
    }

    private function normalizePriorityKey(mixed $value, string $fallback): string
    {
        $normalized = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($normalized, ['low', 'medium', 'high', 'urgent'], true) ? $normalized : $fallback;
    }

    private function normalizeText(mixed $value, string $fallback): string
    {
        if (!is_string($value)) {
            return $fallback;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : $fallback;
    }

    private function truncateText(string $text, int $limit): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        if (mb_strlen($normalized) <= $limit) {
            return $normalized;
        }

        return rtrim(mb_substr($normalized, 0, max(0, $limit - 3))) . '...';
    }

    /**
     * @param string[] $headers
     * @param array<string, mixed> $payload
     *
     * @return array{status: int, body: string}
     */
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
