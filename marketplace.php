<?php
/**
 * AgriTrack – marketplace.php
 * Browse all farmers' listed products. Buyers can contact farmers.
 */
require_once 'includes/auth_check.php';
$db        = getDB();
$uid       = (int)$_SESSION['user_id'];
$userRole  = $_SESSION['role'] ?? 'farmer';
$pageTitle = 'Marketplace';

// --- Filters ---
$filterCounty = trim($_GET['county'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$search       = trim($_GET['q']      ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 12;
$offset       = ($page - 1) * $perPage;

// --- Build query ---
$where  = ["u.role = 'farmer'"];
$params = [];

if ($search !== '') {
    $where[]          = '(c.name LIKE :q OR c.variety LIKE :q2 OR u.name LIKE :q3)';
    $params[':q']     = "%{$search}%";
    $params[':q2']    = "%{$search}%";
    $params[':q3']    = "%{$search}%";
}
if ($filterCounty !== '') {
    $where[]             = 'u.county = :county';
    $params[':county']   = $filterCounty;
}
$validStatuses = ['planted','growing','harvested','failed'];
if ($filterStatus !== '' && in_array($filterStatus, $validStatuses)) {
    $where[]             = 'c.status = :status';
    $params[':status']   = $filterStatus;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Count
$countSQL  = "SELECT COUNT(*) FROM crops c JOIN users u ON c.user_id = u.id $whereSQL";
$countStmt = $db->prepare($countSQL);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages    = max(1, (int)ceil($totalProducts / $perPage));

// Products
$sql   = "SELECT c.*, u.name AS farmer_name, u.county AS farmer_county,
                 u.phone AS farmer_phone, u.id AS farmer_user_id
          FROM crops c
          JOIN users u ON c.user_id = u.id
          $whereSQL
          ORDER BY c.created_at DESC
          LIMIT :limit OFFSET :offset";
$stmt  = $db->prepare($sql);
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Counties for filter
$countiesStmt = $db->query("SELECT DISTINCT county FROM users WHERE role='farmer' AND county IS NOT NULL AND county != '' ORDER BY county");
$counties     = $countiesStmt->fetchAll(PDO::FETCH_COLUMN);

$statusColors = ['planted'=>'primary','growing'=>'success','harvested'=>'warning','failed'=>'danger'];

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">🌾 AgriTrack Marketplace</h4>
    <span class="badge bg-success fs-6"><?= $totalProducts ?> product<?= $totalProducts !== 1 ? 's' : '' ?> listed</span>
</div>

<!-- Filter Bar -->
<form method="GET" class="card card-body mb-4 p-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-semibold mb-1">Search</label>
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="Crop name, farmer…" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">County</label>
            <select name="county" class="form-select form-select-sm">
                <option value="">All Counties</option>
                <?php foreach ($counties as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $filterCounty === $c ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="planted"   <?= $filterStatus==='planted'   ? 'selected':'' ?>>Planted</option>
                <option value="growing"   <?= $filterStatus==='growing'   ? 'selected':'' ?>>Growing</option>
                <option value="harvested" <?= $filterStatus==='harvested' ? 'selected':'' ?>>Harvested</option>
                <option value="failed"    <?= $filterStatus==='failed'    ? 'selected':'' ?>>Failed</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-success btn-sm flex-grow-1">
                <i class="bi bi-search me-1"></i>Filter
            </button>
            <a href="marketplace.php" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
    </div>
</form>

<!-- Product Grid -->
<?php if (empty($products)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox display-4 d-block mb-3"></i>
        <p>No products found. Try adjusting your filters.</p>
        <?php if ($filterCounty || $filterStatus || $search): ?>
            <a href="marketplace.php" class="btn btn-outline-success btn-sm">View all products</a>
        <?php endif; ?>
    </div>
<?php else: ?>
<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4 mb-4">
    <?php foreach ($products as $p): ?>
    <div class="col">
        <div class="card h-100 shadow-sm">
            <!-- Image -->
            <?php if ($p['image'] && file_exists("uploads/{$p['image']}")): ?>
                <img src="uploads/<?= htmlspecialchars($p['image']) ?>"
                     class="card-img-top" style="height:180px;object-fit:cover;"
                     alt="<?= htmlspecialchars($p['name']) ?>">
            <?php else: ?>
                <div class="bg-success bg-opacity-10 d-flex align-items-center justify-content-center"
                     style="height:180px;font-size:3rem;">🌿</div>
            <?php endif; ?>

            <div class="card-body pb-1">
                <h6 class="card-title fw-bold mb-1"><?= htmlspecialchars($p['name']) ?></h6>
                <?php if ($p['variety']): ?>
                    <small class="text-muted"><?= htmlspecialchars($p['variety']) ?></small><br>
                <?php endif; ?>
                <small class="text-muted">
                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($p['farmer_name']) ?>
                </small>
                <div class="mt-2 d-flex gap-1 flex-wrap">
                    <?php if ($p['farmer_county']): ?>
                        <span class="badge bg-success"><?= htmlspecialchars($p['farmer_county']) ?></span>
                    <?php endif; ?>
                    <span class="badge bg-<?= $statusColors[$p['status']] ?? 'secondary' ?>">
                        <?= htmlspecialchars(ucfirst($p['status'])) ?>
                    </span>
                </div>
                <?php if ($p['notes']): ?>
                    <p class="card-text small text-muted mt-2 mb-0">
                        <?= htmlspecialchars(mb_substr($p['notes'], 0, 100)) ?><?= mb_strlen($p['notes']) > 100 ? '…' : '' ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    <?= date('d M Y', strtotime($p['created_at'])) ?>
                </small>
                <?php if ($userRole === 'buyer'): ?>
                    <a href="inquiry.php?crop_id=<?= $p['id'] ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-chat me-1"></i>Contact Farmer
                    </a>
                <?php else: ?>
                    <a href="view_crop.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-eye me-1"></i>View
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav>
    <ul class="pagination justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">‹ Prev</a>
        </li>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next ›</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
