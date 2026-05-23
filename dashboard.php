<?php
require_once 'includes/auth_check.php';
$pageTitle = 'Dashboard';

$db       = getDB();
$uid      = (int)$_SESSION['user_id'];
$userName = htmlspecialchars($_SESSION['name'] ?? 'User');
$role     = $_SESSION['role'] ?? 'farmer';

// ── FARMER STATS ──────────────────────────────────────────────────────────────
if ($role === 'farmer') {

    $stmt = $db->prepare("SELECT COUNT(*) FROM crops WHERE user_id=?");
    $stmt->execute([$uid]);
    $totalProducts = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM crops WHERE user_id=? AND status='available'");
    $stmt->execute([$uid]);
    $availableProducts = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM inquiries WHERE farmer_id=? AND status='pending'");
    $stmt->execute([$uid]);
    $pendingInquiries = (int)$stmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT c.*, COALESCE((SELECT COUNT(*) FROM inquiries WHERE crop_id=c.id),0) AS inquiry_count
         FROM crops c WHERE c.user_id=? ORDER BY c.created_at DESC"
    );
    $stmt->execute([$uid]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $recentInqStmt = $db->prepare(
        "SELECT i.*, c.name AS crop_name, u.name AS buyer_name, u.email AS buyer_email, u.phone AS buyer_phone
         FROM inquiries i
         JOIN crops c ON i.crop_id=c.id
         JOIN users u ON i.buyer_id=u.id
         WHERE i.farmer_id=? ORDER BY i.created_at DESC LIMIT 5"
    );
    $recentInqStmt->execute([$uid]);
    $recentInquiries = $recentInqStmt->fetchAll();

// ── BUYER STATS ────────────────────────────────────────────────────────────────
} else {

    $stmt = $db->query("SELECT COUNT(*) FROM crops c JOIN users u ON c.user_id=u.id WHERE u.role='farmer'");
    $totalProducts = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM inquiries WHERE buyer_id=?");
    $stmt->execute([$uid]);
    $myInquiries = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(DISTINCT county) FROM users WHERE role='farmer' AND county IS NOT NULL AND county!=''");
    $countiesCount = (int)$stmt->fetchColumn();

    $latestStmt = $db->query(
        "SELECT c.*, u.name AS farmer_name, u.county AS farmer_county
         FROM crops c JOIN users u ON c.user_id=u.id
         WHERE u.role='farmer' AND c.status='available'
         ORDER BY c.created_at DESC LIMIT 6"
    );
    $latestProducts = $latestStmt->fetchAll();

    $myInqStmt = $db->prepare(
        "SELECT i.*, c.name AS crop_name
         FROM inquiries i JOIN crops c ON i.crop_id=c.id
         WHERE i.buyer_id=? ORDER BY i.created_at DESC LIMIT 5"
    );
    $myInqStmt->execute([$uid]);
    $myRecentInq = $myInqStmt->fetchAll();
}

require_once 'includes/header.php';

$statusBadge = ['available'=>'success','out_of_stock'=>'secondary','planted'=>'primary','growing'=>'success','harvested'=>'warning','failed'=>'danger'];
?>

<div class="container-fluid py-3">

