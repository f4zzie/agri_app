#!/bin/sh
# AgriTrack container start-up: wait for the database, migrate, optionally seed, then start Apache.
set -e

cd /var/www/html

echo "Waiting for database ${DB_HOST:-localhost}:${DB_PORT:-3306}..."
php -r '
$dsn = sprintf("mysql:host=%s;port=%d", getenv("DB_HOST") ?: "localhost", (int) (getenv("DB_PORT") ?: 3306));
for ($i = 0; $i < 60; $i++) {
    try {
        new PDO($dsn, getenv("DB_USER") ?: "root", getenv("DB_PASS") ?: "", [PDO::ATTR_TIMEOUT => 2]);
        fwrite(STDOUT, "Database is up.\n");
        exit(0);
    } catch (PDOException $e) {
        sleep(1);
    }
}
fwrite(STDERR, "Database not reachable after 60s: " . $e->getMessage() . "\n");
exit(1);
'

php bin/migrate.php

if [ "${SEED_DEMO_DATA:-0}" = "1" ]; then
    php bin/seed.php --if-empty
fi

# Named volumes are created root-owned on first run
mkdir -p public/uploads/avatars public/uploads/disease_checks
chown -R www-data:www-data public/uploads

exec "$@"
