<?php

declare(strict_types=1);

namespace App;

/**
 * Public demo mode (DEMO_MODE=1).
 *
 * The seeded demo logins are shown on the landing and login pages, so
 * strangers share them. Their sign-in details (password, email, role) are
 * locked so one visitor cannot lock the others out; everything else works.
 */
final class Demo
{
    /** Seeded by bin/seed.php; their password is shown on the login page. */
    public const ACCOUNTS = ['farmer@agritrack.test', 'buyer@agritrack.test'];

    public static function enabled(): bool
    {
        return ($_ENV['DEMO_MODE'] ?? '') === '1';
    }

    /** True when demo mode is on and $email is one of the shared demo logins. */
    public static function isProtectedAccount(?string $email): bool
    {
        return self::enabled()
            && $email !== null
            && in_array(strtolower(trim($email)), self::ACCOUNTS, true);
    }
}
