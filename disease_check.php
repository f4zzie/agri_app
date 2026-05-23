<?php
require_once 'includes/auth_check.php';
$pageTitle = 'Disease Check';
require_once 'includes/header.php';

$db  = getDB();
$uid = $_SESSION['user_id'];

$uploadedImage = null;
$errors        = [];
$resultShown   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (empty($_FILES['leaf_image']['name'])) {
        $errors[] = 'Please select a leaf image to upload.';
    } else {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($_FILES['leaf_image']['tmp_name']);

        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = 'Only JPEG, PNG, GIF, or WebP images are accepted.';
        } elseif ($_FILES['leaf_image']['size'] > 10 * 1024 * 1024) {
            $errors[] = 'Image must be smaller than 10 MB.';
        } else {
            $ext = pathinfo($_FILES['leaf_image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('disease_', true) . '.' . strtolower($ext);
            $uploadDir = __DIR__ . '/uploads/disease_checks/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            if (move_uploaded_file($_FILES['leaf_image']['tmp_name'], $uploadDir . $filename)) {
                $uploadedImage = $filename;
                $resultShown   = true;
                log_activity($uid, 'disease_check', 'Uploaded leaf image for disease check: ' . $filename);
            } else {
                $errors[] = 'Failed to upload image. Please try again.';
            }
        }
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <h1 class="h3 fw-bold mb-1 text-success">
                <i class="bi bi-virus me-2"></i>Disease Check
            </h1>
            <p class="text-muted mb-4">Upload a photo of your crop leaf for an AI-powered disease check.</p>

            <!-- Disclaimer Note -->
            <div class="alert alert-warning d-flex align-items-start mb-4">
                <i class="bi bi-exclamation-triangle-fill me-3 fs-5 text-warning flex-shrink-0 mt-1"></i>
                <div>
                    <strong>Note: AI integration not yet connected.</strong>
                    This is a demonstration stub. The disease analysis feature will connect to an AI image recognition API in a future release.
                    Results shown are placeholder outputs for UI demonstration purposes only.
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Upload Form -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-cloud-upload me-2 text-success"></i>Upload Leaf Photo
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="disease_check.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <div class="mb-3">
                            <label for="leaf_image" class="form-label fw-semibold">
                                Select Leaf Image <span class="text-danger">*</span>
                            </label>
                            <input type="file" class="form-control form-control-lg"
                                   id="leaf_image" name="leaf_image"
                                   accept="image/*" required
                                   onchange="previewLeaf(event)">
                            <div class="form-text">Accepted: JPEG, PNG, GIF, WebP. Max 10 MB.</div>
                        </div>

                        <!-- Image Preview -->
                        <div id="leafPreviewWrapper" class="mb-3 d-none">
                            <label class="form-label fw-semibold">Preview</label>
                            <div>
                                <img id="leafPreview" src="#" alt="Leaf preview"
                                     class="img-thumbnail" style="max-height:200px;">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg" id="analyzeBtn">
                            <i class="bi bi-search me-1"></i> Analyze Leaf
                        </button>
                    </form>
                </div>
            </div>

            <!-- ─── Result Card ──────────────────────────────────────────── -->
            <?php if ($resultShown && $uploadedImage): ?>
            <div class="card border-0 shadow-sm border-start border-4 border-success">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center mb-3 mb-md-0">
                            <img src="uploads/disease_checks/<?= htmlspecialchars($uploadedImage) ?>"
                                 alt="Uploaded leaf"
                                 class="img-fluid rounded shadow-sm"
                                 style="max-height:220px;object-fit:cover;">
                        </div>
                        <div class="col-md-8">
                            <div class="d-flex align-items-center mb-3">
                                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                                    <i class="bi bi-patch-check-fill text-success fs-3"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-success mb-0">Analysis Complete</h5>
                                    <p class="text-muted small mb-0">Leaf scan finished</p>
                                </div>
                            </div>

                            <div class="alert alert-success mb-3">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <strong>Your crop appears healthy!</strong><br>
                                No obvious disease symptoms detected in this leaf sample.
                            </div>

                            <p class="text-muted small mb-0">
                                <i class="bi bi-info-circle me-1 text-primary"></i>
                                Always consult your local <strong>agricultural extension officer</strong> for a definitive
                                diagnosis and treatment plan before taking action.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tips -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-lightbulb me-2 text-warning"></i>Tips for Best Results</h5>
                </div>
                <div class="card-body p-4">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="bi bi-check text-success me-2"></i>Use a clear, well-lit photo</li>
                        <li class="mb-2"><i class="bi bi-check text-success me-2"></i>Capture the affected area close-up</li>
                        <li class="mb-2"><i class="bi bi-check text-success me-2"></i>Include both healthy and unhealthy parts for comparison</li>
                        <li class="mb-0"><i class="bi bi-check text-success me-2"></i>Avoid blurry or dark images</li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function previewLeaf(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('leafPreview').src = e.target.result;
        document.getElementById('leafPreviewWrapper').classList.remove('d-none');
    };
    reader.readAsDataURL(file);
}
</script>

<?php require_once 'includes/footer.php'; ?>
