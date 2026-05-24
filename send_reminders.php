<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'includes/auth_check.php';
$pageTitle = 'Harvest Reminders';
require_once 'includes/header.php';

$db  = getDB();
$uid = $_SESSION['user_id'];

// Fetch crops with harvest in next 7 days
$stmt = $db->prepare(
    "SELECT * FROM crops
     WHERE user_id = ?
       AND expected_harvest BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     ORDER BY expected_harvest ASC"
);
$stmt->execute([$uid]);
$upcomingCrops = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user email
$userStmt = $db->prepare("SELECT name, email FROM users WHERE id = ?");
$userStmt->execute([$uid]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

$successMsg  = flash('success');
$errorMsg    = flash('error');
$remindersSent = false;
$sentCrops   = [];
$phpMailerAvailable = file_exists(__DIR__ . '/vendor/autoload.php');

// ─── Handle POST: Send Reminders ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $sentCrops = $upcomingCrops;

    if ($phpMailerAvailable) {
        // Try to load PHPMailer
        require_once __DIR__ . '/vendor/autoload.php';

        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            

            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $_ENV['MAIL_HOST']     ?? 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = $_ENV['MAIL_USER']     ?? '';
                $mail->Password   = $_ENV['MAIL_PASS']     ?? '';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = (int) ($_ENV['MAIL_PORT'] ?? 587);

                $mail->setFrom($_ENV['MAIL_FROM'] ?? $_ENV['MAIL_USER'] ?? 'noreply@agritrack.app', 'AgriTrack');
                $mail->addAddress($user['email'], $user['name']);

                $mail->isHTML(true);
                $mail->Subject = 'AgriTrack — Upcoming Harvest Reminders';

                $cropListHtml = '<ul>';
                foreach ($upcomingCrops as $c) {
                    $cropListHtml .= '<li><strong>' . htmlspecialchars($c['name']) . '</strong>';
                    if (!empty($c['variety'])) {
                        $cropListHtml .= ' (' . htmlspecialchars($c['variety']) . ')';
                    }
                    $cropListHtml .= ' — Harvest: ' . htmlspecialchars($c['expected_harvest']) . '</li>';
                }
                $cropListHtml .= '</ul>';

                $mail->Body = '
                    <html><body style="font-family:sans-serif;color:#333;">
                    <h2 style="color:#28a745;">🌿 Harvest Reminders — AgriTrack</h2>
                    <p>Hi ' . htmlspecialchars($user['name']) . ',</p>
                    <p>The following crops are due for harvest within the next 7 days:</p>
                    ' . $cropListHtml . '
                    <p>Log in to <a href="' . ($_ENV['APP_URL'] ?? '#') . '">AgriTrack</a> to update their status.</p>
                    <hr>
                    <p style="color:#888;font-size:12px;">Powered by AgriTrack — Agricultural Crop Management System</p>
                    </body></html>';
                $mail->AltBody = 'Upcoming harvests: ' . implode(', ', array_column($upcomingCrops, 'name'));

                $mail->send();

                log_activity($uid, 'send_reminders', 'Harvest reminder email sent for ' . count($upcomingCrops) . ' crop(s)');
                $remindersSent = true;
                flash('success', 'Reminder emails sent successfully to ' . $user['email'] . '!');
                header('Location: send_reminders.php');
                exit;

            } catch (Exception $e) {
                // Fall through to simulation mode
                log_activity($uid, 'send_reminders', 'Reminder simulation (SMTP error): ' . count($upcomingCrops) . ' crop(s)');
                $remindersSent = true;
            }
        } else {
            log_activity($uid, 'send_reminders', 'Reminder simulation (PHPMailer not found): ' . count($upcomingCrops) . ' crop(s)');
            $remindersSent = true;
        }
    } else {
        // Simulation mode — no composer
        log_activity($uid, 'send_reminders', 'Reminder simulation (no vendor): ' . count($upcomingCrops) . ' crop(s)');
        $remindersSent = true;
    }
}

