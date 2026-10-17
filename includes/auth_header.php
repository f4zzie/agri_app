<?php
/**
 * Layout top for signed-out pages (login, register, password reset…).
 * Expects $pageTitle.
 */
$currentLang = $_SESSION['lang'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e(t('app_name') . ' – ' . t('app_tagline')) ?>">
    <title><?= e($pageTitle ?? t('app_name')) ?> | <?= t('app_name') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <link rel="manifest" href="manifest.webmanifest">
    <meta name="theme-color" content="#198754">
    <link rel="icon" href="assets/favicon-32.png" sizes="32x32">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
</head>
<body>

<main class="auth-wrapper">
    <div class="auth-card card shadow p-4">

        <div class="d-flex justify-content-end mb-2 gap-1">
            <a href="<?= lang_url('en') ?>" class="btn btn-sm <?= $currentLang === 'en' ? 'btn-success' : 'btn-outline-success' ?>"><?= t('english') ?></a>
            <a href="<?= lang_url('sw') ?>" class="btn btn-sm <?= $currentLang === 'sw' ? 'btn-success' : 'btn-outline-success' ?>"><?= t('swahili') ?></a>
        </div>

        <div class="text-center mb-3">
            <a href="index.php" class="text-decoration-none">
                <span style="font-size:2rem;" aria-hidden="true">🌿</span>
                <span class="d-block h4 fw-bold text-success mb-0"><?= t('app_name') ?></span>
            </a>
            <h1 class="h6 text-muted mt-1"><?= e($pageTitle ?? '') ?></h1>
        </div>

        <?php foreach (['success', 'danger', 'warning', 'info'] as $fType):
            if ($fMsg = flash($fType)): ?>
            <div class="alert alert-<?= $fType ?> alert-dismissible fade show flash-alert" role="alert">
                <?= e($fMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= t('close') ?>"></button>
            </div>
        <?php endif; endforeach; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>
