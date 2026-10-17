<?php
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = t('dashboard');

$db       = getDB();
$uid      = (int) $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? 'User';
$role     = $_SESSION['role'] ?? 'farmer';

$inqColors = ['pending' => 'warning', 'read' => 'info', 'responded' => 'primary', 'completed' => 'success'];

if ($role === 'farmer') {
    // ── FARMER ────────────────────────────────────────────────────────────────
    $stmt = $db->prepare(
        "SELECT COUNT(*)                                        AS total,
                COALESCE(SUM(status = 'available'), 0)          AS available,
                COALESCE(SUM(growth_stage IN ('planted','growing')
                    AND expected_harvest BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY), 0) AS due_soon
           FROM crops WHERE user_id = ?"
    );
    $stmt->execute([$uid]);
    $stats = $stmt->fetch();

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM inquiries i JOIN crops c ON c.id = i.crop_id
          WHERE c.user_id = ? AND i.status = 'pending'"
    );
    $stmt->execute([$uid]);
    $pendingInquiries = (int) $stmt->fetchColumn();

    $stmt = $db->prepare(
        'SELECT c.*, cat.name AS category,
                (SELECT COUNT(*) FROM inquiries i WHERE i.crop_id = c.id) AS inquiry_count
           FROM crops c
           LEFT JOIN categories cat ON cat.id = c.category_id
          WHERE c.user_id = ?
          ORDER BY c.created_at DESC'
    );
    $stmt->execute([$uid]);
    $products = $stmt->fetchAll();

    $stmt = $db->prepare(
        'SELECT i.*, c.name AS crop_name, u.name AS buyer_name, m.body AS message
           FROM inquiries i
           JOIN crops c ON i.crop_id = c.id
           JOIN users u ON i.buyer_id = u.id
           JOIN messages m ON m.id = (SELECT MAX(id) FROM messages WHERE inquiry_id = i.id)
          WHERE c.user_id = ?
          ORDER BY m.created_at DESC LIMIT 5'
    );
    $stmt->execute([$uid]);
    $recentInquiries = $stmt->fetchAll();
} else {
    // ── BUYER ─────────────────────────────────────────────────────────────────
    $totalProducts = (int) $db->query(
        "SELECT COUNT(*) FROM crops c JOIN users u ON c.user_id = u.id
          WHERE u.role = 'farmer' AND c.status = 'available'"
    )->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM inquiries WHERE buyer_id = ?');
    $stmt->execute([$uid]);
    $myInquiries = (int) $stmt->fetchColumn();

    $countiesCount = (int) $db->query(
        "SELECT COUNT(DISTINCT county_id) FROM users WHERE role = 'farmer'"
    )->fetchColumn();

    $latestProducts = $db->query(
        "SELECT c.*, u.name AS farmer_name, co.name AS farmer_county
           FROM crops c
           JOIN users u ON c.user_id = u.id
           LEFT JOIN counties co ON co.id = u.county_id
          WHERE u.role = 'farmer' AND c.status = 'available'
          ORDER BY c.created_at DESC LIMIT 6"
    )->fetchAll();

    $stmt = $db->prepare(
        'SELECT i.*, c.name AS crop_name
           FROM inquiries i JOIN crops c ON i.crop_id = c.id
          WHERE i.buyer_id = ? ORDER BY i.created_at DESC LIMIT 5'
    );
    $stmt->execute([$uid]);
    $myRecentInq = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';

/** One stat card. */
$statCard = static function (string $icon, string $color, string $label, $value, string $extra = ''): void { ?>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle bg-<?= $color ?> bg-opacity-10 p-3 me-3">
                    <i class="bi <?= $icon ?> fs-4 text-<?= $color ?>"></i>
                </div>
                <div>
                    <div class="text-muted small"><?= e($label) ?></div>
                    <div class="fs-3 fw-bold"><?= (int) $value ?> <?= $extra ?></div>
                </div>
            </div>
        </div>
    </div>
<?php };
?>

<div class="container-fluid py-3">

<?php if ($role === 'farmer'): ?>

    <div class="dash-hero dash-hero-farmer mb-4">
        <div class="position-relative px-4 py-4 w-100 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="dash-hero-title"><?= sprintf(t('welcome_back'), e($userName)) ?> 🌾</h1>
                <p class="dash-hero-sub"><?= t('farmer_dash_sub') ?></p>
            </div>
            <a href="add_crop.php" class="btn btn-lg dash-hero-btn text-success">
                <i class="bi bi-plus-circle me-2"></i><?= t('list_new_product') ?>
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php
        $statCard('bi-box-seam', 'primary', t('total_products'), $stats['total']);
        $statCard('bi-check-circle', 'success', t('available_for_sale'), $stats['available']);
        $statCard('bi-chat-dots', 'warning', t('pending_inquiries'), $pendingInquiries,
            $pendingInquiries > 0 ? '<span class="badge bg-warning text-dark fs-6">' . e(t('new')) . '</span>' : '');
        $statCard('bi-calendar-event', 'danger', t('harvests_due_7d'), $stats['due_soon']);
        ?>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0 fw-semibold"><i class="bi bi-bag me-2 text-success"></i><?= t('my_listings') ?></h2>
            <a href="marketplace.php" class="btn btn-outline-success btn-sm"><?= t('view_marketplace') ?></a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-bag-x fs-1 text-muted"></i>
                    <p class="text-muted mt-3 mb-1"><?= t('no_products_yet') ?></p>
                    <a href="add_crop.php" class="btn btn-success mt-2"><i class="bi bi-plus-circle me-1"></i><?= t('list_first_product') ?></a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= t('photo') ?></th>
                                <th><?= t('product') ?></th>
                                <th class="d-none d-md-table-cell"><?= t('category') ?></th>
                                <th><?= t('price') ?></th>
                                <th class="d-none d-md-table-cell"><?= t('quantity') ?></th>
                                <th><?= t('status') ?></th>
                                <th class="d-none d-lg-table-cell"><?= t('inquiries') ?></th>
                                <th class="text-end"><?= t('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): $img = upload_url($p['image']); ?>
                            <tr>
                                <td>
                                    <?php if ($img): ?>
                                        <img src="<?= e($img) ?>" class="rounded" style="width:50px;height:50px;object-fit:cover;" alt="">
                                    <?php else: ?>
                                        <div class="rounded bg-success bg-opacity-10 d-flex align-items-center justify-content-center"
                                             style="width:50px;height:50px;font-size:1.2rem;" aria-hidden="true">🌿</div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold">
                                    <?= e($p['name']) ?>
                                    <?php if ($p['variety']): ?><small class="text-muted d-block fw-normal"><?= e($p['variety']) ?></small><?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell"><small class="text-muted"><?= e(t_category($p['category'])) ?></small></td>
                                <td class="small"><?= format_price($p['price'], $p['unit']) ?></td>
                                <td class="d-none d-md-table-cell"><?= $p['quantity'] !== null ? (int) $p['quantity'] . ' ' . e(t('unit_' . $p['unit'])) : '—' ?></td>
                                <td><?= status_badge($p['status']) ?> <?= stage_badge($p['growth_stage']) ?></td>
                                <td class="d-none d-lg-table-cell">
                                    <?php if ($p['inquiry_count'] > 0): ?>
                                        <a href="my_inquiries.php" class="badge bg-warning text-dark text-decoration-none">
                                            <?= sprintf(t('n_inquiries'), (int) $p['inquiry_count']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small"><?= t('none') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="edit_crop.php?id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="<?= t('edit') ?>" aria-label="<?= t('edit') ?>">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="qrcode.php?crop_id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-secondary" title="<?= t('qr_code') ?>" aria-label="<?= t('qr_code') ?>">
                                        <i class="bi bi-qr-code"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-btn"
                                            data-crop-id="<?= (int) $p['id'] ?>" data-crop-name="<?= e($p['name']) ?>"
                                            title="<?= t('delete') ?>" aria-label="<?= t('delete') ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($recentInquiries)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0 fw-semibold"><i class="bi bi-chat-dots me-2 text-warning"></i><?= t('recent_buyer_inquiries') ?></h2>
            <a href="my_inquiries.php" class="btn btn-outline-warning btn-sm"><?= t('view_all') ?></a>
        </div>
        <ul class="list-group list-group-flush">
            <?php foreach ($recentInquiries as $inq): ?>
            <li class="list-group-item list-group-item-action d-flex justify-content-between align-items-start py-3 position-relative">
                <div>
                    <div class="fw-semibold"><a href="inquiry_thread.php?id=<?= (int) $inq['id'] ?>" class="stretched-link text-reset text-decoration-none"><?= e($inq['buyer_name']) ?></a>
                        <small class="text-muted fw-normal"><?= t('re') ?>: <?= e($inq['crop_name']) ?></small>
                    </div>
                    <div class="small text-muted"><?= e(mb_substr($inq['message'], 0, 80)) ?><?= mb_strlen($inq['message']) > 80 ? '…' : '' ?></div>
                </div>
                <span class="badge bg-<?= $inqColors[$inq['status']] ?? 'secondary' ?> ms-2"><?= t('inq_' . $inq['status']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

<?php else: ?>

    <div class="dash-hero dash-hero-buyer mb-4">
        <div class="position-relative px-4 py-4 w-100 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="dash-hero-title"><?= sprintf(t('welcome'), e($userName)) ?> 🏪</h1>
                <p class="dash-hero-sub"><?= t('buyer_dash_sub') ?></p>
            </div>
            <a href="marketplace.php" class="btn btn-lg dash-hero-btn text-primary">
                <i class="bi bi-shop me-2"></i><?= t('browse_marketplace') ?>
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php
        $statCard('bi-bag-heart', 'success', t('products_available'), $totalProducts);
        $statCard('bi-chat-dots', 'primary', t('inquiries_sent'), $myInquiries);
        $statCard('bi-geo-alt', 'warning', t('counties_with_farmers'), $countiesCount);
        ?>
        <div class="col-6 col-lg-3">
            <a href="price_trends.php" class="card stat-card border-0 shadow-sm h-100 text-decoration-none text-reset">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="bi bi-graph-up fs-4 text-info"></i></div>
                    <div class="fw-semibold"><?= t('price_trends') ?> →</div>
                </div>
            </a>
        </div>
    </div>

    <h2 class="h5 fw-semibold mb-3"><?= t('latest_products') ?></h2>
    <?php if (empty($latestProducts)): ?>
        <div class="alert alert-info"><?= t('no_products_check_back') ?></div>
    <?php else: ?>
    <div class="row g-3 mb-4">
        <?php foreach ($latestProducts as $p): $img = upload_url($p['image']); ?>
        <div class="col-md-4 col-sm-6">
            <div class="card h-100 border-0 shadow-sm">
                <?php if ($img): ?>
                    <img src="<?= e($img) ?>" class="card-img-top" style="height:140px;object-fit:cover;" alt="<?= e($p['name']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="height:140px;font-size:2rem;" aria-hidden="true">🌿</div>
                <?php endif; ?>
                <div class="card-body py-2">
                    <h3 class="h6 fw-bold mb-1"><?= e($p['name']) ?></h3>
                    <small class="text-muted"><i class="bi bi-person me-1"></i><?= e($p['farmer_name']) ?></small>
                    <?php if ($p['farmer_county']): ?>
                        <span class="badge bg-success ms-1"><?= e($p['farmer_county']) ?></span>
                    <?php endif; ?>
                    <div class="fw-semibold text-success mt-1"><?= format_price($p['price'], $p['unit']) ?></div>
                </div>
                <div class="card-footer bg-transparent pt-0">
                    <a href="inquiry.php?crop_id=<?= (int) $p['id'] ?>" class="btn btn-success btn-sm w-100">
                        <i class="bi bi-chat me-1"></i><?= t('contact_farmer') ?>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($myRecentInq)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between">
            <h2 class="h5 mb-0 fw-semibold"><i class="bi bi-chat me-2"></i><?= t('my_recent_inquiries') ?></h2>
            <a href="my_inquiries.php" class="btn btn-outline-secondary btn-sm"><?= t('view_all') ?></a>
        </div>
        <ul class="list-group list-group-flush">
            <?php foreach ($myRecentInq as $inq): ?>
            <li class="list-group-item list-group-item-action d-flex justify-content-between align-items-center position-relative">
                <div>
                    <a href="inquiry_thread.php?id=<?= (int) $inq['id'] ?>" class="stretched-link text-reset text-decoration-none fw-semibold"><?= e($inq['crop_name']) ?></a>
                    <small class="text-muted d-block"><?= date('d M Y', strtotime($inq['created_at'])) ?></small>
                </div>
                <span class="badge bg-<?= $inqColors[$inq['status']] ?? 'secondary' ?>"><?= t('inq_' . $inq['status']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
<?php endif; ?>

</div>

<?php if ($role === 'farmer'): ?>
<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 text-danger" id="deleteModalTitle"><i class="bi bi-exclamation-triangle me-2"></i><?= t('delete_product') ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
            </div>
            <div class="modal-body">
                <?= t('confirm_delete_product') ?> <strong id="deleteCropName"></strong>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                <form id="deleteForm" method="POST" action="delete_crop.php">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="crop_id" id="deleteCropId">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i><?= t('delete') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.delete-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('deleteCropId').value = this.dataset.cropId;
        document.getElementById('deleteCropName').textContent = this.dataset.cropName;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
