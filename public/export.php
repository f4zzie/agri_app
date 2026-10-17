<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role('farmer');

$db  = getDB();
$uid = (int) $_SESSION['user_id'];
$format = $_GET['format'] ?? '';

$fetchCrops = static function () use ($db, $uid): array {
    $stmt = $db->prepare(
        'SELECT c.id, c.name, c.variety, cat.name AS category, c.price, c.unit, c.quantity,
                c.status, c.growth_stage, c.planting_date, c.expected_harvest, c.notes, c.created_at
           FROM crops c LEFT JOIN categories cat ON cat.id = c.category_id
          WHERE c.user_id = ? ORDER BY c.created_at DESC'
    );
    $stmt->execute([$uid]);
    return $stmt->fetchAll();
};

// ─── CSV ──────────────────────────────────────────────────────────────────────
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="agritrack_products_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8

    fputcsv($out, ['ID', 'Name', 'Variety', 'Category', 'Price (KES)', 'Unit', 'Quantity', 'Availability',
                   'Growth stage', 'Planting date', 'Expected harvest', 'Notes', 'Listed at'], ',', '"', '\\');
    foreach ($fetchCrops() as $c) {
        // Prefix cells that a spreadsheet would treat as formulas (CSV injection)
        $row = array_map(static fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, array_values($c));
        fputcsv($out, $row, ',', '"', '\\');
    }
    fclose($out);
    exit;
}

// ─── PDF (Dompdf if installed, otherwise a print-friendly page) ───────────────
if ($format === 'pdf') {
    $crops = $fetchCrops();

    $rowsHtml = '';
    foreach ($crops as $i => $c) {
        $rowsHtml .= '<tr><td>' . ($i + 1) . '</td><td>' . e($c['name']) . '</td><td>' . e($c['variety'] ?? '—')
                   . '</td><td>' . format_price($c['price'], $c['unit']) . '</td><td>' . e(t('status_' . $c['status']))
                   . '</td><td>' . e($c['growth_stage'] ? t($c['growth_stage']) : '—') . '</td><td>' . e($c['expected_harvest'] ?? '—') . '</td></tr>';
    }
    $tableHtml = '<table class="table table-bordered"><thead class="table-success"><tr><th>#</th><th>' . e(t('product'))
               . '</th><th>' . e(t('variety')) . '</th><th>' . e(t('price')) . '</th><th>' . e(t('availability'))
               . '</th><th>' . e(t('growth_stage')) . '</th><th>' . e(t('expected_harvest')) . '</th></tr></thead><tbody>'
               . $rowsHtml . '</tbody></table>';

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }

    if (class_exists(\Dompdf\Dompdf::class)) {
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
                body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
                h1 { color: #198754; }
                table { width: 100%; border-collapse: collapse; margin-top: 16px; }
                th { background: #198754; color: #fff; padding: 6px; text-align: left; }
                td { padding: 6px; border-bottom: 1px solid #ddd; }
                .footer { margin-top: 30px; font-size: 9px; color: #888; text-align: center; }
             </style></head><body>'
            . '<h1>AgriTrack — ' . e(t('product_report')) . '</h1>'
            . '<p>' . e($_SESSION['name'] ?? '') . ' · ' . date('Y-m-d H:i') . '</p>'
            . $tableHtml
            . '<div class="footer">' . e(t('powered_by')) . '</div></body></html>'
        );
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('agritrack_products_' . date('Y-m-d') . '.pdf', ['Attachment' => true]);
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="<?= e($_SESSION['lang'] ?? 'en') ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AgriTrack — <?= t('product_report') ?></title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
        <style>@media print { .no-print { display: none !important; } }</style>
    </head>
    <body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <h1 class="h3 text-success">AgriTrack — <?= t('product_report') ?></h1>
            <div>
                <button onclick="window.print()" class="btn btn-success me-2"><i class="bi bi-printer me-1"></i><?= t('print') ?></button>
                <a href="export.php" class="btn btn-outline-secondary"><?= t('back') ?></a>
            </div>
        </div>
        <div class="alert alert-info no-print"><?= t('pdf_needs_composer') ?></div>
        <h1 class="h3 text-success d-none d-print-block">AgriTrack — <?= t('product_report') ?></h1>
        <p class="text-muted"><?= e($_SESSION['name'] ?? '') ?> · <?= date('Y-m-d H:i') ?></p>
        <?= $tableHtml ?>
    </div>
    </body></html>
    <?php
    exit;
}

// ─── Export page ──────────────────────────────────────────────────────────────
$pageTitle       = t('export_data');
$dompdfAvailable = is_file(__DIR__ . '/../vendor/dompdf/dompdf/src/Dompdf.php');

$stmt = $db->prepare('SELECT COUNT(*) FROM crops WHERE user_id = ?');
$stmt->execute([$uid]);
$totalCrops = (int) $stmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <h1 class="h3 fw-bold mb-4 text-success"><i class="bi bi-download me-2"></i><?= t('export_data') ?></h1>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <p class="text-muted"><?= sprintf(t('export_count'), $totalCrops) ?></p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-4 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="bi bi-filetype-csv display-4 text-success mb-3"></i>
                                <h2 class="h6 fw-bold"><?= t('download_csv') ?></h2>
                                <p class="text-muted small mb-3"><?= t('csv_hint') ?></p>
                                <a href="export.php?format=csv" class="btn btn-success w-100"><i class="bi bi-download me-1"></i><?= t('download_csv') ?></a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-4 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="bi bi-filetype-pdf display-4 text-danger mb-3"></i>
                                <h2 class="h6 fw-bold"><?= t('download_pdf') ?></h2>
                                <p class="text-muted small mb-3"><?= $dompdfAvailable ? t('pdf_hint') : t('pdf_needs_composer') ?></p>
                                <a href="export.php?format=pdf" class="btn <?= $dompdfAvailable ? 'btn-danger' : 'btn-outline-danger' ?> w-100">
                                    <i class="bi <?= $dompdfAvailable ? 'bi-download' : 'bi-printer' ?> me-1"></i><?= $dompdfAvailable ? t('download_pdf') : t('print_report') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
