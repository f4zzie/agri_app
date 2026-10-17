<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role('farmer');

$db        = getDB();
$uid       = (int) $_SESSION['user_id'];
$pageTitle = t('list_product');

$errors = [];
$form   = App\CropInput::emptyForm();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $input  = App\CropInput::fromPost($_POST, categories());
    $form   = $input['form'];
    $errors = array_map('t', $input['errors']);

    $imageFilename = null;
    if (empty($errors) && App\Upload::wasSubmitted($_FILES['image'] ?? null)) {
        $up = App\Upload::storeImage($_FILES['image'], __DIR__ . '/uploads/', 'prod_', 5 * 1024 * 1024);
        if ($up['ok']) {
            $imageFilename = $up['filename'];
        } else {
            $errors[] = t($up['error']);
        }
    }

    if (empty($errors)) {
        $d = $input['data'];
        $stmt = $db->prepare(
            'INSERT INTO crops (user_id, name, variety, category_id, price, unit, quantity, status,
                                growth_stage, planting_date, expected_harvest, notes, image)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $uid, $d['name'], $d['variety'], $d['category_id'], $d['price'], $d['unit'], $d['quantity'],
            $d['status'], $d['growth_stage'], $d['planting_date'], $d['expected_harvest'], $d['notes'],
            $imageFilename,
        ]);
        log_activity($uid, 'add_product', "Listed product: {$d['name']}");
        flash('success', sprintf(t('product_listed'), $d['name']));
        header('Location: dashboard.php');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
<div class="row justify-content-center">
<div class="col-lg-8">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-success"><?= t('dashboard') ?></a></li>
            <li class="breadcrumb-item active"><?= t('list_product') ?></li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-success text-white py-3">
            <h1 class="h4 mb-0 fw-bold"><i class="bi bi-plus-circle me-2"></i><?= t('list_product_for_sale') ?></h1>
        </div>
        <div class="card-body p-4">

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <div id="offlineNotice" class="alert alert-warning d-none" role="status" aria-live="polite"
                 data-offline-saved="<?= e(t('draft_offline_saved')) ?>"
                 data-restored="<?= e(t('draft_restored')) ?>"
                 data-back-online="<?= e(t('draft_back_online')) ?>"
                 data-reload-label="<?= e(t('reload')) ?>"></div>

            <form method="POST" enctype="multipart/form-data" novalidate id="cropForm" data-draft-key="agritrack:add_crop:<?= $uid ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <?php require __DIR__ . '/../includes/crop_form.php'; ?>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-bag-check me-1"></i><?= t('list_product') ?>
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary"><?= t('cancel') ?></a>
                </div>
            </form>

        </div>
    </div>
</div>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