$successMsg = flash('success');
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <h1 class="h3 fw-bold mb-1 text-success">
                <i class="bi bi-bell me-2"></i>Harvest Reminders
            </h1>
            <p class="text-muted mb-4">Crops with harvest dates in the next 7 days.</p>

            <?php if ($successMsg): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($successMsg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- SMTP Note -->
            <?php if (!$phpMailerAvailable): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Email not configured.</strong>
                    Run <code>composer install</code> and configure SMTP settings in <code>.env</code> to enable real email delivery.
                    Reminders will be <em>simulated</em> and logged.
                </div>
            <?php endif; ?>

            <!-- Sent Result -->
            <?php if ($remindersSent && !empty($sentCrops)): ?>
                <div class="card border-0 shadow-sm border-start border-4 border-success mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-success mb-3">
                            <i class="bi bi-send-check me-2"></i>Reminders Processed!
                        </h5>
                        <p class="text-muted mb-3">
                            <?= $phpMailerAvailable ? 'Reminder email sent to <strong>' . htmlspecialchars($user['email']) . '</strong>.' : 'Simulation mode: email not actually sent. Configure SMTP to enable real delivery.' ?>
                        </p>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($sentCrops as $c): ?>
                            <li class="list-group-item px-0 py-2 border-bottom">
                                <i class="bi bi-tree text-success me-2"></i>
                                <strong><?= htmlspecialchars($c['name']) ?></strong>
                                <?php if (!empty($c['variety'])): ?>
                                    <span class="text-muted">(<?= htmlspecialchars($c['variety']) ?>)</span>
                                <?php endif; ?>
                                <span class="badge bg-warning text-dark ms-2">
                                    Harvest: <?= htmlspecialchars($c['expected_harvest']) ?>
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php elseif ($remindersSent && empty($sentCrops)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    No crops are due for harvest in the next 7 days. Nothing to remind.
                </div>
            <?php endif; ?>

            <!-- Upcoming Crops List -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-calendar-week me-2 text-warning"></i>
                        Crops Due in Next 7 Days
                        <span class="badge bg-warning text-dark ms-2"><?= count($upcomingCrops) ?></span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($upcomingCrops)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-calendar-check fs-1 text-muted"></i>
                            <p class="text-muted mt-3 mb-0">No crops are due for harvest in the next 7 days.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Crop Name</th>
                                        <th>Variety</th>
                                        <th>Status</th>
                                        <th>Expected Harvest</th>
                                        <th>Days Remaining</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcomingCrops as $c): ?>
                                    <?php
                                    $harvestDate = new DateTime($c['expected_harvest']);
                                    $today       = new DateTime('today');
                                    $daysLeft    = (int) $today->diff($harvestDate)->days;
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($c['name']) ?></td>
                                        <td><?= htmlspecialchars($c['variety'] ?? '—') ?></td>
                                        <td>
                                            <?php
                                            $statusBadge = ['planted'=>'primary','growing'=>'success','harvested'=>'warning','failed'=>'danger'];
                                            $b = $statusBadge[$c['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $b ?> text-capitalize"><?= htmlspecialchars($c['status']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($c['expected_harvest']) ?></td>
                                        <td>
                                            <span class="badge <?= $daysLeft <= 2 ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                                <?= $daysLeft === 0 ? 'Today!' : $daysLeft . ' day' . ($daysLeft !== 1 ? 's' : '') ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Send Button -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="fw-semibold mb-2">
                        <i class="bi bi-envelope me-2 text-success"></i>Send Reminder Emails
                    </h5>
                    <p class="text-muted small mb-3">
                        Sends a reminder email to <strong><?= htmlspecialchars($user['email']) ?></strong>
                        listing all crops due for harvest in the next 7 days.
                    </p>
                    <?php if (!empty($upcomingCrops)): ?>
                        <form method="POST" action="send_reminders.php">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-success" id="sendRemindersBtn">
                                <i class="bi bi-send me-1"></i>
                                Send Reminder Emails (<?= count($upcomingCrops) ?> crop<?= count($upcomingCrops) !== 1 ? 's' : '' ?>)
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-secondary" disabled>
                            <i class="bi bi-send me-1"></i>No crops to remind
                        </button>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
