<?php

namespace App\Security;

use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class LegacyCompatiblePasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT);
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        if ($this->looksHashed($hashedPassword)) {
            return password_verify($plainPassword, $hashedPassword);
        }

        return hash_equals($hashedPassword, $plainPassword);
    }

    public function needsRehash(string $hashedPassword): bool
    {
        if (!$this->looksHashed($hashedPassword)) {
            return true;
        }

        return password_needs_rehash($hashedPassword, PASSWORD_BCRYPT);
    }

    private function looksHashed(string $value): bool
    {
        return str_starts_with($value, '$2y$')
            || str_starts_with($value, '$2a$')
            || str_starts_with($value, '$argon2i$')
            || str_starts_with($value, '$argon2id$');
    }
}
