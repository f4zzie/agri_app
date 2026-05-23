<?php
/**
 * AgriTrack – index.php
 * Public landing page. Redirects logged-in users to the dashboard.
 */
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$currentLang = $_SESSION['lang'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AgriTrack – Manage your crops, track harvests, and get disease alerts all in one place.">
    <title><?= t('app_name') ?> – Agricultural Crop Tracker</title>

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card card shadow p-4 text-center">

        <!-- Language Switcher -->
        <div class="d-flex justify-content-end mb-2 gap-1">
            <a href="?lang=en"
               class="btn btn-sm <?= $currentLang === 'en' ? 'btn-success' : 'btn-outline-success' ?>">
                <?= t('english') ?>
            </a>
            <a href="?lang=sw"
               class="btn btn-sm <?= $currentLang === 'sw' ? 'btn-success' : 'btn-outline-success' ?>">
                <?= t('swahili') ?>
            </a>
        </div>

        <!-- Logo / Title -->
        <div class="mb-3">
            <span style="font-size: 3rem;">🌿</span>
            <h1 class="h2 fw-bold text-success mt-1"><?= t('app_name') ?></h1>
            <p class="text-muted mb-0">Agricultural Crop Tracking &amp; Management</p>
        </div>

        <hr>

        <!-- Description -->
        <p class="text-secondary small mb-4">
            Track your crops, monitor growth stages, detect diseases early,
            and receive harvest reminders – all in one simple platform.
        </p>

        <!-- Action Buttons -->
        <div class="d-grid gap-2">
            <a href="login.php"
               class="btn btn-success btn-lg"
               id="btnLogin">
                <i class="bi bi-box-arrow-in-right me-2"></i><?= t('login') ?>
            </a>
            <a href="register.php"
               class="btn btn-outline-success btn-lg"
               id="btnRegister">
                <i class="bi bi-person-plus me-2"></i><?= t('register') ?>
            </a>
        </div>

        <p class="text-muted mt-4 mb-0" style="font-size:0.8rem;">
            SCO 207 Academic Project
        </p>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
