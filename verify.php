<?php
/**
 * AgriTrack – verify.php
 * Handles email address verification via token link.
 */
require_once __DIR__ . '/config.php';

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    flash('danger', 'No verification token provided.');
    header('Location: login.php');
    exit;
}

$db = getDB();

// Find a user matching this token who has not yet verified their email
$stmt = $db->prepare(
    'SELECT * FROM users
      WHERE verification_token = :token
        AND email_verified = 0
      LIMIT 1'
);
$stmt->execute([':token' => $token]);
$user = $stmt->fetch();

if (!$user) {
    flash('danger', 'Invalid or already-used verification link.');
    header('Location: login.php');
    exit;
}

// Mark email as verified and clear the token
$update = $db->prepare(
    'UPDATE users
        SET email_verified = 1,
            verification_token = NULL
      WHERE id = :id'
);
$update->execute([':id' => $user['id']]);

log_activity((int) $user['id'], 'email_verified', 'Email address verified');

flash('success', t('email_verified'));
header('Location: login.php');
exit;
