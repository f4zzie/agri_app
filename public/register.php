<?php
/**
 * AgriTrack – register.php
 * New account (farmer or business owner) with email verification.
 */
require_once __DIR__ . '/../config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$form   = ['name' => '', 'email' => '', 'phone' => '', 'county' => '', 'role' => 'farmer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach (array_keys($form) as $k) {
        $form[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $password        = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $role            = in_array($form['role'], ['farmer', 'buyer'], true) ? $form['role'] : 'farmer';
    $countyId        = $form['county'] !== '' ? (int) $form['county'] : null;

    if ($form['name'] === '' || mb_strlen($form['name']) > 100) {
        $errors[] = t('err_name_required_user');
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 150) {
        $errors[] = t('err_email_invalid');
    }
    if (strlen($password) < 8) {
        $errors[] = t('err_password_short');
    }
    if ($password !== $confirmPassword) {
        $errors[] = t('err_password_mismatch');
    }
    if ($form['phone'] !== '' && !preg_match('/^[0-9+\s\-]{7,15}$/', $form['phone'])) {
        $errors[] = t('err_phone_invalid');
    }
    if ($countyId !== null && !isset(counties()[$countyId])) {
        $errors[] = t('err_county_invalid');
    }

    $db = getDB();
    if (empty($errors)) {
        $check = $db->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $check->execute([$form['email']]);
        if ($check->fetch()) {
            $errors[] = t('err_email_taken');
        }
    }

    if (empty($errors)) {
        // Without SMTP there is no way to deliver a link, so accounts start verified.
        $needsVerification   = mailConfigured();
        [$token, $tokenHash] = $needsVerification ? App\Token::generate() : [null, null];

        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO users (name, email, password_hash, phone, county_id, role, email_verified, verification_token_hash)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $form['name'], $form['email'], password_hash($password, PASSWORD_DEFAULT),
                $form['phone'] !== '' ? $form['phone'] : null, $countyId, $role,
                $needsVerification ? 0 : 1, $tokenHash,
            ]);
            $newUserId = (int) $db->lastInsertId();
            log_activity($newUserId, 'register', "New {$role} registered" . ($countyId ? ' from ' . counties()[$countyId] : ''));
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        if ($needsVerification) {
            $verifyUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost/agri_app', '/') . '/verify.php?token=' . $token;
            $sent = sendEmail(
                $form['email'],
                $form['name'],
                'Verify your AgriTrack account',
                '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;">'
                . '<h2 style="color:#198754;">Welcome to AgriTrack!</h2>'
                . '<p>Hello <strong>' . e($form['name']) . '</strong>,</p>'
                . '<p>Please confirm your email address to activate your account:</p>'
                . '<p><a href="' . e($verifyUrl) . '" style="background:#198754;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;">Verify my email →</a></p>'
                . '<p style="color:#666;font-size:13px;">Or paste this link: ' . e($verifyUrl) . '</p></div>',
                "Verify your AgriTrack account: {$verifyUrl}"
            );
            if (!$sent) {
                // SMTP configured but failing: don't lock the user out
                $db->prepare('UPDATE users SET email_verified = 1, verification_token_hash = NULL WHERE id = ?')
                   ->execute([$newUserId]);
            }
            flash('success', $sent ? t('account_created_verify') : t('account_created'));
        } else {
            flash('success', t('account_created'));
        }

        header('Location: login.php');
        exit;
    }
}

$pageTitle = t('register');
require __DIR__ . '/../includes/auth_header.php';
?>

<form method="POST" action="register.php" novalidate id="registerForm">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <fieldset class="mb-3">
        <legend class="form-label fw-semibold fs-6"><?= t('registering_as') ?></legend>
        <div class="row g-2">
            <?php foreach (['farmer' => ['🌾', 'role_farmer_desc'], 'buyer' => ['🏪', 'role_buyer_desc']] as $r => [$icon, $desc]): ?>
            <div class="col-6">
                <input type="radio" class="btn-check" name="role" id="role_<?= $r ?>" value="<?= $r ?>" <?= $form['role'] === $r ? 'checked' : '' ?>>
                <label class="card role-card text-center p-2 h-100 w-100" for="role_<?= $r ?>">
                    <span style="font-size:2rem;" aria-hidden="true"><?= $icon ?></span>
                    <span class="fw-bold small"><?= t('role_' . $r) ?></span>
                    <span class="text-muted" style="font-size:.72rem;"><?= t($desc) ?></span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div class="mb-3">
        <label for="name" class="form-label"><?= t('full_name') ?></label>
        <input type="text" class="form-control" id="name" name="name" value="<?= e($form['name']) ?>" required maxlength="100" autocomplete="name">
    </div>
    <div class="mb-3">
        <label for="email" class="form-label"><?= t('email') ?></label>
        <input type="email" class="form-control" id="email" name="email" value="<?= e($form['email']) ?>" required autocomplete="email" placeholder="you@example.com">
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="phone" class="form-label"><?= t('phone') ?></label>
            <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($form['phone']) ?>" autocomplete="tel" placeholder="0712 345678">
        </div>
        <div class="col-md-6 mb-3">
            <label for="county" class="form-label"><?= t('county') ?></label>
            <select class="form-select" id="county" name="county">
                <option value="">— <?= t('select') ?> —</option>
                <?php foreach (counties() as $cid => $cname): ?>
                    <option value="<?= $cid ?>" <?= $form['county'] === (string) $cid ? 'selected' : '' ?>><?= e($cname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="mb-3">
        <label for="password" class="form-label"><?= t('password') ?></label>
        <input type="password" class="form-control" id="password" name="password" required minlength="8" autocomplete="new-password" placeholder="<?= t('password_min_8') ?>">
    </div>
    <div class="mb-3">
        <label for="confirm_password" class="form-label"><?= t('confirm_password') ?></label>
        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
    </div>
    <div class="d-grid mb-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-person-plus me-1"></i><?= t('register') ?></button>
    </div>
</form>

<?php if (googleOAuthConfigured()): ?>
    <div class="text-center my-2 text-muted small">— <?= t('or') ?> —</div>
    <div class="d-grid mb-3">
        <a href="oauth_google.php?action=login&amp;role=<?= e($form['role']) ?>" class="btn btn-outline-dark" id="btnGoogleRegister">
            <?php require __DIR__ . '/../includes/google_icon.php'; ?><?= t('continue_with_google') ?>
        </a>
    </div>
    <script>
    // Keep the Google sign-up role in sync with the selected card
    document.querySelectorAll('input[name="role"]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('btnGoogleRegister').href = 'oauth_google.php?action=login&role=' + encodeURIComponent(this.value);
        });
    });
    </script>
<?php endif; ?>

<div class="text-center small mt-2">
    <?= t('have_account') ?> <a href="login.php" class="text-success fw-semibold"><?= t('login') ?></a>
</div>

<?php require __DIR__ . '/../includes/auth_footer.php'; ?>
