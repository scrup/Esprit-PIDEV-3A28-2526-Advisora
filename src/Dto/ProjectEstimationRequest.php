<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ProjectEstimationRequest
{
    /**
     * Listes de choix centralisees pour garder le formulaire et le prompt alignes.
     *
     * @var string[]
     */
    public const PROJECT_TYPES = [
        'IT/Startups',
        'E-commerce',
        'Immobilier',
        'Finance/Fintech',
        'Sante',
        'Education',
        'Energie',
        'Transport/Logistique',
        'Agriculture',
        'Tourisme',
        'Artisanat',
        'Restauration',
        'Autre / Multisectoriel',
    ];

    /**
     * @var string[]
     */
    public const REGIONS = [
        'Tunis',
        'Sfax',
        'Sousse',
        'Monastir',
        'Bizerte',
        'Nabeul',
        'Gabes',
        'Kairouan',
        'Gafsa',
        'Medenine',
        'Toutes les regions',
    ];

    /**
     * @var string[]
     */
    public const FUNDING_SOURCES = [
        'Fonds propres',
        'SICAR',
        'BTS (Banque Tunisienne de Solidarite)',
        'BFPME',
        'Smart Capital',
        'Credit bancaire',
        'Partenaire strategique',
        'Investisseur etranger',
        'Mixte',
        'Pas encore defini',
    ];

    /**
     * @var string[]
     */
    public const TARGET_MARKETS = [
        'Grand public (B2C)',
        'Entreprises (B2B)',
        'Administration (B2G)',
        'Mixte',
    ];

    /**
     * @var string[]
     */
    public const MARKET_STUDY_STATUSES = [
        'Oui',
        'Non',
        'En cours',
    ];

    /**
     * @var string[]
     */
    public const MVP_STATUSES = [
        'Oui',
        'Non',
        'En cours',
    ];

    /**
     * @var string[]
     */
    public const LEGAL_STATUSES = [
        'SUARL',
        'SARL',
        'SA',
        'Auto-entrepreneur',
        'Association',
        'Pas encore defini',
    ];

    /**
     * @var string[]
     */
    public const CERTIFICATION_STATUSES = [
        'Oui',
        'Non',
        'Je ne sais pas',
    ];

    #[Assert\NotBlank(message: 'Le nom du projet est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 160,
        minMessage: 'Le nom du projet doit contenir au moins 3 caracteres.',
        maxMessage: 'Le nom du projet ne doit pas depasser 160 caracteres.'
    )]
    public ?string $projectName = null;

    #[Assert\NotBlank(message: 'Le type de projet est obligatoire.')]
    #[Assert\Choice(choices: self::PROJECT_TYPES, message: 'Le type de projet selectionne est invalide.')]
    public ?string $projectType = null;

    #[Assert\NotBlank(message: 'La description du projet est obligatoire.')]
    #[Assert\Length(
        min: 20,
        max: 4000,
        minMessage: 'La description doit contenir au moins 20 caracteres.',
        maxMessage: 'La description ne doit pas depasser 4000 caracteres.'
    )]
    public ?string $projectDescription = null;

    #[Assert\NotBlank(message: 'La region de lancement est obligatoire.')]
    #[Assert\Choice(choices: self::REGIONS, message: 'La region selectionnee est invalide.')]
    public ?string $launchRegion = null;

    #[Assert\NotNull(message: 'La date souhaitee de lancement est obligatoire.')]
    #[Assert\GreaterThan(value: 'today', message: 'La date souhaitee de lancement doit etre dans le futur.')]
    public ?\DateTimeImmutable $desiredLaunchDate = null;

    #[Assert\NotNull(message: 'Le budget total disponible est obligatoire.')]
    #[Assert\GreaterThan(value: 0, message: 'Le budget total doit etre strictement superieur a 0.')]
    public ?float $totalBudgetDt = null;

    #[Assert\NotNull(message: 'Le budget marketing est obligatoire.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le budget marketing ne peut pas etre negatif.')]
    public ?float $marketingBudgetDt = null;

    #[Assert\NotBlank(message: 'La source de financement est obligatoire.')]
    #[Assert\Choice(choices: self::FUNDING_SOURCES, message: 'La source de financement selectionnee est invalide.')]
    public ?string $fundingSource = null;

    #[Assert\NotNull(message: 'Le revenu mensuel estime est obligatoire.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le revenu mensuel estime ne peut pas etre negatif.')]
    public ?float $estimatedMonthlyRevenueDt = null;

    #[Assert\NotNull(message: 'Le delai de rentabilite est obligatoire.')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'Le delai de rentabilite doit etre d au moins 1 mois.')]
    public ?int $estimatedProfitabilityDelayMonths = null;

    #[Assert\NotNull(message: 'Le nombre de membres de l equipe est obligatoire.')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'L equipe doit contenir au moins une personne.')]
    public ?int $teamSize = null;

    #[Assert\NotNull(message: 'L experience du porteur de projet est obligatoire.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'L experience du porteur de projet ne peut pas etre negative.')]
    public ?float $founderExperienceYears = null;

    #[Assert\NotBlank(message: 'Les competences cles de l equipe sont obligatoires.')]
    #[Assert\Length(
        min: 5,
        max: 2000,
        minMessage: 'Les competences cles doivent contenir au moins 5 caracteres.',
        maxMessage: 'Les competences cles ne doivent pas depasser 2000 caracteres.'
    )]
    public ?string $teamKeySkills = null;

    #[Assert\NotNull(message: 'Merci d indiquer si l equipe a deja lance un projet en Tunisie.')]
    public ?bool $alreadyLaunchedInTunisia = null;

    #[Assert\NotBlank(message: 'La cible principale est obligatoire.')]
    #[Assert\Choice(choices: self::TARGET_MARKETS, message: 'La cible selectionnee est invalide.')]
    public ?string $targetMarket = null;

    #[Assert\NotNull(message: 'Le nombre de concurrents directs est obligatoire.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le nombre de concurrents directs ne peut pas etre negatif.')]
    public ?int $directCompetitorsTunisia = null;

    #[Assert\NotBlank(message: 'L avantage concurrentiel est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: 'L avantage concurrentiel doit contenir au moins 10 caracteres.',
        maxMessage: 'L avantage concurrentiel ne doit pas depasser 2000 caracteres.'
    )]
    public ?string $competitiveAdvantage = null;

    #[Assert\NotBlank(message: 'Le statut de l etude de marche est obligatoire.')]
    #[Assert\Choice(choices: self::MARKET_STUDY_STATUSES, message: 'Le statut de l etude de marche est invalide.')]
    public ?string $tunisianMarketStudyStatus = null;

    #[Assert\NotNull(message: 'Merci d indiquer si le projet vise l export.')]
    public ?bool $exportTarget = null;

    #[Assert\NotBlank(message: 'Le statut du MVP ou prototype est obligatoire.')]
    #[Assert\Choice(choices: self::MVP_STATUSES, message: 'Le statut du MVP selectionne est invalide.')]
    public ?string $mvpStatus = null;

    #[Assert\NotBlank(message: 'La technologie principale est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 160,
        minMessage: 'La technologie principale doit contenir au moins 2 caracteres.',
        maxMessage: 'La technologie principale ne doit pas depasser 160 caracteres.'
    )]
    public ?string $mainTechnology = null;

    #[Assert\NotBlank(message: 'Le statut juridique prevu est obligatoire.')]
    #[Assert\Choice(choices: self::LEGAL_STATUSES, message: 'Le statut juridique selectionne est invalide.')]
    public ?string $plannedLegalStatus = null;

    #[Assert\NotBlank(message: 'Merci d indiquer si une labellisation ou un agrement est necessaire.')]
    #[Assert\Choice(choices: self::CERTIFICATION_STATUSES, message: 'Le statut de labellisation selectionne est invalide.')]
    public ?string $needsCertification = null;

    #[Assert\NotBlank(message: 'Les risques specifiques au marche tunisien sont obligatoires.')]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: 'Les risques identifies doivent contenir au moins 10 caracteres.',
        maxMessage: 'Les risques identifies ne doivent pas depasser 2000 caracteres.'
    )]
    public ?string $tunisianSpecificRisks = null;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (
            $this->marketingBudgetDt !== null
            && $this->totalBudgetDt !== null
            && $this->marketingBudgetDt > $this->totalBudgetDt
        ) {
            $context->buildViolation('Le budget marketing ne peut pas depasser le budget total disponible.')
                ->atPath('marketingBudgetDt')
                ->addViolation();
        }
    }

    /**
     * @return array<string, string>
     */
    public function toPromptContext(): array
    {
        return [
            'Nom' => trim((string) $this->projectName),
            'Type' => trim((string) $this->projectType),
            'Description' => trim((string) $this->projectDescription),
            'Region cible' => trim((string) $this->launchRegion),
            'Date souhaitee' => $this->desiredLaunchDate?->format('d/m/Y') ?? '',
            'Budget total' => $this->formatMoney($this->totalBudgetDt),
            'Budget marketing' => $this->formatMoney($this->marketingBudgetDt),
            'Source de financement' => trim((string) $this->fundingSource),
            'Revenu mensuel estime' => $this->formatMoney($this->estimatedMonthlyRevenueDt),
            'Delai de rentabilite' => $this->estimatedProfitabilityDelayMonths !== null ? $this->estimatedProfitabilityDelayMonths . ' mois' : '',
            'Ressources humaines mobilisees' => $this->teamSize !== null ? $this->teamSize . ' personnes' : '',
            'Experience pertinente du porteur' => $this->founderExperienceYears !== null ? rtrim(rtrim(number_format($this->founderExperienceYears, 2, '.', ''), '0'), '.') . ' ans' : '',
            'Competences et ressources cles' => trim((string) $this->teamKeySkills),
            'Projet similaire deja pilote en Tunisie' => $this->formatBoolean($this->alreadyLaunchedInTunisia),
            'Clientele ou beneficiaires principaux' => trim((string) $this->targetMarket),
            'Alternatives ou concurrents en Tunisie' => $this->directCompetitorsTunisia !== null ? (string) $this->directCompetitorsTunisia : '',
            'Differenciation sur le marche tunisien' => trim((string) $this->competitiveAdvantage),
            'Validation terrain ou etude locale' => trim((string) $this->tunisianMarketStudyStatus),
            'Ouverture vers des clients hors Tunisie' => $this->formatBoolean($this->exportTarget),
            'Niveau d avancement du projet' => $this->formatProgressStatus($this->mvpStatus),
            'Moyen principal de production ou technologie' => trim((string) $this->mainTechnology),
            'Structuration juridique ou administrative' => trim((string) $this->plannedLegalStatus),
            'Autorisation ou conformite necessaire' => trim((string) $this->needsCertification),
            'Contraintes et risques de mise en oeuvre en Tunisie' => trim((string) $this->tunisianSpecificRisks),
        ];
    }

    private function formatMoney(?float $amount): string
    {
        if ($amount === null) {
            return '';
        }

        return number_format($amount, 2, '.', ' ') . ' DT';
    }

    private function formatBoolean(?bool $value): string
    {
        return match ($value) {
            true => 'Oui',
            false => 'Non',
            default => '',
        };
    }

    private function formatProgressStatus(?string $value): string
    {
        return match ($value) {
            'Oui' => 'Pret pour un test, une vente ou une mise en service',
            'En cours' => 'En preparation ou en cours de finalisation',
            'Non' => 'Encore au stade d idee, de cadrage ou de conception',
            default => trim((string) $value),
        };
    }
}
