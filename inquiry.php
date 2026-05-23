<?php
/**
 * AgriTrack – inquiry.php
 * Buyer sends an inquiry to a farmer about a specific product.
 */
require_once 'includes/auth_check.php';
$db       = getDB();
$uid      = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'farmer';

// Only buyers can send inquiries
if ($userRole === 'farmer') {
    flash('danger', 'Only business owners can send inquiries. Farmers can list products instead.');
    header('Location: marketplace.php');
    exit;
}

$cropId = (int)($_GET['crop_id'] ?? $_POST['crop_id'] ?? 0);
if (!$cropId) {
    flash('danger', 'Product not found.');
    header('Location: marketplace.php');
    exit;
}

// Fetch crop + farmer info
$stmt = $db->prepare(
    "SELECT c.*, u.name AS farmer_name, u.email AS farmer_email,
            u.county AS farmer_county, u.phone AS farmer_phone
     FROM crops c
     JOIN users u ON c.user_id = u.id
     WHERE c.id = ? AND u.role = 'farmer'"
);
$stmt->execute([$cropId]);
$crop = $stmt->fetch();

if (!$crop) {
    flash('danger', 'Product not found.');
    header('Location: marketplace.php');
    exit;
}

// Fetch buyer's phone for pre-fill
$buyerStmt = $db->prepare('SELECT phone FROM users WHERE id = ?');
$buyerStmt->execute([$uid]);
$buyerData = $buyerStmt->fetch();
$buyerPhone = $buyerData['phone'] ?? '';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $message    = trim($_POST['message']     ?? '');
    $phoneInput = trim($_POST['buyer_phone'] ?? $buyerPhone);

    if (empty($message) || mb_strlen($message) < 10) {
        $errors[] = 'Please write a message of at least 10 characters.';
    }

    if (empty($errors)) {
        // Save inquiry
        $ins = $db->prepare(
            'INSERT INTO inquiries (crop_id, buyer_id, farmer_id, message, buyer_name, buyer_email, buyer_phone, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
        );
        $ins->execute([
            $cropId,
            $uid,
            (int)$crop['user_id'],
            $message,
            $_SESSION['name'],
            $_SESSION['email'],
            $phoneInput,
        ]);

        log_activity($uid, 'send_inquiry', "Inquiry sent for: {$crop['name']}");

        // Email to farmer
        $appUrl  = $_ENV['APP_URL'] ?? 'http://localhost/agri_app';
        $htmlBody =
            "<p>Hello <strong>{$crop['farmer_name']}</strong>,</p>" .
            "<p>You have a new inquiry from <strong>" . htmlspecialchars($_SESSION['name']) . "</strong> about your product <strong>" . htmlspecialchars($crop['name']) . "</strong>.</p>" .
            "<hr>" .
            "<p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>" .
            "<p><strong>Buyer contact email:</strong> " . htmlspecialchars($_SESSION['email']) . "<br>" .
            "<strong>Buyer phone:</strong> " . htmlspecialchars($phoneInput) . "</p>" .
            "<hr>" .
            "<p><a href='{$appUrl}/my_inquiries.php' style='background:#198754;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>View Inquiry →</a></p>" .
            "<p style='color:#888;font-size:12px;'>AgriTrack – Connecting Farmers to Business Owners</p>";

        sendEmail(
            $crop['farmer_email'],
            $crop['farmer_name'],
            'New Inquiry: ' . $crop['name'],
            $htmlBody
        );

        flash('success', 'Your inquiry has been sent! The farmer will contact you soon.');
        header('Location: my_inquiries.php');
        exit;
    }
}

$pageTitle = 'Contact Farmer';
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-chat-dots me-2"></i>Contact Farmer</h5>
            </div>
            <div class="card-body">

                <!-- Product summary -->
                <div class="d-flex gap-3 align-items-center mb-4 p-3 bg-light rounded">
                    <?php if ($crop['image'] && file_exists("uploads/{$crop['image']}")): ?>
                        <img src="uploads/<?= htmlspecialchars($crop['image']) ?>"
                             style="width:60px;height:60px;object-fit:cover;border-radius:8px;"
                             alt="<?= htmlspecialchars($crop['name']) ?>">
                    <?php else: ?>
                        <div class="bg-success bg-opacity-25 rounded d-flex align-items-center justify-content-center"
                             style="width:60px;height:60px;font-size:1.5rem;">🌿</div>
                    <?php endif; ?>
                    <div>
                        <div class="fw-bold"><?= htmlspecialchars($crop['name']) ?></div>
                        <div class="small text-muted">
                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($crop['farmer_name']) ?>
                            <?php if ($crop['farmer_county']): ?>
                                · <span class="badge bg-success"><?= htmlspecialchars($crop['farmer_county']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Errors -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $e): ?>
                            <div><?= htmlspecialchars($e) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Inquiry Form -->
                <form method="POST" action="inquiry.php">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="crop_id"   value="<?= $cropId ?>">

                    <div class="mb-3">
                        <label for="buyer_phone" class="form-label">Your Contact Phone</label>
                        <input type="tel" name="buyer_phone" id="buyer_phone" class="form-control"
                               value="<?= htmlspecialchars($buyerPhone) ?>"
                               placeholder="e.g. 0712 345678">
                        <div class="form-text">The farmer will use this to reach you.</div>
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">Your Message <span class="text-danger">*</span></label>
                        <textarea name="message" id="message" class="form-control" rows="5"
                                  placeholder="Describe what you need — quantities, quality, delivery, preferred price range…" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-send me-1"></i>Send Inquiry
                        </button>
                        <a href="marketplace.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
