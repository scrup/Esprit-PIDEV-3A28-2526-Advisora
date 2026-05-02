<?php

namespace App\Service;

interface ProjectEstimationMetaAwareInterface
{
    /**
     * @return array{provider_used: string|null, used_fallback: bool, warning: string|null, model: string|null}
     */
    public function getLastEstimationMeta(): array;
}
