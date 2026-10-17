<?php
/**
 * Reproduce docs/EXPLAIN.md: query plans and timings before/after 008_indexes.sql.
 *
 *   php tools/explain_demo.php
 *
 * Uses a separate scratch database (agri_app_explain) so your data is
 * untouched: migrates it to 007, seeds demo data, adds bulk synthetic rows
 * so the optimizer has a realistic amount of data, measures, applies 008,
 * and measures again.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}

const SCRATCH_DB = 'agri_app_explain';
const RUNS       = 30;

putenv('DB_NAME=' . SCRATCH_DB);
$_ENV['DB_NAME'] = SCRATCH_DB;
require_once __DIR__ . '/../config.php';

$php  = escapeshellarg(PHP_BINARY);
$root = escapeshellarg(dirname(__DIR__));
$run  = static function (string $cmd): void {
    passthru($cmd, $code);
    if ($code !== 0) {
        fwrite(STDERR, "Command failed: {$cmd}\n");
        exit(1);
    }
};

echo "== Preparing scratch database " . SCRATCH_DB . "\n";
$run("{$php} {$root}/bin/migrate.php --fresh --to=007 > /dev/null");
$run("{$php} {$root}/bin/seed.php > /dev/null");

$db = getDB();
mt_srand(42);

/** Multi-row insert helper. */
$bulk = static function (string $table, array $cols, int $count, callable $row) use ($db): void {
    $batch = [];
    for ($i = 0; $i < $count; $i++) {
        $batch[] = $row($i);
        if (count($batch) === 1000 || $i === $count - 1) {
            $ph   = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
            $stmt = $db->prepare("INSERT INTO {$table} (" . implode(',', $cols) . ') VALUES '
                                 . implode(',', array_fill(0, count($batch), $ph)));
            $stmt->execute(array_merge(...$batch));
            $batch = [];
        }
    }
};

echo "== Adding bulk synthetic rows\n";
// 2,000 extra farmers spread over all counties, ~10 listings each
$bulk('users', ['name', 'email', 'role', 'county_id', 'email_verified'], 2000, static fn ($i) => [
    "Bulk Farmer {$i}", "bulk.farmer{$i}@example.com", 'farmer', mt_rand(1, 47), 1,
]);
$farmers = $db->query("SELECT id FROM users WHERE role = 'farmer'")->fetchAll(PDO::FETCH_COLUMN);
$userIds = $db->query('SELECT id FROM users')->fetchAll(PDO::FETCH_COLUMN);
$ago     = static fn (int $maxDays): string => date('Y-m-d H:i:s', time() - mt_rand(0, $maxDays * 86400));

$bulk('crops', ['user_id', 'name', 'unit', 'price', 'status', 'created_at'], 20000, static fn ($i) => [
    $farmers[array_rand($farmers)], 'Bulk product ' . ($i % 500), 'kg', mt_rand(20, 500),
    mt_rand(1, 100) <= 30 ? 'available' : 'out_of_stock', $ago(720),
]);
$bulk('login_attempts', ['email', 'ip_address', 'attempted_at'], 50000, static fn ($i) => [
    "user{$i}@example.com", '10.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254), $ago(365),
]);
$bulk('password_resets', ['user_id', 'token_hash', 'expires_at', 'used'], 20000, static fn ($i) => [
    $userIds[array_rand($userIds)], hash('sha256', "token{$i}"), $ago(365), 1,
]);
$bulk('activity_log', ['user_id', 'action', 'details', 'ip_address', 'created_at'], 50000, static fn ($i) => [
    $userIds[array_rand($userIds)], 'login', 'User logged in', '127.0.0.1', $ago(365),
]);
$bulk('price_history', ['product_key', 'unit', 'county_id', 'price', 'recorded_at'], 50000, static fn ($i) => [
    'bulk product ' . ($i % 500), 'kg', mt_rand(1, 47), mt_rand(20, 500), $ago(720),
]);
foreach (['crops', 'users', 'login_attempts', 'password_resets', 'activity_log', 'price_history'] as $t) {
    $db->query("ANALYZE TABLE {$t}")->fetchAll();
}

$demoFarmer = (int) $db->query("SELECT id FROM users WHERE email = 'farmer@agritrack.test'")->fetchColumn();
$queries = [
    'Login rate limit (login.php)' => [
        "SELECT COUNT(*) FROM login_attempts WHERE ip_address = '10.1.2.3' AND attempted_at >= NOW() - INTERVAL 15 MINUTE",
        'idx_login_attempts_ip_time',
    ],
    'Password reset token lookup (reset_password.php)' => [
        "SELECT * FROM password_resets WHERE token_hash = '" . hash('sha256', 'token12345') . "' AND used = 0 AND expires_at > NOW()",
        'uq_password_resets_token',
    ],
    'Marketplace: newest available listings in a county (marketplace.php)' => [
        "SELECT * FROM v_marketplace WHERE status = 'available' AND county_id = 32 ORDER BY created_at DESC LIMIT 12",
        '— (existing FK indexes suffice)',
    ],
    'Buyer dashboard: newest available listings (dashboard.php)' => [
        "SELECT * FROM v_marketplace WHERE status = 'available' ORDER BY created_at DESC LIMIT 6",
        'idx_crops_status_created',
    ],
    'Farmer dashboard: my listings, newest first (dashboard.php)' => [
        "SELECT * FROM crops WHERE user_id = {$demoFarmer} ORDER BY created_at DESC",
        'idx_crops_user_created',
    ],
    'Activity log page (activity_log.php)' => [
        "SELECT * FROM activity_log WHERE user_id = {$demoFarmer} ORDER BY created_at DESC LIMIT 20",
        'idx_activity_user_created',
    ],
    'Price trend for one product (price_trends.php)' => [
        "SELECT DATE_FORMAT(recorded_at, '%Y-%m') ym, AVG(price), MIN(price), MAX(price), COUNT(*)
           FROM price_history WHERE product_key = 'maize' AND unit = 'kg'
            AND recorded_at >= CURDATE() - INTERVAL 12 MONTH GROUP BY ym ORDER BY ym",
        'idx_price_history_lookup',
    ],
];

