<?php

namespace App\Exception;

final class InsufficientWalletException extends \RuntimeException
{
    public function __construct(
        private readonly float $missingCoins,
        private readonly float $requiredCoins,
        private readonly float $currentBalance,
    ) {
        parent::__construct(sprintf(
            'Solde insuffisant. Requis: %.3f coins, disponible: %.3f coins.',
            $requiredCoins,
            $currentBalance
        ));
    }

    public function getMissingCoins(): float
    {
        return $this->missingCoins;
    }

    public function getRequiredCoins(): float
    {
        return $this->requiredCoins;
    }

    public function getCurrentBalance(): float
    {
        return $this->currentBalance;
    }
}
