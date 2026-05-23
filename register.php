<?php
/**
 * AgriTrack – register.php
 * New user registration with email verification.
 */
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors   = [];
$nameVal  = $emailVal = $phoneVal = $countyVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name            = trim($_POST['name']             ?? '');
    $email           = trim($_POST['email']            ?? '');
    $password        = trim($_POST['password']         ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $phone           = trim($_POST['phone']            ?? '');
    $county          = trim($_POST['county']           ?? '');

    $nameVal   = htmlspecialchars($name);
    $emailVal  = htmlspecialchars($email);
    $phoneVal  = htmlspecialchars($phone);
    $countyVal = htmlspecialchars($county);

    // --- Validation ---
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!empty($phone) && !preg_match('/^[0-9+\s\-]{7,15}$/', $phone)) {
        $errors[] = 'Enter a valid phone number.';
    }

    $validCounties = ['Mombasa','Kwale','Kilifi','Tana River','Lamu','Taita-Taveta','Garissa','Wajir','Mandera','Marsabit','Isiolo','Meru','Tharaka-Nithi','Embu','Kitui','Machakos','Makueni','Nyandarua','Nyeri','Kirinyaga',"Murang'a",'Kiambu','Turkana','West Pokot','Samburu','Trans Nzoia','Uasin Gishu','Elgeyo-Marakwet','Nandi','Baringo','Laikipia','Nakuru','Narok','Kajiado','Kericho','Bomet','Kakamega','Vihiga','Bungoma','Busia','Siaya','Kisumu','Homa Bay','Migori','Kisii','Nyamira','Nairobi'];
    if (!empty($county) && !in_array($county, $validCounties)) {
        $errors[] = 'Please select a valid county.';
    }

    if (empty($errors)) {
        $db = getDB();

        // Check email uniqueness
        $check = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $check->execute([':email' => $email]);
        if ($check->fetch()) {
            $errors[] = 'That email address is already registered. Please log in.';
        }
    }

    if (empty($errors)) {
        $db = getDB();

        $passwordHash      = password_hash($password, PASSWORD_BCRYPT);
        $verificationToken = bin2hex(random_bytes(32));
        $role              = in_array($role ?? '', ['farmer','buyer']) ? $role : 'farmer';

        $stmt = $db->prepare(
            'INSERT INTO users (name, email, password_hash, phone, county, role, email_verified, verification_token)
             VALUES (:name, :email, :hash, :phone, :county, :role, 0, :token)'
        );
        $stmt->execute([
            ':name'   => $name,
            ':email'  => $email,
            ':hash'   => $passwordHash,
            ':phone'  => $phone,
            ':county' => $county,
            ':role'   => $role,
            ':token'  => $verificationToken,
        ]);

        $newUserId = (int) $db->lastInsertId();
        log_activity($newUserId, 'register', "New {$role} registered" . ($county ? " from {$county}" : ''));

        // --- Send welcome / verification email ---
        $appUrl     = $_ENV['APP_URL'] ?? 'http://localhost/agri_app';
        $verifyUrl  = $appUrl . '/verify.php?token=' . urlencode($verificationToken);
        $roleLabel  = $role === 'buyer' ? 'Business Owner' : 'Farmer';

        $htmlBody =
            '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;">' .
            '<div style="background:#198754;padding:20px;text-align:center;border-radius:8px 8px 0 0;">' .
            '<h2 style="color:white;margin:0;">Welcome to AgriTrack!</h2></div>' .
            '<div style="padding:24px;border:1px solid #dee2e6;border-top:none;border-radius:0 0 8px 8px;">' .
            '<p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>' .
            '<p>Your <strong>' . $roleLabel . '</strong> account has been created. ' .
            'Please click the button below to verify your email address and activate your account.</p>' .
            '<div style="text-align:center;margin:28px 0;">' .
            '<a href="' . $verifyUrl . '" style="background:#198754;color:white;padding:12px 30px;' .
            'text-decoration:none;border-radius:6px;font-weight:bold;display:inline-block;">' .
            'Verify My Email &rarr;</a></div>' .
            '<p style="color:#666;font-size:13px;">Or paste this link in your browser:<br>' .
            '<a href="' . $verifyUrl . '">' . $verifyUrl . '</a></p>' .
            '<p style="color:#999;font-size:12px;border-top:1px solid #eee;padding-top:12px;">' .
            'If you did not create this account, please ignore this email.</p>' .
            '</div></div>';

        $emailSent = sendEmail(
            $email,
            $name,
            'Verify your AgriTrack account',
            $htmlBody,
            "Hello {$name}, verify your AgriTrack account here: {$verifyUrl}"
        );

        if ($emailSent) {
            flash('success', 'Account created! Check your email to verify your account before logging in.');
        } else {
            // Email not configured — auto-verify so user isn't stuck
            $db->prepare('UPDATE users SET email_verified=1, verification_token=NULL WHERE id=?')
               ->execute([$newUserId]);
            flash('success', 'Account created successfully! You can now log in.');
        }

        header('Location: login.php');
        exit;
    }
}

