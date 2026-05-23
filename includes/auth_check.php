<?php
/**
 * AgriTrack – Auth guard
 * Include this at the top of every protected page.
 */
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    header('Location: ' . ($_ENV['APP_URL'] ?? '') . '/login.php');
    exit;
}
