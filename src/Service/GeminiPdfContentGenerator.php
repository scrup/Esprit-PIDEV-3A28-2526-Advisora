<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Strategie;

/**
 * @phpstan-type GeminiHttpResponse array{status:int, body:string}
 * @phpstan-type OutcomePoint array{period:string, value:float}
 * @phpstan-type ExecutionPhase array{title:string, horizon:string, focus:string}
 * @phpstan-type KpiRow array{name:string, target:string, cadence:string}
 * @phpstan-type OutcomeChartPoint array{period:string, value:float, value_label:string, x:float, y:float, value_y:float}
 * @phpstan-type OutcomeChart array{
 *     width:int,
 *     height:int,
 *     plot_left:int,
 *     plot_right:int,
 *     plot_top:int,
 *     plot_bottom:int,
 *     x_label_y:int,
 *     y_ticks:array<int, array{label:string, y:float}>,
 *     points:array<int, OutcomeChartPoint>,
 *     polyline_points:string,
 *     area_points:string,
 *     start_value_label:string,
 *     final_value_label:string,
 *     aria_label:string
 * }
 * @phpstan-type PlaybookContent array{
 *     executive_summary:string,
 *     strategic_diagnosis:string,
 *     highlights:array<int, string>,
 *     strategic_priorities:array<int, string>,
 *     opportunities:array<int, string>,
 *     expected_outcome_curve:array<int, OutcomePoint>,
 *     expected_outcome_summary:string,
 *     expected_outcome_chart:OutcomeChart,
 *     execution_phases:array<int, ExecutionPhase>,
 *     risks:array<int, string>,
 *     mitigation_actions:array<int, string>,
 *     actions:array<int, string>,
 *     kpis:array<int, KpiRow>
 * }
 */
class GeminiPdfContentGenerator
{
    private const MAX_TRANSIENT_RETRY_ATTEMPTS = 3;
    private const TRANSIENT_RETRY_DELAYS_MS = [800, 1500];

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

        $this->model = $this->normalizeModelName(
            $this->resolveFirstNonEmpty($this->model, $this->readEnv('GEMINI_MODEL')) ?? 'gemini-2.5-flash'
        );

