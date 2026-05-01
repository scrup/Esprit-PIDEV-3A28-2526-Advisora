<?php

namespace App\Service;

use App\Dto\ProjectEstimationRequest;

abstract class AbstractProjectEstimationAnalyzer implements ProjectEstimationAnalyzerInterface, ProjectEstimationMetaAwareInterface
{
    /**
     * @var array{provider_used: string|null, used_fallback: bool, warning: string|null, model: string|null}
     */
    protected array $lastEstimationMeta = [
        'provider_used' => null,
        'used_fallback' => false,
        'warning' => null,
        'model' => null,
    ];

    /**
     * @return array{provider_used: string|null, used_fallback: bool, warning: string|null, model: string|null}
     */
    public function getLastEstimationMeta(): array
    {
        return $this->lastEstimationMeta;
    }

    protected function resetLastEstimationMeta(): void
    {
        $this->lastEstimationMeta = [
            'provider_used' => null,
            'used_fallback' => false,
            'warning' => null,
            'model' => null,
        ];
    }

    protected function recordLastEstimationMeta(string $provider, ?string $model = null, bool $usedFallback = false, ?string $warning = null): void
    {
        $this->lastEstimationMeta = [
            'provider_used' => $provider,
            'used_fallback' => $usedFallback,
            'warning' => $warning,
            'model' => $model,
        ];
    }

    protected function buildAnalysisInstructions(): string
    {
        return implode("\n", [
            'Tu es un expert en lancement, structuration et viabilite de projets sur le marche tunisien.',
            'Tu peux analyser aussi bien des projets de services, commerce, industrie, digital, immobilier, sante, artisanat, tourisme ou restauration.',
            'Tu connais la regulation tunisienne des entreprises, les organismes de financement tunisiens, les regions economiques de Tunisie, les contraintes d exploitation locales et les habitudes de consommation du marche.',
            'Analyse les informations fournies uniquement dans le contexte du marche tunisien.',
            'Reste concret, precise les risques locaux, propose des recommandations exploitables et privilegie les options realistes pour la Tunisie quel que soit le type de projet.',
            'Le champ startup_act doit etre evalue uniquement si cela a du sens pour le projet; sinon explique clairement pourquoi ce cadre n est pas pertinent.',
            'Retourne strictement un JSON valide conforme au schema demande, sans texte avant ni apres.',
        ]);
    }

