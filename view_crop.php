<?php
require_once 'config.php';

$cropId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$cropId) {
    http_response_code(404);
    die('Crop not found.');
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM crops WHERE id = ?");
$stmt->execute([$cropId]);
$crop = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$crop) {
    http_response_code(404);
    die('Crop not found.');
}

$statusMap = [
    'planted'   => ['label' => 'Planted',   'color' => 'primary'],
    'growing'   => ['label' => 'Growing',   'color' => 'success'],
    'harvested' => ['label' => 'Harvested', 'color' => 'warning'],
    'failed'    => ['label' => 'Failed',    'color' => 'danger'],
];
$statusInfo = $statusMap[$crop['status']] ?? ['label' => ucfirst($crop['status']), 'color' => 'secondary'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($crop['name']) ?> — AgriTrack</title>
    <meta name="description" content="View details for <?= htmlspecialchars($crop['name']) ?> on AgriTrack.">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .crop-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        .crop-image {
            width: 100%;
            max-height: 350px;
            object-fit: cover;
            border-radius: 0.5rem;
        }
        .detail-row {
            display: flex;
            align-items: flex-start;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #495057;
            min-width: 160px;
            flex-shrink: 0;
        }
        .powered-footer {
            text-align: center;
            color: #adb5bd;
            font-size: 0.82rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">

            <div class="card border-0 shadow">
                <!-- Card Header -->
                <div class="card-header crop-header py-4 rounded-top">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h1 class="h3 fw-bold mb-1">
                                <i class="bi bi-tree me-2"></i><?= htmlspecialchars($crop['name']) ?>
                            </h1>
                            <?php if (!empty($crop['variety'])): ?>
                                <p class="mb-0 opacity-75">Variety: <?= htmlspecialchars($crop['variety']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="badge bg-white text-<?= $statusInfo['color'] ?> fs-6 px-3 py-2">
                            <?= $statusInfo['label'] ?>
                        </span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <!-- Crop Image -->
                    <?php if (!empty($crop['image']) && file_exists(__DIR__ . '/uploads/' . $crop['image'])): ?>
                        <img src="uploads/<?= htmlspecialchars($crop['image']) ?>"
                             alt="<?= htmlspecialchars($crop['name']) ?>"
                             class="crop-image mb-4">
                    <?php else: ?>
                        <div class="bg-light rounded d-flex align-items-center justify-content-center mb-4"
                             style="height:180px;">
                            <div class="text-center text-muted">
                                <i class="bi bi-image fs-1"></i>
                                <p class="mt-2 mb-0">No image available</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Details -->
                    <div class="detail-row">
                        <span class="detail-label"><i class="bi bi-circle-fill text-<?= $statusInfo['color'] ?> me-2 small"></i>Status</span>
                        <span>
                            <span class="badge bg-<?= $statusInfo['color'] ?>"><?= $statusInfo['label'] ?></span>
                        </span>
                    </div>

                    <?php if (!empty($crop['planting_date'])): ?>
                    <div class="detail-row">
                        <span class="detail-label"><i class="bi bi-calendar-event me-2 text-muted"></i>Planting Date</span>
                        <span><?= htmlspecialchars(date('F j, Y', strtotime($crop['planting_date']))) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($crop['expected_harvest'])): ?>
                    <div class="detail-row">
                        <span class="detail-label"><i class="bi bi-calendar-check me-2 text-muted"></i>Expected Harvest</span>
                        <span><?= htmlspecialchars(date('F j, Y', strtotime($crop['expected_harvest']))) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($crop['notes'])): ?>
                    <div class="detail-row">
                        <span class="detail-label"><i class="bi bi-card-text me-2 text-muted"></i>Notes</span>
                        <span><?= nl2br(htmlspecialchars($crop['notes'])) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="detail-row">
                        <span class="detail-label"><i class="bi bi-clock me-2 text-muted"></i>Added On</span>
                        <span><?= htmlspecialchars(date('F j, Y', strtotime($crop['created_at']))) ?></span>
                    </div>

                </div>
            </div>

            <div class="powered-footer">
                <i class="bi bi-leaf me-1"></i>
                Powered by <strong>AgriTrack</strong> — Agricultural Crop Management System
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
