<?php
/**
 * AgriTrack – Central configuration file
 * All helper functions are defined here and automatically initialised at the bottom.
 */

// ---------------------------------------------------------------------------
// 1. loadEnv() – parse .env file and populate $_ENV
// ---------------------------------------------------------------------------
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return; // silently skip; fall back to existing environment
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Split on the first '=' only
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Strip surrounding quotes if present
        if (
            strlen($value) >= 2 &&
            (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            )
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

// ---------------------------------------------------------------------------
// 2. getDB() – return a singleton PDO connection
// ---------------------------------------------------------------------------
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host    = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname  = $_ENV['DB_NAME'] ?? 'agri_app';
    $user    = $_ENV['DB_USER'] ?? 'root';
    $pass    = $_ENV['DB_PASS'] ?? '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // In production, do not expose the message
        error_log('DB Connection failed: ' . $e->getMessage());
        die('<div style="font-family:sans-serif;color:red;padding:20px;">
               <strong>Database connection failed.</strong> Please check your .env file.
             </div>');
    }

    return $pdo;
}

// ---------------------------------------------------------------------------
// 3. setLang() – determine active language and store in session
// ---------------------------------------------------------------------------
function setLang(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $allowed = ['en', 'sw'];

    if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed, true)) {
        $_SESSION['lang'] = $_GET['lang'];
    }

    if (!isset($_SESSION['lang']) || !in_array($_SESSION['lang'], $allowed, true)) {
        $_SESSION['lang'] = 'en';
    }
}

// ---------------------------------------------------------------------------
// 4. t() – return a translated string
// ---------------------------------------------------------------------------
function t(string $key): string
{
    static $strings = null;

    if ($strings === null) {
        $lang    = $_SESSION['lang'] ?? 'en';
        $file    = __DIR__ . "/lang/{$lang}.php";
        $fallback = __DIR__ . '/lang/en.php';

        if (file_exists($file)) {
            $strings = require $file;
        } elseif (file_exists($fallback)) {
            $strings = require $fallback;
        } else {
            $strings = [];
        }
    }

    return $strings[$key] ?? $key;
}

// ---------------------------------------------------------------------------
// 5. csrf_token() – generate / return CSRF token
// ---------------------------------------------------------------------------
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// ---------------------------------------------------------------------------
// 6. csrf_verify() – verify CSRF token from POST, die on mismatch
// ---------------------------------------------------------------------------
function csrf_verify(): void
{
    $posted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';

    if (!hash_equals($stored, $posted)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:20px;color:red;">
               <strong>403 – Invalid CSRF token.</strong> Please go back and try again.
             </div>');
    }
}

// ---------------------------------------------------------------------------
// 7. log_activity() – insert a record into activity_log
// ---------------------------------------------------------------------------
function log_activity(int $user_id, string $action, string $details = ''): void
{
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $stmt = $db->prepare(
            'INSERT INTO activity_log (user_id, action, details, ip_address)
             VALUES (:uid, :action, :details, :ip)'
        );
        $stmt->execute([
            ':uid'     => $user_id,
            ':action'  => $action,
            ':details' => $details,
            ':ip'      => $ip,
        ]);
    } catch (PDOException $e) {
        error_log('log_activity error: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// 8. flash() – get / set flash messages stored in session
// ---------------------------------------------------------------------------
function flash(string $key, ?string $msg = null): ?string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($msg !== null) {
        // Setter
        $_SESSION['flash'][$key] = $msg;
        return null;
    }

    // Getter – read and remove
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

// ---------------------------------------------------------------------------
// 9. isLoggedIn() – check if a user session exists
// ---------------------------------------------------------------------------
function isLoggedIn(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// ---------------------------------------------------------------------------
// 10. sendEmail() – send HTML email via PHPMailer (requires composer install)
// ---------------------------------------------------------------------------
function sendEmail(string $to, string $toName, string $subject, string $htmlBody, string $altBody = ''): bool
{
    $vendorAutoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($vendorAutoload)) {
        error_log('AgriTrack sendEmail: vendor/autoload.php not found – run composer install');
        return false;
    }

    require_once $vendorAutoload;

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return false;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';
        $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'] ?? '';
        $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($_ENV['SMTP_PORT'] ?? 587);

        $mail->setFrom($_ENV['SMTP_USER'] ?? 'noreply@agritrack.com', 'AgriTrack');
        $mail->addAddress($to, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody ?: strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('AgriTrack email error: ' . $e->getMessage());
        return false;
    }
}

// ---------------------------------------------------------------------------
// Auto-initialisation
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment variables from .env (same directory as config.php)
loadEnv(__DIR__ . '/.env');

// Set active language
setLang();
