<?php
/**
 * AgriTrack – logout.php
 * POST only (with CSRF token), so another site can't log users out with a link.
 */
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (isLoggedIn() ? 'dashboard.php' : 'login.php'));
    exit;
}
csrf_verify();

if (isLoggedIn()) {
    log_activity((int) $_SESSION['user_id'], 'logout', 'User logged out');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'],
        'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'],
    ]);
}
session_destroy();

header('Location: login.php');
exit;
