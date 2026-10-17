<?php
/**
 * AgriTrack – layout top for signed-in pages (navbar + sidebar).
 * Set $pageTitle before including.
 */
require_once __DIR__ . '/../config.php';

$root        = '';
$currentLang = $_SESSION['lang'] ?? 'en';
$userName    = $_SESSION['name'] ?? 'User';
$userRole    = $_SESSION['role'] ?? 'farmer';
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

/** Sidebar entry: [file, icon, label key]. */
$nav = [
    ['dashboard.php',    'bi-speedometer2',  'dashboard'],
    ['marketplace.php',  'bi-shop',          'marketplace'],
    ['price_trends.php', 'bi-graph-up',      'price_trends'],
    ['my_inquiries.php', 'bi-chat-dots',     'my_inquiries'],
];
if ($userRole === 'farmer') {
    $nav[] = ['add_crop.php',       'bi-plus-circle',       'add_crop'];
    $nav[] = ['send_reminders.php', 'bi-bell',              'send_reminders'];
    $nav[] = ['export.php',         'bi-file-earmark-arrow-down', 'export_pdf'];
    $nav[] = ['disease_check.php',  'bi-bug',               'disease_check'];
}
$nav[] = ['activity_log.php', 'bi-clock-history', 'activity_log'];

$activeFor = ['edit_crop.php' => 'dashboard.php', 'qrcode.php' => 'dashboard.php',
              'inquiry_thread.php' => 'my_inquiries.php', 'inquiry.php' => 'marketplace.php',
              'farmer.php' => 'marketplace.php'];
$activePage = $activeFor[$currentPage] ?? $currentPage;
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
    <link rel="stylesheet" href="<?= $root ?>style.css">
    <link rel="manifest" href="manifest.webmanifest">
    <meta name="theme-color" content="#198754">
    <link rel="icon" href="assets/favicon-32.png" sizes="32x32">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
</head>
<body class="bg-light">
<a class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-light" href="#main"><?= t('skip_to_content') ?></a>

<nav class="navbar navbar-dark bg-success px-3 app-navbar">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-light btn-sm d-lg-none me-1" id="sidebarToggle" type="button"
                aria-controls="sidebar" aria-expanded="false" aria-label="<?= t('toggle_sidebar') ?>">
            <i class="bi bi-list fs-5"></i>
        </button>
        <a class="navbar-brand fw-bold" href="<?= $root ?>dashboard.php">🌿 <?= t('app_name') ?></a>
    </div>

    <div class="ms-auto d-flex align-items-center gap-2 gap-md-3">
        <div class="btn-group" role="group" aria-label="<?= t('language') ?>">
            <a href="<?= lang_url('en') ?>" class="btn btn-sm <?= $currentLang === 'en' ? 'btn-light' : 'btn-outline-light' ?>" lang="en">EN</a>
            <a href="<?= lang_url('sw') ?>" class="btn btn-sm <?= $currentLang === 'sw' ? 'btn-light' : 'btn-outline-light' ?>" lang="sw">SW</a>
        </div>

        <a href="<?= $root ?>profile.php" class="text-white small d-none d-md-inline text-decoration-none">
            <i class="bi bi-person-circle me-1"></i><?= e($userName) ?>
        </a>

        <form method="POST" action="<?= $root ?>logout.php" class="m-0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <button type="submit" class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-right"></i><span class="d-none d-sm-inline ms-1"><?= t('logout') ?></span>
            </button>
        </form>
    </div>
</nav>

<div class="d-flex app-body">
    <nav id="sidebar" class="bg-dark text-white flex-shrink-0" aria-label="<?= t('main_menu') ?>">
        <ul class="nav flex-column pt-3">
            <?php foreach ($nav as [$file, $icon, $label]): ?>
                <li class="nav-item">
                    <a class="nav-link text-white sidebar-link <?= $activePage === $file ? 'active' : '' ?>"
                       href="<?= $root . $file ?>" <?= $activePage === $file ? 'aria-current="page"' : '' ?>>
                        <i class="bi <?= $icon ?> me-2"></i><?= t($label) ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <li class="nav-item mt-2 border-top border-secondary pt-2">
                <a class="nav-link text-white sidebar-link <?= $activePage === 'profile.php' ? 'active' : '' ?>" href="<?= $root ?>profile.php">
                    <i class="bi bi-person me-2"></i><?= t('profile') ?>
                </a>
            </li>
            <li class="nav-item px-3 mt-3">
                <span class="badge <?= $userRole === 'farmer' ? 'bg-success' : 'bg-primary' ?> w-100 py-2">
                    <?= $userRole === 'farmer' ? '🌾 ' . t('role_farmer') : '🏪 ' . t('role_buyer') ?>
                </span>
            </li>
        </ul>
    </nav>

    <main id="main" class="flex-grow-1 p-3 p-md-4 min-w-0">
<?php foreach (['success', 'danger', 'warning', 'info'] as $fType):
    if (($fMsg = flash($fType)) !== null): ?>
        <div class="alert alert-<?= $fType ?> alert-dismissible fade show flash-alert" role="alert">
            <?= e($fMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= t('close') ?>"></button>
        </div>
<?php endif; endforeach; ?>
