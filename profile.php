<?php
require_once 'includes/auth_check.php';
$pageTitle = 'My Profile';
require_once 'includes/header.php';

$db  = getDB();
$uid = $_SESSION['user_id'];

// Fetch current user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$profileErrors   = [];
$passwordErrors  = [];
$successMsg      = flash('success');
$errorMsg        = flash('error');

// ─── Handle Profile Update ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'profile') {
    csrf_verify();

    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        $profileErrors[] = 'Name is required.';
    }

    $newAvatar = $user['avatar'];

    if (!empty($_FILES['avatar']['name'])) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($_FILES['avatar']['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            $profileErrors[] = 'Avatar must be a JPEG, PNG, or GIF image.';
        } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            $profileErrors[] = 'Avatar must be smaller than 2 MB.';
        } else {
            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $avatarFilename = uniqid('avatar_', true) . '.' . strtolower($ext);
            $avatarDir = __DIR__ . '/uploads/avatars/';
            if (!is_dir($avatarDir)) {
                mkdir($avatarDir, 0755, true);
            }
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $avatarDir . $avatarFilename)) {
                // Delete old avatar if it exists
                if (!empty($user['avatar']) && file_exists($avatarDir . $user['avatar'])) {
                    unlink($avatarDir . $user['avatar']);
                }
                $newAvatar = $avatarFilename;
            } else {
                $profileErrors[] = 'Failed to upload avatar. Please try again.';
            }
        }
    }

    if (empty($profileErrors)) {
        $stmt = $db->prepare("UPDATE users SET name = ?, avatar = ? WHERE id = ?");
        $stmt->execute([$name, $newAvatar, $uid]);

        $_SESSION['user_name'] = $name;

        log_activity($uid, 'update_profile', 'Updated profile name/avatar');
        flash('success', 'Profile updated successfully.');
        header('Location: profile.php');
        exit;
    }
}

// ─── Handle Password Change ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    csrf_verify();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword)) {
        $passwordErrors[] = 'Current password is required.';
    } elseif (!password_verify($currentPassword, $user['password_hash'])) {
        $passwordErrors[] = 'Current password is incorrect.';
    }

    if (strlen($newPassword) < 8) {
        $passwordErrors[] = 'New password must be at least 8 characters.';
    }
    if ($newPassword !== $confirmPassword) {
        $passwordErrors[] = 'New passwords do not match.';
    }

    if (empty($passwordErrors)) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $uid]);

        log_activity($uid, 'change_password', 'Password changed successfully');
        flash('success', 'Password changed successfully.');
        header('Location: profile.php');
        exit;
    }
}

// Re-fetch user in case of partial errors
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <h1 class="h3 fw-bold mb-4 text-success">
                <i class="bi bi-person-circle me-2"></i>My Profile
            </h1>

            <?php if ($successMsg): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($successMsg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($errorMsg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- ─── Profile Update Card ────────────────────────────────── -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-person me-2 text-success"></i>Profile Information</h5>
                </div>
                <div class="card-body p-4">

                    <?php if (!empty($profileErrors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($profileErrors as $err): ?>
                                    <li><?= htmlspecialchars($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="profile.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="profile">

                        <div class="d-flex align-items-center mb-4">
                            <!-- Avatar Preview -->
                            <div class="me-4">
                                <?php if (!empty($user['avatar']) && file_exists('uploads/avatars/' . $user['avatar'])): ?>
                                    <img src="uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>"
                                         id="avatarPreview"
                                         class="rounded-circle border"
                                         style="width:100px;height:100px;object-fit:cover;"
                                         alt="Profile Avatar">
                                <?php else: ?>
                                    <div id="avatarPreview"
                                         class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center border"
                                         style="width:100px;height:100px;">
                                        <i class="bi bi-person-fill fs-1 text-success"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label for="avatar" class="form-label fw-semibold">Profile Photo</label>
                                <input type="file" class="form-control" id="avatar" name="avatar"
                                       accept="image/*" onchange="previewAvatar(event)">
                                <div class="form-text">JPEG, PNG, or GIF. Max 2 MB.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
                            <div class="form-text">Email cannot be changed.</div>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save me-1"></i> Save Profile
                        </button>
                    </form>
                </div>
            </div>

            <!-- ─── Change Password Card ─────────────────────────────────── -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-shield-lock me-2 text-warning"></i>Change Password</h5>
                </div>
                <div class="card-body p-4">

                    <?php if (!empty($passwordErrors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($passwordErrors as $err): ?>
                                    <li><?= htmlspecialchars($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="profile.php">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="password">

                        <div class="mb-3">
                            <label for="current_password" class="form-label fw-semibold">Current Password</label>
                            <input type="password" class="form-control" id="current_password"
                                   name="current_password" autocomplete="current-password" required>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label fw-semibold">New Password</label>
                            <input type="password" class="form-control" id="new_password"
                                   name="new_password" autocomplete="new-password" required
                                   minlength="8">
                            <div class="form-text">At least 8 characters.</div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password"
                                   name="confirm_password" autocomplete="new-password" required>
                        </div>

                        <button type="submit" class="btn btn-warning text-dark">
                            <i class="bi bi-shield-lock me-1"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function previewAvatar(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('avatarPreview');
        if (preview.tagName === 'IMG') {
            preview.src = e.target.result;
        } else {
            // Replace icon div with an img
            const img = document.createElement('img');
            img.src = e.target.result;
            img.id = 'avatarPreview';
            img.className = 'rounded-circle border';
            img.style.cssText = 'width:100px;height:100px;object-fit:cover;';
            img.alt = 'Profile Avatar';
            preview.replaceWith(img);
        }
    };
    reader.readAsDataURL(file);
}
</script>

<?php require_once 'includes/footer.php'; ?>
