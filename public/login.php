<?php
/**
 * AgriTrack – login.php
 * Email/password login with rate limiting, plus a Google OAuth link.
 */
require_once __DIR__ . '/../config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Failed attempts allowed per 15 minutes. The per-account limit stops
// password guessing on one account from many IPs; the per-IP limit is
// higher because many users can share one IP (cyber cafés, mobile NAT).
const MAX_FAILS_PER_ACCOUNT = 5;
const MAX_FAILS_PER_IP      = 20;

$errors   = [];
$emailVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $emailVal = $email;

    if ($email === '' || $password === '') {
        $errors[] = t('err_login_required');
    } else {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $stmt = $db->prepare(
            'SELECT
               (SELECT COUNT(*) FROM login_attempts WHERE email = ?      AND attempted_at >= NOW() - INTERVAL 15 MINUTE),
               (SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at >= NOW() - INTERVAL 15 MINUTE)'
        );
        $stmt->execute([$email, $ip]);
        [$accountFails, $ipFails] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM));

        if ($accountFails >= MAX_FAILS_PER_ACCOUNT || $ipFails >= MAX_FAILS_PER_IP) {
            $errors[] = t('too_many_attempts');
        } else {
            $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && $user['password_hash'] && password_verify($password, $user['password_hash'])) {
                if (!$user['email_verified']) {
                    $errors[] = t('verify_email_first');
                } else {
                    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                           ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                    }
                    // A successful login clears this account's failure count
                    $db->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);

                    login_user($user);
                    log_activity((int) $user['id'], 'login', 'User logged in');
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $db->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)')->execute([$email, $ip]);
                $errors[] = t('invalid_credentials');
            }
        }
    }
}

$pageTitle = t('login');
require __DIR__ . '/../includes/auth_header.php';
?>

<form method="POST" action="login.php" novalidate id="loginForm">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="mb-3">
        <label for="email" class="form-label"><?= t('email') ?></label>
        <input type="email" class="form-control" id="email" name="email" value="<?= e($emailVal) ?>" required autocomplete="email" placeholder="you@example.com">
    </div>
    <div class="mb-3">
        <label for="password" class="form-label"><?= t('password') ?></label>
        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
    </div>
    <div class="d-grid mb-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-box-arrow-in-right me-1"></i><?= t('login') ?></button>
    </div>
</form>

<?php if (googleOAuthConfigured()): ?>
    <div class="text-center my-2 text-muted small">— <?= t('or') ?> —</div>
    <div class="d-grid mb-3">
        <a href="oauth_google.php?action=login" class="btn btn-outline-dark"><?php require __DIR__ . '/../includes/google_icon.php'; ?><?= t('continue_with_google') ?></a>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between small">
    <a href="forgot_password.php" class="text-success"><?= t('forgot_password') ?></a>
    <a href="register.php" class="text-success"><?= t('register') ?></a>
</div>

<?php if (($_ENV['DEMO_MODE'] ?? '') === '1'): ?>
    <div class="alert alert-light border small mt-3 mb-0">
        <strong><?= t('demo_accounts') ?></strong><br>
        farmer@agritrack.test · buyer@agritrack.test<br>
        <?= t('password') ?>: <code>Demo@1234</code>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/auth_footer.php'; ?>
