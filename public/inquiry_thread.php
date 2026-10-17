<?php
/**
 * AgriTrack – inquiry_thread.php
 * Conversation between a buyer and a farmer about one product.
 * Either party can reply or mark the deal completed; the buyer can then rate.
 */
require_once __DIR__ . '/../includes/auth_check.php';

$db   = getDB();
$uid  = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'farmer';
$id   = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$thread = App\Inquiries::findForUser($db, $id, $uid);
if (!$thread) {
    flash('danger', t('inquiry_not_found'));
    header('Location: my_inquiries.php');
    exit;
}
$isFarmer = (int) $thread['farmer_id'] === $uid;
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'reply') {
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body === '' || mb_strlen($body) > 2000) {
            $errors[] = t('err_reply_length');
        } else {
            App\Inquiries::reply($db, $thread, $uid, $isFarmer ? 'farmer' : 'buyer', $body);
            log_activity($uid, 'inquiry_reply', "Replied on inquiry #{$id} ({$thread['crop_name']})");

            $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost/agri_app', '/');
            [$toEmail, $toName] = $isFarmer
                ? [$thread['buyer_email'], $thread['buyer_name']]
                : [$thread['farmer_email'], $thread['farmer_name']];
            sendEmail(
                $toEmail,
                $toName,
                'New message about ' . $thread['crop_name'],
                '<p><strong>' . e($_SESSION['name']) . '</strong> wrote:</p><blockquote>' . nl2br(e($body)) . '</blockquote>'
                . '<p><a href="' . e($appUrl . '/inquiry_thread.php?id=' . $id) . '">Open the conversation →</a></p>'
            );

            sendSms(
                $isFarmer ? ($thread['contact_phone'] ?: $thread['buyer_phone']) : $thread['farmer_phone'],
                "AgriTrack: new message from {$_SESSION['name']} about {$thread['crop_name']}: "
                    . mb_substr($body, 0, 80) . (mb_strlen($body) > 80 ? '…' : '') . " {$appUrl}/inquiry_thread.php?id={$id}"
            );

            header('Location: inquiry_thread.php?id=' . $id . '#latest');
            exit;
        }
    } elseif ($action === 'complete' && $thread['status'] !== 'completed') {
        $db->prepare("UPDATE inquiries SET status = 'completed' WHERE id = ?")->execute([$id]);
        log_activity($uid, 'inquiry_completed', "Marked inquiry #{$id} as completed");
        flash('success', t('deal_marked_complete'));
        header('Location: inquiry_thread.php?id=' . $id);
        exit;
    } elseif ($action === 'rate' && App\Inquiries::canRate($thread, $uid)) {
        $score   = (int) ($_POST['score'] ?? 0);
        $comment = trim((string) ($_POST['comment'] ?? ''));
        if ($score < 1 || $score > 5 || mb_strlen($comment) > 500) {
            $errors[] = t('err_rating_invalid');
        } else {
            $db->prepare('INSERT INTO ratings (inquiry_id, score, comment) VALUES (?, ?, ?)')
               ->execute([$id, $score, $comment !== '' ? $comment : null]);
            log_activity($uid, 'rate_farmer', "Rated {$thread['farmer_name']} {$score}/5");
            flash('success', t('rating_saved'));
            header('Location: inquiry_thread.php?id=' . $id);
            exit;
        }
    }
}

// Opening the thread marks a new inquiry as seen by the farmer
if ($isFarmer) {
    $next = App\Inquiries::statusAfterFarmerView($thread['status']);
    if ($next !== $thread['status']) {
        $db->prepare('UPDATE inquiries SET status = ? WHERE id = ?')->execute([$next, $id]);
        $thread['status'] = $next;
    }
}

$messages = App\Inquiries::messages($db, $id);

// Details of the other party
$other = $isFarmer
    ? ['label' => t('buyer'), 'name' => $thread['buyer_name'], 'email' => $thread['buyer_email'],
       'phone' => $thread['contact_phone'] ?: $thread['buyer_phone'], 'county' => $thread['buyer_county']]
    : ['label' => t('farmer'), 'name' => $thread['farmer_name'], 'email' => $thread['farmer_email'],
       'phone' => $thread['farmer_phone'], 'county' => $thread['farmer_county']];

