<?php
/**
 * AgriTrack – reset_password.php
 * Validates a reset token (by its hash) and lets the user set a new password.
 */
require_once __DIR__ . '/../config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$token  = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

$resetRecord = null;
if (App\Token::looksValid($token)) {
    $stmt = getDB()->prepare(
        'SELECT * FROM password_resets
          WHERE token_hash = ? AND used = 0 AND expires_at > NOW()
          LIMIT 1'
    );
    $stmt->execute([App\Token::hash($token)]);
    $resetRecord = $stmt->fetch() ?: null;
}

if (!$resetRecord) {
    flash('danger', t('reset_link_invalid'));
    header('Location: forgot_password.php');
    exit;
}

$owner = getDB()->prepare('SELECT email FROM users WHERE id = ?');
$owner->execute([$resetRecord['user_id']]);
if (App\Demo::isProtectedAccount($owner->fetchColumn() ?: null)) {
    flash('warning', t('demo_account_locked'));
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $newPassword     = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($newPassword) < 8) {
        $errors[] = t('err_password_short');
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = t('err_password_mismatch');
    }

    if (empty($errors)) {
        $db = getDB();
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
               ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $resetRecord['user_id']]);
            // Retire this and any other outstanding links for the account
            $db->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ?')
               ->execute([$resetRecord['user_id']]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        log_activity((int) $resetRecord['user_id'], 'password_reset', 'Password reset via email link');
        flash('success', t('password_changed') . ' ' . t('please_log_in'));
        header('Location: login.php');
        exit;
    }
}

$pageTitle = t('reset_password');
require __DIR__ . '/../includes/auth_header.php';
?>

<form method="POST" action="reset_password.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="mb-3">
        <label for="new_password" class="form-label"><?= t('new_password') ?></label>
        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8"
               autocomplete="new-password" placeholder="<?= t('password_min_8') ?>">
    </div>
    <div class="mb-3">
        <label for="confirm_password" class="form-label"><?= t('confirm_password') ?></label>
        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
    </div>
    <div class="d-grid">
        <button type="submit" class="btn btn-success"><i class="bi bi-key me-1"></i><?= t('reset_password') ?></button>
    </div>
</form>

<div class="text-center small mt-3">
    <a href="login.php" class="text-success"><i class="bi bi-arrow-left me-1"></i><?= t('back_to_login') ?></a>
</div>

<?php require __DIR__ . '/../includes/auth_footer.php'; ?>
