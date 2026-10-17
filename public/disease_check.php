<?php
/**
 * AgriTrack – disease_check.php
 * Farmers upload a leaf photo. Automated diagnosis is NOT implemented:
 * the page says so plainly and points to common symptoms and to human
 * experts, rather than showing a made-up result.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_role('farmer');
$pageTitle = t('disease_check');

$uid           = (int) $_SESSION['user_id'];
$uploadedImage = null;
$errors        = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (!App\Upload::wasSubmitted($_FILES['leaf_image'] ?? null)) {
        $errors[] = t('err_select_leaf');
    } else {
        $up = App\Upload::storeImage($_FILES['leaf_image'], __DIR__ . '/uploads/disease_checks/', 'disease_', 10 * 1024 * 1024);
        if ($up['ok']) {
            $uploadedImage = $up['filename'];
            log_activity($uid, 'disease_check', 'Uploaded leaf photo ' . $uploadedImage);
        } else {
            $errors[] = t($up['error']);
        }
    }
}

// Common problems in Kenya, as a quick reference (keys -> lang files)
$guide = ['maize' => ['mln', 'faw'], 'potato_tomato' => ['late_blight', 'bacterial_wilt'], 'beans' => ['bean_rust', 'anthracnose']];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-2">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <h1 class="h3 fw-bold mb-1 text-success"><i class="bi bi-bug me-2"></i><?= t('disease_check') ?></h1>
            <p class="text-muted mb-4"><?= t('disease_intro') ?></p>

            <div class="alert alert-info d-flex gap-3">
                <i class="bi bi-info-circle-fill fs-5 flex-shrink-0"></i>
                <div><?= t('disease_not_automated') ?></div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
            <?php endif; ?>

            <?php if ($uploadedImage): ?>
                <div class="card border-0 shadow-sm border-start border-4 border-success mb-4">
                    <div class="card-body d-flex gap-3 align-items-center flex-wrap">
                        <img src="uploads/disease_checks/<?= e($uploadedImage) ?>" alt="" class="rounded" style="width:120px;height:120px;object-fit:cover;">
                        <div>
                            <h2 class="h6 fw-bold text-success mb-1"><?= t('photo_saved') ?></h2>
                            <p class="small mb-0"><?= t('photo_saved_next') ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h5 mb-0 fw-semibold"><i class="bi bi-cloud-upload me-2 text-success"></i><?= t('disease_upload_label') ?></h2>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="disease_check.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div class="mb-3">
                            <label for="leaf_image" class="form-label fw-semibold"><?= t('leaf_photo') ?></label>
                            <input type="file" class="form-control" id="leaf_image" name="leaf_image" accept="image/jpeg,image/png,image/gif,image/webp" capture="environment" required>
                            <div class="form-text"><?= t('leaf_photo_hint') ?></div>
                        </div>
                        <button type="submit" class="btn btn-success"><i class="bi bi-upload me-1"></i><?= t('upload_photo') ?></button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h5 mb-0 fw-semibold"><i class="bi bi-journal-medical me-2 text-warning"></i><?= t('common_problems') ?></h2>
                </div>
                <div class="card-body p-4">
                    <?php foreach ($guide as $cropKey => $problems): ?>
                        <h3 class="h6 fw-bold mt-2"><?= t('guide_' . $cropKey) ?></h3>
                        <ul class="small">
                            <?php foreach ($problems as $pk): ?>
                                <li><strong><?= t('guide_' . $pk) ?></strong> — <?= t('guide_' . $pk . '_desc') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                    <p class="small text-muted mb-0"><i class="bi bi-telephone me-1"></i><?= t('guide_get_help') ?></p>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