$measure = static function () use ($db, $queries): array {
    $out = [];
    foreach ($queries as $label => [$sql]) {
        $plan = $db->query('EXPLAIN ' . $sql)->fetchAll(PDO::FETCH_ASSOC);
        $times = [];
        for ($i = 0; $i < RUNS; $i++) {
            $t = hrtime(true);
            $db->query($sql)->fetchAll();
            $times[] = (hrtime(true) - $t) / 1e6;
        }
        sort($times);
        $out[$label] = ['plan' => $plan, 'ms' => $times[intdiv(RUNS, 2)]];
    }
    return $out;
};

echo "== Measuring without 008 indexes\n";
$before = $measure();

echo "== Applying 008_indexes.sql\n";
$run("{$php} {$root}/bin/migrate.php > /dev/null");
foreach (['crops', 'users', 'login_attempts', 'password_resets', 'activity_log', 'price_history'] as $t) {
    $db->query("ANALYZE TABLE {$t}")->fetchAll();
}

echo "== Measuring with 008 indexes\n";
$after = $measure();

// ---------------------------------------------------------------------------
// Write docs/EXPLAIN.md
// ---------------------------------------------------------------------------
$planTable = static function (array $plan): string {
    $cols = ['table', 'type', 'key', 'rows', 'Extra'];
    $md = '| ' . implode(' | ', $cols) . " |\n|" . str_repeat(' --- |', count($cols)) . "\n";
    foreach ($plan as $r) {
        $md .= '| ' . implode(' | ', array_map(static fn ($c) => str_replace('|', '\|', (string) ($r[$c] ?? '')) ?: '—', $cols)) . " |\n";
    }
    return $md;
};
$counts = [];
foreach (['crops', 'login_attempts', 'password_resets', 'activity_log', 'price_history', 'users'] as $t) {
    $counts[] = "`{$t}` " . number_format((int) $db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn());
}
$version = $db->query('SELECT VERSION()')->fetchColumn();

$md  = "# Query plans before and after `008_indexes.sql`\n\n";
$md .= "Generated by `php tools/explain_demo.php` on " . date('Y-m-d') . " (MariaDB {$version}).\n\n";
$md .= "Data: demo seed plus bulk synthetic rows — " . implode(', ', $counts) . ".\n";
$md .= "Timings are the median of " . RUNS . " runs on a laptop; compare them relative to each other, not as absolutes.\n\n";
$md .= "| Query | Index added | Rows examined (est.) before → after | Median time before → after |\n| --- | --- | --- | --- |\n";
foreach ($queries as $label => [$sql, $index]) {
    $rb = array_sum(array_map(static fn ($r) => (int) $r['rows'], $before[$label]['plan']));
    $ra = array_sum(array_map(static fn ($r) => (int) $r['rows'], $after[$label]['plan']));
    $md .= sprintf("| %s | `%s` | %s → %s | %.2f ms → %.2f ms |\n", $label, $index,
        number_format($rb), number_format($ra), $before[$label]['ms'], $after[$label]['ms']);
}
foreach ($queries as $label => [$sql, $index]) {
    $md .= "\n## {$label}\n\n```sql\n" . preg_replace('/\s+/', ' ', trim($sql)) . "\n```\n\n";
    $md .= "**Before** (" . sprintf('%.2f', $before[$label]['ms']) . " ms)\n\n" . $planTable($before[$label]['plan']);
    $md .= "\n**After** (" . sprintf('%.2f', $after[$label]['ms']) . " ms)\n\n" . $planTable($after[$label]['plan']);
}
$md .= "\n## Notes\n\n"
     . "- **Buyer dashboard:** the estimated `rows` goes *up* while the time drops. With `idx_crops_status_created` the optimizer reads available listings already in `created_at` order and stops after `LIMIT` rows; `rows` reports the size of the range, not the rows actually read. Before, every farmer was joined, the result put in a temporary table and sorted.\n"
     . "- **Marketplace by county:** no index added. `county_id` is already selective and served by the index InnoDB created for `fk_users_county`. A composite `(role, county_id)` index was tried; the plan and timing did not change, so it was left out (indexes cost space and slow every write).\n"
     . "- **Farmer dashboard / activity log:** the gain is removing `Using filesort`; at a few dozen rows per user the absolute time difference is small, but it no longer grows with the user's history.\n";
$md .= "\n## Reading the plans\n\n"
     . "- `type: ALL` is a full table scan; `ref`/`range`/`const` mean an index narrowed the rows first.\n"
     . "- `Using filesort` means rows were sorted after reading; an index in `ORDER BY` order removes it, so `LIMIT` can stop early.\n"
     . "- `rows` is the optimizer's estimate of rows to examine for that table.\n";

@mkdir(__DIR__ . '/../docs');
file_put_contents(__DIR__ . '/../docs/EXPLAIN.md', $md);
echo "== Wrote docs/EXPLAIN.md\n";

getServerConnection()->exec('DROP DATABASE `' . SCRATCH_DB . '`');
echo "== Dropped scratch database\n";
