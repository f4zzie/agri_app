<?php
/**
 * AgriTrack – database migration CLI
 *
 *   php bin/migrate.php            apply all pending migrations
 *   php bin/migrate.php --to=007   apply pending migrations up to 007
 *   php bin/migrate.php --fresh    DROP the database, recreate it, apply all
 *   php bin/migrate.php --status   list applied / pending migrations
 *
 * Connection settings come from .env (DB_HOST, DB_PORT, DB_NAME, ...).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}

require_once __DIR__ . '/../config.php';

$opts   = getopt('', ['to:', 'fresh', 'status']);
$dbName = $_ENV['DB_NAME'] ?? 'agri_app';

if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
    fwrite(STDERR, "Refusing to use unusual database name '{$dbName}'.\n");
    exit(1);
}

$server = getServerConnection();

if (isset($opts['fresh'])) {
    echo "Dropping database `{$dbName}`...\n";
    $server->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
$server->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$server->exec("USE `{$dbName}`");

$migrator = new App\Migrator($server, __DIR__ . '/../migrations');

if (isset($opts['status'])) {
    $applied = array_flip($migrator->applied());
    foreach ($migrator->all() as $file) {
        printf("  [%s] %s\n", isset($applied[$file]) ? 'x' : ' ', $file);
    }
    exit(0);
}

$done = $migrator->migrate($opts['to'] ?? null, static function (string $msg): void {
    echo $msg, "\n";
});
echo $done ? 'Applied ' . count($done) . " migration(s).\n" : "Nothing to migrate.\n";
