<?php
/**
 * AgriTrack – inquiry.php
 * A buyer starts a conversation with a farmer about a product.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_role('buyer');

$db     = getDB();
$uid    = (int) $_SESSION['user_id'];
$cropId = (int) ($_GET['crop_id'] ?? $_POST['crop_id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM v_marketplace WHERE id = ?');
$stmt->execute([$cropId]);
$crop = $stmt->fetch();

if (!$crop) {
    flash('danger', t('product_not_found'));
    header('Location: marketplace.php');
    exit;
}

// One conversation per buyer and product: continue the existing one
if ($existing = App\Inquiries::existingId($db, $cropId, $uid)) {
    header('Location: inquiry_thread.php?id=' . $existing);
    exit;
}

if ($crop['status'] !== 'available') {
    flash('warning', t('product_unavailable'));
    header('Location: marketplace.php');
    exit;
}

$buyerStmt = $db->prepare('SELECT phone FROM users WHERE id = ?');
$buyerStmt->execute([$uid]);
$contactPhone = (string) ($buyerStmt->fetchColumn() ?: '');
$message      = '';
$errors       = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $message      = trim((string) ($_POST['message'] ?? ''));
    $contactPhone = trim((string) ($_POST['contact_phone'] ?? ''));

    if (mb_strlen($message) < 10) {
        $errors[] = t('err_message_short');
    }
    if (mb_strlen($message) > 2000) {
        $errors[] = t('err_message_long');
    }
    if ($contactPhone !== '' && !preg_match('/^[0-9+\s\-]{7,15}$/', $contactPhone)) {
        $errors[] = t('err_phone_invalid');
    }

    if (empty($errors)) {
        $inquiryId = App\Inquiries::open($db, $cropId, $uid, $message, $contactPhone);
        log_activity($uid, 'send_inquiry', "Inquiry sent for: {$crop['name']}");

        $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost/agri_app', '/');
        sendEmail(
            $crop['farmer_email'],
            $crop['farmer_name'],
            'New inquiry: ' . $crop['name'],
            '<p>Hello <strong>' . e($crop['farmer_name']) . '</strong>,</p>'
            . '<p><strong>' . e($_SESSION['name']) . '</strong> asked about <strong>' . e($crop['name']) . '</strong>:</p>'
            . '<blockquote>' . nl2br(e($message)) . '</blockquote>'
            . '<p><a href="' . e($appUrl . '/inquiry_thread.php?id=' . $inquiryId) . '">Reply in AgriTrack →</a></p>'
        );

        flash('success', t('inquiry_sent'));
        header('Location: inquiry_thread.php?id=' . $inquiryId);
        exit;
    }
}

$pageTitle = t('contact_farmer');
require_once __DIR__ . '/../includes/header.php';
$img = upload_url($crop['image']);
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h1 class="h5 mb-0"><i class="bi bi-chat-dots me-2"></i><?= t('contact_farmer') ?></h1>
            </div>
            <div class="card-body">

                <div class="d-flex gap-3 align-items-center mb-4 p-3 bg-light rounded">
                    <?php if ($img): ?>
                        <img src="<?= e($img) ?>" style="width:60px;height:60px;object-fit:cover;border-radius:8px;" alt="">
                    <?php else: ?>
                        <div class="bg-success bg-opacity-25 rounded d-flex align-items-center justify-content-center"
                             style="width:60px;height:60px;font-size:1.5rem;" aria-hidden="true"><?= product_emoji($crop['category'] ?? null) ?></div>
                    <?php endif; ?>
                    <div>
                        <div class="fw-bold"><?= e($crop['name']) ?> <span class="text-success small ms-1"><?= format_price($crop['price'], $crop['unit']) ?></span></div>
                        <div class="small text-muted">
                            <a href="farmer.php?id=<?= (int) $crop['farmer_id'] ?>" class="text-reset"><i class="bi bi-person me-1"></i><?= e($crop['farmer_name']) ?></a>
                            <?php if ($crop['farmer_county']): ?> · <span class="badge bg-success"><?= e($crop['farmer_county']) ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
                <?php endif; ?>

                <form method="POST" action="inquiry.php">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="crop_id" value="<?= $cropId ?>">

                    <div class="mb-3">
                        <label for="contact_phone" class="form-label"><?= t('your_contact_phone') ?></label>
                        <input type="tel" name="contact_phone" id="contact_phone" class="form-control"
                               value="<?= e($contactPhone) ?>" placeholder="0712 345678">
                        <div class="form-text"><?= t('contact_phone_hint') ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label"><?= t('your_message') ?> <span class="text-danger">*</span></label>
                        <textarea name="message" id="message" class="form-control" rows="5" required minlength="10" maxlength="2000"
                                  placeholder="<?= t('inquiry_message_ph') ?>"><?= e($message) ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success"><i class="bi bi-send me-1"></i><?= t('send_inquiry') ?></button>
                        <a href="marketplace.php" class="btn btn-outline-secondary"><?= t('cancel') ?></a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
