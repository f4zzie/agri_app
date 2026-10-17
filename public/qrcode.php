<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role('farmer');

$db     = getDB();
$uid    = (int) $_SESSION['user_id'];
$cropId = (int) ($_GET['crop_id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM crops WHERE id = ? AND user_id = ?');
$stmt->execute([$cropId, $uid]);
$crop = $stmt->fetch();

if (!$crop) {
    flash('danger', t('product_not_found'));
    header('Location: dashboard.php');
    exit;
}

$viewUrl   = rtrim($_ENV['APP_URL'] ?? 'http://localhost/agri_app', '/') . '/view_crop.php?id=' . $crop['id'];
$isLocal   = (bool) preg_match('#^https?://(localhost|127\.|192\.168\.|10\.)#', $viewUrl);
$pageTitle = t('qr_code');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-2">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">

            <nav aria-label="breadcrumb" class="mb-3 no-print">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-success"><?= t('dashboard') ?></a></li>
                    <li class="breadcrumb-item active"><?= t('qr_code') ?></li>
                </ol>
            </nav>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 no-print">
                    <h1 class="h4 mb-0 fw-bold"><i class="bi bi-qr-code me-2 text-success"></i><?= t('qr_code') ?> — <?= e($crop['name']) ?></h1>
                </div>
                <div class="card-body p-4 text-center">
                    <p class="text-muted mb-4 no-print"><?= t('qr_intro') ?></p>

                    <!-- Printable label -->
                    <div class="qr-label d-inline-block border rounded p-3 bg-white shadow-sm mb-3">
                        <div id="qr" class="mx-auto" style="width:200px;height:200px;" role="img" aria-label="<?= e(t('qr_code') . ': ' . $crop['name']) ?>"></div>
                        <div class="fw-bold mt-2"><?= e($crop['name']) ?><?= $crop['variety'] ? ' · ' . e($crop['variety']) : '' ?></div>
                        <div class="small text-success"><?= format_price($crop['price'], $crop['unit']) ?></div>
                        <div class="small text-muted"><?= e($_SESSION['name']) ?> · AgriTrack</div>
                    </div>

                    <?php if ($isLocal): ?>
                        <div class="alert alert-warning small text-start no-print"><?= t('qr_local_warning') ?></div>
                    <?php endif; ?>

                    <div class="mb-4 no-print">
                        <label for="viewUrlInput" class="form-label fw-semibold text-muted small"><?= t('qr_links_to') ?></label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm bg-light" id="viewUrlInput" value="<?= e($viewUrl) ?>" readonly>
                            <button class="btn btn-outline-secondary btn-sm" type="button" id="copyBtn" title="<?= t('copy') ?>" aria-label="<?= t('copy') ?>"><i class="bi bi-clipboard"></i></button>
                        </div>
                        <a href="<?= e($viewUrl) ?>" target="_blank" rel="noopener" class="small text-success"><i class="bi bi-box-arrow-up-right me-1"></i><?= t('open_product_page') ?></a>
                    </div>

                    <div class="d-flex gap-2 justify-content-center flex-wrap no-print">
                        <a href="#" class="btn btn-success" id="downloadQrBtn" download="qr_<?= e(preg_replace('/[^a-z0-9]+/i', '_', $crop['name'])) ?>.gif">
                            <i class="bi bi-download me-1"></i><?= t('download_qr') ?>
                        </a>
                        <button type="button" class="btn btn-outline-success" onclick="window.print()"><i class="bi bi-printer me-1"></i><?= t('print_label') ?></button>
                        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= t('back') ?></a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- QR generated in the browser: the product URL is never sent to a third-party service -->
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>
(function () {
    const url = <?= json_encode($viewUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
    const qr = qrcode(0, 'M');
    qr.addData(url);
    qr.make();
    document.getElementById('qr').innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
    document.getElementById('downloadQrBtn').href = qr.createDataURL(8, 4);

    document.getElementById('copyBtn').addEventListener('click', function () {
        const btn = this;
        navigator.clipboard.writeText(url).then(function () {
            btn.innerHTML = '<i class="bi bi-check text-success"></i>';
            setTimeout(function () { btn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 2000);
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
