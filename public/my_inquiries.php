<?php
/**
 * AgriTrack – my_inquiries.php
 * Conversation list: farmers see inquiries about their products,
 * buyers see the inquiries they started.
 */
require_once __DIR__ . '/../includes/auth_check.php';
$db        = getDB();
$uid       = (int) $_SESSION['user_id'];
$isFarmer  = ($_SESSION['role'] ?? 'farmer') === 'farmer';
$pageTitle = t('my_inquiries');

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

// Which side of the thread "me" is on (column names are fixed, not user input)
$mineCol  = $isFarmer ? 'farmer_id' : 'buyer_id';
$otherCol = $isFarmer ? 'buyer_name' : 'farmer_name';

$counts = $db->prepare("SELECT status, COUNT(*) FROM v_inquiry_threads WHERE $mineCol = ? GROUP BY status");
$counts->execute([$uid]);
$byStatus = $counts->fetchAll(PDO::FETCH_KEY_PAIR) + ['pending' => 0, 'read' => 0, 'responded' => 0, 'completed' => 0];
$total    = array_sum($byStatus);

$stmt = $db->prepare(
    "SELECT id, status, crop_name, $otherCol AS other_name,
            last_body, last_message_at AS last_at, last_sender_id AS last_sender,
            message_count AS msg_count, rating
       FROM v_inquiry_threads
      WHERE $mineCol = ?
      ORDER BY last_message_at DESC, id DESC
      LIMIT ? OFFSET ?"
);
$stmt->bindValue(1, $uid, PDO::PARAM_INT);
$stmt->bindValue(2, $perPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$threads = $stmt->fetchAll();

$totalPages   = max(1, (int) ceil($total / $perPage));
$statusColors = ['pending' => 'warning', 'read' => 'info', 'responded' => 'primary', 'completed' => 'success'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0"><i class="bi bi-chat-dots me-2"></i><?= $isFarmer ? t('buyer_inquiries') : t('my_sent_inquiries') ?></h1>
    <?php if (!$isFarmer): ?>
        <a href="marketplace.php" class="btn btn-success btn-sm"><i class="bi bi-shop me-1"></i><?= t('browse_marketplace') ?></a>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <?php foreach (['pending', 'read', 'responded', 'completed'] as $st): ?>
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-<?= $statusColors[$st] ?>"><?= (int) $byStatus[$st] ?></div>
                <div class="small text-muted"><?= t('inq_' . $st) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($threads)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-chat-square-dots display-4 d-block mb-3"></i>
        <?php if ($isFarmer): ?>
            <p><?= t('no_inquiries_farmer') ?></p>
            <a href="add_crop.php" class="btn btn-success"><?= t('list_product') ?></a>
        <?php else: ?>
            <p><?= t('no_inquiries_buyer') ?></p>
            <a href="marketplace.php" class="btn btn-success"><?= t('browse_marketplace') ?></a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="list-group shadow-sm">
        <?php foreach ($threads as $th):
            $needsAttention = $isFarmer ? $th['status'] === 'pending' : $th['status'] === 'responded'; ?>
            <a href="inquiry_thread.php?id=<?= (int) $th['id'] ?>"
               class="list-group-item list-group-item-action py-3 <?= $needsAttention ? 'list-group-item-warning' : '' ?>">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <div class="fw-semibold">
                            <?= e($th['other_name']) ?>
                            <span class="text-muted fw-normal small ms-1"><?= t('re') ?>: <?= e($th['crop_name']) ?></span>
                        </div>
                        <div class="small text-muted text-truncate">
                            <?= (int) $th['last_sender'] === $uid ? t('you') . ': ' : '' ?><?= e(mb_substr((string) $th['last_body'], 0, 120)) ?>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                        <span class="badge bg-<?= $statusColors[$th['status']] ?>"><?= t('inq_' . $th['status']) ?></span>
                        <?php if ($th['rating'] !== null): ?><div class="text-warning small"><?= str_repeat('★', (int) $th['rating']) ?></div><?php endif; ?>
                        <div class="small text-muted"><?= $th['last_at'] ? date('d M, H:i', strtotime($th['last_at'])) : '' ?> · <i class="bi bi-chat"></i> <?= (int) $th['msg_count'] ?></div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-4" aria-label="<?= t('pagination') ?>">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