$currentLang = $_SESSION['lang'] ?? 'en';
$roleVal      = htmlspecialchars($_POST['role'] ?? 'farmer');
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create a new AgriTrack account">
    <title><?= t('register') ?> | <?= t('app_name') ?></title>

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
            <p class="text-muted small"><?= t('register') ?></p>
        </div>

        <!-- Errors -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <form method="POST" action="register.php" novalidate id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($roleVal ?? 'farmer') ?>">

            <!-- Role Selector -->
            <div class="mb-3">
                <label class="form-label fw-semibold">I am registering as a:</label>
                <div class="row g-2" id="roleSelector">
                    <div class="col-6">
                        <div class="card role-card border-2 border-success bg-success bg-opacity-10 text-center p-2"
                             id="cardFarmer" onclick="selectRole('farmer')" style="cursor:pointer;">
                            <div style="font-size:2rem;">🌾</div>
                            <div class="fw-bold small">Farmer</div>
                            <div class="text-muted" style="font-size:0.72rem;">List &amp; sell my products</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card role-card border-2 text-center p-2"
                             id="cardBuyer" onclick="selectRole('buyer')" style="cursor:pointer;">
                            <div style="font-size:2rem;">🏪</div>
                            <div class="fw-bold small">Business Owner</div>
                            <div class="text-muted" style="font-size:0.72rem;">Browse &amp; buy from farmers</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="name" class="form-label"><?= t('name') ?></label>
                <input type="text"
                       class="form-control"
                       id="name"
                       name="name"
                       value="<?= $nameVal ?>"
                       required
                       autocomplete="name"
                       placeholder="Your full name">
            </div>

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

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel"
                           class="form-control"
                           id="phone"
                           name="phone"
                           value="<?= $phoneVal ?>"
                           autocomplete="tel"
                           placeholder="e.g. 0712 345678">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="county" class="form-label">County</label>
                    <select class="form-select" id="county" name="county">
                        <option value="">-- Select County --</option>
                        <?php
                        $counties = ['Mombasa','Kwale','Kilifi','Tana River','Lamu','Taita-Taveta',
                                     'Garissa','Wajir','Mandera','Marsabit','Isiolo','Meru',
                                     'Tharaka-Nithi','Embu','Kitui','Machakos','Makueni','Nyandarua',
                                     'Nyeri','Kirinyaga',"Murang'a",'Kiambu','Turkana','West Pokot',
                                     'Samburu','Trans Nzoia','Uasin Gishu','Elgeyo-Marakwet','Nandi',
                                     'Baringo','Laikipia','Nakuru','Narok','Kajiado','Kericho','Bomet',
                                     'Kakamega','Vihiga','Bungoma','Busia','Siaya','Kisumu','Homa Bay',
                                     'Migori','Kisii','Nyamira','Nairobi'];
                        foreach ($counties as $c):
                            $sel = ($countyVal === $c) ? 'selected' : '';
                        ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $sel ?>>
                                <?= htmlspecialchars($c) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label"><?= t('password') ?></label>
                <input type="password"
                       class="form-control"
                       id="password"
                       name="password"
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
                       placeholder="Repeat your password">
            </div>

            <div class="d-grid mb-2">
                <button type="submit" class="btn btn-success" id="btnRegisterSubmit">
                    <i class="bi bi-person-plus me-1"></i><?= t('register') ?>
                </button>
            </div>
        </form>

        <div class="text-center my-2 text-muted small">— or —</div>

        <!-- Google OAuth -->
        <div class="d-grid mb-3">
            <a href="oauth_google.php?action=login"
               class="btn btn-outline-dark"
               id="btnGoogleRegister">
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

        <div class="text-center small mt-2">
            Already have an account?
            <a href="login.php" class="text-success fw-semibold"><?= t('login') ?></a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="script.js"></script>
<script>
function selectRole(r) {
    document.getElementById('roleInput').value = r;
    var farmer = document.getElementById('cardFarmer');
    var buyer  = document.getElementById('cardBuyer');
    if (r === 'farmer') {
        farmer.classList.add('border-success','bg-success','bg-opacity-10');
        buyer.classList.remove('border-success','bg-success','bg-opacity-10');
    } else {
        buyer.classList.add('border-success','bg-success','bg-opacity-10');
        farmer.classList.remove('border-success','bg-success','bg-opacity-10');
    }
}
// Init to saved value on page load
selectRole(document.getElementById('roleInput').value || 'farmer');
</script>
</body>
</html>
