<?php
/**
 * AgriTrack – reset the public demo to its seeded state
 *
 *   php bin/reset_demo.php
 *   docker compose exec app php bin/reset_demo.php     (from the host, e.g. nightly cron)
 *
 * Drops and re-creates the database (migrate --fresh), re-seeds the demo
 * data and deletes everything visitors uploaded. Refuses to run unless
 * DEMO_MODE=1, so it can never wipe a real deployment by accident.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}

require_once __DIR__ . '/../config.php';

if (!App\Demo::enabled()) {
    fwrite(STDERR, "Refusing to run: DEMO_MODE is not 1. This script deletes ALL data and uploads.\n");
    exit(1);
}

$run = static function (string $script, string ...$args): void {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $script)
         . ($args ? ' ' . implode(' ', array_map('escapeshellarg', $args)) : '');
    passthru($cmd, $code);
    if ($code !== 0) {
        fwrite(STDERR, "{$script} failed (exit {$code}); demo reset aborted.\n");
        exit($code);
    }
};

echo '[' . date('c') . "] Resetting demo...\n";
$run('migrate.php', '--fresh');
$run('seed.php');

$deleted = App\Demo::clearUploads(__DIR__ . '/../public/uploads');
echo "Deleted {$deleted} uploaded file(s).\n";
echo '[' . date('c') . "] Demo reset complete.\n";
