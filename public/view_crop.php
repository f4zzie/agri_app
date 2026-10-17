<?php
/**
 * AgriTrack – view_crop.php
 * Public product page (the QR code on a farmer's packaging links here).
 * Shows listing details only — no farmer contact data for anonymous visitors.
 */
require_once __DIR__ . '/../config.php';

$cropId = (int) ($_GET['id'] ?? 0);

$stmt = getDB()->prepare('SELECT * FROM v_marketplace WHERE id = ?');
$stmt->execute([$cropId]);
$crop = $stmt->fetch();

if (!$crop) {
    http_response_code(404);
}

$img         = $crop ? upload_url($crop['image']) : null;
$currentLang = $_SESSION['lang'] ?? 'en';
$isBuyer     = isLoggedIn() && ($_SESSION['role'] ?? '') === 'buyer';

$row = static function (string $icon, string $label, string $valueHtml): void { ?>
    <div class="detail-row">
        <span class="detail-label"><i class="bi <?= $icon ?> me-2 text-muted"></i><?= e($label) ?></span>
        <span><?= $valueHtml ?></span>
    </div>
<?php };
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $crop ? e($crop['name']) : t('product_not_found') ?> — <?= t('app_name') ?></title>
    <?php if ($crop): ?>
        <meta name="description" content="<?= e($crop['name'] . ' — ' . ($crop['farmer_county'] ?? 'Kenya')) ?> on AgriTrack.">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <link rel="manifest" href="manifest.webmanifest">
    <meta name="theme-color" content="#198754">
    <link rel="icon" href="assets/favicon-32.png" sizes="32x32">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">

            <div class="d-flex justify-content-end gap-1 mb-2">
                <a href="<?= lang_url('en') ?>" class="btn btn-sm <?= $currentLang === 'en' ? 'btn-success' : 'btn-outline-success' ?>"><?= t('english') ?></a>
                <a href="<?= lang_url('sw') ?>" class="btn btn-sm <?= $currentLang === 'sw' ? 'btn-success' : 'btn-outline-success' ?>"><?= t('swahili') ?></a>
            </div>

            <?php if (!$crop): ?>
                <div class="alert alert-warning"><?= t('product_not_found') ?></div>
            <?php else: ?>
            <div class="card border-0 shadow">
                <div class="card-header crop-header py-4 rounded-top">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <h1 class="h3 fw-bold mb-1"><i class="bi bi-basket me-2"></i><?= e($crop['name']) ?></h1>
                            <?php if (!empty($crop['variety'])): ?>
                                <p class="mb-0 opacity-75"><?= t('variety') ?>: <?= e($crop['variety']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="badge bg-white text-success fs-6 px-3 py-2"><?= format_price($crop['price'], $crop['unit']) ?></span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <?php if ($img): ?>
                        <img src="<?= e($img) ?>" alt="<?= e($crop['name']) ?>" class="crop-image mb-4">
                    <?php endif; ?>

                    <?php
                    $row('bi-shop', t('availability'), status_badge($crop['status']) . ' ' . stage_badge($crop['growth_stage']));
                    $row('bi-person', t('farmer'), e($crop['farmer_name']) . ($crop['farmer_county'] ? ' · ' . e($crop['farmer_county']) : ''));
                    if ($crop['category']) {
                        $row('bi-tag', t('category'), e(t_category($crop['category'])));
                    }
                    if ($crop['quantity'] !== null) {
                        $row('bi-box-seam', t('quantity_available'), (int) $crop['quantity'] . ' ' . e(t('unit_' . $crop['unit'])));
                    }
                    if ($crop['planting_date']) {
                        $row('bi-calendar-event', t('planting_date'), e(date('j M Y', strtotime($crop['planting_date']))));
                    }
                    if ($crop['expected_harvest']) {
                        $row('bi-calendar-check', t('expected_harvest'), e(date('j M Y', strtotime($crop['expected_harvest']))));
                    }
                    if ($crop['notes']) {
                        $row('bi-card-text', t('description'), nl2br(e($crop['notes'])));
                    }
                    $row('bi-clock', t('listed_on'), e(date('j M Y', strtotime($crop['created_at']))));
                    ?>

                    <div class="mt-4 d-grid">
                        <?php if ($isBuyer && $crop['status'] === 'available'): ?>
                            <a href="inquiry.php?crop_id=<?= (int) $crop['id'] ?>" class="btn btn-success"><i class="bi bi-chat me-1"></i><?= t('contact_farmer') ?></a>
                        <?php elseif (!isLoggedIn()): ?>
                            <a href="register.php" class="btn btn-outline-success"><?= t('register_to_contact') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="powered-footer">
                <i class="bi bi-flower1 me-1"></i><?= t('powered_by') ?>
            </div>

        </div>
    </div>
</div>

<script>if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js');</script>
</body>
</html>
