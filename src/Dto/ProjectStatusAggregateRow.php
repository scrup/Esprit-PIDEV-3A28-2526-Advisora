<?php

namespace App\Dto;

final class ProjectStatusAggregateRow
{
    public function __construct(
        private readonly string $status,
        private readonly mixed $total,
    ) {
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
