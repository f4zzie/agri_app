<?php
/**
 * AgriTrack – forgot_password.php
 * Request a password reset link. The same message is shown whether or not
 * the email exists, so the form cannot be used to discover accounts.
 */
require_once __DIR__ . '/../config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim((string) ($_POST['email'] ?? ''));

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Shared demo logins can't be reset (same message, so nothing is revealed)
        if ($user && !App\Demo::isProtectedAccount($email)) {
            [$token, $tokenHash] = App\Token::generate();

            $db->beginTransaction();
            // Only the newest link works: retire any earlier unused tokens
            $db->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0')
               ->execute([$user['id']]);
            $db->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at)
                          VALUES (?, ?, NOW() + INTERVAL 1 HOUR)')
               ->execute([$user['id'], $tokenHash]);
            $db->commit();

            $resetUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost/agri_app', '/')
                      . '/reset_password.php?token=' . $token;
            sendEmail(
                $email,
                $user['name'],
                'AgriTrack – Password Reset',
                '<p>Hello ' . e($user['name']) . ',</p>'
                . '<p>Click the link below to reset your password (expires in 1 hour):</p>'
                . '<p><a href="' . e($resetUrl) . '">' . e($resetUrl) . '</a></p>'
                . '<p>If you did not request a reset, please ignore this email.</p>',
                'Reset your AgriTrack password: ' . $resetUrl
            );
        }
    }
    $submitted = true;
}

$pageTitle = t('forgot_password');
require __DIR__ . '/../includes/auth_header.php';
?>

<?php if ($submitted): ?>
    <div class="alert alert-info" role="status"><i class="bi bi-info-circle me-1"></i><?= t('email_sent') ?></div>
    <?php if (!mailConfigured()): ?>
        <p class="small text-muted"><?= t('reset_needs_smtp') ?></p>
    <?php endif; ?>
<?php else: ?>
    <p class="text-muted small mb-3"><?= t('forgot_password_intro') ?></p>
    <form method="POST" action="forgot_password.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="mb-3">
            <label for="email" class="form-label"><?= t('email') ?></label>
            <input type="email" class="form-control" id="email" name="email" required autocomplete="email" placeholder="you@example.com">
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-success"><i class="bi bi-envelope me-1"></i><?= t('send_reset_link') ?></button>
        </div>
    </form>
<?php endif; ?>

<div class="text-center small mt-3">
    <a href="login.php" class="text-success"><i class="bi bi-arrow-left me-1"></i><?= t('back_to_login') ?></a>
</div>

<?php require __DIR__ . '/../includes/auth_footer.php'; ?>
