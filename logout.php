<?php
/**
 * AgriTrack – logout.php
 * Logs activity, destroys the session, and redirects to login.
 */
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    log_activity((int) $_SESSION['user_id'], 'logout', 'User logged out');
}

// Clear session data and destroy the session
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: login.php');
exit;
