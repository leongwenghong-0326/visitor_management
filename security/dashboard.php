<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
if (!is_security() && !is_admin()) {
    flash('error', 'Access denied.');
    redirect(role_home_path());
}

expire_outdated_visitors();

// Reliable button checkout (no fetch dependency)
if (is_post() && (string) post('action') === 'checkout') {
    require_csrf();
    if (!can('visitors.checkout')) {
        flash('error', 'Permission denied.');
        redirect('security/dashboard.php');
    }
    $result = process_visitor_checkout(int_id(post('visitor_id')), (int) current_user()['id']);
    flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('security/dashboard.php');
}

$inside = list_visitors(['inside' => true], 1, 20);
$todayCount = count_visitors_today();
$insideCount = count_visitors_inside();
$announcements = published_announcements_for_role('security', 5);

$pageTitle = 'Security Dashboard';
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h3 mb-0">Security Dashboard</h1>
    <a href="<?= e(url('security/scan.php')) ?>" class="btn btn-teal"><i class="fa-solid fa-qrcode me-1"></i>Open QR Scanner</a>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card"><div class="text-muted small">Currently Inside</div><div class="stat-value"><?= (int)$insideCount ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="text-muted small">Today's Visitors</div><div class="stat-value"><?= (int)$todayCount ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="text-muted small">Quick Action</div><div class="mt-2"><a href="<?= e(url('security/scan.php')) ?>" class="btn btn-sm btn-outline-primary">Scan / Check-in</a></div></div></div>
</div>

<div class="row g-3">
<div class="col-lg-8">
<div class="panel">
    <h2 class="h5">Visitors Currently Inside</h2>
    <div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead><tr><th>Visitor</th><th>Host</th><th>Unit</th><th>Plate</th><th>In at</th><th></th></tr></thead>
        <tbody>
        <?php if (!$inside['rows']): ?>
            <tr><td colspan="6" class="text-muted">No visitors inside.</td></tr>
        <?php else: foreach ($inside['rows'] as $v): ?>
            <tr>
                <td><?= e($v['visitor_name']) ?></td>
                <td><?= e($v['resident_name']) ?></td>
                <td><?= e($v['unit_number']) ?></td>
                <td><?= e($v['car_plate'] ?? '-') ?></td>
                <td><?= e(format_datetime($v['checked_in_at'])) ?></td>
                <td class="text-end">
                    <?php if (can('visitors.checkout')): ?>
                    <form method="post" action="" class="d-inline" onsubmit="return confirm('Check out this visitor?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="checkout">
                        <input type="hidden" name="visitor_id" value="<?= (int)$v['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Check Out</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>
</div>
<div class="col-lg-4">
<div class="panel">
    <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Announcements</h2><a class="small" href="<?= e(url('security/announcements.php')) ?>">View all</a></div>
    <?php if (!$announcements): ?><p class="text-muted mb-0">None.</p>
    <?php else: foreach ($announcements as $a): ?>
        <div class="mb-3"><div class="fw-semibold"><?= e($a['title']) ?></div><div class="small"><?= e(mb_strimwidth($a['body'],0,120,'...')) ?></div></div>
    <?php endforeach; endif; ?>
</div>
</div>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>