    protected function buildInputText(ProjectEstimationRequest $request): string
    {
        $lines = ['Donnees du projet a analyser :'];

        foreach ($request->toPromptContext() as $label => $value) {
            $lines[] = sprintf('- %s : %s', $label, $value);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getResponseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'verdict',
                'score',
                'resume',
                'points_forts',
                'points_faibles',
                'recommandations',
                'financement_recommande',
                'region_recommandee',
                'delai_recommande',
                'budget_minimum_dt',
                'probabilite_succes',
                'startup_act',
                'prochaine_etape',
            ],
            'properties' => [
                'verdict' => [
                    'type' => 'string',
                    'enum' => ['VIABLE', 'RISQUE', 'NON_VIABLE'],
                ],
                'score' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'maximum' => 100,
                ],
                'resume' => [
                    'type' => 'string',
                    'minLength' => 20,
                ],
                'points_forts' => $this->buildStringListSchema(),
                'points_faibles' => $this->buildStringListSchema(),
                'recommandations' => $this->buildStringListSchema(),
                'financement_recommande' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['organisme', 'explication'],
                    'properties' => [
                        'organisme' => ['type' => 'string'],
                        'explication' => ['type' => 'string'],
                    ],
                ],
                'region_recommandee' => [
                    'type' => 'string',
                ],
                'delai_recommande' => [
                    'type' => 'string',
                ],
                'budget_minimum_dt' => [
                    'type' => 'number',
                    'minimum' => 0,
                ],
                'probabilite_succes' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'maximum' => 100,
                ],
                'startup_act' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['eligible', 'explication'],
                    'properties' => [
                        'eligible' => ['type' => 'boolean'],
                        'explication' => ['type' => 'string'],
                    ],
                ],
                'prochaine_etape' => [
                    'type' => 'string',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStringListSchema(): array
    {
        return [
            'type' => 'array',
            'minItems' => 3,
            'maxItems' => 3,
            'items' => [
                'type' => 'string',
            ],
        ];
    }

    /**
     * Le nettoyage reste volontairement tolerant pour survivre a un texte parasite.
     *
     * @return array<string, mixed>
     */
    protected function decodeStructuredResponse(string $responseText): array
    {
        $jsonDocument = $this->extractJsonDocument($responseText);

        try {
            $decoded = json_decode($jsonDocument, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('La reponse de l estimation tunisienne n est pas un JSON valide.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('La reponse de l estimation tunisienne ne correspond pas au schema attendu.');
        }

        return $decoded;
    }

    protected function extractJsonDocument(string $responseText): string
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
     * @return array{
     *     verdict: string,
     *     score: int,
     *     resume: string,
     *     points_forts: array<int, string>,
     *     points_faibles: array<int, string>,
     *     recommandations: array<int, string>,
     *     financement_recommande: array{organisme: string, explication: string},
     *     region_recommandee: string,
     *     delai_recommande: string,
     *     budget_minimum_dt: float,
     *     probabilite_succes: int,
     *     startup_act: array{eligible: bool, explication: string},
     *     prochaine_etape: string
     * }
     */
    protected function normalizeEstimation(array $decoded): array
    {
        return [
            'verdict' => $this->normalizeVerdict($decoded['verdict'] ?? null),
            'score' => $this->clampInteger($decoded['score'] ?? null),
            'resume' => $this->cleanText($decoded['resume'] ?? null, 'Resume non disponible.'),
            'points_forts' => $this->normalizeStringList($decoded['points_forts'] ?? []),
            'points_faibles' => $this->normalizeStringList($decoded['points_faibles'] ?? []),
            'recommandations' => $this->normalizeStringList($decoded['recommandations'] ?? []),
            'financement_recommande' => [
                'organisme' => $this->cleanText($decoded['financement_recommande']['organisme'] ?? null, 'Organisme a confirmer'),
                'explication' => $this->cleanText($decoded['financement_recommande']['explication'] ?? null, 'Explication non disponible.'),
            ],
            'region_recommandee' => $this->cleanText($decoded['region_recommandee'] ?? null, 'Region a confirmer'),
            'delai_recommande' => $this->cleanText($decoded['delai_recommande'] ?? null, 'Delai a confirmer'),
            'budget_minimum_dt' => $this->normalizeFloat($decoded['budget_minimum_dt'] ?? 0),
            'probabilite_succes' => $this->clampInteger($decoded['probabilite_succes'] ?? null),
            'startup_act' => [
                'eligible' => (bool) ($decoded['startup_act']['eligible'] ?? false),
                'explication' => $this->cleanText($decoded['startup_act']['explication'] ?? null, 'Eligibilite a confirmer.'),
            ],
            'prochaine_etape' => $this->cleanText($decoded['prochaine_etape'] ?? null, 'Aucune prochaine etape n a ete proposee.'),
        ];
    }

    /**
     * @param mixed $value
     *
     * @return array<int, string>
     */
    protected function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $text = trim((string) $item);
            if ($text !== '') {
                $items[] = $text;
            }
        }

        return array_slice($items, 0, 3);
    }

    protected function normalizeVerdict(mixed $value): string
    {
        $verdict = strtoupper(trim((string) $value));

        return match ($verdict) {
            'VIABLE', 'RISQUE', 'NON_VIABLE' => $verdict,
            'NON VIABLE' => 'NON_VIABLE',
            default => 'RISQUE',
        };
    }

    protected function clampInteger(mixed $value): int
    {
        $integer = (int) round((float) $value);

        return max(0, min(100, $integer));
    }

    protected function normalizeFloat(mixed $value): float
    {
        return max(0, (float) $value);
    }

    protected function cleanText(mixed $value, string $fallback): string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : $fallback;
    }
}
