<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
if (!is_resident()) {
    flash('error', 'Access denied.');
    redirect(role_home_path());
}

$residentId = current_resident_id();
$resident = $residentId ? get_resident($residentId) : null;
if (!$resident) {
    flash('error', 'No resident profile linked to your account. Contact admin.');
    $pageTitle = 'Resident Dashboard';
    require __DIR__ . '/../includes/layout/header.php';
    echo '<div class="alert alert-warning">Resident profile missing.</div>';
    require __DIR__ . '/../includes/layout/footer.php';
    exit;
}

$qr = get_active_resident_qr($residentId);
$visitors = list_visitors(['resident_id' => $residentId], 1, 8);
$upcoming = list_visitors(['resident_id' => $residentId, 'from' => today()], 1, 5);
$announcements = published_announcements_for_role('resident', 5);
$unread = unread_notification_count((int) current_user()['id']);

$pageTitle = 'Resident Dashboard';
$extraScripts = '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>';
require __DIR__ . '/../includes/layout/header.php';
?>
<h1 class="h3 mb-1">Welcome, <?= e($resident['full_name']) ?></h1>
<p class="text-muted mb-4">Unit <?= e($resident['unit_number']) ?> · <?= e($resident['resident_code']) ?></p>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card"><div class="text-muted small">My Visitors</div><div class="stat-value"><?= (int)$visitors['pager']['total'] ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="text-muted small">Unread Notifications</div><div class="stat-value"><?= (int)$unread ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="text-muted small">Status</div><div class="mt-2"><?= status_badge($resident['status']) ?></div></div></div>
</div>

<div class="row g-3">
<div class="col-lg-4">
    <div class="panel mb-3">
        <h2 class="h5">My Info</h2>
        <dl class="row small mb-0">
            <dt class="col-5">Phone</dt><dd class="col-7"><?= e($resident['phone'] ?? '—') ?></dd>
            <dt class="col-5">Email</dt><dd class="col-7"><?= e($resident['email'] ?? '—') ?></dd>
            <dt class="col-5">Emergency</dt><dd class="col-7"><?= e($resident['emergency_contact'] ?? '—') ?></dd>
        </dl>
        <a class="btn btn-sm btn-outline-primary mt-2" href="<?= e(url('resident/qr.php')) ?>">View QR</a>
        <a class="btn btn-sm btn-teal mt-2" href="<?= e(url('resident/visitors/create.php')) ?>">Register Visitor</a>
    </div>
    <?php if ($qr): ?>
    <div class="panel">
        <h2 class="h5">My QR</h2>
        <div class="qr-box" id="qrcode"></div>
        <script>document.addEventListener('DOMContentLoaded',function(){new QRCode(document.getElementById('qrcode'),{text:<?= json_encode($qr['token']) ?>,width:160,height:160});});</script>
    </div>
    <?php endif; ?>
</div>
<div class="col-lg-8">
    <div class="panel mb-3">
        <div class="d-flex justify-content-between"><h2 class="h5 mb-0">My Visitors</h2><a href="<?= e(url('resident/visitors/index.php')) ?>">View all</a></div>
        <div class="table-responsive mt-2">
            <table class="table table-sm mb-0">
                <thead><tr><th>Name</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (!$visitors['rows']): ?><tr><td colspan="3" class="text-muted">No visitors yet.</td></tr>
                <?php else: foreach ($visitors['rows'] as $v): ?>
                    <tr>
                        <td><a href="<?= e(url('resident/visitors/view.php?id='.$v['id'])) ?>"><?= e($v['visitor_name']) ?></a></td>
                        <td><?= e(format_date($v['visit_date'])) ?></td>
                        <td><?= status_badge($v['status']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel">
        <h2 class="h5">Announcements</h2>
        <?php if (!$announcements): ?><p class="text-muted mb-0">No announcements.</p>
        <?php else: foreach ($announcements as $a): ?>
            <div class="mb-2 pb-2 border-bottom"><strong><?= e($a['title']) ?></strong><div class="small"><?= e(mb_strimwidth($a['body'],0,140,'…')) ?></div></div>
        <?php endforeach; endif; ?>
        <a class="small" href="<?= e(url('resident/announcements.php')) ?>">See all</a>
    </div>
</div>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
