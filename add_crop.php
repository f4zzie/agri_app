<?php ob_start();
require_once 'includes/auth_check.php';
$pageTitle = 'List a Product';
require_once 'includes/header.php';

$db  = getDB();
$uid = $_SESSION['user_id'];

$categories = ['Grains & Cereals','Vegetables','Fruits','Dairy & Eggs','Meat & Poultry','Herbs & Spices','Legumes & Pulses','Roots & Tubers','Other'];
$units      = ['kg','bag','crate','litre','dozen','piece','tonne','bundle'];

$errors   = [];
$formData = ['name'=>'','category'=>'','price'=>'','unit'=>'kg','quantity'=>'','status'=>'available','notes'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $formData['name']     = trim($_POST['name']     ?? '');
    $formData['category'] = trim($_POST['category'] ?? '');
    $formData['price']    = trim($_POST['price']     ?? '');
    $formData['unit']     = trim($_POST['unit']      ?? 'kg');
    $formData['quantity'] = trim($_POST['quantity']  ?? '');
    $formData['status']   = trim($_POST['status']    ?? 'available');
    $formData['notes']    = trim($_POST['notes']     ?? '');

    if (empty($formData['name']))     $errors[] = 'Product name is required.';
    if ($formData['price'] !== '' && (!is_numeric($formData['price']) || $formData['price'] < 0))
        $errors[] = 'Enter a valid price.';
    if ($formData['quantity'] !== '' && (!ctype_digit($formData['quantity']) || $formData['quantity'] < 0))
        $errors[] = 'Enter a valid quantity.';
    if (!in_array($formData['status'], ['available','out_of_stock'])) $formData['status'] = 'available';

    // Image upload
    $imageFilename = null;
    if (!empty($_FILES['image']['name'])) {
        $allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
        $fileType     = mime_content_type($_FILES['image']['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = 'Only JPEG, PNG, GIF or WebP images allowed.';
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Image must be smaller than 5 MB.';
        } else {
            $ext           = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $imageFilename = uniqid('prod_', true) . '.' . $ext;
            $uploadDir     = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageFilename)) {
                $errors[] = 'Failed to upload image.';
                $imageFilename = null;
            }
        }
    }

    if (empty($errors)) {
        $stmt = $db->prepare(
            "INSERT INTO crops (user_id, name, category, price, unit, quantity, status, notes, image, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $uid,
            $formData['name'],
            $formData['category'] ?: null,
            $formData['price']    !== '' ? $formData['price']    : null,
            $formData['unit'],
            $formData['quantity'] !== '' ? $formData['quantity'] : null,
            $formData['status'],
            $formData['notes']    ?: null,
            $imageFilename,
        ]);
        log_activity($uid, 'add_product', "Listed product: {$formData['name']}");
        flash('success', "Product \"{$formData['name']}\" listed successfully!");
        header('Location: dashboard.php');
        exit;
    }
}
?>

<div class="container py-4">
<div class="row justify-content-center">
<div class="col-lg-8">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-success">Dashboard</a></li>
            <li class="breadcrumb-item active">List a Product</li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-success text-white py-3">
            <h4 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2"></i>List a Product for Sale</h4>
        </div>
        <div class="card-body p-4">

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="row g-3">
                    <!-- Product Name -->
                    <div class="col-md-8">
                        <label for="name" class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?= htmlspecialchars($formData['name']) ?>"
                               placeholder="e.g. Fresh Maize, Tomatoes, Raw Milk" required>
                    </div>

                    <!-- Category -->
                    <div class="col-md-4">
                        <label for="category" class="form-label fw-semibold">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">-- Select --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= $formData['category']===$cat?'selected':'' ?>>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Price -->
                    <div class="col-md-4">
                        <label for="price" class="form-label fw-semibold">Price (KES)</label>
                        <div class="input-group">
                            <span class="input-group-text">KSh</span>
                            <input type="number" class="form-control" id="price" name="price"
                                   value="<?= htmlspecialchars($formData['price']) ?>"
                                   placeholder="0.00" min="0" step="0.01">
                        </div>
                        <div class="form-text">Leave blank if negotiable</div>
                    </div>

                    <!-- Unit -->
                    <div class="col-md-4">
                        <label for="unit" class="form-label fw-semibold">Price Per</label>
                        <select class="form-select" id="unit" name="unit">
                            <?php foreach ($units as $u): ?>
                                <option value="<?= $u ?>" <?= $formData['unit']===$u?'selected':'' ?>><?= ucfirst($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Quantity -->
                    <div class="col-md-4">
                        <label for="quantity" class="form-label fw-semibold">Quantity Available</label>
                        <input type="number" class="form-control" id="quantity" name="quantity"
                               value="<?= htmlspecialchars($formData['quantity']) ?>"
                               placeholder="e.g. 500" min="0">
                    </div>

                    <!-- Status -->
                    <div class="col-md-4">
                        <label for="status" class="form-label fw-semibold">Availability</label>
                        <select class="form-select" id="status" name="status">
                            <option value="available"   <?= $formData['status']==='available'   ?'selected':'' ?>>✅ Available</option>
                            <option value="out_of_stock" <?= $formData['status']==='out_of_stock'?'selected':'' ?>>❌ Out of Stock</option>
                        </select>
                    </div>

                    <!-- Image -->
                    <div class="col-md-8">
                        <label for="image" class="form-label fw-semibold">Product Photo</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <div class="form-text">JPEG, PNG or GIF. Max 5 MB.</div>
                        <img id="imgPreview" src="" alt="Preview" class="mt-2 img-thumbnail d-none" style="max-height:120px;">
                    </div>

                    <!-- Description -->
                    <div class="col-12">
                        <label for="notes" class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  placeholder="Describe your product — quality, location, how to get it, bulk discounts..."><?= htmlspecialchars($formData['notes']) ?></textarea>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-bag-check me-1"></i>List Product
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>

        </div>
    </div>
</div>
</div>
</div>

<script>
document.getElementById('image').addEventListener('change', function() {
    const preview = document.getElementById('imgPreview');
    if (this.files && this.files[0]) {
        preview.src = URL.createObjectURL(this.files[0]);
        preview.classList.remove('d-none');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
