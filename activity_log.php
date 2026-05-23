<?php
require_once 'includes/auth_check.php';
$pageTitle = 'Activity Log';
require_once 'includes/header.php';

$db  = getDB();
$uid = $_SESSION['user_id'];

$perPage    = 20;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

$filterAction = trim($_GET['action'] ?? '');

// Build query
if ($filterAction !== '') {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id = ? AND action = ?");
    $countStmt->execute([$uid, $filterAction]);

    $stmt = $db->prepare(
        "SELECT * FROM activity_log WHERE user_id = ? AND action = ?
         ORDER BY created_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->execute([$uid, $filterAction, $perPage, $offset]);
} else {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id = ?");
    $countStmt->execute([$uid]);

    $stmt = $db->prepare(
        "SELECT * FROM activity_log WHERE user_id = ?
         ORDER BY created_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->execute([$uid, $perPage, $offset]);
}

$totalRows  = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$logs       = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch distinct actions for filter dropdown
$actionsStmt = $db->prepare("SELECT DISTINCT action FROM activity_log WHERE user_id = ? ORDER BY action ASC");
$actionsStmt->execute([$uid]);
$distinctActions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

$actionLabels = [
    'add_crop'        => 'Add Crop',
    'edit_crop'       => 'Edit Crop',
    'delete_crop'     => 'Delete Crop',
    'update_profile'  => 'Update Profile',
    'change_password' => 'Change Password',
    'disease_check'   => 'Disease Check',
    'send_reminders'  => 'Send Reminders',
    'login'           => 'Login',
    'logout'          => 'Logout',
];
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-0 text-success">
                <i class="bi bi-clock-history me-2"></i>Activity Log
            </h1>
            <p class="text-muted mb-0 small">A record of all actions you've performed.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="activity_log.php" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label for="filterAction" class="col-form-label fw-semibold">Filter by Action:</label>
                </div>
                <div class="col-auto">
                    <select name="action" id="filterAction" class="form-select form-select-sm"
                            onchange="this.form.submit()">
                        <option value="">— All Actions —</option>
                        <?php foreach ($distinctActions as $act): ?>
                            <option value="<?= htmlspecialchars($act) ?>"
                                    <?= $filterAction === $act ? 'selected' : '' ?>>
                                <?= htmlspecialchars($actionLabels[$act] ?? ucfirst(str_replace('_', ' ', $act))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($filterAction): ?>
                    <div class="col-auto">
                        <a href="activity_log.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i>Clear Filter
                        </a>
                    </div>
                <?php endif; ?>
                <div class="col-auto ms-auto">
                    <small class="text-muted">
                        <?= number_format($totalRows) ?> record<?= $totalRows !== 1 ? 's' : '' ?> found
                    </small>
                </div>
            </form>
        </div>
    </div>

    <!-- Log Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-journal-x fs-1 text-muted"></i>
                    <p class="text-muted mt-3">No activity records found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:180px;">Date / Time</th>
                                <th style="width:160px;">Action</th>
                                <th>Details</th>
                                <th style="width:140px;">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="text-muted small">
                                    <?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($log['created_at']))) ?>
                                </td>
                                <td>
                                    <?php
                                    $actionKey   = $log['action'];
                                    $actionLabel = $actionLabels[$actionKey] ?? ucfirst(str_replace('_', ' ', $actionKey));
                                    $iconMap = [
                                        'add_crop'        => 'bi-plus-circle text-success',
                                        'edit_crop'       => 'bi-pencil text-primary',
                                        'delete_crop'     => 'bi-trash text-danger',
                                        'update_profile'  => 'bi-person text-info',
                                        'change_password' => 'bi-shield-lock text-warning',
                                        'disease_check'   => 'bi-virus text-purple',
                                        'send_reminders'  => 'bi-bell text-warning',
                                        'login'           => 'bi-box-arrow-in-right text-success',
                                        'logout'          => 'bi-box-arrow-right text-secondary',
                                    ];
                                    $icon = $iconMap[$actionKey] ?? 'bi-activity text-secondary';
                                    ?>
                                    <span class="badge bg-light text-dark border small">
                                        <i class="bi <?= $icon ?> me-1"></i>
                                        <?= htmlspecialchars($actionLabel) ?>
                                    </span>
                                </td>
                                <td class="small"><?= htmlspecialchars($log['details'] ?? '—') ?></td>
                                <td class="text-muted small font-monospace">
                                    <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Activity log pagination" class="mt-4">
        <ul class="pagination justify-content-center">
            <!-- Previous -->
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $currentPage - 1 ?>&action=<?= urlencode($filterAction) ?>"
                   aria-label="Previous">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>

            <?php
            $start = max(1, $currentPage - 2);
            $end   = min($totalPages, $currentPage + 2);
            if ($start > 1):
            ?>
                <li class="page-item">
                    <a class="page-link" href="?page=1&action=<?= urlencode($filterAction) ?>">1</a>
                </li>
                <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $start; $p <= $end; $p++): ?>
                <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&action=<?= urlencode($filterAction) ?>">
                        <?= $p ?>
                    </a>
                </li>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $totalPages ?>&action=<?= urlencode($filterAction) ?>">
                        <?= $totalPages ?>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Next -->
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $currentPage + 1 ?>&action=<?= urlencode($filterAction) ?>"
                   aria-label="Next">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
        <p class="text-center text-muted small mt-1">
            Page <?= $currentPage ?> of <?= $totalPages ?>
        </p>
    </nav>
    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
