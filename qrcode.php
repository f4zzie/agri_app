<?php
require_once 'includes/auth_check.php';
$pageTitle = 'QR Code';
require_once 'includes/header.php';

$db  = getDB();
$uid = $_SESSION['user_id'];

$cropId = isset($_GET['crop_id']) ? (int) $_GET['crop_id'] : 0;

if (!$cropId) {
    flash('error', 'Invalid crop ID.');
    header('Location: dashboard.php');
    exit;
}

// Fetch crop and verify ownership
$stmt = $db->prepare("SELECT * FROM crops WHERE id = ? AND user_id = ?");
$stmt->execute([$cropId, $uid]);
$crop = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$crop) {
    flash('error', 'Crop not found or access denied.');
    header('Location: dashboard.php');
    exit;
}

$appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost/agri_app', '/');
$viewUrl = $appUrl . '/view_crop.php?id=' . $crop['id'];
$qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($viewUrl);
$qrDownloadUrl = $qrApiUrl . '&download=1';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-success">Dashboard</a></li>
                    <li class="breadcrumb-item active">QR Code</li>
                </ol>
            </nav>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h4 class="mb-0 fw-bold">
                        <i class="bi bi-qr-code me-2 text-success"></i>
                        QR Code — <?= htmlspecialchars($crop['name']) ?>
                    </h4>
                </div>
                <div class="card-body p-4 text-center">

                    <p class="text-muted mb-4">
                        Scan this QR code to view crop details instantly. Share it on physical labels or packaging.
                    </p>

                    <!-- QR Code Image -->
                    <div class="d-inline-block border rounded p-4 bg-white shadow-sm mb-4">
                        <img src="<?= htmlspecialchars($qrApiUrl) ?>"
                             alt="QR Code for <?= htmlspecialchars($crop['name']) ?>"
                             width="200" height="200"
                             class="img-fluid"
                             onerror="this.src='https://via.placeholder.com/200?text=QR+Error'">
                    </div>

                    <!-- Crop Name Badge -->
                    <div class="mb-3">
                        <span class="badge bg-success fs-6 px-3 py-2">
                            <i class="bi bi-tree me-1"></i><?= htmlspecialchars($crop['name']) ?>
                        </span>
                    </div>

                    <!-- URL display -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-muted small">This QR code links to:</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm bg-light"
                                   id="viewUrlInput"
                                   value="<?= htmlspecialchars($viewUrl) ?>"
                                   readonly>
                            <button class="btn btn-outline-secondary btn-sm" type="button"
                                    onclick="copyUrl()" title="Copy URL">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        <div class="mt-1">
                            <a href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" class="small text-success">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Open crop page
                            </a>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div class="alert alert-light border text-start">
                        <p class="mb-1 small"><i class="bi bi-info-circle text-primary me-1"></i>
                            <strong>Right-click the QR code</strong> and choose "Save image as…" to save it.
                        </p>
                        <p class="mb-0 small"><i class="bi bi-download text-success me-1"></i>
                            Or use the button below to download directly.
                        </p>
                    </div>

                    <!-- Buttons -->
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <a href="<?= htmlspecialchars($qrDownloadUrl) ?>"
                           class="btn btn-success"
                           download="qr_<?= htmlspecialchars($crop['name']) ?>.png"
                           id="downloadQrBtn">
                            <i class="bi bi-download me-1"></i> Download QR Code
                        </a>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function copyUrl() {
    const input = document.getElementById('viewUrlInput');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(function() {
        const btn = input.nextElementSibling;
        btn.innerHTML = '<i class="bi bi-check text-success"></i>';
        setTimeout(function() {
            btn.innerHTML = '<i class="bi bi-clipboard"></i>';
        }, 2000);
    }).catch(function() {
        document.execCommand('copy');
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
