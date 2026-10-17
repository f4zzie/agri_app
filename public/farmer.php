<?php
/**
 * AgriTrack – farmer.php
 * A farmer's profile: county, rating from completed deals, reviews and listings.
 */
require_once __DIR__ . '/../includes/auth_check.php';
$db       = getDB();
$farmerId = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare(
    "SELECT u.id, u.name, u.avatar, u.created_at, co.name AS county,
            fr.avg_rating, COALESCE(fr.rating_count, 0) AS rating_count,
            (SELECT COUNT(*) FROM v_inquiry_threads t
              WHERE t.farmer_id = u.id AND t.status = 'completed') AS deals_completed
       FROM users u
       LEFT JOIN counties co         ON co.id = u.county_id
       LEFT JOIN v_farmer_ratings fr ON fr.farmer_id = u.id
      WHERE u.id = ? AND u.role = 'farmer'"
);
$stmt->execute([$farmerId]);
$farmer = $stmt->fetch();

if (!$farmer) {
    flash('danger', t('farmer_not_found'));
    header('Location: marketplace.php');
    exit;
}

$stmt = $db->prepare(
    'SELECT r.score, r.comment, r.created_at, b.name AS buyer_name, c.name AS crop_name
       FROM ratings r
       JOIN inquiries i ON i.id = r.inquiry_id
       JOIN crops c     ON c.id = i.crop_id
       JOIN users b     ON b.id = i.buyer_id
      WHERE c.user_id = ?
      ORDER BY r.created_at DESC LIMIT 10'
);
$stmt->execute([$farmerId]);
$reviews = $stmt->fetchAll();

$stmt = $db->prepare(
    "SELECT * FROM v_marketplace WHERE farmer_id = ? AND status = 'available' ORDER BY created_at DESC LIMIT 12"
);
$stmt->execute([$farmerId]);
$listings = $stmt->fetchAll();

$pageTitle = $farmer['name'];
require_once __DIR__ . '/../includes/header.php';
$avatar = upload_url($farmer['avatar'], 'avatars');
$stars  = static fn (float $s): string => str_repeat('★', (int) round($s)) . str_repeat('☆', 5 - (int) round($s));
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex align-items-center gap-3 flex-wrap">
        <?php if ($avatar): ?>
            <img src="<?= e($avatar) ?>" class="rounded-circle" style="width:80px;height:80px;object-fit:cover;" alt="">
        <?php else: ?>
            <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width:80px;height:80px;font-size:2rem;" aria-hidden="true">🌾</div>
        <?php endif; ?>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1"><?= e($farmer['name']) ?></h1>
            <div class="text-muted small">
                <?php if ($farmer['county']): ?><i class="bi bi-geo-alt me-1"></i><?= e($farmer['county']) ?> · <?php endif; ?>
                <?= sprintf(t('member_since'), date('M Y', strtotime($farmer['created_at']))) ?>
            </div>
        </div>
        <div class="text-center">
            <?php if ($farmer['rating_count'] > 0): ?>
                <div class="text-warning fs-4" aria-label="<?= e((string) $farmer['avg_rating']) ?>/5"><?= $stars((float) $farmer['avg_rating']) ?></div>
                <div class="small text-muted"><?= sprintf(t('rating_summary'), $farmer['avg_rating'], (int) $farmer['rating_count']) ?></div>
            <?php else: ?>
                <div class="small text-muted"><?= t('no_ratings_yet') ?></div>
            <?php endif; ?>
            <div class="small text-muted"><?= sprintf(t('deals_completed'), (int) $farmer['deals_completed']) ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <h2 class="h5 mb-3"><?= t('available_products') ?></h2>
        <?php if (empty($listings)): ?>
            <p class="text-muted"><?= t('no_available_products') ?></p>
        <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 g-3">
            <?php foreach ($listings as $p): $img = upload_url($p['image']); ?>
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <?php if ($img): ?>
                        <img src="<?= e($img) ?>" class="card-img-top" style="height:130px;object-fit:cover;" alt="" loading="lazy">
                    <?php else: ?>
                        <div class="bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="height:130px;font-size:2.5rem;" aria-hidden="true"><?= product_emoji($p['category']) ?></div>
                    <?php endif; ?>
                    <div class="card-body py-2">
                        <h3 class="h6 fw-bold mb-1"><a href="view_crop.php?id=<?= (int) $p['id'] ?>" class="text-reset"><?= e($p['name']) ?></a></h3>
                        <div class="text-success small fw-semibold"><?= format_price($p['price'], $p['unit']) ?></div>
                        <?= stage_badge($p['growth_stage']) ?>
                    </div>
                    <?php if (($_SESSION['role'] ?? '') === 'buyer'): ?>
                    <div class="card-footer bg-transparent">
                        <a href="inquiry.php?crop_id=<?= (int) $p['id'] ?>" class="btn btn-success btn-sm w-100"><?= t('contact_farmer') ?></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <h2 class="h5 mb-3"><?= t('reviews') ?></h2>
        <?php if (empty($reviews)): ?>
            <p class="text-muted"><?= t('no_ratings_yet') ?></p>
        <?php else: ?>
            <?php foreach ($reviews as $r): ?>
                <div class="card border-0 shadow-sm mb-2">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between">
                            <span class="text-warning"><?= $stars((float) $r['score']) ?></span>
                            <small class="text-muted"><?= date('d M Y', strtotime($r['created_at'])) ?></small>
                        </div>
                        <?php if ($r['comment']): ?><p class="small mb-1">“<?= e($r['comment']) ?>”</p><?php endif; ?>
                        <small class="text-muted">— <?= e(strtok($r['buyer_name'], ' ')) ?>, <?= e($r['crop_name']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
