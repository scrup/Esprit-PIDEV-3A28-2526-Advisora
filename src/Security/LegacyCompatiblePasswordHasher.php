<?php

namespace App\Security;

use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class LegacyCompatiblePasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plainPassword): string
    {
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash password.');
        }

        // Java jBCrypt commonly uses "$2a$". Convert PHP "$2y$" prefix for cross-app format parity.
        if (str_starts_with($hash, '$2y$')) {
            $hash = '$2a$' . substr($hash, 4);
        }

        return $hash;
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        $normalizedHash = $this->normalizeHash($hashedPassword);

        if ($this->looksHashed($normalizedHash)) {
            return password_verify($plainPassword, $normalizedHash);
        }

        return hash_equals($normalizedHash, $plainPassword);
    }

    public function needsRehash(string $hashedPassword): bool
    {
        $normalizedHash = $this->normalizeHash($hashedPassword);

        if (!$this->looksHashed($normalizedHash)) {
            return true;
        }

        return password_needs_rehash($normalizedHash, PASSWORD_BCRYPT);
    }

    private function looksHashed(string $value): bool
    {
        return str_starts_with($value, '$2y$')
            || str_starts_with($value, '$2a$')
            || str_starts_with($value, '$2b$')
            || str_starts_with($value, '$argon2i$')
            || str_starts_with($value, '$argon2id$');
    }

    private function normalizeHash(string $hash): string
    {
        $normalized = trim($hash);

        if (str_starts_with($normalized, '{bcrypt}')) {
            return substr($normalized, 8);
        }

        return $normalized;
    }
}
