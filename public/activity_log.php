<?php
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = t('activity_log');

$db  = getDB();
$uid = (int) $_SESSION['user_id'];

$perPage      = 20;
$currentPage  = max(1, (int) ($_GET['page'] ?? 1));
$offset       = ($currentPage - 1) * $perPage;
$filterAction = trim((string) ($_GET['action'] ?? ''));

$where  = 'user_id = ?' . ($filterAction !== '' ? ' AND action = ?' : '');
$params = $filterAction !== '' ? [$uid, $filterAction] : [$uid];

$countStmt = $db->prepare("SELECT COUNT(*) FROM activity_log WHERE {$where}");
$countStmt->execute($params);
$totalRows  = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

$stmt = $db->prepare("SELECT * FROM activity_log WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?");
foreach ($params as $i => $p) {
    $stmt->bindValue($i + 1, $p);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$actionsStmt = $db->prepare('SELECT DISTINCT action FROM activity_log WHERE user_id = ? ORDER BY action');
$actionsStmt->execute([$uid]);
$distinctActions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

$icons = [
    'add_product' => 'bi-plus-circle text-success', 'edit_product' => 'bi-pencil text-primary',
    'delete_product' => 'bi-trash text-danger', 'update_profile' => 'bi-person text-info',
    'change_password' => 'bi-shield-lock text-warning', 'password_reset' => 'bi-key text-warning',
    'disease_check' => 'bi-bug text-secondary', 'send_reminders' => 'bi-bell text-warning',
    'login' => 'bi-box-arrow-in-right text-success', 'login_google' => 'bi-google text-success',
    'logout' => 'bi-box-arrow-right text-secondary', 'register' => 'bi-person-plus text-success',
    'register_google' => 'bi-person-plus text-success', 'email_verified' => 'bi-envelope-check text-success',
    'send_inquiry' => 'bi-chat-left-text text-success', 'inquiry_reply' => 'bi-reply text-primary',
    'inquiry_completed' => 'bi-check2-circle text-success', 'rate_farmer' => 'bi-star text-warning',
];
$label = static function (string $action): string {
    $key = 'act_' . $action;
    $tr  = t($key);
    return $tr === $key ? ucfirst(str_replace('_', ' ', $action)) : $tr;
};
$pageUrl = static fn (int $p): string => '?' . e(http_build_query(['page' => $p] + ($filterAction !== '' ? ['action' => $filterAction] : [])));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-2">
    <h1 class="h3 fw-bold mb-0 text-success"><i class="bi bi-clock-history me-2"></i><?= t('activity_log') ?></h1>
    <p class="text-muted small mb-4"><?= t('activity_log_sub') ?></p>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="activity_log.php" class="row g-2 align-items-center">
                <div class="col-auto"><label for="filterAction" class="col-form-label fw-semibold"><?= t('filter_by_action') ?></label></div>
                <div class="col-auto">
                    <select name="action" id="filterAction" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">— <?= t('all') ?> —</option>
                        <?php foreach ($distinctActions as $act): ?>
                            <option value="<?= e($act) ?>" <?= $filterAction === $act ? 'selected' : '' ?>><?= e($label($act)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <noscript><div class="col-auto"><button class="btn btn-sm btn-success"><?= t('filter') ?></button></div></noscript>
                <?php if ($filterAction !== ''): ?>
                    <div class="col-auto"><a href="activity_log.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i><?= t('clear') ?></a></div>
                <?php endif; ?>
                <div class="col-auto ms-auto"><small class="text-muted"><?= sprintf(t('n_records'), $totalRows) ?></small></div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="text-center py-5"><i class="bi bi-journal-x fs-1 text-muted"></i><p class="text-muted mt-3"><?= t('no_activity') ?></p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:170px;"><?= t('date_time') ?></th>
                                <th style="width:190px;"><?= t('action') ?></th>
                                <th><?= t('details') ?></th>
                                <th class="d-none d-md-table-cell" style="width:140px;"><?= t('ip_address') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="text-muted small text-nowrap"><?= e(date('Y-m-d H:i', strtotime($log['created_at']))) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border small">
                                        <i class="bi <?= $icons[$log['action']] ?? 'bi-activity text-secondary' ?> me-1"></i><?= e($label($log['action'])) ?>
                                    </span>
                                </td>
                                <td class="small"><?= e($log['details'] ?: '—') ?></td>
                                <td class="text-muted small font-monospace d-none d-md-table-cell"><?= e($log['ip_address'] ?? '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav aria-label="<?= t('pagination') ?>" class="mt-4">
        <ul class="pagination justify-content-center flex-wrap">
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $pageUrl($currentPage - 1) ?>">‹</a></li>
            <?php for ($p = max(1, $currentPage - 2), $end = min($totalPages, $currentPage + 2); $p <= $end; $p++): ?>
                <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>"><a class="page-link" href="<?= $pageUrl($p) ?>"><?= $p ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $pageUrl($currentPage + 1) ?>">›</a></li>
        </ul>
        <p class="text-center text-muted small mt-1"><?= sprintf(t('page_x_of_y'), $currentPage, $totalPages) ?></p>
    </nav>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
