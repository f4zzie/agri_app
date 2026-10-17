<?php
/**
 * AgriTrack – oauth_google.php
 * Manual Google OAuth 2.0 implementation (no library required).
 *
 * Flow:
 *   Step 1 – ?action=login  → redirect browser to Google
 *   Step 2 – ?code=...      → exchange code, fetch user info, create/login user
 */
require_once __DIR__ . '/../config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$clientId     = $_ENV['GOOGLE_CLIENT_ID']     ?? '';
$clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
$redirectUri  = $_ENV['GOOGLE_REDIRECT_URI']  ?? '';

// ------------------------------------------------------------------
// If Google credentials are not configured, show setup instructions
// ------------------------------------------------------------------
if (!googleOAuthConfigured()) {
    $pageTitle = t('google_not_configured');
    require __DIR__ . '/../includes/auth_header.php';
    ?>
    <div class="alert alert-warning" role="alert">
        <p class="mb-2"><?= t('google_setup_intro') ?></p>
        <ol class="mb-0 small">
            <li><?= t('google_setup_1') ?> <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud Console</a></li>
            <li><?= t('google_setup_2') ?></li>
            <li><?= t('google_setup_3') ?> <code><?= e($redirectUri ?: 'http://localhost/agri_app/oauth_google.php') ?></code></li>
            <li><?= t('google_setup_4') ?></li>
        </ol>
    </div>
    <div class="text-center">
        <a href="login.php" class="btn btn-outline-success"><i class="bi bi-arrow-left me-1"></i><?= t('back_to_login') ?></a>
    </div>
    <?php
    require __DIR__ . '/../includes/auth_footer.php';
    exit;
}

// ------------------------------------------------------------------
// STEP 1 – Initiate OAuth: redirect to Google
// ------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    // Generate a random state value for CSRF protection
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    // Check if role is specified and valid
    if (isset($_GET['role']) && in_array($_GET['role'], ['farmer', 'buyer'])) {
        $_SESSION['oauth_role'] = $_GET['role'];
    } else {
        unset($_SESSION['oauth_role']);
    }

    $params = http_build_query([
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ]);

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

// ------------------------------------------------------------------
// STEP 2 – Handle callback from Google (?code=...)
// ------------------------------------------------------------------
if (isset($_GET['code'])) {
    // Verify state to prevent CSRF
    $returnedState = $_GET['state'] ?? '';
    $storedState   = $_SESSION['oauth_state'] ?? '';

    if (!hash_equals($storedState, $returnedState)) {
        flash('danger', t('google_auth_failed'));
        header('Location: login.php');
        exit;
    }
    unset($_SESSION['oauth_state']);

    $code = $_GET['code'];

    // --- Exchange code for access token ---
    $tokenUrl  = 'https://oauth2.googleapis.com/token';
    $tokenData = http_build_query([
        'code'          => $code,
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code',
    ]);

    $tokenContext = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                         "Content-Length: " . strlen($tokenData) . "\r\n",
            'content' => $tokenData,
            'ignore_errors' => true,
        ],
    ]);

    $tokenResponse = @file_get_contents($tokenUrl, false, $tokenContext);

    if ($tokenResponse === false) {
        flash('danger', t('google_auth_failed'));
        header('Location: login.php');
        exit;
    }

    $tokenJson = json_decode($tokenResponse, true);

    if (empty($tokenJson['access_token'])) {
        error_log('Google OAuth token error: ' . $tokenResponse);
        flash('danger', t('google_auth_failed'));
        header('Location: login.php');
        exit;
    }

    $accessToken = $tokenJson['access_token'];

    // --- Fetch user info ---
    $userInfoContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$accessToken}\r\n",
            'ignore_errors' => true,
        ],
    ]);

    $userInfoResponse = @file_get_contents(
        'https://www.googleapis.com/oauth2/v2/userinfo',
        false,
        $userInfoContext
    );

    if ($userInfoResponse === false) {
        flash('danger', t('google_auth_failed'));
        header('Location: login.php');
        exit;
    }

    $googleUser = json_decode($userInfoResponse, true);

    if (empty($googleUser['email'])) {
        flash('danger', t('google_auth_failed'));
        header('Location: login.php');
        exit;
    }

    // Only trust the address if Google has verified it; otherwise anyone could
    // create a Google account with someone else's email and take over theirs.
    if (empty($googleUser['verified_email'])) {
        flash('danger', t('google_email_unverified'));
        header('Location: login.php');
        exit;
    }

    $googleId    = $googleUser['id']      ?? '';
    $googleEmail = $googleUser['email']   ?? '';
    $googleName  = $googleUser['name']    ?? $googleEmail;
    $googleAvatar = $googleUser['picture'] ?? null;

    $db = getDB();

    // --- Find or create user ---
    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $googleEmail]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        // Update google_id if not already set
        if (empty($existingUser['google_id'])) {
            $db->prepare('UPDATE users SET google_id = :gid WHERE id = :id')
               ->execute([':gid' => $googleId, ':id' => $existingUser['id']]);
        }
        $userId   = (int) $existingUser['id'];
        $userName = $existingUser['name'];
        $role     = $existingUser['role'] ?? 'farmer';
    } else {
        // Create new user (no password, Google-only)
        $role = $_SESSION['oauth_role'] ?? 'farmer';
        if (!in_array($role, ['farmer', 'buyer'])) {
            $role = 'farmer';
        }
        $ins = $db->prepare(
            'INSERT INTO users (name, email, google_id, email_verified, role)
             VALUES (:name, :email, :gid, 1, :role)'
        );
        $ins->execute([
            ':name'  => $googleName,
            ':email' => $googleEmail,
            ':gid'   => $googleId,
            ':role'  => $role,
        ]);
        $userId   = (int) $db->lastInsertId();
        $userName = $googleName;

        log_activity($userId, 'register_google', "Account created via Google OAuth as {$role}");
        unset($_SESSION['oauth_role']);
    }

    login_user(['id' => $userId, 'name' => $userName, 'email' => $googleEmail, 'role' => $role]);

    log_activity($userId, 'login_google', 'Logged in via Google OAuth');

    header('Location: dashboard.php');
    exit;
}

// If we reach here, it's an unexpected state — send back to login
flash('danger', t('google_auth_failed'));
header('Location: login.php');
exit;
