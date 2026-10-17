<?php
/**
 * AgriTrack – Auth guard
 * Include this at the top of every protected page.
 */
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

/**
 * Restrict a page to one role. Links are hidden in the sidebar, but the
 * server must enforce it too, otherwise a buyer could open add_crop.php.
 */
function require_role(string $role): void
{
    if (($_SESSION['role'] ?? '') !== $role) {
        flash('danger', t('access_denied_role'));
        header('Location: dashboard.php');
        exit;
    }
}
