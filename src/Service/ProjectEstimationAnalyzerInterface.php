<?php

namespace App\Service;

use App\Dto\ProjectEstimationRequest;

interface ProjectEstimationAnalyzerInterface
{
    /**
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
    public function estimate(ProjectEstimationRequest $request): array;
}
