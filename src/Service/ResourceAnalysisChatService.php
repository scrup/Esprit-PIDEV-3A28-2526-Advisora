<?php

namespace App\Service;

/**
 * Couche chat (optionnelle) au-dessus du moteur d'analyse.
 *
 * Objectif: repondre aux questions metier sans surconsommer les tokens.
 * Strategie:
 * - contexte compresse (KPI + top actions),
 * - reponse courte,
 * - fallback local si API indisponible.
 */
class ResourceAnalysisChatService
{
    private const HARD_API_KEY = '';
    private const HARD_MODEL = 'gpt-4o-mini';
    private const HARD_BASE_URL = '';
    private const HARD_MAX_TOKENS = 220;

    private ?string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $maxTokens;

    public function __construct()
    {
        $this->apiKey = self::HARD_API_KEY;
        $this->model = self::HARD_MODEL;
        $this->baseUrl = rtrim(self::HARD_BASE_URL, '/');
        $this->maxTokens = self::HARD_MAX_TOKENS;
    }

    /**
     * @param array<string, mixed> $analysis
     */
    public function answer(array $analysis, string $question): string
    {
        $question = trim($question);
        if ($question === '') {
            return 'Merci de poser une question sur l analyse ressources.';
        }

        // Fallback deterministic immediat pour rester robuste en production.
        $fallback = $this->buildLocalAnswer($analysis, $question);

        if ($this->apiKey === null || $this->apiKey === '') {
            return $fallback;
        }

        try {
            $payload = [
                'model' => $this->model,
                'temperature' => 0.1,
                'max_tokens' => $this->maxTokens,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un assistant operations supply. Reponse concise, actionnable, en francais. Pas de blabla.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->buildPrompt($analysis, $question),
                    ],
                ],
            ];

            $response = $this->sendJsonRequest(
                $this->baseUrl . '/chat/completions',
                [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                ],
                $payload
            );

            $decoded = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return $fallback;
            }

            $text = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
            if ($text === '') {
                return $fallback;
            }

            return $text;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * @param array<string, mixed> $analysis
     */
    private function buildPrompt(array $analysis, string $question): string
    {
        $kpis = is_array($analysis['kpis'] ?? null) ? $analysis['kpis'] : [];
        $actions = is_array($analysis['actions'] ?? null) ? $analysis['actions'] : [];
        $topActions = array_slice($actions, 0, 6);

        $actionLines = [];
        foreach ($topActions as $index => $action) {
            if (!is_array($action)) {
                continue;
            }

            $actionLines[] = sprintf(
                '%d) [%s] %s | %s | conf=%s%% | delai=%s',
                $index + 1,
                strtoupper((string) ($action['priority'] ?? 'basse')),
                (string) ($action['action_code'] ?? 'ACTION'),
                (string) ($action['resource_name'] ?? 'Ressource'),
                (string) ($action['confidence_pct'] ?? '0'),
                (string) ($action['delay'] ?? 'N/A')
            );
        }

        return implode("\n", [
            'Contexte compact (pour limiter tokens):',
            sprintf('- stock_critique: %s', (string) ($kpis['stock_critique'] ?? 0)),
            sprintf('- surstock: %s', (string) ($kpis['surstock'] ?? 0)),
            sprintf('- prix_anormal: %s', (string) ($kpis['prix_anormal'] ?? 0)),
            sprintf('- fournisseurs_a_risque: %s', (string) ($kpis['fournisseurs_a_risque'] ?? 0)),
            sprintf('- actions_priorite_haute: %s', (string) ($kpis['actions_priorite_haute'] ?? 0)),
            '- top_actions:',
            $actionLines !== [] ? implode("\n", $actionLines) : 'Aucune action',
            '',
            'Question utilisateur:',
            $question,
            '',
            'Reponds en 4 a 8 lignes max, avec priorites claires.',
        ]);
    }

    /**
     * @param array<string, mixed> $analysis
     */
    private function buildLocalAnswer(array $analysis, string $question): string
    {
        $kpis = is_array($analysis['kpis'] ?? null) ? $analysis['kpis'] : [];
        $actions = is_array($analysis['actions'] ?? null) ? $analysis['actions'] : [];
        $summary = is_array($analysis['summary'] ?? null) ? $analysis['summary'] : [];

        $q = mb_strtolower($question);
        if (str_contains($q, 'stock')) {
            return sprintf(
                "Stock critique: %d | Surstock: %d.\nPriorite immediate: traiter les actions STOCK_CRITIQUE_REAPPRO puis reequilibrer les surstocks.",
                (int) ($kpis['stock_critique'] ?? 0),
                (int) ($kpis['surstock'] ?? 0)
            );
        }

        if (str_contains($q, 'prix')) {
            return sprintf(
                "Prix anormal detecte: %d.\nAction recommandee: auditer PRIX_ANORMAL_AUDITER sur les ressources en priorite haute.",
                (int) ($kpis['prix_anormal'] ?? 0)
            );
        }

        if (str_contains($q, 'fournisseur')) {
            return sprintf(
                "Fournisseurs a risque: %d.\nAction prioritaire: diversifier les dependances critiques et securiser les ressources avec statut indisponible.",
                (int) ($kpis['fournisseurs_a_risque'] ?? 0)
            );
        }

        $headline = (string) ($summary['headline'] ?? 'Analyse disponible.');
        $narrative = (string) ($summary['narrative'] ?? '');
        $topCode = is_array($actions[0] ?? null) ? (string) ($actions[0]['action_code'] ?? 'Aucune action') : 'Aucune action';

        return trim($headline . "\n" . $narrative . "\nTop action: " . $topCode);
    }

    /**
     * @param array<int, string> $headers
     * @param array<string, mixed> $payload
     * @return array{status:int, body:string}
     */
    private function sendJsonRequest(string $url, array $headers, array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('HTTP init error');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
        ]);

        $response = curl_exec($curl);
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException($error !== '' ? $error : 'HTTP error');
        }

        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return [
            'status' => $status,
            'body' => (string) $response,
        ];
    }

}
