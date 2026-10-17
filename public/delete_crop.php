<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role('farmer');

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

csrf_verify();

$db  = getDB();
$uid = $_SESSION['user_id'];
$cropId = isset($_POST['crop_id']) ? (int) $_POST['crop_id'] : 0;

if (!$cropId) {
    flash('danger', t('product_not_found'));
    header('Location: dashboard.php');
    exit;
}

// Fetch crop and verify ownership
$stmt = $db->prepare("SELECT * FROM crops WHERE id = ? AND user_id = ?");
$stmt->execute([$cropId, $uid]);
$crop = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$crop) {
    flash('danger', t('product_not_found'));
    header('Location: dashboard.php');
    exit;
}

// Delete image file if it exists
if (\App\Upload::isSafeStoredName($crop['image'])) {
    $imagePath = __DIR__ . '/uploads/' . $crop['image'];
    if (file_exists($imagePath)) {
        unlink($imagePath);
    }
}

// Delete the crop record
$stmt = $db->prepare("DELETE FROM crops WHERE id = ? AND user_id = ?");
$stmt->execute([$cropId, $uid]);

$name = $crop['name'];
log_activity($uid, 'delete_product', "Deleted product: {$name} (ID: {$cropId})");
flash('success', sprintf(t('product_deleted'), $name));
header('Location: dashboard.php');
exit;