        $this->baseUrl = rtrim(
            $this->resolveFirstNonEmpty($this->baseUrl, $this->readEnv('GEMINI_API_BASE_URL'))
                ?? 'https://generativelanguage.googleapis.com/v1beta',
            '/'
        );
    }

    /**
     * @return PlaybookContent
     */
    public function generate(Strategie $strategy, ?Project $project): array
    {
        $this->resetLastGenerationMeta();

        if ($this->apiKey === null || $this->apiKey === '') {
            $this->lastGenerationWarning = 'La cle API Gemini est absente. Configurez GEMINI_API_KEY ou GOOGLE_API_KEY pour activer la generation IA.';

            return $this->buildFallbackContent($strategy, $project);
        }

        $prompt = $this->buildPrompt($strategy, $project);

        try {
            $decoded = $this->requestGeneratedContent($prompt, true);
        } catch (\Throwable $exception) {
            if ($this->shouldRetryWithoutSchema($exception)) {
                try {
                    $decoded = $this->requestGeneratedContent($prompt, false);
                } catch (\Throwable $fallbackException) {
                    $exception = $fallbackException;
                }
            }

            if (!isset($decoded)) {
                $this->lastGenerationWarning = $this->buildGenerationWarning($exception);

                return $this->buildFallbackContent($strategy, $project);
            }
        }

        $this->lastGenerationUsedAi = true;

        return $this->normalizeGeneratedContent($decoded, $strategy, $project);
    }

    /**
     * @return array{used_ai: bool, warning: string|null, model: string|null, configured: bool}
     */
    public function getLastGenerationMeta(): array
    {
        return [
            'used_ai' => $this->lastGenerationUsedAi,
            'warning' => $this->lastGenerationWarning,
            'model' => $this->model,
            'configured' => $this->apiKey !== null && $this->apiKey !== '',
        ];
    }

    /**
     * @param array<int, string> $headers
     * @param array<string, mixed> $payload
     *
     * @return GeminiHttpResponse
     */
    protected function sendJsonRequest(string $url, array $headers, array $payload): array
    {
        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $curl = curl_init($url);

        if ($curl === false) {
            throw new \RuntimeException('Impossible d initialiser la requete HTTP Gemini.');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $encodedPayload,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $body = curl_exec($curl);

        if ($body === false) {
            $error = curl_error($curl);
            curl_close($curl);

            throw new \RuntimeException(sprintf('La requete Gemini a echoue: %s', $error !== '' ? $error : 'erreur inconnue'));
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return [
            'status' => $statusCode,
            'body' => (string) $body,
        ];
    }

    protected function pauseBeforeRetry(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requestGeneratedContent(string $prompt, bool $withResponseSchema): array
    {
        $generationConfig = [
            'responseMimeType' => 'application/json',
            'temperature' => 0.6,
        ];

        if ($withResponseSchema) {
            $generationConfig['responseJsonSchema'] = $this->getResponseSchema();
        }

        $response = $this->executeJsonRequestWithRetry(
            sprintf('%s/models/%s:generateContent', $this->baseUrl, rawurlencode((string) $this->model)),
            [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey,
            ],
            [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
                'generationConfig' => $generationConfig,
            ]
        );

        if ($response['status'] >= 400) {
            throw $this->buildApiErrorException($response);
        }

        $payload = $this->decodeApiPayload($response['body']);

        if (isset($payload['promptFeedback']['blockReason'])) {
            throw new \RuntimeException(sprintf(
                'Gemini a bloque la requete: %s',
                (string) $payload['promptFeedback']['blockReason']
            ));
        }

        $finishReason = $payload['candidates'][0]['finishReason'] ?? null;
        if (!in_array($finishReason, [null, 'STOP', 'MAX_TOKENS'], true)) {
            throw new \RuntimeException(sprintf('Generation Gemini interrompue (%s).', (string) $finishReason));
        }

        $jsonText = $this->extractCandidateText($payload);
        if ($jsonText === null) {
            throw new \RuntimeException('La reponse Gemini est vide ou invalide.');
        }

        try {
            $decoded = json_decode($this->extractJsonDocument($jsonText), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('La reponse Gemini n est pas un JSON valide.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('La reponse Gemini ne correspond pas au schema attendu.');
        }

        return $decoded;
    }

    /**
     * @param array<int, string> $headers
     * @param array<string, mixed> $payload
     *
     * @return GeminiHttpResponse
     */
    private function executeJsonRequestWithRetry(string $url, array $headers, array $payload): array
    {
        for ($attempt = 1; $attempt <= self::MAX_TRANSIENT_RETRY_ATTEMPTS; ++$attempt) {
            try {
                $response = $this->sendJsonRequest($url, $headers, $payload);
            } catch (\Throwable $exception) {
                if (!$this->shouldRetryRequestException($exception) || $attempt >= self::MAX_TRANSIENT_RETRY_ATTEMPTS) {
                    throw $exception;
                }

                $this->pauseBeforeRetry($this->getRetryDelayMilliseconds($attempt));

                continue;
            }

            if (!$this->isTransientHttpStatus((int) $response['status']) || $attempt >= self::MAX_TRANSIENT_RETRY_ATTEMPTS) {
                return $response;
            }

            $this->pauseBeforeRetry($this->getRetryDelayMilliseconds($attempt));
        }

        throw new \RuntimeException('La requete Gemini a echoue apres plusieurs tentatives.');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeApiPayload(string $rawPayload): array
    {
        try {
            $decoded = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('La reponse de l API Gemini n est pas un JSON valide.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('La reponse de l API Gemini ne correspond pas au format attendu.');
        }

        return $decoded;
    }

    /**
     * @param GeminiHttpResponse $response
     */
    private function buildApiErrorException(array $response): \RuntimeException
    {
        $status = $response['status'];

        return new \RuntimeException(sprintf(
            'Gemini API error (%d): %s',
            $status,
            $this->extractApiErrorMessage($response['body'], $status)
        ));
    }

    private function extractApiErrorMessage(string $rawPayload, int $status): string
    {
        $rawPayload = trim($rawPayload);

        if ($rawPayload !== '') {
            try {
                $payload = $this->decodeApiPayload($rawPayload);
                $message = $payload['error']['message'] ?? null;

                if (is_string($message) && trim($message) !== '') {
                    return trim($message);
                }
            } catch (\Throwable) {
            }
        }

        if ($this->isTransientHttpStatus($status)) {
            return 'Service temporairement indisponible.';
        }

        return 'La requete Gemini a echoue.';
    }

    private function buildPrompt(Strategie $strategy, ?Project $project): string
    {
        $projectTitle = $project?->getTitleProj() ?: 'Aucun projet associe';
        $projectDescription = $project?->getDescriptionProj() ?: 'Non definie';
        $projectBudget = $project?->getBudgetProj();
        $projectProgress = $project?->getAvancementProj();
        $projectType = $project?->getTypeProj() ?: 'Non defini';
        $projectStatus = $project?->getStatusLabel() ?? 'Non defini';
        $projectCreatedAt = $project?->getCreatedAtProj()?->format('d/m/Y') ?: 'Non definie';
        $strategyOwner = $strategy->getUser()
            ? trim(sprintf(
                '%s %s',
                (string) $strategy->getUser()->getPrenomUser(),
                (string) $strategy->getUser()->getNomUser()
            ))
            : 'Non defini';
        $objectives = [];

        foreach ($strategy->getObjectives() as $objective) {
            $objectiveLabel = trim((string) ($objective->getNomObj() ?: 'Objectif'));
            $objectiveDescription = trim((string) ($objective->getDescriptionOb() ?: ''));
            $objectives[] = sprintf(
                '- %s [%s]%s',
                $objectiveLabel,
                $objective->getPriorityLabel(),
                $objectiveDescription !== '' ? ': ' . $objectiveDescription : ''
            );
        }

        if ($objectives === []) {
            $objectives[] = '- Aucun objectif rattache pour le moment';
        }

        $estimatedGainAmount = $this->calculateEstimatedGainAmount($strategy);
        $estimatedRoiPercent = $this->calculateEstimatedRoiPercent($strategy);

        return implode("\n", [
            'Tu es un conseiller strategique senior pour startups et PME innovantes.',
            'Genere un playbook strategique premium en francais, dense, concret et directement exploitable par un dirigeant.',
            'Retourne uniquement un JSON valide conforme au schema impose. Aucun markdown, aucun texte hors JSON.',
            'Le document doit aider a comprendre la situation, prioriser les chantiers, organiser l execution et piloter la performance.',
            '',
            'Contexte du projet :',
            sprintf('- Projet : %s', $projectTitle),
            sprintf('- Description projet : %s', $projectDescription),
            sprintf('- Type projet : %s', $projectType),
            sprintf('- Statut projet : %s', $projectStatus),
            sprintf('- Date de creation projet : %s', $projectCreatedAt),
            sprintf(
                '- Budget projet : %s',
                $projectBudget !== null ? $this->formatAmount($projectBudget) . ' DT' : 'Non defini'
            ),
            sprintf(
                '- Avancement projet : %s',
                $projectProgress !== null ? number_format((float) $projectProgress, 0, ',', ' ') . '%' : 'Non defini'
            ),
            '',
            'Contexte de la strategie :',
            sprintf('- Nom : %s', $strategy->getNomStrategie() ?: 'Strategie sans nom'),
            sprintf('- Type : %s', $strategy->getType() ?: 'Non defini'),
            sprintf('- Responsable : %s', $strategyOwner !== '' ? $strategyOwner : 'Non defini'),
            sprintf(
                '- Duree : %s mois',
                $strategy->getDureeTerme() !== null ? (string) $strategy->getDureeTerme() : 'Non definie'
            ),
            sprintf(
                '- Budget strategie : %s',
                $strategy->getBudgetTotal() !== null ? $this->formatAmount($strategy->getBudgetTotal()) . ' DT' : 'Non defini'
            ),
            sprintf(
                '- Gain estime (montant) : %s',
                $estimatedGainAmount !== null ? $this->formatAmount($estimatedGainAmount) . ' DT' : 'Non defini'
            ),
            sprintf(
                '- ROI estime : %s',
                $estimatedRoiPercent !== null ? number_format($estimatedRoiPercent, 2, ',', ' ') . '%' : 'Non defini'
            ),
            sprintf('- Justification : %s', $strategy->getJustification() ?: 'Aucune justification fournie'),
            '',
            'Objectifs relies :',
            implode("\n", $objectives),
            '',
            'Structure attendue :',
            '- executive_summary : 3 a 5 phrases qui synthetisent ambition, faisabilite et conditions de succes.',
            '- strategic_diagnosis : 1 paragraphe analytique qui explique le point de depart, les tensions et les leviers.',
            '- highlights : 3 a 5 faits marquants tres concrets.',
            '- strategic_priorities : 3 a 5 priorites de pilotage ou de transformation.',
            '- opportunities : 3 a 5 leviers de creation de valeur.',
            '- expected_outcome_curve : 4 a 8 points chronologiques, chacun avec period et value numerique, pour projeter la progression de l outcome attendu sur la duree.',
            '- execution_phases : exactement 3 phases, chacune avec title, horizon et focus.',
            '- risks : 2 a 5 risques concrets.',
            '- mitigation_actions : 3 a 5 contre-mesures tres pratiques.',
            '- actions : 4 a 6 actions prioritaires immediates.',
            '- kpis : 3 a 5 objets avec name, target et cadence. Les targets doivent etre mesurables ou directement auditables.',
            '',
            'Contraintes de generation :',
            '- Les contenus doivent etre realistes par rapport au budget, a la duree, a l avancement et aux objectifs.',
            '- Evite les generalites, les slogans et le jargon vide.',
            '- Appuie-toi sur les donnees fournies et sur des deductions plausibles, sans inventer de faits externes.',
            '- Les risques et les contre-mesures doivent mentionner les contraintes budgetaires, de gouvernance ou d execution quand elles sont pertinentes.',
            '- Chaque element doit etre specifique au contexte du projet et de la strategie.',
            '- La courbe expected_outcome_curve doit etre progressive, credible et se terminer proche du ROI estime lorsqu il est disponible.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getResponseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'propertyOrdering' => [
                'executive_summary',
                'strategic_diagnosis',
                'highlights',
                'strategic_priorities',
                'opportunities',
                'expected_outcome_curve',
                'execution_phases',
                'risks',
                'mitigation_actions',
                'actions',
                'kpis',
            ],
            'properties' => [
                'executive_summary' => [
                    'type' => 'string',
                    'description' => 'Resume executif en 3 a 5 phrases, en francais.',
                ],
                'strategic_diagnosis' => [
                    'type' => 'string',
                    'description' => 'Analyse concise mais riche du point de depart, des tensions et des leviers.',
                ],
                'highlights' => [
                    'type' => 'array',
                    'description' => '3 a 5 faits saillants utiles pour presenter la strategie.',
                    'minItems' => 3,
                    'maxItems' => 5,
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'strategic_priorities' => [
                    'type' => 'array',
                    'description' => '3 a 5 priorites strategiques ou de pilotage a tenir dans la duree.',
                    'minItems' => 3,
                    'maxItems' => 5,
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'opportunities' => [
                    'type' => 'array',
                    'description' => '3 a 5 opportunites ou leviers de creation de valeur.',
                    'minItems' => 3,
                    'maxItems' => 5,
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'expected_outcome_curve' => [
                    'type' => 'array',
                    'description' => 'Projection chronologique de l outcome attendu sur la duree de la strategie.',
                    'minItems' => 4,
                    'maxItems' => 8,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'propertyOrdering' => [
                            'period',
                            'value',
                        ],
                        'properties' => [
                            'period' => [
                                'type' => 'string',
                            ],
                            'value' => [
                                'type' => 'number',
                            ],
                        ],
                        'required' => [
                            'period',
                            'value',
                        ],
                    ],
                ],
                'execution_phases' => [
                    'type' => 'array',
                    'description' => 'Exactement 3 phases d execution couvrant le debut, le milieu et la fin du programme.',
                    'minItems' => 3,
                    'maxItems' => 3,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'propertyOrdering' => [
                            'title',
                            'horizon',
                            'focus',
                        ],
                        'properties' => [
                            'title' => [
                                'type' => 'string',
                            ],
                            'horizon' => [
                                'type' => 'string',
                            ],
                            'focus' => [
                                'type' => 'string',
                            ],
                        ],
                        'required' => [
                            'title',
                            'horizon',
                            'focus',
                        ],
                    ],
                ],
                'risks' => [
                    'type' => 'array',
                    'description' => '2 a 5 risques ou points de vigilance concrets.',
                    'minItems' => 2,
                    'maxItems' => 5,
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'mitigation_actions' => [
                    'type' => 'array',
                    'description' => '3 a 5 contre-mesures concretes pour reduire les risques.',
                    'minItems' => 3,
                    'maxItems' => 5,
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'actions' => [
                    'type' => 'array',
                    'description' => '4 a 6 actions prioritaires a lancer.',
                    'minItems' => 4,
                    'maxItems' => 6,
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'kpis' => [
                    'type' => 'array',
                    'description' => '3 a 5 indicateurs de suivi avec nom, cible et cadence.',
                    'minItems' => 3,
                    'maxItems' => 5,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'propertyOrdering' => [
                            'name',
                            'target',
                            'cadence',
                        ],
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                            ],
                            'target' => [
                                'type' => 'string',
                            ],
                            'cadence' => [
                                'type' => 'string',
                            ],
                        ],
                        'required' => [
                            'name',
                            'target',
                            'cadence',
                        ],
                    ],
                ],
            ],
            'required' => [
                'executive_summary',
                'strategic_diagnosis',
                'highlights',
                'strategic_priorities',
                'opportunities',
                'expected_outcome_curve',
                'execution_phases',
                'risks',
                'mitigation_actions',
                'actions',
                'kpis',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractCandidateText(array $payload): ?string
    {
        $parts = $payload['candidates'][0]['content']['parts'] ?? null;
        if (!is_array($parts)) {
            return null;
        }

        $texts = [];
        foreach ($parts as $part) {
            if (!is_array($part) || !isset($part['text']) || !is_string($part['text'])) {
                continue;
            }

            $text = trim($part['text']);
            if ($text === '') {
                continue;
            }

            $texts[] = $text;
        }

        if ($texts === []) {
            return null;
        }

        return implode("\n", $texts);
    }

    private function extractJsonDocument(string $responseText): string
    {
        $responseText = trim($responseText);

        if (preg_match('/```(?:json)?\s*(.+?)\s*```/is', $responseText, $matches) === 1) {
            $responseText = trim($matches[1]);
        }

        if (str_starts_with($responseText, '{') && str_ends_with($responseText, '}')) {
            return $responseText;
        }

        $start = strpos($responseText, '{');
        $end = strrpos($responseText, '}');

        if ($start !== false && $end !== false && $end > $start) {
            return substr($responseText, $start, $end - $start + 1);
        }

        return $responseText;
    }

    /**
     * @param array<string, mixed> $decoded
     *
     * @return PlaybookContent
     */
    private function normalizeGeneratedContent(array $decoded, Strategie $strategy, ?Project $project): array
    {
        $fallback = $this->buildFallbackContent($strategy, $project);
        $expectedOutcomeCurve = $this->normalizeOutcomeCurve(
            $decoded['expected_outcome_curve'] ?? null,
            $fallback['expected_outcome_curve'],
            $strategy
        );

        return [
            'executive_summary' => $this->normalizeText($decoded['executive_summary'] ?? null, $fallback['executive_summary']),
            'strategic_diagnosis' => $this->normalizeText($decoded['strategic_diagnosis'] ?? null, $fallback['strategic_diagnosis']),
            'highlights' => $this->normalizeStringList($decoded['highlights'] ?? null, $fallback['highlights']),
            'strategic_priorities' => $this->normalizeStringList($decoded['strategic_priorities'] ?? null, $fallback['strategic_priorities']),
            'opportunities' => $this->normalizeStringList($decoded['opportunities'] ?? null, $fallback['opportunities']),
            'expected_outcome_curve' => $expectedOutcomeCurve,
            'expected_outcome_summary' => $this->buildOutcomeSummary($expectedOutcomeCurve, $strategy),
            'expected_outcome_chart' => $this->buildOutcomeChart($expectedOutcomeCurve, $strategy),
            'execution_phases' => $this->normalizeExecutionPhases($decoded['execution_phases'] ?? null, $fallback['execution_phases']),
            'risks' => $this->normalizeStringList($decoded['risks'] ?? null, $fallback['risks']),
            'mitigation_actions' => $this->normalizeStringList($decoded['mitigation_actions'] ?? null, $fallback['mitigation_actions']),
            'actions' => $this->normalizeStringList($decoded['actions'] ?? null, $fallback['actions']),
            'kpis' => $this->normalizeKpis($decoded['kpis'] ?? null, $fallback['kpis']),
        ];
    }

    /**
     * @return PlaybookContent
     */
    private function buildFallbackContent(Strategie $strategy, ?Project $project): array
    {
        $estimatedGainAmount = $this->calculateEstimatedGainAmount($strategy);
        $estimatedRoiPercent = $this->calculateEstimatedRoiPercent($strategy);
        $projectBudget = $project?->getBudgetProj();
        $projectTitle = $project?->getTitleProj() ?: 'Aucun projet associe';
        $projectStatus = $project?->getStatusLabel() ?? 'Non defini';
        $projectProgressLabel = $project?->getAvancementProj() !== null
            ? number_format((float) $project->getAvancementProj(), 0, ',', ' ') . '%'
            : 'Non defini';
        $projectOwner = $project?->getUser()
            ? trim(sprintf(
                '%s %s',
                (string) $project->getUser()->getPrenomUser(),
                (string) $project->getUser()->getNomUser()
            ))
            : 'Non defini';
        $strategyOwner = $strategy->getUser()
            ? trim(sprintf(
                '%s %s',
                (string) $strategy->getUser()->getPrenomUser(),
                (string) $strategy->getUser()->getNomUser()
            ))
            : 'Non defini';

        $riskMessages = [];
        if ($strategy->getBudgetTotal() !== null && $estimatedGainAmount !== null && $estimatedGainAmount < $strategy->getBudgetTotal()) {
            $riskMessages[] = sprintf(
                'Le gain estime en montant (%s DT) reste inferieur au budget engage (%s DT).',
                $this->formatAmount($estimatedGainAmount),
                $this->formatAmount($strategy->getBudgetTotal())
            );
        }

        if ($projectBudget !== null && $strategy->getBudgetTotal() !== null && $strategy->getBudgetTotal() > $projectBudget) {
            $riskMessages[] = sprintf(
                'Le budget de la strategie (%s DT) depasse le budget du projet (%s DT).',
                $this->formatAmount($strategy->getBudgetTotal()),
                $this->formatAmount($projectBudget)
            );
        }

        if ($riskMessages === []) {
            $riskMessages[] = 'Aucun risque bloquant n a ete detecte a partir des donnees budgetaires disponibles.';
        }

        $mitigationMessages = [
            'Installer un point de pilotage mensuel avec arbitrage budget, avancement et decisions ouvertes.',
            'Valider les hypotheses de valeur avant tout engagement de ressources supplementaires.',
            $project !== null
                ? 'Synchroniser les jalons de la strategie avec le calendrier reel du projet support.'
                : 'Associer rapidement un projet support pour clarifier les dependances et la gouvernance.',
        ];

        if ($strategy->getBudgetTotal() !== null && $estimatedGainAmount !== null && $estimatedGainAmount < $strategy->getBudgetTotal()) {
            $mitigationMessages[] = 'Reprioriser le perimetre ou renforcer la proposition de valeur si le ROI previsionnel reste insuffisant.';
        }

        if ($projectBudget !== null && $strategy->getBudgetTotal() !== null && $strategy->getBudgetTotal() > $projectBudget) {
            $mitigationMessages[] = 'Decouper le programme en lots et traiter en premier les chantiers au meilleur impact.';
        }

        $strategicPriorities = $this->buildStrategicPriorities($strategy);
        $expectedOutcomeCurve = $this->buildFallbackOutcomeCurve($strategy);

        return [
            'executive_summary' => sprintf(
                'La strategie "%s" vise une execution sur %d mois avec un budget de %s DT. Elle est rattachee au projet "%s" et affiche un gain estime de %s (ROI: %s). Sa reussite depend d un pilotage serre, d un scope maitrise et d une mise en oeuvre progressive.',
                (string) ($strategy->getNomStrategie() ?: 'Strategie sans nom'),
                (int) ($strategy->getDureeTerme() ?? 0),
                $this->formatAmount($strategy->getBudgetTotal()),
                $projectTitle,
                $estimatedGainAmount !== null ? $this->formatAmount($estimatedGainAmount) . ' DT' : 'Non defini',
                $estimatedRoiPercent !== null ? number_format($estimatedRoiPercent, 2, ',', ' ') . '%' : 'Non defini'
            ),
            'strategic_diagnosis' => sprintf(
                'Le point de depart montre une strategie de type %s portee par %s, avec un horizon de %s mois et un budget de %s DT. Le projet support "%s" se situe actuellement a %s d avancement avec un statut "%s". La dynamique semble favorable si les priorites sont resserrees, les hypotheses budgetaires confirmees et les objectifs traduits en jalons operationnels suivis dans le temps.',
                $strategy->getType() ?: 'Non defini',
                $strategyOwner !== '' ? $strategyOwner : 'un responsable non defini',
                $strategy->getDureeTerme() !== null ? (string) $strategy->getDureeTerme() : '0',
                $this->formatAmount($strategy->getBudgetTotal()),
                $projectTitle,
                $projectProgressLabel,
                $projectStatus
            ),
            'highlights' => [
                sprintf('Type de strategie: %s', $strategy->getType() ?: 'Non defini'),
                sprintf('Responsable strategie: %s', $strategyOwner !== '' ? $strategyOwner : 'Non defini'),
                sprintf('Projet support: %s', $projectTitle),
                sprintf('Client du projet: %s', $projectOwner !== '' ? $projectOwner : 'Non defini'),
                sprintf('Avancement du projet support: %s', $projectProgressLabel),
            ],
            'strategic_priorities' => $strategicPriorities,
            'opportunities' => [
                $estimatedGainAmount !== null
                    ? sprintf(
                        'Le gain estime represente environ %s DT sur la base du budget courant.',
                        $this->formatAmount($estimatedGainAmount)
                    )
                    : 'Le gain financier estime ne peut pas etre calcule avec les donnees actuelles.',
                $project !== null
                    ? sprintf(
                        'Le projet associe dispose d un avancement de %s%% pour accueillir la strategie.',
                        number_format((float) $project->getAvancementProj(), 0, ',', ' ')
                    )
                    : 'Associer cette strategie a un projet clarifiera la gouvernance et le suivi.',
                     'Les objectifs relies peuvent servir de feuille de route immediate pour le lancement du playbook.',
                'Une execution par phases permet de tester rapidement la traction avant de generaliser les investissements.',
            ],
            'expected_outcome_curve' => $expectedOutcomeCurve,
            'expected_outcome_summary' => $this->buildOutcomeSummary($expectedOutcomeCurve, $strategy),
            'expected_outcome_chart' => $this->buildOutcomeChart($expectedOutcomeCurve, $strategy),
            'execution_phases' => $this->buildExecutionPhases($strategy, $project),
            'risks' => $riskMessages,
            'mitigation_actions' => array_slice(array_values(array_unique($mitigationMessages)), 0, 5),
            'actions' => [
                'Valider les hypotheses budgetaires, les dependances et les criteres de succes avant lancement.',
                'Convertir les priorites strategiques en chantiers avec responsables, livrables et echeances.',
                'Mettre en place un rituel de revue avec decisions, ecarts et arbitrages documentes.',
                $project !== null ? 'Aligner la strategie avec le calendrier du projet associe.' : 'Associer un projet de reference avant execution.',
                'Suivre les objectifs relies avec un tableau de bord simple et partage.',
            ],
            'kpis' => $this->buildFallbackKpis($strategy, $project),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildStrategicPriorities(Strategie $strategy): array
    {
        $priorities = [];

        foreach ($strategy->getObjectives() as $objective) {
            $label = trim((string) ($objective->getNomObj() ?: 'Objectif'));
            $description = trim((string) ($objective->getDescriptionOb() ?: ''));
            $priorityLabel = strtolower($objective->getPriorityLabel());

            $priorities[] = $description !== '' && $description !== $label
                ? sprintf('%s (%s): %s', $label, $priorityLabel, $description)
                : sprintf('%s (%s): structurer ce chantier comme priorite executable.', $label, $priorityLabel);

            if (count($priorities) >= 3) {
                break;
            }
        }

        if ($strategy->getBudgetTotal() !== null) {
            $priorities[] = sprintf(
                'Maintenir une discipline budgetaire stricte autour d une enveloppe de %s DT.',
                $this->formatAmount($strategy->getBudgetTotal())
            );
        }

        if ($strategy->getProject() !== null) {
            $priorities[] = 'Aligner les priorites avec les jalons, contraintes et dependances du projet support.';
        }

        if ($priorities === []) {
            $priorities[] = 'Clarifier les objectifs business et les criteres de succes avant le lancement.';
        }

        return array_slice(array_values(array_unique($priorities)), 0, 5);
    }

    /**
     * @return array<int, ExecutionPhase>
     */
    private function buildExecutionPhases(Strategie $strategy, ?Project $project): array
    {
        $projectProgressLabel = $project?->getAvancementProj() !== null
            ? number_format((float) $project->getAvancementProj(), 0, ',', ' ') . '%'
            : 'progression a preciser';

        return [
            [
                'title' => 'Cadrage et alignement',
                'horizon' => 'Debut de programme',
                'focus' => sprintf(
                    'Confirmer la cible, le budget, les responsables et les dependances critiques autour de la strategie "%s".',
                    $strategy->getNomStrategie() ?: 'sans nom'
                ),
            ],
            [
                'title' => 'Execution pilote',
                'horizon' => 'Milieu de programme',
                'focus' => $project !== null
                    ? sprintf(
                        'Lancer les chantiers prioritaires en tenant compte de l avancement du projet support (%s).',
                        $projectProgressLabel
                    )
                    : 'Lancer un premier lot d actions mesurables afin de valider rapidement la traction.',
            ],
            [
                'title' => 'Acceleration et mesure',
                'horizon' => 'Fin de programme',
                'focus' => 'Industrialiser ce qui fonctionne, corriger les ecarts et consolider le suivi de performance.',
            ],
        ];
    }

    /**
     * @return array<int, KpiRow>
     */
    private function buildFallbackKpis(Strategie $strategy, ?Project $project): array
    {
        $objectiveCount = max(1, $strategy->getObjectives()->count());
        $estimatedGainAmount = $this->calculateEstimatedGainAmount($strategy);
        $estimatedRoiPercent = $this->calculateEstimatedRoiPercent($strategy);
        $projectProgressLabel = $project?->getAvancementProj() !== null
            ? number_format((float) $project->getAvancementProj(), 0, ',', ' ') . '%'
            : 'progression definie a chaque revue';

        return [
            [
                'name' => 'Respect budgetaire',
                'target' => $strategy->getBudgetTotal() !== null
                    ? '<= ' . $this->formatAmount($strategy->getBudgetTotal()) . ' DT'
                    : 'Budget approuve sans derive',
                'cadence' => 'Mensuelle',
            ],
            [
                'name' => 'Couverture des objectifs',
                'target' => sprintf('100%% des %d objectifs suivis', $objectiveCount),
                'cadence' => 'Mensuelle',
            ],
            [
                'name' => 'Avancement du projet support',
                'target' => $project !== null
                    ? 'Progression maintenue au-dela de ' . $projectProgressLabel
                    : 'Progression revue et validee a chaque revue',
                'cadence' => 'Mensuelle',
            ],
            [
                'name' => 'Creation de valeur',
                'target' => $estimatedGainAmount !== null
                    ? sprintf(
                        'Tendre vers %s DT de gain (ROI cible: %s)',
                        $this->formatAmount($estimatedGainAmount),
                        $estimatedRoiPercent !== null ? number_format($estimatedRoiPercent, 2, ',', ' ') . '%' : 'Non defini'
                    )
                    : 'Benefices documentes par trimestre',
                'cadence' => 'Trimestrielle',
            ],
        ];
    }

    /**
     * @return array<int, OutcomePoint>
     */
    private function buildFallbackOutcomeCurve(Strategie $strategy): array
    {
        $weights = [0.06, 0.22, 0.48, 0.76, 1.0];
        $finalValue = $this->determineOutcomeFinalValue($strategy);
        $labels = $this->buildOutcomePeriodLabels($strategy, count($weights));
        $curve = [];
        $lastValue = 0.0;

        foreach ($weights as $index => $weight) {
            $value = round($finalValue * $weight, 1);
            $value = max($lastValue, $value);

            if ($index === array_key_last($weights)) {
                $value = round($finalValue, 1);
            }

            $curve[] = [
                'period' => $labels[$index] ?? sprintf('Etape %d', $index + 1),
                'value' => $value,
            ];

            $lastValue = $value;
        }

        return $curve;
    }

    /**
     * @return array<int, string>
     */
    private function buildOutcomePeriodLabels(Strategie $strategy, int $count): array
    {
        $duration = (int) ($strategy->getDureeTerme() ?? 0);

        if ($duration >= $count) {
            $labels = [];
            $lastMonth = 0;

            for ($index = 1; $index <= $count; ++$index) {
                $targetMonth = (int) round(($duration * $index) / $count);
                $targetMonth = max($targetMonth, $lastMonth + 1);
                $remaining = $count - $index;
                $targetMonth = min($targetMonth, $duration - $remaining);
                $labels[] = 'M' . max(1, $targetMonth);
                $lastMonth = $targetMonth;
            }

            return $labels;
        }

        return [
            'Demarrage',
            'Cadrage',
            'Pilotage',
            'Acceleration',
            'Cible',
        ];
    }

    private function determineOutcomeFinalValue(Strategie $strategy): float
    {
        $estimatedRoiPercent = $this->calculateEstimatedRoiPercent($strategy);

        if ($estimatedRoiPercent !== null && $estimatedRoiPercent > 0) {
            return round($estimatedRoiPercent, 1);
        }

        $objectiveFactor = max(0, $strategy->getObjectives()->count() - 1) * 8;

        return (float) min(140, max(45, 70 + $objectiveFactor));
    }

    /**
     * @param array<int, OutcomePoint> $curve
     */
    private function buildOutcomeSummary(array $curve, Strategie $strategy): string
    {
        if ($curve === []) {
            return 'La trajectoire d outcome attendue sera precisee lors du prochain cadrage.';
        }

        $midpoint = $curve[(int) floor((count($curve) - 1) / 2)] ?? $curve[0];
        $lastPoint = $curve[array_key_last($curve)] ?? $curve[0];
        $durationText = $strategy->getDureeTerme() !== null
            ? sprintf('sur %d mois', (int) $strategy->getDureeTerme())
            : 'sur l horizon defini';
        $metricLabel = $this->calculateEstimatedRoiPercent($strategy) !== null ? 'de ROI projete' : 'de resultat cible';

        return sprintf(
            'La trajectoire attendue projette une progression graduelle %s, avec une acceleration visible autour de %s (%s) avant une cible finale a %s (%s).',
            $durationText,
            $midpoint['period'],
            $this->formatOutcomeValueWithUnit((float) $midpoint['value']),
            $lastPoint['period'],
            $this->formatOutcomeValueWithUnit((float) $lastPoint['value']) . ' ' . $metricLabel
        );
    }

    /**
     * @param array<int, OutcomePoint> $curve
     *
     * @return OutcomeChart
     */
    private function buildOutcomeChart(array $curve, Strategie $strategy): array
    {
        $width = 640;
        $height = 260;
        $plotLeft = 56;
        $plotRight = $width - 22;
        $plotTop = 20;
        $plotBottom = $height - 46;
        $innerWidth = $plotRight - $plotLeft;
        $innerHeight = $plotBottom - $plotTop;
        $tickCount = 5;
        $values = array_map(static fn (array $point): float => (float) $point['value'], $curve);
        $dataMax = max($values !== [] ? $values : [0.0]);
        $maxValue = max(20.0, round($dataMax * 1.15, 1));
        $pointCount = count($curve);
        $points = [];

        foreach ($curve as $index => $point) {
            $x = $pointCount > 1
                ? $plotLeft + ($innerWidth * $index / ($pointCount - 1))
                : $plotLeft + ($innerWidth / 2);
            $ratio = $maxValue > 0 ? ((float) $point['value']) / $maxValue : 0.0;
            $y = $plotBottom - ($innerHeight * $ratio);

            $points[] = [
                'period' => $point['period'],
                'value' => (float) $point['value'],
                'value_label' => $this->formatOutcomeValueWithUnit((float) $point['value']),
                'x' => round($x, 1),
                'y' => round($y, 1),
                'value_y' => round(max($plotTop + 12, $y - 12), 1),
            ];
        }

        $polylinePoints = implode(' ', array_map(
            static fn (array $point): string => sprintf('%.1f,%.1f', $point['x'], $point['y']),
            $points
        ));
        $lastPointKey = array_key_last($points);
        if ($points === [] || $lastPointKey === null) {
            return $this->buildOutcomeChart($this->buildFallbackOutcomeCurve($strategy), $strategy);
        }

        $firstPoint = $points[0];
        $lastPoint = $points[$lastPointKey];

        $areaPoints = sprintf(
            '%.1f,%.1f %s %.1f,%.1f',
            $firstPoint['x'],
            $plotBottom,
            $polylinePoints,
            $lastPoint['x'],
            $plotBottom
        );
        $yTicks = [];

        for ($tickIndex = 0; $tickIndex < $tickCount; ++$tickIndex) {
            $ratio = $tickIndex / ($tickCount - 1);
            $value = $maxValue * (1 - $ratio);
            $yTicks[] = [
                'label' => $this->formatOutcomeValueWithUnit($value),
                'y' => round($plotTop + ($innerHeight * $ratio), 1),
            ];
        }

        return [
            'width' => $width,
            'height' => $height,
            'plot_left' => $plotLeft,
            'plot_right' => $plotRight,
            'plot_top' => $plotTop,
            'plot_bottom' => $plotBottom,
            'x_label_y' => $plotBottom + 24,
            'y_ticks' => $yTicks,
            'points' => $points,
            'polyline_points' => $polylinePoints,
            'area_points' => $areaPoints,
            'start_value_label' => $firstPoint['value_label'],
            'final_value_label' => $lastPoint['value_label'],
            'aria_label' => sprintf(
                'Projection d outcome attendu de %s a %s sur %s.',
                $firstPoint['value_label'],
                $lastPoint['value_label'],
                $strategy->getDureeTerme() !== null ? (int) $strategy->getDureeTerme() . ' mois' : 'la periode definie'
            ),
        ];
    }

    /**
     * @param array<int, OutcomePoint> $fallback
     *
     * @return array<int, OutcomePoint>
     */
    private function normalizeOutcomeCurve(mixed $value, array $fallback, Strategie $strategy): array
    {
        if (!is_array($value)) {
            return $fallback;
        }

        $maxAllowed = max(100.0, $this->determineOutcomeFinalValue($strategy) * 1.5);
        $normalized = [];
        $previousValue = 0.0;

        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $period = $this->cleanText($item['period'] ?? null);
            $numericValue = $this->extractNumericValue($item['value'] ?? null);

            if ($period === '' || $numericValue === null) {
                continue;
            }

            $numericValue = min($maxAllowed, max(0.0, round($numericValue, 1)));
            $numericValue = max($previousValue, $numericValue);

            $normalized[] = [
                'period' => $period,
                'value' => $numericValue,
            ];

            $previousValue = $numericValue;

            if (count($normalized) >= 8) {
                break;
            }
        }

        return count($normalized) >= 4 ? $normalized : $fallback;
    }

    /**
     * @param array<int, ExecutionPhase> $fallback
     *
     * @return array<int, ExecutionPhase>
     */
    private function normalizeExecutionPhases(mixed $value, array $fallback): array
    {
        if (!is_array($value)) {
            return $fallback;
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = $this->cleanText($item['title'] ?? null);
            $horizon = $this->cleanText($item['horizon'] ?? null);
            $focus = $this->cleanText($item['focus'] ?? null);

            if ($title === '' || $horizon === '' || $focus === '') {
                continue;
            }

            $normalized[] = [
                'title' => $title,
                'horizon' => $horizon,
                'focus' => $focus,
            ];
        }

        return count($normalized) >= 3 ? array_slice($normalized, 0, 3) : $fallback;
    }

    /**
     * @param array<int, KpiRow> $fallback
     *
     * @return array<int, KpiRow>
     */
    private function normalizeKpis(mixed $value, array $fallback): array
    {
        if (!is_array($value)) {
            return $fallback;
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = $this->cleanText($item['name'] ?? null);
            $target = $this->cleanText($item['target'] ?? null);
            $cadence = $this->cleanText($item['cadence'] ?? null);

            if ($name === '' || $target === '' || $cadence === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'target' => $target,
                'cadence' => $cadence,
            ];
        }

        return count($normalized) >= 3 ? array_slice($normalized, 0, 5) : $fallback;
    }

    private function cleanText(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param array<int, string> $fallback
     *
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value, array $fallback): array
    {
        if (!is_array($value)) {
            return $fallback;
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim($item);
            if ($item === '') {
                continue;
            }

            $normalized[] = $item;
        }

        return $normalized !== [] ? array_values(array_unique($normalized)) : $fallback;
    }

    private function normalizeText(mixed $value, string $fallback): string
    {
        if (!is_string($value)) {
            return $fallback;
        }

        $value = trim($value);

        return $value !== '' ? $value : $fallback;
    }

    private function extractNumericValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = str_replace(',', '.', trim($value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function calculateEstimatedGainAmount(Strategie $strategy): ?float
    {
        $budget = $strategy->getBudgetTotal();
        $estimatedGainPercent = $strategy->getGainEstime();

        if ($budget === null || $estimatedGainPercent === null) {
            return null;
        }

        return ($budget * $estimatedGainPercent) / 100;
    }

    private function calculateEstimatedRoiPercent(Strategie $strategy): ?float
    {
        $budget = $strategy->getBudgetTotal();
        $gainAmount = $this->calculateEstimatedGainAmount($strategy);

        if ($budget === null || $budget <= 0 || $gainAmount === null) {
            return null;
        }

        return $strategy->getGainEstime();
    }

    private function formatAmount(?float $amount): string
    {
        return $amount !== null ? number_format($amount, 0, ',', ' ') : 'Non defini';
    }

    private function formatOutcomeValueWithUnit(float $value): string
    {
        return $this->formatOutcomeValue($value) . ' %';
    }

    private function formatOutcomeValue(float $value): string
    {
        $formatted = number_format($value, 1, ',', ' ');

        return str_ends_with($formatted, ',0') ? substr($formatted, 0, -2) : $formatted;
    }

    private function resetLastGenerationMeta(): void
    {
        $this->lastGenerationUsedAi = false;
        $this->lastGenerationWarning = null;
    }

    private function buildGenerationWarning(\Throwable $exception): string
    {
        if ($this->isTemporaryGeminiFailure($exception)) {
            return 'Gemini est temporairement indisponible. Le playbook de secours a ete utilise automatiquement. Vous pouvez relancer la generation dans quelques instants.';
        }

        $message = trim(preg_replace('/\s+/', ' ', $exception->getMessage()) ?? '');
        if ($message === '') {
            return 'La generation IA Gemini a echoue. Le playbook de secours a ete utilise.';
        }

        return sprintf(
            'La generation IA Gemini a echoue (%s). Le playbook de secours a ete utilise.',
            $message
        );
    }

    private function shouldRetryWithoutSchema(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'gemini api error (400)')
            || str_contains($message, 'json valide')
            || str_contains($message, 'schema attendu')
            || str_contains($message, 'vide ou invalide');
    }

    private function shouldRetryRequestException(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'temporary failure')
            || str_contains($message, 'temporarily unavailable')
            || str_contains($message, 'failed to connect')
            || str_contains($message, 'could not resolve')
            || str_contains($message, 'couldn\'t connect')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'empty reply')
            || str_contains($message, 'recv failure');
    }

    private function isTransientHttpStatus(int $status): bool
    {
        return in_array($status, [429, 500, 502, 503, 504], true);
    }

    private function getRetryDelayMilliseconds(int $attempt): int
    {
        $delays = self::TRANSIENT_RETRY_DELAYS_MS;

        return $delays[$attempt - 1] ?? $delays[array_key_last($delays)];
    }

    private function isTemporaryGeminiFailure(\Throwable $exception): bool
    {
        $message = $exception->getMessage();
        if (preg_match('/gemini api error \((\d+)\)/i', $message, $matches) === 1) {
            return $this->isTransientHttpStatus((int) $matches[1]);
        }

        $message = strtolower($message);

        return str_contains($message, 'high demand')
            || str_contains($message, 'try again later')
            || $this->shouldRetryRequestException($exception);
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

    private function normalizeModelName(string $model): string
    {
        $model = trim($model);

        if (str_starts_with($model, 'models/')) {
            $model = substr($model, 7);
        }

        return $model !== '' ? $model : 'gemini-2.5-flash';
    }

    private function readEnv(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) ? $value : null;
    }
}
