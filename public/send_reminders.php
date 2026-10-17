<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role('farmer');
$pageTitle = t('harvest_reminders');

$db  = getDB();
$uid = (int) $_SESSION['user_id'];

// Crops still in the ground whose expected harvest is within 7 days.
// Harvested / failed crops (or crops without a tracked stage) need no reminder.
$stmt = $db->prepare(
    "SELECT * FROM crops
      WHERE user_id = ?
        AND growth_stage IN ('planted', 'growing')
        AND expected_harvest BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY
      ORDER BY expected_harvest ASC"
);
$stmt->execute([$uid]);
$upcomingCrops = $stmt->fetchAll();

$userStmt = $db->prepare('SELECT name, email FROM users WHERE id = ?');
$userStmt->execute([$uid]);
$user = $userStmt->fetch();

$remindersSent  = false;
$emailDelivered = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $items = '';
    foreach ($upcomingCrops as $c) {
        $items .= '<li><strong>' . e($c['name']) . '</strong>'
                . (!empty($c['variety']) ? ' (' . e($c['variety']) . ')' : '')
                . ' — ' . e($c['expected_harvest']) . '</li>';
    }
    $body = '<div style="font-family:sans-serif;color:#333;">'
          . '<h2 style="color:#28a745;">🌿 Harvest Reminders — AgriTrack</h2>'
          . '<p>Hi ' . e($user['name']) . ',</p>'
          . '<p>The following crops are due for harvest within the next 7 days:</p>'
          . '<ul>' . $items . '</ul>'
          . '<p>Log in to <a href="' . e($_ENV['APP_URL'] ?? '#') . '">AgriTrack</a> to update their stage.</p>'
          . '</div>';

    $emailDelivered = !empty($upcomingCrops)
        && sendEmail($user['email'], $user['name'], 'AgriTrack — Upcoming Harvest Reminders', $body);

    log_activity(
        $uid,
        'send_reminders',
        ($emailDelivered ? 'Harvest reminder email sent for ' : 'Reminder simulated (email not configured) for ')
            . count($upcomingCrops) . ' crop(s)'
    );
    $remindersSent = true;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <h1 class="h3 fw-bold mb-1 text-success"><i class="bi bi-bell me-2"></i><?= t('harvest_reminders') ?></h1>
            <p class="text-muted mb-4"><?= t('harvest_reminders_sub') ?></p>

            <?php if (!mailConfigured()): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i><?= t('email_not_configured_simulated') ?>
                </div>
            <?php endif; ?>

            <?php if ($remindersSent && !empty($upcomingCrops)): ?>
                <div class="alert <?= $emailDelivered ? 'alert-success' : 'alert-warning' ?>">
                    <i class="bi bi-send-check me-2"></i>
                    <?= $emailDelivered
                        ? sprintf(t('reminder_sent_to'), '<strong>' . e($user['email']) . '</strong>')
                        : t('reminder_simulated') ?>
                </div>
            <?php elseif ($remindersSent): ?>
                <div class="alert alert-warning"><?= t('no_harvest_due') ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h5 mb-0 fw-semibold">
                        <i class="bi bi-calendar-week me-2 text-warning"></i><?= t('crops_due_7d') ?>
                        <span class="badge bg-warning text-dark ms-2"><?= count($upcomingCrops) ?></span>
                    </h2>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($upcomingCrops)): ?>
                        <div class="text-center py-5 px-3">
                            <i class="bi bi-calendar-check fs-1 text-muted"></i>
                            <p class="text-muted mt-3 mb-0"><?= t('no_harvest_due') ?></p>
                            <p class="text-muted small mb-0"><?= t('reminders_need_stage') ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><?= t('product') ?></th>
                                        <th><?= t('variety') ?></th>
                                        <th><?= t('growth_stage') ?></th>
                                        <th><?= t('expected_harvest') ?></th>
                                        <th><?= t('days_remaining') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($upcomingCrops as $c):
                                    $daysLeft = (int) (new DateTime('today'))->diff(new DateTime($c['expected_harvest']))->days; ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($c['name']) ?></td>
                                        <td><?= e($c['variety'] ?? '—') ?></td>
                                        <td><?= stage_badge($c['growth_stage']) ?></td>
                                        <td><?= e($c['expected_harvest']) ?></td>
                                        <td>
                                            <span class="badge <?= $daysLeft <= 2 ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                                <?= $daysLeft === 0 ? t('today') : sprintf(t('n_days'), $daysLeft) ?>
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

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-2"><i class="bi bi-envelope me-2 text-success"></i><?= t('send_reminder_emails') ?></h2>
                    <p class="text-muted small mb-3"><?= sprintf(t('reminder_explainer'), '<strong>' . e($user['email']) . '</strong>') ?></p>
                    <?php if (!empty($upcomingCrops)): ?>
                        <form method="POST" action="send_reminders.php">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-success" id="sendRemindersBtn">
                                <i class="bi bi-send me-1"></i><?= t('send_reminder_emails') ?> (<?= count($upcomingCrops) ?>)
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-secondary" disabled><i class="bi bi-send me-1"></i><?= t('nothing_to_remind') ?></button>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
