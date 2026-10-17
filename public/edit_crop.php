<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role('farmer');

$db        = getDB();
$uid       = (int) $_SESSION['user_id'];
$cropId    = (int) ($_GET['id'] ?? 0);
$pageTitle = t('edit_product');

$stmt = $db->prepare('SELECT * FROM crops WHERE id = ? AND user_id = ?');
$stmt->execute([$cropId, $uid]);
$crop = $stmt->fetch();

if (!$crop) {
    flash('danger', t('product_not_found'));
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$form   = App\CropInput::formFromRow($crop);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $input  = App\CropInput::fromPost($_POST, categories());
    $form   = $input['form'];
    $errors = array_map('t', $input['errors']);

    $newImage  = $crop['image'];
    $uploadDir = __DIR__ . '/uploads/';
    if (empty($errors) && App\Upload::wasSubmitted($_FILES['image'] ?? null)) {
        $up = App\Upload::storeImage($_FILES['image'], $uploadDir, 'prod_', 5 * 1024 * 1024);
        if ($up['ok']) {
            $newImage = $up['filename'];
        } else {
            $errors[] = t($up['error']);
        }
    }

    if (empty($errors)) {
        $d = $input['data'];
        $stmt = $db->prepare(
            'UPDATE crops SET name=?, variety=?, category_id=?, price=?, unit=?, quantity=?, status=?,
                              growth_stage=?, planting_date=?, expected_harvest=?, notes=?, image=?
              WHERE id=? AND user_id=?'
        );
        $stmt->execute([
            $d['name'], $d['variety'], $d['category_id'], $d['price'], $d['unit'], $d['quantity'], $d['status'],
            $d['growth_stage'], $d['planting_date'], $d['expected_harvest'], $d['notes'], $newImage,
            $cropId, $uid,
        ]);

        // Remove the replaced photo only after the row points at the new one
        if ($newImage !== $crop['image'] && App\Upload::isSafeStoredName($crop['image'])
            && is_file($uploadDir . $crop['image'])) {
            unlink($uploadDir . $crop['image']);
        }

        log_activity($uid, 'edit_product', "Updated product: {$d['name']} (ID:{$cropId})");
        flash('success', sprintf(t('product_updated'), $d['name']));
        header('Location: dashboard.php');
        exit;
    }
}

$currentImage = $crop['image'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
<div class="row justify-content-center">
<div class="col-lg-8">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-success"><?= t('dashboard') ?></a></li>
            <li class="breadcrumb-item active"><?= t('edit_product') ?></li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white py-3">
            <h1 class="h4 mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i><?= t('edit_product_listing') ?></h1>
        </div>
        <div class="card-body p-4">

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="edit_crop.php?id=<?= $cropId ?>" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <?php require __DIR__ . '/../includes/crop_form.php'; ?>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i><?= t('save_changes') ?></button>
                    <a href="dashboard.php" class="btn btn-outline-secondary"><?= t('cancel') ?></a>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
