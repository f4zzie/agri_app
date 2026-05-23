<?php
/**
 * AgriTrack – login.php
 * Handles user authentication with rate limiting and Google OAuth link.
 */
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors   = [];
$emailVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $emailVal = htmlspecialchars($email);

    if (empty($email) || empty($password)) {
        $errors[] = 'Email and password are required.';
    } else {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // --- Rate limiting: max 5 attempts per IP in 15 minutes ---
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM login_attempts
              WHERE ip_address = :ip
                AND attempted_at >= NOW() - INTERVAL 15 MINUTE'
        );
        $stmt->execute([':ip' => $ip]);
        $attemptCount = (int) $stmt->fetchColumn();

        if ($attemptCount >= 5) {
            $errors[] = t('too_many_attempts');
        } else {
            // --- Find user ---
            $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && $user['password_hash'] && password_verify($password, $user['password_hash'])) {

                // Check email verified
                if (!$user['email_verified']) {
                    $errors[] = 'Please verify your email first. Check your inbox for the verification link.';
                } else {
                    // --- Successful login ---
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name']    = $user['name'];
                    $_SESSION['email']   = $user['email'];
                    $_SESSION['role']    = $user['role'] ?? 'farmer';

                    log_activity((int) $user['id'], 'login', 'User logged in');

                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                // --- Failed login: record attempt ---
                $ins = $db->prepare(
                    'INSERT INTO login_attempts (email, ip_address) VALUES (:email, :ip)'
                );
                $ins->execute([':email' => $email, ':ip' => $ip]);
                $errors[] = t('invalid_credentials');
            }
        }
    }
}

$currentLang = $_SESSION['lang'] ?? 'en';
$flashSuccess = flash('success');
$flashDanger  = flash('danger');
$flashInfo    = flash('info');
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to AgriTrack – Agricultural Crop Management System">
    <title><?= t('login') ?> | <?= t('app_name') ?></title>

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
            <a href="?lang=en"
               class="btn btn-sm <?= $currentLang === 'en' ? 'btn-success' : 'btn-outline-success' ?>">
                <?= t('english') ?>
            </a>
            <a href="?lang=sw"
               class="btn btn-sm <?= $currentLang === 'sw' ? 'btn-success' : 'btn-outline-success' ?>">
                <?= t('swahili') ?>
            </a>
        </div>

        <!-- Header -->
        <div class="text-center mb-3">
            <span style="font-size:2rem;">🌿</span>
            <h1 class="h4 fw-bold text-success"><?= t('app_name') ?></h1>
            <p class="text-muted small"><?= t('login') ?></p>
        </div>

        <!-- Flash messages -->
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success alert-dismissible fade show flash-alert" role="alert">
                <?= htmlspecialchars($flashSuccess) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($flashDanger): ?>
            <div class="alert alert-danger alert-dismissible fade show flash-alert" role="alert">
                <?= htmlspecialchars($flashDanger) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($flashInfo): ?>
            <div class="alert alert-info alert-dismissible fade show flash-alert" role="alert">
                <?= htmlspecialchars($flashInfo) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Inline errors -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="login.php" novalidate id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="mb-3">
                <label for="email" class="form-label"><?= t('email') ?></label>
                <input type="email"
                       class="form-control"
                       id="email"
                       name="email"
                       value="<?= $emailVal ?>"
                       required
                       autocomplete="email"
                       placeholder="you@example.com">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label"><?= t('password') ?></label>
                <input type="password"
                       class="form-control"
                       id="password"
                       name="password"
                       required
                       autocomplete="current-password"
                       placeholder="••••••••">
            </div>

            <div class="d-grid mb-2">
                <button type="submit" class="btn btn-success" id="btnLoginSubmit">
                    <i class="bi bi-box-arrow-in-right me-1"></i><?= t('login') ?>
                </button>
            </div>
        </form>

        <div class="text-center my-2 text-muted small">— or —</div>

        <!-- Google OAuth -->
        <div class="d-grid mb-3">
            <a href="oauth_google.php?action=login"
               class="btn btn-outline-dark"
               id="btnGoogleLogin">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                     viewBox="0 0 48 48" class="me-2">
                    <path fill="#EA4335" d="M24 9.5c3.5 0 6.3 1.2 8.4 3.1l6.3-6.3C34.9 3 29.8 1 24 1 14.8 1 7 6.6 3.5 14.5l7.4 5.7C12.8 13.9 17.9 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.2-.4-4.7H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.4 5.7C43.7 37.5 46.5 31.4 46.5 24.5z"/>
                    <path fill="#FBBC05" d="M10.9 28.8A14.9 14.9 0 0 1 9.5 24c0-1.7.3-3.3.8-4.8L2.9 13.5A23.5 23.5 0 0 0 1 24c0 3.8.9 7.4 2.5 10.5l7.4-5.7z"/>
                    <path fill="#34A853" d="M24 47c5.8 0 10.7-1.9 14.2-5.2l-7.4-5.7c-1.9 1.3-4.4 2-6.8 2-6.1 0-11.2-4.1-13-9.8l-7.4 5.7C7 41.4 14.8 47 24 47z"/>
                </svg>
                Continue with Google
            </a>
        </div>

        <div class="d-flex justify-content-between small">
            <a href="forgot_password.php" class="text-success"><?= t('forgot_password') ?></a>
            <a href="register.php" class="text-success"><?= t('register') ?></a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="script.js"></script>
</body>
</html>
