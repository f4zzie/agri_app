<?php
/**
 * AgriTrack – price_trends.php
 * Monthly price trend for a product, plus a county comparison,
 * aggregated from the trigger-maintained price_history ledger.
 */
require_once __DIR__ . '/../includes/auth_check.php';
$db        = getDB();
$pageTitle = t('price_trends');

// Products with enough data to chart, most-observed first
$products = $db->query(
    'SELECT product_key, unit, COUNT(*) AS n
       FROM price_history
      GROUP BY product_key, unit
     HAVING n >= 2
      ORDER BY n DESC, product_key
      LIMIT 100'
)->fetchAll();

$selKey    = (string) ($_GET['product'] ?? ($products[0]['product_key'] ?? ''));
$units     = array_values(array_unique(array_column(array_filter($products, static fn ($p) => $p['product_key'] === $selKey), 'unit')));
$selUnit   = in_array($_GET['unit'] ?? '', $units, true) ? $_GET['unit'] : ($units[0] ?? 'kg');
$selCounty = (int) ($_GET['county'] ?? 0);
$months    = in_array((int) ($_GET['months'] ?? 12), [3, 6, 12, 24], true) ? (int) ($_GET['months'] ?? 12) : 12;

$monthly   = [];
$byCounty  = [];
$summary   = null;

if ($selKey !== '') {
    $sql = "SELECT DATE_FORMAT(recorded_at, '%Y-%m') AS ym,
                   ROUND(AVG(price), 2) AS avg_price,
                   MIN(price) AS min_price, MAX(price) AS max_price, COUNT(*) AS n
              FROM price_history
             WHERE product_key = :p AND unit = :u
               AND recorded_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') - INTERVAL :m MONTH"
         . ($selCounty ? ' AND county_id = :c' : '')
         . ' GROUP BY ym ORDER BY ym';
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':p', $selKey);
    $stmt->bindValue(':u', $selUnit);
    $stmt->bindValue(':m', $months - 1, PDO::PARAM_INT);
    if ($selCounty) {
        $stmt->bindValue(':c', $selCounty, PDO::PARAM_INT);
    }
    $stmt->execute();
    $monthly = $stmt->fetchAll();

    // Average over the last 90 days per county (where farmers sell it)
    $stmt = $db->prepare(
        'SELECT co.name AS county, ROUND(AVG(ph.price), 2) AS avg_price, COUNT(*) AS n
           FROM price_history ph
           JOIN counties co ON co.id = ph.county_id
          WHERE ph.product_key = ? AND ph.unit = ? AND ph.recorded_at >= CURDATE() - INTERVAL 90 DAY
          GROUP BY co.id, co.name
          ORDER BY avg_price'
    );
    $stmt->execute([$selKey, $selUnit]);
    $byCounty = $stmt->fetchAll();

    // Current listings for comparison
    $stmt = $db->prepare(
        "SELECT ROUND(AVG(price), 2) AS avg_now, MIN(price) AS min_now, MAX(price) AS max_now, COUNT(*) AS listings
           FROM crops
          WHERE LOWER(TRIM(name)) = ? AND unit = ? AND status = 'available' AND price IS NOT NULL"
    );
    $stmt->execute([$selKey, $selUnit]);
    $summary = $stmt->fetch();
}

$change = null;
if (count($monthly) >= 2) {
    $first  = (float) $monthly[0]['avg_price'];
    $last   = (float) $monthly[count($monthly) - 1]['avg_price'];
    $change = $first > 0 ? round(($last - $first) / $first * 100, 1) : null;
}

$label = static fn (string $key): string => mb_convert_case($key, MB_CASE_TITLE, 'UTF-8');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0"><i class="bi bi-graph-up me-2 text-success"></i><?= t('price_trends') ?></h1>
</div>
<p class="text-muted small"><?= t('price_trends_sub') ?></p>

<?php if (empty($products)): ?>
    <div class="alert alert-info"><?= t('no_price_data') ?></div>
<?php else: ?>

