<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$uid = $_SESSION['user_id'];
$q   = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$like = '%' . $q . '%';

$db   = getDB();
$stmt = $db->prepare(
    "SELECT id, name, variety, status, expected_harvest
     FROM crops
     WHERE user_id = ? AND (name LIKE ? OR variety LIKE ?)
     ORDER BY created_at DESC
     LIMIT 10"
);
$stmt->execute([$uid, $like, $like]);
$crops = $stmt->fetchAll(PDO::FETCH_ASSOC);

$appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost/agri_app', '/');

$results = [];
foreach ($crops as $crop) {
    $results[] = [
        'id'               => (int) $crop['id'],
        'name'             => $crop['name'],
        'variety'          => $crop['variety'] ?? null,
        'status'           => $crop['status'],
        'expected_harvest' => $crop['expected_harvest'] ?? null,
        'edit_url'         => $appUrl . '/edit_crop.php?id=' . $crop['id'],
        'qr_url'           => $appUrl . '/qrcode.php?crop_id=' . $crop['id'],
    ];
}

echo json_encode($results);
exit;
