<?php

declare(strict_types=1);

namespace App;

/**
 * One-time tokens (password reset, email verification).
 * The plaintext goes into the emailed link; only the hash is stored.
 */
final class Token
{
    /** @return array{0:string,1:string} [plaintext, sha256 hash] */
    public static function generate(): array
    {
        $plain = bin2hex(random_bytes(32));
        return [$plain, self::hash($plain)];
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** Shape check before touching the database. */
    public static function looksValid(string $plain): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $plain) === 1;
    }
}
