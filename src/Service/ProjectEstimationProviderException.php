<?php

namespace App\Service;

final class ProjectEstimationProviderException extends \RuntimeException
{
    public function __construct(
        private string $provider,
        string $message,
        private bool $retryable = false,
        private ?int $statusCode = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}