$statusColors = ['pending' => 'warning', 'read' => 'info', 'responded' => 'primary', 'completed' => 'success'];
$pageTitle    = t('conversation') . ': ' . $thread['crop_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="my_inquiries.php" class="text-success"><?= t('my_inquiries') ?></a></li>
        <li class="breadcrumb-item active"><?= e($thread['crop_name']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <div class="col-lg-8 order-2 order-lg-1">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h1 class="h5 mb-0"><i class="bi bi-chat-left-text me-2 text-success"></i><?= e($thread['crop_name']) ?></h1>
                <span class="badge bg-<?= $statusColors[$thread['status']] ?>"><?= t('inq_' . $thread['status']) ?></span>
            </div>
            <div class="card-body chat-body">
                <?php foreach ($messages as $i => $m): $mine = (int) $m['sender_id'] === $uid; ?>
                    <div class="d-flex mb-3 <?= $mine ? 'justify-content-end' : '' ?>" <?= $i === count($messages) - 1 ? 'id="latest"' : '' ?>>
                        <div class="chat-bubble <?= $mine ? 'chat-mine' : 'chat-theirs' ?>">
                            <div class="small fw-semibold mb-1"><?= $mine ? t('you') : e($m['sender_name']) ?></div>
                            <div><?= nl2br(e($m['body'])) ?></div>
                            <div class="chat-time"><?= date('d M Y, H:i', strtotime($m['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card-footer bg-white">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
                <?php endif; ?>
                <form method="POST" action="inquiry_thread.php" class="d-flex gap-2 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="reply">
                    <label for="body" class="visually-hidden"><?= t('your_message') ?></label>
                    <textarea name="body" id="body" class="form-control" rows="2" maxlength="2000" required
                              placeholder="<?= t('write_reply') ?>"></textarea>
                    <button type="submit" class="btn btn-success"><i class="bi bi-send"></i><span class="visually-hidden"><?= t('send') ?></span></button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4 order-1 order-lg-2">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase small mb-2"><?= e($other['label']) ?></h2>
                <div class="fw-semibold">
                    <?php if (!$isFarmer): ?>
                        <a href="farmer.php?id=<?= (int) $thread['farmer_id'] ?>" class="text-reset"><?= e($other['name']) ?></a>
                    <?php else: ?>
                        <?= e($other['name']) ?>
                    <?php endif; ?>
                </div>
                <?php if ($other['county']): ?><div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($other['county']) ?></div><?php endif; ?>
                <ul class="list-unstyled small mt-2 mb-0">
                    <li><i class="bi bi-envelope me-1"></i><a href="mailto:<?= e($other['email']) ?>"><?= e($other['email']) ?></a></li>
                    <?php if ($other['phone']): ?>
                        <li><i class="bi bi-telephone me-1"></i><a href="tel:<?= e($other['phone']) ?>"><?= e($other['phone']) ?></a></li>
                    <?php endif; ?>
                </ul>
                <div class="mt-2"><?= whatsapp_button($other['phone'], sprintf(t('wa_about'), $thread['crop_name'])) ?></div>
            </div>
        </div>

        <?php if ($thread['status'] !== 'completed'): ?>
            <form method="POST" action="inquiry_thread.php" class="mb-3">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="action" value="complete">
                <button type="submit" class="btn btn-outline-success w-100"><i class="bi bi-check2-circle me-1"></i><?= t('mark_deal_complete') ?></button>
                <div class="form-text"><?= t('mark_deal_complete_hint') ?></div>
            </form>
        <?php endif; ?>

        <?php if ($thread['rating_score'] !== null): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-1"><?= t('rating') ?></h2>
                    <div class="text-warning fs-5" aria-label="<?= (int) $thread['rating_score'] ?>/5"><?= str_repeat('★', (int) $thread['rating_score']) . str_repeat('☆', 5 - (int) $thread['rating_score']) ?></div>
                    <?php if ($thread['rating_comment']): ?><p class="small mb-0 mt-1">“<?= e($thread['rating_comment']) ?>”</p><?php endif; ?>
                </div>
            </div>
        <?php elseif (App\Inquiries::canRate($thread, $uid)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6"><?= sprintf(t('rate_farmer'), e($thread['farmer_name'])) ?></h2>
                    <form method="POST" action="inquiry_thread.php">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="action" value="rate">
                        <fieldset class="star-input mb-2">
                            <legend class="visually-hidden"><?= t('rating') ?></legend>
                            <?php for ($s = 5; $s >= 1; $s--): ?>
                                <input type="radio" name="score" id="star<?= $s ?>" value="<?= $s ?>" required>
                                <label for="star<?= $s ?>" title="<?= $s ?>/5">★</label>
                            <?php endfor; ?>
                        </fieldset>
                        <label for="comment" class="visually-hidden"><?= t('comment') ?></label>
                        <textarea name="comment" id="comment" class="form-control form-control-sm mb-2" rows="2" maxlength="500"
                                  placeholder="<?= t('rating_comment_ph') ?>"></textarea>
                        <button type="submit" class="btn btn-warning btn-sm w-100"><?= t('submit_rating') ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
