<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Minimal forward-only SQL migration runner.
 *
 * Files in migrations/ are named NNN_description.sql and applied in
 * order. Applied versions are recorded in schema_migrations so each file
 * runs exactly once. Statements are separated by a semicolon at the end
 * of a line, so the same files can also be imported by hand through
 * phpMyAdmin (no DELIMITER blocks are used anywhere).
 */
final class Migrator
{
    public function __construct(private PDO $db, private string $dir)
    {
    }

    public function ensureTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version    VARCHAR(255) PRIMARY KEY,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return list<string> migration file names, sorted */
    public function all(): array
    {
        $files = glob(rtrim($this->dir, '/') . '/[0-9][0-9][0-9]_*.sql') ?: [];
        $names = array_map('basename', $files);
        sort($names, SORT_STRING);
        return $names;
    }

    /** @return list<string> */
    public function applied(): array
    {
        $this->ensureTable();
        return $this->db->query('SELECT version FROM schema_migrations ORDER BY version')
                        ->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<string> */
    public function pending(?string $upTo = null): array
    {
        $done = array_flip($this->applied());
        return array_values(array_filter(
            $this->all(),
            static fn (string $f) => !isset($done[$f]) && ($upTo === null || strncmp($f, $upTo, strlen($upTo)) <= 0)
        ));
    }

    /**
     * Apply pending migrations (optionally only up to a version prefix such as "007").
     *
     * @param callable(string):void|null $log
     * @return list<string> applied file names
     */
    public function migrate(?string $upTo = null, ?callable $log = null): array
    {
        $applied = [];
        foreach ($this->pending($upTo) as $file) {
            $log && $log("Applying {$file}");
            foreach (self::splitStatements((string) file_get_contents($this->dir . '/' . $file)) as $sql) {
                $this->db->exec($sql);
            }
            $this->db->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$file]);
            $applied[] = $file;
        }
        return $applied;
    }

    /**
     * Split a SQL script into statements on semicolons that end a line.
     * Comment-only chunks are dropped.
     *
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $parts = preg_split('/;[ \t]*(?:\R|$)/', $sql) ?: [];
        $out   = [];
        foreach ($parts as $part) {
            $code = trim((string) preg_replace('/^\s*--.*$/m', '', $part));
            if ($code !== '') {
                $out[] = trim($part);
            }
        }
        return $out;
    }
}
