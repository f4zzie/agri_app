<?php
/**
 * AgriTrack – reset_password.php
 * Validates a reset token and lets the user set a new password.
 */
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$token  = trim($_GET['token'] ?? $_POST['token'] ?? '');

// --- Validate token on first load ---
$resetRecord = null;
if (!empty($token)) {
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM password_resets
          WHERE token    = :token
            AND used     = 0
            AND expires_at > NOW()
          LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $resetRecord = $stmt->fetch();
}

if (empty($token) || !$resetRecord) {
    flash('danger', 'This password reset link is invalid or has expired. Please request a new one.');
    header('Location: forgot_password.php');
    exit;
}

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $newPassword     = trim($_POST['new_password']     ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if (strlen($newPassword) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $db = getDB();

        // Find the user by email from the reset record
        $userStmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $userStmt->execute([':email' => $resetRecord['email']]);
        $user = $userStmt->fetch();

        if (!$user) {
            flash('danger', 'Account not found. Please contact support.');
            header('Location: forgot_password.php');
            exit;
        }

        // Update password
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
           ->execute([':hash' => $newHash, ':id' => $user['id']]);

        // Mark reset token as used
        $db->prepare('UPDATE password_resets SET used = 1 WHERE id = :id')
           ->execute([':id' => $resetRecord['id']]);

        log_activity((int) $user['id'], 'password_reset', 'Password reset via email link');

        flash('success', t('password_changed') . ' Please log in with your new password.');
        header('Location: login.php');
        exit;
    }
}

$currentLang = $_SESSION['lang'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reset your AgriTrack password">
    <title><?= t('reset_password') ?> | <?= t('app_name') ?></title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card card shadow p-4">

        <!-- Language Switcher -->
        <div class="d-flex justify-content-end mb-2 gap-1">
            <a href="?lang=en&token=<?= urlencode($token) ?>"
               class="btn btn-sm <?= $currentLang === 'en' ? 'btn-success' : 'btn-outline-success' ?>">
                <?= t('english') ?>
            </a>
            <a href="?lang=sw&token=<?= urlencode($token) ?>"
               class="btn btn-sm <?= $currentLang === 'sw' ? 'btn-success' : 'btn-outline-success' ?>">
                <?= t('swahili') ?>
            </a>
        </div>

        <div class="text-center mb-3">
            <span style="font-size:2rem;">🌿</span>
            <h1 class="h4 fw-bold text-success"><?= t('app_name') ?></h1>
            <p class="text-muted small"><?= t('reset_password') ?></p>
        </div>

        <!-- Errors -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="reset_password.php" novalidate id="resetForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="mb-3">
                <label for="new_password" class="form-label"><?= t('new_password') ?></label>
                <input type="password"
                       class="form-control"
                       id="new_password"
                       name="new_password"
                       required
                       autocomplete="new-password"
                       placeholder="At least 6 characters">
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label"><?= t('confirm_password') ?></label>
                <input type="password"
                       class="form-control"
                       id="confirm_password"
                       name="confirm_password"
                       required
                       autocomplete="new-password"
                       placeholder="Repeat new password">
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-success" id="btnResetSubmit">
                    <i class="bi bi-key me-1"></i><?= t('reset_password') ?>
                </button>
            </div>
        </form>

        <div class="text-center small mt-3">
            <a href="login.php" class="text-success">
                <i class="bi bi-arrow-left me-1"></i>Back to Login
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="script.js"></script>
</body>
</html>
