<?php
/**
 * AgriTrack – verify.php
 * Confirms an email address from the link sent at registration.
 */
require_once __DIR__ . '/../config.php';

$token = trim((string) ($_GET['token'] ?? ''));
$user  = null;

if (App\Token::looksValid($token)) {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT id FROM users WHERE verification_token_hash = ? AND email_verified = 0 LIMIT 1'
    );
    $stmt->execute([App\Token::hash($token)]);
    $user = $stmt->fetch();
}

if (!$user) {
    flash('danger', t('verify_link_invalid'));
    header('Location: login.php');
    exit;
}

$db->prepare('UPDATE users SET email_verified = 1, verification_token_hash = NULL WHERE id = ?')
   ->execute([$user['id']]);
log_activity((int) $user['id'], 'email_verified', 'Email address verified');

flash('success', t('email_verified'));
header('Location: login.php');
exit;
