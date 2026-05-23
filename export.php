<?php
require_once 'includes/auth_check.php';
require_once 'config.php';

$db  = getDB();
$uid = $_SESSION['user_id'];

// ─── CSV Download ─────────────────────────────────────────────────────────────
if (isset($_GET['format']) && $_GET['format'] === 'csv') {
    $stmt = $db->prepare("SELECT id, name, variety, planting_date, expected_harvest, status, notes, created_at FROM crops WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    $crops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="agritrack_crops_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fputs($out, "\xEF\xBB\xBF");

    fputcsv($out, ['ID', 'Crop Name', 'Variety', 'Planting Date', 'Expected Harvest', 'Status', 'Notes', 'Created At']);
    foreach ($crops as $crop) {
        fputcsv($out, [
            $crop['id'],
            $crop['name'],
            $crop['variety'] ?? '',
            $crop['planting_date'] ?? '',
            $crop['expected_harvest'] ?? '',
            $crop['status'],
            $crop['notes'] ?? '',
            $crop['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// ─── PDF Download ─────────────────────────────────────────────────────────────
if (isset($_GET['format']) && $_GET['format'] === 'pdf') {
    $stmt = $db->prepare("SELECT * FROM crops WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    $crops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dompdfAvailable = file_exists(__DIR__ . '/vendor/autoload.php')
        && class_exists('Dompdf\Dompdf', false)
        || (file_exists(__DIR__ . '/vendor/autoload.php') && include_once(__DIR__ . '/vendor/autoload.php') && class_exists('Dompdf\Dompdf'));

    if ($dompdfAvailable) {
        use Dompdf\Dompdf;
        use Dompdf\Options;

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
            <style>
                body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
                h1 { color: #28a745; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background-color: #28a745; color: white; padding: 8px; text-align: left; }
                td { padding: 7px 8px; border-bottom: 1px solid #ddd; }
                tr:nth-child(even) td { background-color: #f9f9f9; }
                .footer { margin-top: 30px; font-size: 10px; color: #888; text-align: center; }
            </style></head><body>';
        $html .= '<h1>AgriTrack — Crop Report</h1>';
        $html .= '<p>Generated: ' . date('Y-m-d H:i') . '</p>';
        $html .= '<table><thead><tr>
            <th>#</th><th>Name</th><th>Variety</th><th>Status</th>
            <th>Planting Date</th><th>Expected Harvest</th>
        </tr></thead><tbody>';
        $i = 1;
        foreach ($crops as $crop) {
            $html .= '<tr>';
            $html .= '<td>' . $i++ . '</td>';
            $html .= '<td>' . htmlspecialchars($crop['name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($crop['variety'] ?? '—') . '</td>';
            $html .= '<td>' . htmlspecialchars(ucfirst($crop['status'])) . '</td>';
            $html .= '<td>' . htmlspecialchars($crop['planting_date'] ?? '—') . '</td>';
            $html .= '<td>' . htmlspecialchars($crop['expected_harvest'] ?? '—') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table><div class="footer">Powered by AgriTrack — Agricultural Crop Management System</div></body></html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('agritrack_crops_' . date('Y-m-d') . '.pdf', ['Attachment' => true]);
        exit;
    } else {
        // Print-friendly fallback
        $stmt = $db->prepare("SELECT * FROM crops WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$uid]);
        $crops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>AgriTrack — Crop Report</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
            <style>
                @media print { .no-print { display: none !important; } body { padding: 20px; } }
                body { font-family: 'Segoe UI', sans-serif; }
            </style>
        </head>
        <body>
        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <h2 class="text-success">AgriTrack — Crop Report</h2>
                <div>
                    <button onclick="window.print()" class="btn btn-success me-2">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                    <a href="export.php" class="btn btn-outline-secondary">Back</a>
                </div>
            </div>
            <div class="alert alert-info no-print">
                <strong>Note:</strong> Run <code>composer install</code> to enable PDF download. Showing print-friendly page instead.
            </div>
            <h2 class="text-success d-none d-print-block">AgriTrack — Crop Report</h2>
            <p class="text-muted">Generated: <?= date('Y-m-d H:i') ?></p>
            <table class="table table-bordered">
                <thead class="table-success">
                    <tr>
                        <th>#</th><th>Name</th><th>Variety</th><th>Status</th>
                        <th>Planting Date</th><th>Expected Harvest</th><th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($crops as $crop): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($crop['name']) ?></td>
                        <td><?= htmlspecialchars($crop['variety'] ?? '—') ?></td>
                        <td><?= htmlspecialchars(ucfirst($crop['status'])) ?></td>
                        <td><?= htmlspecialchars($crop['planting_date'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($crop['expected_harvest'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($crop['notes'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
        </body></html>
        <?php
        exit;
    }
}

// ─── Export Page (HTML) ───────────────────────────────────────────────────────
$pageTitle = 'Export Data';
require_once 'includes/header.php';

$dompdfAvailable = file_exists(__DIR__ . '/vendor/autoload.php');

$stmt = $db->prepare("SELECT COUNT(*) FROM crops WHERE user_id = ?");
$stmt->execute([$uid]);
$totalCrops = $stmt->fetchColumn();
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <h1 class="h3 fw-bold mb-4 text-success">
                <i class="bi bi-download me-2"></i>Export Data
            </h1>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-database me-2 text-success"></i>Your Crop Data
                    </h5>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted">
                        You have <strong><?= $totalCrops ?></strong> crop(s) in your records. Export them using one of the options below.
                    </p>

                    <div class="row g-3">
                        <!-- CSV -->
                        <div class="col-md-6">
                            <div class="border rounded p-4 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="bi bi-filetype-csv display-4 text-success mb-3"></i>
                                <h6 class="fw-bold">Download CSV</h6>
                                <p class="text-muted small mb-3">Opens in Excel, Google Sheets, or any spreadsheet app.</p>
                                <a href="export.php?format=csv" class="btn btn-success w-100" id="csvDownloadBtn">
                                    <i class="bi bi-download me-1"></i> Download CSV
                                </a>
                            </div>
                        </div>

                        <!-- PDF -->
                        <div class="col-md-6">
                            <div class="border rounded p-4 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="bi bi-filetype-pdf display-4 text-danger mb-3"></i>
                                <h6 class="fw-bold">Download PDF</h6>
                                <?php if ($dompdfAvailable): ?>
                                    <p class="text-muted small mb-3">Nicely formatted PDF report of all your crops.</p>
                                    <a href="export.php?format=pdf" class="btn btn-danger w-100" id="pdfDownloadBtn">
                                        <i class="bi bi-download me-1"></i> Download PDF
                                    </a>
                                <?php else: ?>
                                    <p class="text-muted small mb-3">Print-friendly page available. Run <code>composer install</code> to enable PDF download.</p>
                                    <a href="export.php?format=pdf" class="btn btn-outline-danger w-100" id="pdfPrintBtn">
                                        <i class="bi bi-printer me-1"></i> Print / View Report
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!$dompdfAvailable): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Enable PDF generation:</strong> Run <code>composer install</code> in your project directory to install dompdf.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
