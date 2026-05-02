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
    private const HARD_MODEL = 'gpt-4o';
    private const HARD_BASE_URL = '';
    private const HARD_MAX_TOKENS = 320;
    private const OFF_TOPIC_MESSAGE = 'Desole, je reponds uniquement aux questions de gestion des ressources/projets (stock, reservations, fournisseurs, prix, KPI, actions).';
    /**
     * @var string[]
     */
    private const DOMAIN_KEYWORDS = [
        'ressource',
        'resource',
        'resources',
        'stock',
        'surstock',
        'rupture',
        'reappro',
        'reservation',
        'reservations',
        'fournisseur',
        'fournisseurs',
        'fornisseur',
        'fornisseurs',
        'fourniseur',
        'fourniseurs',
        'prix',
        'kpi',
        'analyse',
        'action',
        'actions',
        'priorite',
        'projet',
        'projets',
        'project',
        'projects',
        'supply',
        'gestion',
        'inventaire',
        'delai',
        'confiance',
    ];

    private string $apiKey;
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

        if (!$this->isManagementQuestion($question)) {
            return self::OFF_TOPIC_MESSAGE;
        }

        if ($this->isSupplierListingQuestion($question)) {
            return $this->buildSupplierListAnswer($analysis);
        }

        if ($this->isResourceListingQuestion($question)) {
            return $this->buildResourceListAnswer($analysis);
        }

        // Fallback deterministic immediat pour rester robuste en production.
        $fallback = $this->buildLocalAnswer($analysis, $question);

        if ($this->apiKey === '') {
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
                        'content' => 'Tu es un assistant operations supply pour la gestion des ressources/projets. Reponse concise, actionnable, en francais. Si la question est hors sujet, reponds exactement: "' . self::OFF_TOPIC_MESSAGE . '".',
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
     * @param array<string, mixed> $analysis
     */
    private function buildResourceListAnswer(array $analysis): string
    {
        $rows = is_array($analysis['resource_signals'] ?? null) ? $analysis['resource_signals'] : [];
        if ($rows === []) {
            return 'Aucune ressource analysee pour le moment. Clique sur "Analyser" pour charger les donnees.';
        }

        $lines = [];
        $max = min(10, count($rows));
        for ($index = 0; $index < $max; ++$index) {
            $row = $rows[$index];
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['resource_id'] ?? 0);
            $name = trim((string) ($row['resource_name'] ?? 'Ressource'));
            $supplier = trim((string) ($row['supplier_name'] ?? 'Non renseigne'));
            $stockAvailable = (int) ($row['stock_available'] ?? 0);
            $stockTotal = (int) ($row['stock_total'] ?? 0);
            $status = trim((string) ($row['status'] ?? 'N/A'));

            $lines[] = sprintf(
                '%d) #%d %s | fournisseur: %s | stock: %d/%d | statut: %s',
                $index + 1,
                $id,
                $name !== '' ? $name : 'Ressource',
                $supplier !== '' ? $supplier : 'Non renseigne',
                $stockAvailable,
                $stockTotal,
                $status !== '' ? $status : 'N/A'
            );
        }

        if ($lines === []) {
            return 'Aucune ressource valide dans le snapshot analyse.';
        }

        return "Voici les ressources existantes:\n" . implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $analysis
     */
    private function buildSupplierListAnswer(array $analysis): string
    {
        $rows = is_array($analysis['resource_signals'] ?? null) ? $analysis['resource_signals'] : [];
        if ($rows === []) {
            return 'Aucun fournisseur detecte pour le moment. Clique sur "Analyser" pour charger les donnees.';
        }

        $supplierNames = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $supplier = trim((string) ($row['supplier_name'] ?? 'Non renseigne'));
            if ($supplier === '') {
                $supplier = 'Non renseigne';
            }

            if (!in_array($supplier, $supplierNames, true)) {
                $supplierNames[] = $supplier;
            }
        }

        if ($supplierNames === []) {
            return 'Aucun fournisseur detecte dans le snapshot analyse.';
        }

        sort($supplierNames, SORT_NATURAL | SORT_FLAG_CASE);
        $lines = [];
        foreach ($supplierNames as $index => $supplier) {
            $lines[] = sprintf(
                '%d) %s',
                $index + 1,
                $supplier
            );
        }

        return "Voici les fournisseurs:\n" . implode("\n", $lines);
    }

    private function isManagementQuestion(string $question): bool
    {
        $normalized = $this->normalizeText($question);
        if ($normalized === '') {
            return false;
        }

        foreach (self::DOMAIN_KEYWORDS as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isResourceListingQuestion(string $question): bool
    {
        $normalized = $this->normalizeText($question);
        if ($normalized === '') {
            return false;
        }

        $resourceHints = ['ressource', 'resource', 'resources', 'inventaire', 'stock'];
        $listHints = ['donne', 'donner', 'donnee', 'liste', 'affiche', 'afficher', 'existe', 'exist'];

        $hasResourceHint = false;
        foreach ($resourceHints as $hint) {
            if (str_contains($normalized, $hint)) {
                $hasResourceHint = true;
                break;
            }
        }

        if (!$hasResourceHint) {
            return false;
        }

        foreach ($listHints as $hint) {
            if (str_contains($normalized, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function isSupplierListingQuestion(string $question): bool
    {
        $normalized = $this->normalizeText($question);
        if ($normalized === '') {
            return false;
        }

        $supplierHints = [
            'fournisseur',
            'fournisseurs',
            'fornisseur',
            'fornisseurs',
            'fourniseur',
            'fourniseurs',
            'supplier',
            'suppliers',
        ];
        $listHints = ['donne', 'donner', 'donnee', 'liste', 'affiche', 'afficher', 'existe', 'exist'];

        $hasSupplierHint = false;
        foreach ($supplierHints as $hint) {
            if (str_contains($normalized, $hint)) {
                $hasSupplierHint = true;
                break;
            }
        }

        if (!$hasSupplierHint) {
            return false;
        }

        foreach ($listHints as $hint) {
            if (str_contains($normalized, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && trim($ascii) !== '') {
            $value = strtolower($ascii);
        }

        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
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
