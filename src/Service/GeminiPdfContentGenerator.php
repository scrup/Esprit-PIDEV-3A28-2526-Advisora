<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Strategie;

class GeminiPdfContentGenerator
{
    public function __construct(
        private ?string $apiKey = null,
        private string $model = 'gemini-2.5-flash',
        private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta'
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

    public function generate(Strategie $strategy, ?Project $project): array
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            return $this->buildFallbackContent($strategy, $project);
        }

        try {
            $response = $this->sendJsonRequest(
                sprintf('%s/models/%s:generateContent', $this->baseUrl, rawurlencode($this->model)),
                [
                    'Content-Type: application/json',
                    'x-goog-api-key: ' . $this->apiKey,
                ],
                [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $this->buildPrompt($strategy, $project),
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseJsonSchema' => $this->getResponseSchema(),
                        'temperature' => 0.6,
                    ],
                ]
            );

            $payload = $this->decodeApiPayload($response['body']);
            if ($response['status'] >= 400) {
                $errorMessage = $payload['error']['message'] ?? 'La requete Gemini a echoue.';

                throw new \RuntimeException(sprintf('Gemini API error (%d): %s', $response['status'], $errorMessage));
            }

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

            return $this->normalizeGeneratedContent($decoded, $strategy, $project);
        } catch (\Throwable) {
            return $this->buildFallbackContent($strategy, $project);
        }
    }

    private function sendJsonRequest(string $url, array $headers, array $payload): array
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
                (string) $strategy->getUser()?->getPrenomUser(),
                (string) $strategy->getUser()?->getNomUser()
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
            sprintf('- Gain estime : %s', $strategy->getGainEstime() !== null ? $strategy->getGainEstime() . '%' : 'Non defini'),
            sprintf(
                '- Gain estime en montant : %s',
                $estimatedGainAmount !== null ? $this->formatAmount($estimatedGainAmount) . ' DT' : 'Non defini'
            ),
            sprintf('- Actualites / contexte : %s', $strategy->getNews() ?: 'Aucune actualite fournie'),
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
        ]);
    }

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
                'execution_phases',
                'risks',
                'mitigation_actions',
                'actions',
                'kpis',
            ],
        ];
    }

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

    private function normalizeGeneratedContent(array $decoded, Strategie $strategy, ?Project $project): array
    {
        $fallback = $this->buildFallbackContent($strategy, $project);

        return [
            'executive_summary' => $this->normalizeText($decoded['executive_summary'] ?? null, $fallback['executive_summary']),
            'strategic_diagnosis' => $this->normalizeText($decoded['strategic_diagnosis'] ?? null, $fallback['strategic_diagnosis']),
            'highlights' => $this->normalizeStringList($decoded['highlights'] ?? null, $fallback['highlights']),
            'strategic_priorities' => $this->normalizeStringList($decoded['strategic_priorities'] ?? null, $fallback['strategic_priorities']),
            'opportunities' => $this->normalizeStringList($decoded['opportunities'] ?? null, $fallback['opportunities']),
            'execution_phases' => $this->normalizeExecutionPhases($decoded['execution_phases'] ?? null, $fallback['execution_phases']),
            'risks' => $this->normalizeStringList($decoded['risks'] ?? null, $fallback['risks']),
            'mitigation_actions' => $this->normalizeStringList($decoded['mitigation_actions'] ?? null, $fallback['mitigation_actions']),
            'actions' => $this->normalizeStringList($decoded['actions'] ?? null, $fallback['actions']),
            'kpis' => $this->normalizeKpis($decoded['kpis'] ?? null, $fallback['kpis']),
        ];
    }

    private function buildFallbackContent(Strategie $strategy, ?Project $project): array
    {
        $estimatedGainAmount = $this->calculateEstimatedGainAmount($strategy);
        $projectBudget = $project?->getBudgetProj();
        $projectTitle = $project?->getTitleProj() ?: 'Aucun projet associe';
        $projectStatus = $project?->getStatusLabel() ?? 'Non defini';
        $projectProgressLabel = $project?->getAvancementProj() !== null
            ? number_format((float) $project->getAvancementProj(), 0, ',', ' ') . '%'
            : 'Non defini';
        $projectOwner = $project?->getUser()
            ? trim(sprintf(
                '%s %s',
                (string) $project->getUser()?->getPrenomUser(),
                (string) $project->getUser()?->getNomUser()
            ))
            : 'Non defini';
        $strategyOwner = $strategy->getUser()
            ? trim(sprintf(
                '%s %s',
                (string) $strategy->getUser()?->getPrenomUser(),
                (string) $strategy->getUser()?->getNomUser()
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

        return [
            'executive_summary' => sprintf(
                'La strategie "%s" vise une execution sur %d mois avec un budget de %s DT. Elle est rattachee au projet "%s" et affiche un gain estime de %s. Sa reussite depend d un pilotage serre, d un scope maitrise et d une mise en oeuvre progressive.',
                (string) ($strategy->getNomStrategie() ?: 'Strategie sans nom'),
                (int) ($strategy->getDureeTerme() ?? 0),
                $this->formatAmount($strategy->getBudgetTotal()),
                $projectTitle,
                $strategy->getGainEstime() !== null ? $strategy->getGainEstime() . '%' : 'Non defini'
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
                        $project->getAvancementProj() !== null ? number_format((float) $project->getAvancementProj(), 0, ',', ' ') : '0'
                    )
                    : 'Associer cette strategie a un projet clarifiera la gouvernance et le suivi.',
                $strategy->getNews()
                    ? sprintf('Les actualites fournies ouvrent une fenetre de positionnement: %s', $strategy->getNews())
                    : 'Les objectifs relies peuvent servir de feuille de route immediate pour le lancement du playbook.',
                'Une execution par phases permet de tester rapidement la traction avant de generaliser les investissements.',
            ],
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

    private function buildFallbackKpis(Strategie $strategy, ?Project $project): array
    {
        $objectiveCount = max(1, $strategy->getObjectives()->count());
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
                'target' => $strategy->getGainEstime() !== null
                    ? 'Tendre vers +' . $strategy->getGainEstime() . '% a horizon strategie'
                    : 'Benefices documentes par trimestre',
                'cadence' => 'Trimestrielle',
            ],
        ];
    }

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

    private function calculateEstimatedGainAmount(Strategie $strategy): ?float
    {
        if ($strategy->getBudgetTotal() === null || $strategy->getGainEstime() === null) {
            return null;
        }

        return $strategy->getBudgetTotal() * ($strategy->getGainEstime() / 100);
    }

    private function formatAmount(?float $amount): string
    {
        return $amount !== null ? number_format($amount, 0, ',', ' ') : 'Non defini';
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
