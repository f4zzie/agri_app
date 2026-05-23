<?php
/**
 * AgriTrack – forgot_password.php
 * Allows users to request a password reset email.
 */
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim($_POST['email'] ?? '');

    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $db = getDB();

        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate reset token valid for 1 hour
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $ins = $db->prepare(
                'INSERT INTO password_resets (email, token, expires_at)
                 VALUES (:email, :token, :expires)'
            );
            $ins->execute([
                ':email'   => $email,
                ':token'   => $token,
                ':expires' => $expiresAt,
            ]);

            // --- Send reset email if PHPMailer is available ---
            $vendorAutoload = __DIR__ . '/vendor/autoload.php';
            if (file_exists($vendorAutoload)) {
                require_once $vendorAutoload;
            }

            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                try {
                    $appUrl   = $_ENV['APP_URL'] ?? 'http://localhost/agri_app';
                    $resetUrl = $appUrl . '/reset_password.php?token=' . urlencode($token);

                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $_ENV['SMTP_USER'] ?? '';
                    $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = (int) ($_ENV['SMTP_PORT'] ?? 587);

                    $mail->setFrom($_ENV['SMTP_USER'] ?? 'noreply@agritrack.com', 'AgriTrack');
                    $mail->addAddress($email, $user['name']);

                    $mail->isHTML(true);
                    $mail->Subject = 'AgriTrack – Password Reset';
                    $mail->Body    =
                        '<p>Hello ' . htmlspecialchars($user['name']) . ',</p>' .
                        '<p>Click the link below to reset your password (expires in 1 hour):</p>' .
                        '<p><a href="' . $resetUrl . '">' . $resetUrl . '</a></p>' .
                        '<p>If you did not request a reset, please ignore this email.</p>';
                    $mail->AltBody =
                        'Reset your AgriTrack password: ' . $resetUrl;

                    $mail->send();
                } catch (\Exception $e) {
                    error_log('Password reset email error: ' . $e->getMessage());
                }
            }
        }
        // Always show the same message to prevent email enumeration
    }

    $submitted = true;
}

$currentLang = $_SESSION['lang'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Forgot your AgriTrack password? Request a reset link.">
    <title><?= t('forgot_password') ?> | <?= t('app_name') ?></title>

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

        <div class="text-center mb-3">
            <span style="font-size:2rem;">🌿</span>
            <h1 class="h4 fw-bold text-success"><?= t('app_name') ?></h1>
            <p class="text-muted small"><?= t('forgot_password') ?></p>
        </div>

        <?php if ($submitted): ?>
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle me-1"></i>
                <?= t('email_sent') ?>
            </div>
        <?php else: ?>
            <p class="text-muted small mb-3">
                Enter your registered email address and we'll send you a link to reset your password.
            </p>

            <form method="POST" action="forgot_password.php" novalidate id="forgotForm">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="mb-3">
                    <label for="email" class="form-label"><?= t('email') ?></label>
                    <input type="email"
                           class="form-control"
                           id="email"
                           name="email"
                           required
                           autocomplete="email"
                           placeholder="you@example.com">
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-success" id="btnForgotSubmit">
                        <i class="bi bi-envelope me-1"></i>Send Reset Link
                    </button>
                </div>
            </form>
        <?php endif; ?>

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
