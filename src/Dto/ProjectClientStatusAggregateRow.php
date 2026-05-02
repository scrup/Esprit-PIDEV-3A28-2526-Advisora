<?php

namespace App\Dto;

final class ProjectClientStatusAggregateRow
{
    public function __construct(
        private readonly mixed $clientId,
        private readonly string $status,
        private readonly mixed $total,
    ) {
    }

    public function getClientId(): int
    {
        return (int) $this->clientId;
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
