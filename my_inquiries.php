<?php
/**
 * AgriTrack – my_inquiries.php
 * Farmers see received inquiries; Buyers see sent inquiries.
 */
require_once 'includes/auth_check.php';
$db       = getDB();
$uid      = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'farmer';
$pageTitle = 'My Inquiries';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// --- Handle "Mark as Responded" (farmer only) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_responded'])) {
    csrf_verify();
    $iid = (int)($_POST['inquiry_id'] ?? 0);
    if ($iid && $userRole === 'farmer') {
        $upd = $db->prepare("UPDATE inquiries SET status='responded' WHERE id=? AND farmer_id=?");
        $upd->execute([$iid, $uid]);
        log_activity($uid, 'inquiry_responded', "Marked inquiry #{$iid} as responded");
        flash('success', 'Inquiry marked as responded.');
    }
    header('Location: my_inquiries.php');
    exit;
}

if ($userRole === 'farmer') {
    // Mark all pending as 'read' when farmer opens the page
    $db->prepare("UPDATE inquiries SET status='read' WHERE farmer_id=? AND status='pending'")->execute([$uid]);

    // Count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM inquiries WHERE farmer_id=?");
    $countStmt->execute([$uid]);
    $total = (int)$countStmt->fetchColumn();

    $pendingStmt = $db->prepare("SELECT COUNT(*) FROM inquiries WHERE farmer_id=? AND status='pending'");
    $pendingStmt->execute([$uid]);
    $pending = (int)$pendingStmt->fetchColumn();

    $respondedStmt = $db->prepare("SELECT COUNT(*) FROM inquiries WHERE farmer_id=? AND status='responded'");
    $respondedStmt->execute([$uid]);
    $responded = (int)$respondedStmt->fetchColumn();

    // Fetch
    $stmt = $db->prepare(
        "SELECT i.*, c.name AS crop_name, u.name AS buyer_name,
                u.email AS buyer_email, u.phone AS buyer_phone, u.county AS buyer_county
         FROM inquiries i
         JOIN crops c ON i.crop_id = c.id
         JOIN users u ON i.buyer_id = u.id
         WHERE i.farmer_id = ?
         ORDER BY i.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->bindValue(1, $uid, PDO::PARAM_INT);
    $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $inquiries = $stmt->fetchAll();

} else {
    // Buyer
    $countStmt = $db->prepare("SELECT COUNT(*) FROM inquiries WHERE buyer_id=?");
    $countStmt->execute([$uid]);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT i.*, c.name AS crop_name, u.name AS farmer_name,
                u.email AS farmer_email, u.phone AS farmer_phone, u.county AS farmer_county
         FROM inquiries i
         JOIN crops c ON i.crop_id = c.id
         JOIN users u ON i.farmer_id = u.id
         WHERE i.buyer_id = ?
         ORDER BY i.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->bindValue(1, $uid, PDO::PARAM_INT);
    $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $inquiries = $stmt->fetchAll();
}

$totalPages = max(1, (int)ceil($total / $perPage));

$statusColors = ['pending' => 'warning', 'read' => 'info', 'responded' => 'success'];
$statusLabels = ['pending' => '⏳ Pending', 'read' => '👁 Seen', 'responded' => '✅ Responded'];

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-chat-dots me-2"></i>
        <?= $userRole === 'farmer' ? 'Buyer Inquiries' : 'My Sent Inquiries' ?>
    </h4>
    <?php if ($userRole === 'buyer'): ?>
        <a href="marketplace.php" class="btn btn-success btn-sm">
            <i class="bi bi-shop me-1"></i>Browse Marketplace
        </a>
    <?php endif; ?>
</div>

<!-- Stats (farmer only) -->
<?php if ($userRole === 'farmer'): ?>
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="display-6 fw-bold text-dark"><?= $total ?></div>
                <div class="small text-muted">Total Inquiries</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="display-6 fw-bold text-warning"><?= $pending ?></div>
                <div class="small text-muted">Pending</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="display-6 fw-bold text-success"><?= $responded ?></div>
                <div class="small text-muted">Responded</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Buyer status legend -->
<?php if ($userRole === 'buyer' && !empty($inquiries)): ?>
<div class="alert alert-info small mb-3">
    <strong>Status guide:</strong>
    <span class="badge bg-warning text-dark">⏳ Pending</span> = Waiting for farmer to see it &nbsp;
    <span class="badge bg-info">👁 Seen</span> = Farmer has viewed your inquiry &nbsp;
    <span class="badge bg-success">✅ Responded</span> = Farmer replied — check your email!
</div>
<?php endif; ?>

<!-- Empty state -->
<?php if (empty($inquiries)): ?>
<div class="text-center py-5 text-muted">
    <i class="bi bi-chat-square-dots display-4 d-block mb-3"></i>
    <?php if ($userRole === 'buyer'): ?>
        <p>You haven't sent any inquiries yet.</p>
        <a href="marketplace.php" class="btn btn-success">Browse Marketplace</a>
    <?php else: ?>
        <p>No inquiries from buyers yet. Make sure your products are listed!</p>
        <a href="add_crop.php" class="btn btn-success">Add a Product</a>
    <?php endif; ?>
</div>

<?php elseif ($userRole === 'farmer'): ?>
<!-- Farmer view: accordion table -->
<div class="accordion" id="inquiriesAccordion">
    <?php foreach ($inquiries as $i => $inq): ?>
    <div class="accordion-item mb-2 border shadow-sm rounded">
        <h2 class="accordion-header" id="head<?= $i ?>">
            <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?> rounded"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapse<?= $i ?>">
                <div class="d-flex w-100 justify-content-between align-items-center pe-3 flex-wrap gap-2">
                    <div>
                        <strong><?= htmlspecialchars($inq['buyer_name']) ?></strong>
                        <span class="text-muted small ms-2">re: <?= htmlspecialchars($inq['crop_name']) ?></span>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-<?= $statusColors[$inq['status']] ?? 'secondary' ?>">
                            <?= $statusLabels[$inq['status']] ?? $inq['status'] ?>
                        </span>
                        <small class="text-muted"><?= date('d M Y', strtotime($inq['created_at'])) ?></small>
                    </div>
                </div>
            </button>
        </h2>
        <div id="collapse<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>"
             data-bs-parent="#inquiriesAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <p class="mb-1"><strong>Message:</strong></p>
                        <div class="bg-light p-3 rounded"><?= nl2br(htmlspecialchars($inq['message'])) ?></div>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1"><strong>Buyer Contact:</strong></p>
                        <ul class="list-unstyled small">
                            <li><i class="bi bi-envelope me-1"></i>
                                <a href="mailto:<?= htmlspecialchars($inq['buyer_email']) ?>">
                                    <?= htmlspecialchars($inq['buyer_email']) ?>
                                </a>
                            </li>
                            <?php if ($inq['buyer_phone']): ?>
                            <li><i class="bi bi-phone me-1"></i>
                                <a href="tel:<?= htmlspecialchars($inq['buyer_phone']) ?>">
                                    <?= htmlspecialchars($inq['buyer_phone']) ?>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if ($inq['buyer_county']): ?>
                            <li><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($inq['buyer_county']) ?></li>
                            <?php endif; ?>
                        </ul>
                        <?php if ($inq['status'] !== 'responded'): ?>
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="csrf_token"   value="<?= csrf_token() ?>">
                            <input type="hidden" name="inquiry_id"   value="<?= $inq['id'] ?>">
                            <input type="hidden" name="mark_responded" value="1">
                            <button type="submit" class="btn btn-success btn-sm w-100">
                                <i class="bi bi-check2-circle me-1"></i>Mark as Responded
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<!-- Buyer view: cards -->
<div class="row g-3">
    <?php foreach ($inquiries as $inq): ?>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="fw-bold mb-0"><?= htmlspecialchars($inq['crop_name']) ?></h6>
                        <small class="text-muted">
                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($inq['farmer_name']) ?>
                            <?php if ($inq['farmer_county']): ?>
                                · <?= htmlspecialchars($inq['farmer_county']) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    <span class="badge bg-<?= $statusColors[$inq['status']] ?? 'secondary' ?>">
                        <?= $statusLabels[$inq['status']] ?? $inq['status'] ?>
                    </span>
                </div>
                <p class="small text-muted mb-2">
                    <strong>Your message:</strong><br>
                    <?= htmlspecialchars(mb_substr($inq['message'], 0, 120)) ?><?= mb_strlen($inq['message']) > 120 ? '…' : '' ?>
                </p>
                <?php if ($inq['status'] === 'responded'): ?>
                <div class="alert alert-success py-1 px-2 small mb-0">
                    <i class="bi bi-check-circle me-1"></i>
                    Farmer has responded — check your email <strong><?= htmlspecialchars($_SESSION['email']) ?></strong>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent small text-muted">
                Sent <?= date('d M Y, g:i a', strtotime($inq['created_at'])) ?>
                <?php if ($inq['farmer_phone']): ?>
                · <a href="tel:<?= htmlspecialchars($inq['farmer_phone']) ?>">
                    <i class="bi bi-phone me-1"></i><?= htmlspecialchars($inq['farmer_phone']) ?>
                  </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
