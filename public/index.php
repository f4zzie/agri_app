<?php
/**
 * AgriTrack – index.php
 * Public landing page. Signed-in users go straight to their dashboard.
 */
require_once __DIR__ . '/../config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$stats = getDB()->query(
    "SELECT (SELECT COUNT(*) FROM users WHERE role = 'farmer')            AS farmers,
            (SELECT COUNT(*) FROM users WHERE role = 'buyer')             AS buyers,
            (SELECT COUNT(*) FROM crops WHERE status = 'available')       AS listings,
            (SELECT COUNT(DISTINCT county_id) FROM users WHERE role = 'farmer') AS counties"
)->fetch();

$currentLang = $_SESSION['lang'] ?? 'en';
$demo        = ($_ENV['DEMO_MODE'] ?? '') === '1';
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e(t('landing_lead')) ?>">
    <title><?= t('app_name') ?> – <?= e(t('app_tagline')) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="landing-hero text-white">
    <nav class="container d-flex justify-content-between align-items-center py-3">
        <span class="fw-bold fs-4">🌿 <?= t('app_name') ?></span>
        <div class="d-flex gap-2 align-items-center">
            <div class="btn-group" role="group" aria-label="<?= t('language') ?>">
                <a href="<?= lang_url('en') ?>" class="btn btn-sm <?= $currentLang === 'en' ? 'btn-light' : 'btn-outline-light' ?>" lang="en">EN</a>
                <a href="<?= lang_url('sw') ?>" class="btn btn-sm <?= $currentLang === 'sw' ? 'btn-light' : 'btn-outline-light' ?>" lang="sw">SW</a>
            </div>
            <a href="login.php" class="btn btn-sm btn-light"><?= t('login') ?></a>
        </div>
    </nav>
    <div class="container py-5">
        <div class="col-lg-7">
            <h1 class="display-5 fw-bold"><?= t('landing_title') ?></h1>
            <p class="lead opacity-75 my-4"><?= t('landing_lead') ?></p>
            <div class="d-flex gap-2 flex-wrap">
                <a href="register.php" class="btn btn-light btn-lg text-success fw-semibold"><i class="bi bi-person-plus me-1"></i><?= t('get_started') ?></a>
                <a href="login.php" class="btn btn-outline-light btn-lg"><i class="bi bi-box-arrow-in-right me-1"></i><?= t('login') ?></a>
            </div>
            <?php if ($demo): ?>
                <p class="small mt-3 mb-0 opacity-75">
                    <i class="bi bi-lightning-charge me-1"></i><?= t('try_demo') ?>
                    <code class="text-white">farmer@agritrack.test</code> / <code class="text-white">buyer@agritrack.test</code>,
                    <?= t('password') ?> <code class="text-white">Demo@1234</code>
                </p>
            <?php endif; ?>
        </div>
    </div>
</header>

<main>
    <section class="container py-4">
        <div class="row g-3 text-center">
            <?php foreach (['farmers' => 'stat_farmers', 'listings' => 'stat_listings', 'counties' => 'stat_counties', 'buyers' => 'stat_buyers'] as $k => $label): ?>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-white rounded shadow-sm">
                        <div class="fs-2 fw-bold text-success"><?= (int) $stats[$k] ?></div>
                        <div class="small text-muted"><?= t($label) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="container py-4">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h4 fw-bold">🌾 <?= t('for_farmers') ?></h2>
                        <ul class="list-unstyled mt-3 mb-0">
                            <?php foreach (['feat_f1', 'feat_f2', 'feat_f3', 'feat_f4'] as $f): ?>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><?= t($f) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h4 fw-bold">🏪 <?= t('for_buyers') ?></h2>
                        <ul class="list-unstyled mt-3 mb-0">
                            <?php foreach (['feat_b1', 'feat_b2', 'feat_b3', 'feat_b4'] as $f): ?>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i><?= t($f) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="container text-center text-muted small py-4">
    <?= t('powered_by') ?> · SCO 207
</footer>

</body>
</html>
