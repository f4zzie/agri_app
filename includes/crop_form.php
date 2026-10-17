<?php
/**
 * Product listing form fields, shared by add_crop.php and edit_crop.php.
 *
 * Expects: $form (array<string,string>) and optionally $currentImage (string|null).
 */
$currentImage = $currentImage ?? null;
$sel = static fn (string $a, string $b): string => $a === $b ? 'selected' : '';
?>
<div class="row g-3">
    <div class="col-md-8">
        <label for="name" class="form-label fw-semibold"><?= t('product_name') ?> <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="name" name="name" maxlength="100" required
               value="<?= htmlspecialchars($form['name']) ?>" placeholder="<?= t('product_name_ph') ?>">
    </div>
    <div class="col-md-4">
        <label for="category" class="form-label fw-semibold"><?= t('category') ?></label>
        <select class="form-select" id="category" name="category">
            <option value="">— <?= t('select') ?> —</option>
            <?php foreach (categories() as $catId => $catName): ?>
                <option value="<?= $catId ?>" <?= $sel($form['category'], (string) $catId) ?>><?= htmlspecialchars(t_category($catName)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-4">
        <label for="variety" class="form-label fw-semibold"><?= t('variety') ?></label>
        <input type="text" class="form-control" id="variety" name="variety" maxlength="100"
               value="<?= htmlspecialchars($form['variety']) ?>" placeholder="<?= t('variety_ph') ?>">
    </div>
    <div class="col-md-4">
        <label for="price" class="form-label fw-semibold"><?= t('price_kes') ?></label>
        <div class="input-group">
            <span class="input-group-text">KSh</span>
            <input type="number" class="form-control" id="price" name="price" min="0.01" step="0.01"
                   value="<?= htmlspecialchars($form['price']) ?>" placeholder="0.00">
        </div>
        <div class="form-text"><?= t('price_blank_hint') ?></div>
    </div>
    <div class="col-md-4">
        <label for="unit" class="form-label fw-semibold"><?= t('price_per') ?></label>
        <select class="form-select" id="unit" name="unit">
            <?php foreach (App\CropInput::UNITS as $u): ?>
                <option value="<?= $u ?>" <?= $sel($form['unit'], $u) ?>><?= t('unit_' . $u) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-4">
        <label for="quantity" class="form-label fw-semibold"><?= t('quantity_available') ?></label>
        <input type="number" class="form-control" id="quantity" name="quantity" min="0" step="1"
               value="<?= htmlspecialchars($form['quantity']) ?>" placeholder="500">
    </div>
    <div class="col-md-4">
        <label for="status" class="form-label fw-semibold"><?= t('availability') ?></label>
        <select class="form-select" id="status" name="status">
            <?php foreach (App\CropInput::STATUSES as $st): ?>
                <option value="<?= $st ?>" <?= $sel($form['status'], $st) ?>><?= t('status_' . $st) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label for="growth_stage" class="form-label fw-semibold"><?= t('growth_stage') ?></label>
        <select class="form-select" id="growth_stage" name="growth_stage">
            <option value="">— <?= t('not_tracked') ?> —</option>
            <?php foreach (App\CropInput::GROWTH_STAGES as $gs): ?>
                <option value="<?= $gs ?>" <?= $sel($form['growth_stage'], $gs) ?>><?= t($gs) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-6">
        <label for="planting_date" class="form-label fw-semibold"><?= t('planting_date') ?></label>
        <input type="date" class="form-control" id="planting_date" name="planting_date"
               value="<?= htmlspecialchars($form['planting_date']) ?>">
    </div>
    <div class="col-md-6">
        <label for="expected_harvest" class="form-label fw-semibold"><?= t('expected_harvest') ?></label>
        <input type="date" class="form-control" id="expected_harvest" name="expected_harvest"
               value="<?= htmlspecialchars($form['expected_harvest']) ?>">
        <div class="form-text"><?= t('harvest_reminder_hint') ?></div>
    </div>

    <div class="col-md-8">
        <label for="image" class="form-label fw-semibold"><?= t('product_photo') ?></label>
        <?php if ($currentImage && App\Upload::isSafeStoredName($currentImage) && is_file(__DIR__ . '/../public/uploads/' . $currentImage)): ?>
            <div class="mb-2">
                <img src="uploads/<?= htmlspecialchars($currentImage) ?>" id="imgPreview" class="img-thumbnail" style="max-height:100px;" alt="">
            </div>
        <?php else: ?>
            <img id="imgPreview" src="" alt="" class="img-thumbnail d-none mb-2" style="max-height:100px;">
        <?php endif; ?>
        <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
        <div class="form-text"><?= t('photo_hint') ?></div>
    </div>

    <div class="col-12">
        <label for="notes" class="form-label fw-semibold"><?= t('description') ?></label>
        <textarea class="form-control" id="notes" name="notes" rows="3"
                  placeholder="<?= t('description_ph') ?>"><?= htmlspecialchars($form['notes']) ?></textarea>
    </div>
</div>

<script>
document.getElementById('image').addEventListener('change', function () {
    const preview = document.getElementById('imgPreview');
    if (this.files && this.files[0]) {
        preview.src = URL.createObjectURL(this.files[0]);
        preview.classList.remove('d-none');
    }
});
</script>
