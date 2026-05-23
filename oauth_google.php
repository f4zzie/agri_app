<?php
/**
 * AgriTrack – oauth_google.php
 * Manual Google OAuth 2.0 implementation (no library required).
 *
 * Flow:
 *   Step 1 – ?action=login  → redirect browser to Google
 *   Step 2 – ?code=...      → exchange code, fetch user info, create/login user
 */
require_once __DIR__ . '/config.php';

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
if (empty($clientId) || empty($clientSecret)) {
    $currentLang = $_SESSION['lang'] ?? 'en';
    ?>
    <!DOCTYPE html>
    <html lang="<?= $currentLang ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Google OAuth Setup | <?= t('app_name') ?></title>
        <link rel="stylesheet"
              href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <div class="auth-wrapper">
        <div class="auth-card card shadow p-4">
            <div class="text-center mb-3">
                <span style="font-size:2rem;">🌿</span>
                <h1 class="h4 fw-bold text-success"><?= t('app_name') ?></h1>
            </div>

            <div class="alert alert-warning" role="alert">
                <h5 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Google OAuth Not Configured</h5>
                <p>To enable Google login, follow these steps:</p>
                <ol class="mb-0 small">
                    <li>Go to <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a></li>
                    <li>Create a new project (or select an existing one).</li>
                    <li>Enable the <strong>Google+ API</strong> or <strong>OAuth 2.0</strong> under APIs &amp; Services.</li>
                    <li>Go to <strong>Credentials</strong> → <strong>Create Credentials</strong> → <strong>OAuth Client ID</strong>.</li>
                    <li>Set Application type to <strong>Web application</strong>.</li>
                    <li>Add your redirect URI: <code><?= htmlspecialchars($redirectUri ?: 'http://localhost/agri_app/oauth_google.php') ?></code></li>
                    <li>Copy the <strong>Client ID</strong> and <strong>Client Secret</strong>.</li>
                    <li>Paste them into your <code>.env</code> file as <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code>.</li>
                </ol>
            </div>

            <div class="text-center">
                <a href="login.php" class="btn btn-outline-success">
                    <i class="bi bi-arrow-left me-1"></i>Back to Login
                </a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// ------------------------------------------------------------------
// STEP 1 – Initiate OAuth: redirect to Google
// ------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    // Generate a random state value for CSRF protection
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

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
        flash('danger', 'Invalid OAuth state. Please try again.');
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
        flash('danger', 'Failed to connect to Google. Please try again later.');
        header('Location: login.php');
        exit;
    }

    $tokenJson = json_decode($tokenResponse, true);

    if (empty($tokenJson['access_token'])) {
        error_log('Google OAuth token error: ' . $tokenResponse);
        flash('danger', 'Google authentication failed. Please try again.');
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
        flash('danger', 'Could not retrieve your Google profile. Please try again.');
        header('Location: login.php');
        exit;
    }

    $googleUser = json_decode($userInfoResponse, true);

    if (empty($googleUser['email'])) {
        flash('danger', 'Could not retrieve email from Google. Please try again.');
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
    } else {
        // Create new user (no password, Google-only)
        $ins = $db->prepare(
            'INSERT INTO users (name, email, google_id, email_verified)
             VALUES (:name, :email, :gid, 1)'
        );
        $ins->execute([
            ':name'  => $googleName,
            ':email' => $googleEmail,
            ':gid'   => $googleId,
        ]);
        $userId   = (int) $db->lastInsertId();
        $userName = $googleName;

        log_activity($userId, 'register_google', 'Account created via Google OAuth');
    }

    // --- Set session ---
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['name']    = $userName;
    $_SESSION['email']   = $googleEmail;

    log_activity($userId, 'login_google', 'Logged in via Google OAuth');

    header('Location: dashboard.php');
    exit;
}

// If we reach here, it's an unexpected state — send back to login
flash('danger', 'Unexpected OAuth error. Please try again.');
header('Location: login.php');
exit;
