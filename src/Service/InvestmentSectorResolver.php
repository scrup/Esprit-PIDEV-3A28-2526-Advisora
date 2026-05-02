<?php

namespace App\Service;

final class InvestmentSectorResolver
{
    /**
     * @var array<string, array{
     *     label: string,
     *     outlook: string,
     *     macro_bonus: float,
     *     inflation_penalty: float,
     *     lending_penalty: float,
     *     gdp_bonus: float,
     *     aliases: list<string>
     * }>
     */
    private const SECTORS = [
        'it_technologie' => [
            'label' => 'IT / Technologie',
            'outlook' => 'Secteur agile, generalement plus resilient aux cycles de credit que les activites tres capitalistiques.',
            'macro_bonus' => 8.0,
            'inflation_penalty' => 0.9,
            'lending_penalty' => 0.8,
            'gdp_bonus' => 1.3,
            'aliases' => ['it', 'it startups', 'it startup', 'it technologie', 'technologie', 'tech', 'startup', 'startups', 'digital', 'digitale', 'saas', 'software', 'logiciel', 'logiciels', 'application', 'applications', 'ia', 'intelligence artificielle', 'informatique', 'developpement web', 'web', 'mobile', 'ux ui', 'business intelligence', 'bi'],
        ],
        'ecommerce' => [
            'label' => 'E-commerce',
            'outlook' => 'Secteur scalable, mais sensible au pouvoir d achat et aux couts logistiques.',
            'macro_bonus' => 5.0,
            'inflation_penalty' => 1.1,
            'lending_penalty' => 0.7,
            'gdp_bonus' => 1.2,
            'aliases' => ['e commerce', 'ecommerce', 'commerce en ligne', 'vente en ligne', 'retail digital'],
        ],
        'immobilier' => [
            'label' => 'Immobilier',
            'outlook' => 'Secteur plus expose au cout du financement et au ralentissement economique.',
            'macro_bonus' => -4.0,
            'inflation_penalty' => 1.0,
            'lending_penalty' => 1.7,
            'gdp_bonus' => 0.8,
            'aliases' => ['immobilier', 'real estate', 'promotion immobiliere', 'construction'],
        ],
        'finance_fintech' => [
            'label' => 'Finance / Fintech',
            'outlook' => 'Secteur a potentiel, mais fortement sensible au cadre financier et au cout du risque.',
            'macro_bonus' => 2.0,
            'inflation_penalty' => 1.0,
            'lending_penalty' => 1.3,
            'gdp_bonus' => 1.1,
            'aliases' => ['finance', 'fintech', 'finance fintech', 'banque', 'assurance', 'paiement', 'payment'],
        ],
        'sante' => [
            'label' => 'Sante',
            'outlook' => 'Secteur defensif avec une demande souvent stable, meme quand le contexte se tend.',
            'macro_bonus' => 9.0,
            'inflation_penalty' => 0.8,
            'lending_penalty' => 0.9,
            'gdp_bonus' => 1.0,
            'aliases' => ['sante', 'health', 'medical', 'medecine', 'clinique', 'pharma', 'pharmaceutique'],
        ],
        'education' => [
            'label' => 'Education',
            'outlook' => 'Secteur structurel et relativement stable, surtout sur des modeles de formation continue ou numerique.',
            'macro_bonus' => 6.0,
            'inflation_penalty' => 0.8,
            'lending_penalty' => 0.8,
            'gdp_bonus' => 1.0,
            'aliases' => ['education', 'formation', 'edtech', 'enseignement', 'school', 'ecole'],
        ],
        'energie' => [
            'label' => 'Energie',
            'outlook' => 'Secteur strategique a potentiel, mais souvent gourmand en capital et donc sensible aux taux.',
            'macro_bonus' => 3.0,
            'inflation_penalty' => 1.0,
            'lending_penalty' => 1.4,
            'gdp_bonus' => 1.2,
            'aliases' => ['energie', 'energy', 'energies renouvelables', 'renouvelable', 'solaire', 'eolien'],
        ],
        'transport_logistique' => [
            'label' => 'Transport / Logistique',
            'outlook' => 'Secteur utile, mais expose aux couts d exploitation et a la demande economique generale.',
            'macro_bonus' => 0.0,
            'inflation_penalty' => 1.2,
            'lending_penalty' => 1.1,
            'gdp_bonus' => 1.1,
            'aliases' => ['transport', 'transport logistique', 'logistique', 'supply chain', 'livraison', 'freight'],
        ],
        'agriculture' => [
            'label' => 'Agriculture',
            'outlook' => 'Secteur essentiel, mais soumis a des contraintes exogenes et a une execution parfois plus volatile.',
            'macro_bonus' => 1.0,
            'inflation_penalty' => 1.1,
            'lending_penalty' => 1.0,
            'gdp_bonus' => 1.0,
            'aliases' => ['agriculture', 'agri', 'agritech', 'agro', 'agroalimentaire'],
        ],
        'tourisme' => [
            'label' => 'Tourisme',
            'outlook' => 'Secteur opportuniste mais plus cyclique, dependant du climat economique et de la demande externe.',
            'macro_bonus' => -2.0,
            'inflation_penalty' => 1.1,
            'lending_penalty' => 1.0,
            'gdp_bonus' => 1.3,
            'aliases' => ['tourisme', 'tourism', 'hotel', 'hotellerie', 'hospitality', 'voyage', 'travel'],
        ],
        'artisanat' => [
            'label' => 'Artisanat',
            'outlook' => 'Secteur de niche avec de belles opportunites, mais une traction souvent plus progressive.',
            'macro_bonus' => 1.0,
            'inflation_penalty' => 1.0,
            'lending_penalty' => 0.9,
            'gdp_bonus' => 0.9,
            'aliases' => ['artisanat', 'artisan', 'craft', 'metier', 'metiers'],
        ],
        'restauration' => [
            'label' => 'Restauration',
            'outlook' => 'Secteur dynamique mais sensible aux couts matieres et au pouvoir d achat.',
            'macro_bonus' => -1.0,
            'inflation_penalty' => 1.2,
            'lending_penalty' => 0.9,
            'gdp_bonus' => 1.1,
            'aliases' => ['restauration', 'restaurant', 'food', 'foodtech', 'cafe', 'cafeteria'],
        ],
        'services_business' => [
            'label' => 'Services / Business',
            'outlook' => 'Secteur de services plutot flexible, mais dont la traction depend beaucoup de la qualite d execution et de la demande entreprise.',
            'macro_bonus' => 3.0,
            'inflation_penalty' => 0.9,
            'lending_penalty' => 0.8,
            'gdp_bonus' => 1.1,
            'aliases' => ['commercial', 'communication', 'gestion', 'juridique', 'marketing', 'pilotage', 'productivite', 'relation client', 'ressources humaines', 'strategie'],
        ],
        'autre_multisectoriel' => [
            'label' => 'Autre / Multisectoriel',
            'outlook' => 'Profil neutre applique quand le type de projet ne rentre pas clairement dans une famille sectorielle connue.',
            'macro_bonus' => 0.0,
            'inflation_penalty' => 1.0,
            'lending_penalty' => 1.0,
            'gdp_bonus' => 1.0,
            'aliases' => ['autre', 'multisectoriel', 'multi sectoriel', 'resource', 'resource market'],
        ],
    ];

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     outlook: string,
     *     macro_bonus: float,
     *     inflation_penalty: float,
     *     lending_penalty: float,
     *     gdp_bonus: float,
     *     matching_types: list<string>
     * }
     */
    public function resolve(?string $rawType): array
    {
        $normalized = $this->normalize($rawType);

        foreach (self::SECTORS as $key => $profile) {
            if (in_array($normalized, $profile['aliases'], true)) {
                return $this->buildResolvedProfile($key, $profile);
            }
        }

        foreach (self::SECTORS as $key => $profile) {
            foreach ($profile['aliases'] as $alias) {
                if ($this->containsAliasAsWords($normalized, $alias)) {
                    return $this->buildResolvedProfile($key, $profile);
                }
            }
        }

        return $this->buildResolvedProfile('autre_multisectoriel', self::SECTORS['autre_multisectoriel']);
    }

    /**
     * @param array{
     *     label: string,
     *     outlook: string,
     *     macro_bonus: float,
     *     inflation_penalty: float,
     *     lending_penalty: float,
     *     gdp_bonus: float,
     *     aliases: list<string>
     * } $profile
     *
     * @return array{
     *     key: string,
     *     label: string,
     *     outlook: string,
     *     macro_bonus: float,
     *     inflation_penalty: float,
     *     lending_penalty: float,
     *     gdp_bonus: float,
     *     matching_types: list<string>
     * }
     */
    private function buildResolvedProfile(string $key, array $profile): array
    {
        return [
            'key' => $key,
            'label' => $profile['label'],
            'outlook' => $profile['outlook'],
            'macro_bonus' => $profile['macro_bonus'],
            'inflation_penalty' => $profile['inflation_penalty'],
            'lending_penalty' => $profile['lending_penalty'],
            'gdp_bonus' => $profile['gdp_bonus'],
            'matching_types' => $profile['aliases'],
        ];
    }

    private function normalize(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));

        if ($normalized === '') {
            return '';
        }

        $normalized = strtr($normalized, [
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ã' => 'a',
            'å' => 'a',
            'ç' => 'c',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'ö' => 'o',
            'õ' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ý' => 'y',
            'ÿ' => 'y',
            'œ' => 'oe',
            'æ' => 'ae',
        ]);

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);

        if (is_string($ascii) && $ascii !== '') {
            $normalized = $ascii;
        }

        $normalized = str_replace(['_', '-'], ' ', $normalized);
        $normalized = preg_replace('/[^a-z0-9\/ ]+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function containsAliasAsWords(string $normalized, string $alias): bool
    {
        $normalized = $this->normalizeWords($normalized);
        $alias = $this->normalizeWords($alias);

        if ($normalized === '' || $alias === '') {
            return false;
        }

        return str_contains(' ' . $normalized . ' ', ' ' . $alias . ' ');
    }

    private function normalizeWords(string $value): string
    {
        $value = str_replace('/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}