<?php if ($role === 'farmer'): ?>
    <!-- ═══════════════════ FARMER DASHBOARD ═══════════════════ -->

    <!-- Hero Banner -->
    <div class="dash-hero mb-4" style="
        background: url('assets/farm_hero_bg.png') center center / cover no-repeat;
        border-radius: 16px;
        overflow: hidden;
        position: relative;
        min-height: 220px;
        display: flex;
        align-items: center;
    ">
        <!-- Dark green overlay -->
        <div style="
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(10,50,20,0.82) 40%, rgba(20,80,40,0.55));
        "></div>
        <div class="position-relative px-4 py-4 w-100">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="fw-bold mb-1" style="color:#fff;font-size:1.8rem;text-shadow:0 2px 8px rgba(0,0,0,.4);">
                        Welcome back, <?= $userName ?>! 🌾
                    </h1>
                    <p style="color:rgba(255,255,255,.8);margin:0;font-size:1rem;">
                        Manage your product listings and respond to buyer inquiries.
                    </p>
                </div>
                <a href="add_crop.php" class="btn btn-lg" style="
                    background:#fff; color:#155724; font-weight:700;
                    border-radius:50px; padding:10px 28px;
                    box-shadow:0 4px 15px rgba(0,0,0,.25);
                ">
                    <i class="bi bi-plus-circle me-2"></i>List New Product
                </a>
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                        <i class="bi bi-box-seam fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Products Listed</div>
                        <div class="fs-3 fw-bold"><?= $totalProducts ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                        <i class="bi bi-check-circle fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Available for Sale</div>
                        <div class="fs-3 fw-bold"><?= $availableProducts ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                        <i class="bi bi-chat-dots fs-4 text-warning"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending Inquiries</div>
                        <div class="fs-3 fw-bold">
                            <?= $pendingInquiries ?>
                            <?php if ($pendingInquiries > 0): ?>
                                <span class="badge bg-warning text-dark fs-6">New</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- My Products Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-bag me-2 text-success"></i>My Product Listings</h5>
            <a href="marketplace.php" class="btn btn-outline-success btn-sm">View Marketplace</a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-bag-x fs-1 text-muted"></i>
                    <p class="text-muted mt-3 mb-1">No products listed yet.</p>
                    <a href="add_crop.php" class="btn btn-success mt-2">
                        <i class="bi bi-plus-circle me-1"></i>List Your First Product
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Photo</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Qty</th>
                                <th>Status</th>
                                <th>Inquiries</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($p['image']) && file_exists('uploads/'.$p['image'])): ?>
                                        <img src="uploads/<?= htmlspecialchars($p['image']) ?>"
                                             class="rounded" style="width:50px;height:50px;object-fit:cover;">
                                    <?php else: ?>
                                        <div class="rounded bg-success bg-opacity-10 d-flex align-items-center justify-content-center"
                                             style="width:50px;height:50px;font-size:1.2rem;">🌿</div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold"><?= htmlspecialchars($p['name']) ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($p['category'] ?? '—') ?></small></td>
                                <td>
                                    <?php if ($p['price'] !== null): ?>
                                        <span class="fw-semibold">KSh <?= number_format($p['price'], 2) ?></span>
                                        <small class="text-muted d-block">per <?= htmlspecialchars($p['unit'] ?? 'unit') ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">Negotiable</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $p['quantity'] !== null ? htmlspecialchars($p['quantity']).' '.htmlspecialchars($p['unit']??'') : '—' ?></td>
                                <td>
                                    <span class="badge bg-<?= $statusBadge[$p['status']] ?? 'secondary' ?>">
                                        <?= $p['status'] === 'available' ? '✅ Available' : '❌ Out of Stock' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($p['inquiry_count'] > 0): ?>
                                        <a href="my_inquiries.php" class="badge bg-warning text-dark text-decoration-none">
                                            <?= $p['inquiry_count'] ?> inquiry<?= $p['inquiry_count']>1?'s':'' ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="edit_crop.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="qrcode.php?crop_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary me-1" title="QR Code">
                                        <i class="bi bi-qr-code"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-btn"
                                            data-crop-id="<?= $p['id'] ?>"
                                            data-crop-name="<?= htmlspecialchars($p['name']) ?>" title="Delete">
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

    <!-- Recent Inquiries (farmer) -->
    <?php if (!empty($recentInquiries)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-chat-dots me-2 text-warning"></i>Recent Buyer Inquiries</h5>
            <a href="my_inquiries.php" class="btn btn-outline-warning btn-sm">View All</a>
        </div>
        <ul class="list-group list-group-flush">
            <?php foreach ($recentInquiries as $inq): ?>
            <li class="list-group-item d-flex justify-content-between align-items-start py-3">
                <div>
                    <div class="fw-semibold"><?= htmlspecialchars($inq['buyer_name']) ?>
                        <small class="text-muted fw-normal">re: <?= htmlspecialchars($inq['crop_name']) ?></small>
                    </div>
                    <div class="small text-muted"><?= htmlspecialchars(mb_substr($inq['message'], 0, 80)) ?>…</div>
                    <?php if ($inq['buyer_phone']): ?>
                        <small><i class="bi bi-phone me-1"></i><?= htmlspecialchars($inq['buyer_phone']) ?></small>
                    <?php endif; ?>
                </div>
                <span class="badge bg-<?= ['pending'=>'warning','read'=>'info','responded'=>'success'][$inq['status']]??'secondary' ?> ms-2">
                    <?= ucfirst($inq['status']) ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>


<?php else: ?>
    <!-- ═══════════════════ BUYER DASHBOARD ═══════════════════ -->

    <!-- Hero Banner (buyer) -->
    <div class="dash-hero mb-4" style="
        background: url('assets/farm_hero_bg.png') center 60% / cover no-repeat;
        border-radius: 16px;
        overflow: hidden;
        position: relative;
        min-height: 220px;
        display: flex;
        align-items: center;
    ">
        <div style="
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(10,30,60,0.82) 40%, rgba(20,60,100,0.55));
        "></div>
        <div class="position-relative px-4 py-4 w-100">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="fw-bold mb-1" style="color:#fff;font-size:1.8rem;text-shadow:0 2px 8px rgba(0,0,0,.4);">
                        Welcome, <?= $userName ?>! 🏪
                    </h1>
                    <p style="color:rgba(255,255,255,.8);margin:0;font-size:1rem;">
                        Fresh produce from Kenyan farmers, directly to your business.
                    </p>
                </div>
                <a href="marketplace.php" class="btn btn-lg" style="
                    background:#fff; color:#0d3b6e; font-weight:700;
                    border-radius:50px; padding:10px 28px;
                    box-shadow:0 4px 15px rgba(0,0,0,.25);
                ">
                    <i class="bi bi-shop me-2"></i>Browse Marketplace
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                        <i class="bi bi-bag-heart fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Products Available</div>
                        <div class="fs-3 fw-bold"><?= $totalProducts ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                        <i class="bi bi-chat-dots fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="text-muted small">My Inquiries Sent</div>
                        <div class="fs-3 fw-bold"><?= $myInquiries ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                        <i class="bi bi-geo-alt fs-4 text-warning"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Counties with Farmers</div>
                        <div class="fs-3 fw-bold"><?= $countiesCount ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Latest Products -->
    <h5 class="fw-semibold mb-3">Latest Products from Farmers</h5>
    <?php if (empty($latestProducts)): ?>
        <div class="alert alert-info">No products listed yet. Check back soon!</div>
    <?php else: ?>
    <div class="row g-3 mb-4">
        <?php foreach ($latestProducts as $p): ?>
        <div class="col-md-4 col-sm-6">
            <div class="card h-100 border-0 shadow-sm">
                <?php if (!empty($p['image']) && file_exists('uploads/'.$p['image'])): ?>
                    <img src="uploads/<?= htmlspecialchars($p['image']) ?>"
                         class="card-img-top" style="height:140px;object-fit:cover;">
                <?php else: ?>
                    <div class="bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="height:140px;font-size:2rem;">🌿</div>
                <?php endif; ?>
                <div class="card-body py-2">
                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($p['name']) ?></h6>
                    <small class="text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($p['farmer_name']) ?></small>
                    <?php if ($p['farmer_county']): ?>
                        <span class="badge bg-success ms-1"><?= htmlspecialchars($p['farmer_county']) ?></span>
                    <?php endif; ?>
                    <?php if ($p['price'] !== null): ?>
                        <div class="fw-semibold text-success mt-1">KSh <?= number_format($p['price'],2) ?> / <?= htmlspecialchars($p['unit']??'unit') ?></div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent pt-0">
                    <a href="inquiry.php?crop_id=<?= $p['id'] ?>" class="btn btn-success btn-sm w-100">
                        <i class="bi bi-chat me-1"></i>Contact Farmer
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- My recent inquiries -->
    <?php if (!empty($myRecentInq)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-chat me-2"></i>My Recent Inquiries</h5>
            <a href="my_inquiries.php" class="btn btn-outline-secondary btn-sm">View All</a>
        </div>
        <ul class="list-group list-group-flush">
            <?php
            $statusColors = ['pending'=>'warning','read'=>'info','responded'=>'success'];
            foreach ($myRecentInq as $inq): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-semibold"><?= htmlspecialchars($inq['crop_name']) ?></span>
                    <small class="text-muted d-block"><?= date('d M Y', strtotime($inq['created_at'])) ?></small>
                </div>
                <span class="badge bg-<?= $statusColors[$inq['status']]??'secondary' ?>">
                    <?= ucfirst($inq['status']) ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
<?php endif; ?>

</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to remove <strong id="deleteCropName"></strong> from your listings? This cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" action="delete_crop.php">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="crop_id"   id="deleteCropId">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.delete-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('deleteCropId').value       = this.dataset.cropId;
        document.getElementById('deleteCropName').textContent = this.dataset.cropName;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
