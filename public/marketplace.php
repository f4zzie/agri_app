<?php
/**
 * AgriTrack – marketplace.php
 * Browse all farmers' listed products. Buyers can contact farmers.
 */
require_once __DIR__ . '/../includes/auth_check.php';
$db        = getDB();
$userRole  = $_SESSION['role'] ?? 'farmer';
$pageTitle = t('marketplace');

// --- Filters ---
$search       = trim((string) ($_GET['q'] ?? ''));
$filterCounty = (int) ($_GET['county'] ?? 0);
$filterCat    = (int) ($_GET['category'] ?? 0);
$filterStatus = in_array($_GET['status'] ?? '', App\CropInput::STATUSES, true) ? $_GET['status'] : '';
$filterStage  = in_array($_GET['stage'] ?? '', App\CropInput::GROWTH_STAGES, true) ? $_GET['stage'] : '';
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 12;
$offset       = ($page - 1) * $perPage;

// --- Build query ---
$where  = ['1 = 1'];
$params = [];

if ($search !== '') {
    $where[]       = '(v.name LIKE :q OR v.variety LIKE :q2 OR v.farmer_name LIKE :q3)';
    $params[':q']  = "%{$search}%";
    $params[':q2'] = "%{$search}%";
    $params[':q3'] = "%{$search}%";
}
if ($filterCounty > 0) {
    $where[]           = 'v.county_id = :county';
    $params[':county'] = $filterCounty;
}
if ($filterCat > 0) {
    $where[]             = 'v.category_id = :category';
    $params[':category'] = $filterCat;
}
if ($filterStatus !== '') {
    $where[]           = 'v.status = :status';
    $params[':status'] = $filterStatus;
}
if ($filterStage !== '') {
    $where[]          = 'v.growth_stage = :stage';
    $params[':stage'] = $filterStage;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM v_marketplace v $whereSQL");
$countStmt->execute($params);
$totalProducts = (int) $countStmt->fetchColumn();
$totalPages    = max(1, (int) ceil($totalProducts / $perPage));

$stmt = $db->prepare(
    "SELECT v.*, fr.avg_rating, fr.rating_count
       FROM v_marketplace v
       LEFT JOIN v_farmer_ratings fr ON fr.farmer_id = v.farmer_id
     $whereSQL
     ORDER BY v.status = 'available' DESC, v.created_at DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Only counties that actually have farmers
$counties = $db->query(
    "SELECT DISTINCT co.id, co.name FROM counties co JOIN users u ON u.county_id = co.id
      WHERE u.role = 'farmer' ORDER BY co.name"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$hasFilters = $search !== '' || $filterCounty || $filterCat || $filterStatus !== '' || $filterStage !== '';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0">🌾 <?= t('marketplace') ?></h1>
    <span class="badge bg-success fs-6"><?= sprintf(t('n_products_listed'), $totalProducts) ?></span>
</div>

<!-- Filter Bar -->
<form method="GET" class="card card-body mb-4 p-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label for="q" class="form-label small fw-semibold mb-1"><?= t('search') ?></label>
            <input type="search" name="q" id="q" class="form-control form-control-sm"
                   placeholder="<?= t('search_crop_farmer') ?>" value="<?= e($search) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="county" class="form-label small fw-semibold mb-1"><?= t('county') ?></label>
            <select name="county" id="county" class="form-select form-select-sm">
                <option value=""><?= t('all_counties') ?></option>
                <?php foreach ($counties as $cid => $cname): ?>
                    <option value="<?= $cid ?>" <?= $filterCounty === (int) $cid ? 'selected' : '' ?>><?= e($cname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="category" class="form-label small fw-semibold mb-1"><?= t('category') ?></label>
            <select name="category" id="category" class="form-select form-select-sm">
                <option value=""><?= t('all') ?></option>
                <?php foreach (categories() as $catId => $catName): ?>
                    <option value="<?= $catId ?>" <?= $filterCat === (int) $catId ? 'selected' : '' ?>><?= e(t_category($catName)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="status" class="form-label small fw-semibold mb-1"><?= t('availability') ?></label>
            <select name="status" id="status" class="form-select form-select-sm">
                <option value=""><?= t('all') ?></option>
                <?php foreach (App\CropInput::STATUSES as $st): ?>
                    <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= t('status_' . $st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label for="stage" class="form-label small fw-semibold mb-1"><?= t('growth_stage') ?></label>
            <select name="stage" id="stage" class="form-select form-select-sm">
                <option value=""><?= t('all') ?></option>
                <?php foreach (App\CropInput::GROWTH_STAGES as $gs): ?>
                    <option value="<?= $gs ?>" <?= $filterStage === $gs ? 'selected' : '' ?>><?= t($gs) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-success btn-sm flex-grow-1">
                <i class="bi bi-search me-1"></i><?= t('filter') ?>
            </button>
            <a href="marketplace.php" class="btn btn-outline-secondary btn-sm"><?= t('clear') ?></a>
        </div>
    </div>
</form>

<!-- Product Grid -->
<?php if (empty($products)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox display-4 d-block mb-3"></i>
        <p><?= t('no_products_found') ?></p>
        <?php if ($hasFilters): ?>
            <a href="marketplace.php" class="btn btn-outline-success btn-sm"><?= t('view_all_products') ?></a>
        <?php endif; ?>
    </div>
<?php else: ?>
<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 mb-4">
    <?php foreach ($products as $p): $img = upload_url($p['image']); ?>
    <div class="col">
        <div class="card h-100 shadow-sm <?= $p['status'] !== 'available' ? 'opacity-75' : '' ?>">
            <?php if ($img): ?>
                <img src="<?= e($img) ?>" class="card-img-top" style="height:180px;object-fit:cover;" alt="<?= e($p['name']) ?>" loading="lazy">
            <?php else: ?>
                <div class="bg-success bg-opacity-10 d-flex align-items-center justify-content-center"
                     style="height:180px;font-size:3rem;" aria-hidden="true"><?= product_emoji($p['category'] ?? null) ?></div>
            <?php endif; ?>

            <div class="card-body pb-1">
                <h2 class="h6 card-title fw-bold mb-1">
                    <a href="view_crop.php?id=<?= (int) $p['id'] ?>" class="text-reset text-decoration-none"><?= e($p['name']) ?></a>
                </h2>
                <?php if ($p['variety']): ?>
                    <small class="text-muted"><?= e($p['variety']) ?></small><br>
                <?php endif; ?>
                <small class="text-muted">
                    <a href="farmer.php?id=<?= (int) $p['farmer_id'] ?>" class="text-reset"><i class="bi bi-person me-1"></i><?= e($p['farmer_name']) ?></a>
                    <?php if ($p['rating_count']): ?>
                        <span class="text-warning ms-1" title="<?= sprintf(t('rating_summary'), $p['avg_rating'], (int) $p['rating_count']) ?>">★ <?= e((string) $p['avg_rating']) ?></span>
                    <?php endif; ?>
                </small>
                <div class="fw-semibold text-success mt-1"><?= format_price($p['price'], $p['unit']) ?></div>
                <div class="mt-2 d-flex gap-1 flex-wrap">
                    <?= status_badge($p['status']) ?>
                    <?= stage_badge($p['growth_stage']) ?>
                    <?php if ($p['farmer_county']): ?>
                        <span class="badge bg-light text-dark border"><i class="bi bi-geo-alt me-1"></i><?= e($p['farmer_county']) ?></span>
                    <?php endif; ?>
                    <?php if ($p['category']): ?>
                        <span class="badge bg-light text-dark border"><?= e(t_category($p['category'])) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($p['notes']): ?>
                    <p class="card-text small text-muted mt-2 mb-0">
                        <?= e(mb_substr($p['notes'], 0, 100)) ?><?= mb_strlen($p['notes']) > 100 ? '…' : '' ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                <small class="text-muted"><?= date('d M Y', strtotime($p['created_at'])) ?></small>
                <?php if ($userRole === 'buyer' && $p['status'] === 'available'): ?>
                    <a href="inquiry.php?crop_id=<?= (int) $p['id'] ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-chat me-1"></i><?= t('contact_farmer') ?>
                    </a>
                <?php else: ?>
                    <a href="view_crop.php?id=<?= (int) $p['id'] ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-eye me-1"></i><?= t('view') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav aria-label="<?= t('pagination') ?>">
    <ul class="pagination justify-content-center flex-wrap">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">‹</a>
        </li>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">›</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