<form method="GET" class="card card-body mb-4 p-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="product" class="form-label small fw-semibold mb-1"><?= t('product') ?></label>
            <select name="product" id="product" class="form-select form-select-sm" onchange="this.form.unit.value=''; this.form.submit()">
                <?php foreach (array_unique(array_column($products, 'product_key')) as $k): ?>
                    <option value="<?= e($k) ?>" <?= $k === $selKey ? 'selected' : '' ?>><?= e($label($k)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-4 col-md-2">
            <label for="unit" class="form-label small fw-semibold mb-1"><?= t('price_per') ?></label>
            <select name="unit" id="unit" class="form-select form-select-sm">
                <?php foreach ($units as $u): ?>
                    <option value="<?= e($u) ?>" <?= $u === $selUnit ? 'selected' : '' ?>><?= e(t('unit_' . $u)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-8 col-md-3">
            <label for="county" class="form-label small fw-semibold mb-1"><?= t('county') ?></label>
            <select name="county" id="county" class="form-select form-select-sm">
                <option value=""><?= t('all_counties') ?></option>
                <?php foreach (counties() as $cid => $cname): ?>
                    <option value="<?= $cid ?>" <?= $selCounty === (int) $cid ? 'selected' : '' ?>><?= e($cname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="months" class="form-label small fw-semibold mb-1"><?= t('period') ?></label>
            <select name="months" id="months" class="form-select form-select-sm">
                <?php foreach ([3, 6, 12, 24] as $m): ?>
                    <option value="<?= $m ?>" <?= $m === $months ? 'selected' : '' ?>><?= sprintf(t('n_months'), $m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <button class="btn btn-success btn-sm w-100"><?= t('show') ?></button>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted"><?= t('avg_listed_now') ?></div>
            <div class="fs-4 fw-bold"><?= $summary && $summary['avg_now'] ? 'KSh ' . number_format((float) $summary['avg_now'], 2) : '—' ?></div>
            <div class="small text-muted"><?= sprintf(t('n_active_listings'), (int) ($summary['listings'] ?? 0)) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted"><?= t('range_now') ?></div>
            <div class="fs-5 fw-bold"><?= $summary && $summary['min_now'] ? number_format((float) $summary['min_now']) . ' – ' . number_format((float) $summary['max_now']) : '—' ?></div>
            <div class="small text-muted">KSh / <?= e(t('unit_' . $selUnit)) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted"><?= sprintf(t('change_over_period'), $months) ?></div>
            <div class="fs-4 fw-bold <?= $change === null ? '' : ($change >= 0 ? 'text-success' : 'text-danger') ?>">
                <?= $change === null ? '—' : ($change >= 0 ? '▲ ' : '▼ ') . abs($change) . '%' ?>
            </div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted"><?= t('observations') ?></div>
            <div class="fs-4 fw-bold"><?= array_sum(array_column($monthly, 'n')) ?></div>
        </div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h2 class="h6 mb-0"><?= sprintf(t('monthly_price_of'), e($label($selKey)), e(t('unit_' . $selUnit))) ?></h2></div>
            <div class="card-body">
                <?php if (empty($monthly)): ?>
                    <p class="text-muted mb-0"><?= t('no_data_for_filters') ?></p>
                <?php else: ?>
                    <canvas id="trendChart" height="260" role="img" aria-label="<?= t('price_trends') ?>"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><h2 class="h6 mb-0"><?= t('by_county_90d') ?></h2></div>
            <div class="card-body p-0">
                <?php if (empty($byCounty)): ?>
                    <p class="text-muted p-3 mb-0"><?= t('no_data_for_filters') ?></p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th><?= t('county') ?></th><th class="text-end">KSh</th><th class="text-end">n</th></tr></thead>
                        <tbody>
                        <?php foreach ($byCounty as $r): ?>
                            <tr><td><?= e($r['county']) ?></td><td class="text-end"><?= number_format((float) $r['avg_price'], 2) ?></td><td class="text-end text-muted"><?= (int) $r['n'] ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<details class="mt-4 small text-muted">
    <summary><?= t('how_computed') ?></summary>
    <pre class="bg-light p-2 rounded mt-2"><code>SELECT DATE_FORMAT(recorded_at, '%Y-%m') AS ym,
       ROUND(AVG(price), 2), MIN(price), MAX(price), COUNT(*)
  FROM price_history
 WHERE product_key = ? AND unit = ? [AND county_id = ?]
   AND recorded_at >= first_of_month - INTERVAL n MONTH
 GROUP BY ym ORDER BY ym;</code></pre>
</details>

<?php if (!empty($monthly)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const rows = <?= json_encode($monthly, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const labels = rows.map(r => new Date(r.ym + '-01').toLocaleDateString(document.documentElement.lang === 'sw' ? 'sw-KE' : 'en-KE', { month: 'short', year: '2-digit' }));
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: <?= json_encode(t('max')) ?>, data: rows.map(r => +r.max_price), borderColor: 'rgba(25,135,84,.25)', backgroundColor: 'rgba(25,135,84,.10)', pointRadius: 0, fill: '+1' },
                { label: <?= json_encode(t('min')) ?>, data: rows.map(r => +r.min_price), borderColor: 'rgba(25,135,84,.25)', pointRadius: 0, fill: false },
                { label: <?= json_encode(t('average')) ?>, data: rows.map(r => +r.avg_price), borderColor: '#198754', backgroundColor: '#198754', tension: .3, borderWidth: 3 }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: { y: { title: { display: true, text: 'KSh' }, beginAtZero: false } },
            plugins: { legend: { position: 'bottom' } }
        }
    });
})();
</script>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
