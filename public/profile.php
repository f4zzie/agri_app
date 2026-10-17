<?php
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = t('my_profile');

$db  = getDB();
$uid = (int) $_SESSION['user_id'];

$loadUser = static function () use ($db, $uid): array {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    return $stmt->fetch();
};
$user = $loadUser();

$profileErrors  = [];
$passwordErrors = [];
$action         = $_POST['action'] ?? '';

// ─── Profile details ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'profile') {
    csrf_verify();

    $name     = trim((string) ($_POST['name'] ?? ''));
    $phone    = trim((string) ($_POST['phone'] ?? ''));
    $countyId = (int) ($_POST['county'] ?? 0) ?: null;

    if ($name === '' || mb_strlen($name) > 100) {
        $profileErrors[] = t('err_name_required_user');
    }
    if ($phone !== '' && !preg_match('/^[0-9+\s\-]{7,15}$/', $phone)) {
        $profileErrors[] = t('err_phone_invalid');
    }
    if ($countyId !== null && !isset(counties()[$countyId])) {
        $profileErrors[] = t('err_county_invalid');
    }

    $newAvatar = $user['avatar'];
    $avatarDir = __DIR__ . '/uploads/avatars/';
    if (empty($profileErrors) && App\Upload::wasSubmitted($_FILES['avatar'] ?? null)) {
        $up = App\Upload::storeImage($_FILES['avatar'], $avatarDir, 'avatar_', 2 * 1024 * 1024);
        if ($up['ok']) {
            $newAvatar = $up['filename'];
        } else {
            $profileErrors[] = t($up['error']);
        }
    }

    if (empty($profileErrors)) {
        $db->prepare('UPDATE users SET name = ?, phone = ?, county_id = ?, avatar = ? WHERE id = ?')
           ->execute([$name, $phone !== '' ? $phone : null, $countyId, $newAvatar, $uid]);

        if ($newAvatar !== $user['avatar'] && App\Upload::isSafeStoredName($user['avatar'])
            && is_file($avatarDir . $user['avatar'])) {
            unlink($avatarDir . $user['avatar']);
        }

        $_SESSION['name'] = $name;
        log_activity($uid, 'update_profile', 'Updated profile details');
        flash('success', t('profile_updated'));
        header('Location: profile.php');
        exit;
    }
}

// ─── Password ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'password') {
    csrf_verify();

    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword     = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    // Accounts created through Google have no password yet; they may set one.
    $hasPassword = !empty($user['password_hash']);
    if ($hasPassword && !password_verify($currentPassword, $user['password_hash'])) {
        $passwordErrors[] = t('err_current_password');
    }
    if (strlen($newPassword) < 8) {
        $passwordErrors[] = t('err_password_short');
    }
    if ($newPassword !== $confirmPassword) {
        $passwordErrors[] = t('err_password_mismatch');
    }

    if (empty($passwordErrors)) {
        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
           ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $uid]);
        session_regenerate_id(true);
        log_activity($uid, 'change_password', 'Password changed');
        flash('success', t('password_changed'));
        header('Location: profile.php');
        exit;
    }
}

$form = $action === 'profile'
    ? ['name' => $_POST['name'] ?? '', 'phone' => $_POST['phone'] ?? '', 'county' => (string) ($_POST['county'] ?? '')]
    : ['name' => $user['name'], 'phone' => (string) $user['phone'], 'county' => (string) $user['county_id']];
$avatar = upload_url($user['avatar'], 'avatars');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-2">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <h1 class="h3 fw-bold mb-4 text-success"><i class="bi bi-person-circle me-2"></i><?= t('my_profile') ?></h1>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h5 mb-0 fw-semibold"><i class="bi bi-person me-2 text-success"></i><?= t('profile_information') ?></h2>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($profileErrors)): ?>
                        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($profileErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>

                    <form method="POST" action="profile.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="profile">

                        <div class="d-flex align-items-center mb-4 gap-4 flex-wrap">
                            <?php if ($avatar): ?>
                                <img src="<?= e($avatar) ?>" id="avatarPreview" class="rounded-circle border" style="width:100px;height:100px;object-fit:cover;" alt="">
                            <?php else: ?>
                                <img id="avatarPreview" class="rounded-circle border d-none" style="width:100px;height:100px;object-fit:cover;" alt="">
                                <div id="avatarPlaceholder" class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center border" style="width:100px;height:100px;">
                                    <i class="bi bi-person-fill fs-1 text-success"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <label for="avatar" class="form-label fw-semibold"><?= t('profile_photo') ?></label>
                                <input type="file" class="form-control" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                                <div class="form-text"><?= t('avatar_hint') ?></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold"><?= t('full_name') ?></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= e($form['name']) ?>" required maxlength="100">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label fw-semibold"><?= t('phone') ?></label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($form['phone']) ?>" placeholder="0712 345678">
                                <div class="form-text"><?= t('phone_hint') ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="county" class="form-label fw-semibold"><?= t('county') ?></label>
                                <select class="form-select" id="county" name="county">
                                    <option value="">— <?= t('select') ?> —</option>
                                    <?php foreach (counties() as $cid => $cname): ?>
                                        <option value="<?= $cid ?>" <?= $form['county'] === (string) $cid ? 'selected' : '' ?>><?= e($cname) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="emailShown" class="form-label fw-semibold"><?= t('email') ?></label>
                            <input type="email" id="emailShown" class="form-control bg-light" value="<?= e($user['email']) ?>" disabled>
                            <div class="form-text"><?= t('email_cannot_change') ?></div>
                        </div>
                        <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i><?= t('save_profile') ?></button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h5 mb-0 fw-semibold"><i class="bi bi-shield-lock me-2 text-warning"></i><?= $user['password_hash'] ? t('change_password') : t('set_password') ?></h2>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($passwordErrors)): ?>
                        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($passwordErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>

                    <form method="POST" action="profile.php">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="password">

                        <?php if ($user['password_hash']): ?>
                            <div class="mb-3">
                                <label for="current_password" class="form-label fw-semibold"><?= t('current_password') ?></label>
                                <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small"><?= t('google_set_password') ?></p>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="new_password" class="form-label fw-semibold"><?= t('new_password') ?></label>
                            <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="new-password" required minlength="8">
                            <div class="form-text"><?= t('password_min_8') ?></div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label fw-semibold"><?= t('confirm_password') ?></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                        </div>
                        <button type="submit" class="btn btn-warning"><i class="bi bi-shield-lock me-1"></i><?= $user['password_hash'] ? t('change_password') : t('set_password') ?></button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.getElementById('avatar').addEventListener('change', function () {
    const file = this.files && this.files[0];
    if (!file) return;
    const img = document.getElementById('avatarPreview');
    img.src = URL.createObjectURL(file);
    img.classList.remove('d-none');
    const ph = document.getElementById('avatarPlaceholder');
    if (ph) ph.remove();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
