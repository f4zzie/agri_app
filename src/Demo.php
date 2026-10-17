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

    /** Upload sub-folders the app writes into; they are emptied, not removed. */
    public const UPLOAD_DIRS = ['avatars', 'disease_checks'];

    /**
     * Delete everything visitors uploaded under $dir, keeping $dir/.htaccess
     * (blocks script execution) and the empty UPLOAD_DIRS folders.
     *
     * @return int number of files deleted
     */
    public static function clearUploads(string $dir): int
    {
        $deleted = 0;
        foreach (new \FilesystemIterator($dir) as $entry) {
            /** @var \SplFileInfo $entry */
            $name = $entry->getFilename();
            if ($name === '.htaccess' && $entry->isFile()) {
                continue;
            }
            if ($entry->isDir() && !$entry->isLink()) {
                $deleted += self::removeTree($entry->getPathname(), !in_array($name, self::UPLOAD_DIRS, true));
            } elseif (unlink($entry->getPathname())) {
                $deleted++;
            }
        }
        foreach (self::UPLOAD_DIRS as $sub) {
            if (!is_dir("{$dir}/{$sub}")) {
                mkdir("{$dir}/{$sub}", 0775, true);
            }
        }
        return $deleted;
    }

    private static function removeTree(string $dir, bool $removeSelf): int
    {
        $deleted = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir() && !$item->isLink()) {
                rmdir($item->getPathname());
            } elseif (unlink($item->getPathname())) {
                $deleted++;
            }
        }
        if ($removeSelf) {
            rmdir($dir);
        }
        return $deleted;
    }
}
