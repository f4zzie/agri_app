<?php
/**
 * AgriTrack – Main header include (for protected pages with sidebar)
 *
 * Variables expected to be set before including this file:
 *   $pageTitle (string) – page title shown in <title> and page heading
 */
require_once __DIR__ . '/../config.php';

// $root is relative path back to agri_app root from any page.
// Since all pages live at the root of agri_app, this is empty string.
$root = '';

$currentLang = $_SESSION['lang'] ?? 'en';
$userName    = htmlspecialchars($_SESSION['name'] ?? 'User');
$userRole    = $_SESSION['role'] ?? 'farmer';
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AgriTrack – Agricultural Crop Tracking and Management System">
    <title><?= htmlspecialchars($pageTitle ?? t('app_name')) ?> | <?= t('app_name') ?></title>

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $root ?>style.css">
</head>
<body class="bg-light">

<!-- ============================================================
     TOP NAVBAR
     ============================================================ -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success px-3" style="height:56px;">
    <div class="d-flex align-items-center gap-2">
        <!-- Hamburger for mobile sidebar toggle -->
        <button class="btn btn-outline-light btn-sm d-lg-none me-2"
                id="sidebarToggle"
                aria-label="Toggle sidebar">
            <i class="bi bi-list fs-5"></i>
        </button>

        <a class="navbar-brand fw-bold" href="<?= $root ?>dashboard.php">
            🌿 <?= t('app_name') ?>
        </a>
    </div>

    <div class="ms-auto d-flex align-items-center gap-3">
        <!-- Language switcher -->
        <div class="d-flex gap-1">
            <a href="?lang=en"
               class="btn btn-sm <?= $currentLang === 'en' ? 'btn-light' : 'btn-outline-light' ?>">
                <?= t('english') ?>
            </a>
            <a href="?lang=sw"
               class="btn btn-sm <?= $currentLang === 'sw' ? 'btn-light' : 'btn-outline-light' ?>">
                <?= t('swahili') ?>
            </a>
        </div>

        <!-- User name -->
        <span class="text-white small d-none d-md-inline">
            <i class="bi bi-person-circle me-1"></i><?= $userName ?>
        </span>

        <!-- Logout -->
        <a href="<?= $root ?>logout.php"
           class="btn btn-outline-light btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i><?= t('logout') ?>
        </a>
    </div>
</nav>

<!-- ============================================================
     BODY WRAPPER  (sidebar + main)
     ============================================================ -->
<div class="d-flex" style="min-height: calc(100vh - 56px);">

    <!-- ---- SIDEBAR ---- -->
    <nav id="sidebar"
         class="bg-dark text-white flex-shrink-0"
         style="width:220px; min-height:100%;">
        <ul class="nav flex-column pt-3">
            <!-- Common: Dashboard -->
            <li class="nav-item">
                <a class="nav-link text-white sidebar-link" href="<?= $root ?>dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i><?= t('dashboard') ?>
                </a>
            </li>
            <!-- Common: Marketplace -->
            <li class="nav-item">
                <a class="nav-link text-white sidebar-link" href="<?= $root ?>marketplace.php">
                    <i class="bi bi-shop me-2"></i>Marketplace
                </a>
            </li>
            <!-- Common: My Inquiries -->
            <li class="nav-item">
                <a class="nav-link text-white sidebar-link" href="<?= $root ?>my_inquiries.php">
                    <i class="bi bi-chat-dots me-2"></i>My Inquiries
                </a>
            </li>
            <?php if ($userRole === 'farmer'): ?>
            <!-- Farmer-only links -->
            <li class="nav-item">
                <a class="nav-link text-white sidebar-link" href="<?= $root ?>add_crop.php">
                    <i class="bi bi-plus-circle me-2"></i><?= t('add_crop') ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white sidebar-link" href="<?= $root ?>disease_check.php">
                    <i class="bi bi-bug me-2"></i><?= t('disease_check') ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white sidebar-link" href="<?= $root ?>export.php">
                    <i class="bi bi-file-earmark-pdf me-2"></i><?= t('export_pdf') ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white sidebar-link" href="<?= $root ?>activity_log.php">
                    <i class="bi bi-clock-history me-2"></i><?= t('activity_log') ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white sidebar-link" href="<?= $root ?>send_reminders.php">
                    <i class="bi bi-bell me-2"></i><?= t('send_reminders') ?>
                </a>
            </li>
            <?php endif; ?>
            <!-- Common: Profile -->
            <li class="nav-item mt-2 border-top border-secondary pt-2">
                <a class="nav-link text-white sidebar-link" href="<?= $root ?>profile.php">
                    <i class="bi bi-person me-2"></i><?= t('profile') ?>
                </a>
            </li>
            <!-- Role badge at bottom -->
            <li class="nav-item px-3 mt-3">
                <span class="badge <?= $userRole === 'farmer' ? 'bg-success' : 'bg-primary' ?> w-100 py-2">
                    <?= $userRole === 'farmer' ? '🌾 Farmer' : '🏪 Business Owner' ?>
                </span>
            </li>
        </ul>
    </nav>

    <!-- ---- MAIN CONTENT ---- -->
    <main class="flex-grow-1 p-4">
<?php
// Display any flash messages right after opening <main>
$flashTypes = ['success', 'danger', 'warning', 'info'];
foreach ($flashTypes as $fType) {
    $fMsg = flash($fType);
    if ($fMsg !== null): ?>
        <div class="alert alert-<?= $fType ?> alert-dismissible fade show flash-alert"
             role="alert">
            <?= htmlspecialchars($fMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif;
}
?>
