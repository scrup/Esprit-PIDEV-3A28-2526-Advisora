<?php

namespace App\Dto;

final class ProjectTypeStatusAggregateRow
{
    public function __construct(
        private readonly string $normalizedType,
        private readonly string $status,
        private readonly mixed $total,
    ) {
    }

    public function getNormalizedType(): string
    {
        return $this->normalizedType;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTotal(): int
    {
        return (int) $this->total;
    }
}
