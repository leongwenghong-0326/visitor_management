<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
if (!is_admin()) {
    flash('error', 'Access denied.');
    redirect(role_home_path());
}

expire_outdated_visitors();

$stats = [
    'residents_total'  => count_residents(),
    'residents_active' => count_residents('active'),
    'visitors_today'   => count_visitors_today(),
    'visitors_inside'  => count_visitors_inside(),
];

$recent = get_activity_logs([], 1, 10);
$announcements = published_announcements_for_role('admin', 5);
$inside = list_visitors(['inside' => true], 1, 8);

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../includes/layout/header.php';
?>
<h1 class="h3 mb-4">Admin Dashboard</h1>
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Residents', $stats['residents_total'], 'fa-users', 'admin/residents/index.php'],
        ['Active Residents', $stats['residents_active'], 'fa-user-check', 'admin/residents/index.php?status=active'],
        ["Today's Visitors", $stats['visitors_today'], 'fa-id-badge', 'admin/visitors/index.php?visit_date=' . urlencode(today())],
        ['Currently Inside', $stats['visitors_inside'], 'fa-door-open', 'admin/visitors/index.php?status=checked_in'],
    ];
    foreach ($cards as [$label, $val, $icon, $link]): ?>
    <div class="col-6 col-lg-3">
        <a href="<?= e(url($link)) ?>" class="text-decoration-none">
            <div class="stat-card h-100">
                <div class="text-muted small"><i class="fa-solid <?= e($icon) ?> me-1"></i><?= e($label) ?></div>
                <div class="stat-value"><?= (int)$val ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="panel mb-3">
            <h2 class="h5">Visitors Currently Inside</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Visitor</th><th>Host</th><th>Unit</th><th>In at</th></tr></thead>
                    <tbody>
                    <?php if (!$inside['rows']): ?>
                        <tr><td colspan="4" class="text-muted">No visitors inside.</td></tr>
                    <?php else: foreach ($inside['rows'] as $v): ?>
                        <tr>
                            <td><?= e($v['visitor_name']) ?></td>
                            <td><?= e($v['resident_name']) ?></td>
                            <td><?= e($v['unit_number']) ?></td>
                            <td><?= e(format_datetime($v['checked_in_at'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="panel">
            <h2 class="h5">Recent Activity</h2>
            <ul class="list-group list-group-flush">
                <?php foreach ($recent['rows'] as $a): ?>
                    <li class="list-group-item px-0 small">
                        <strong><?= e($a['full_name'] ?? 'System') ?></strong>
                        — <?= e($a['description'] ?: $a['action']) ?>
                        <span class="text-muted"><?= e(format_datetime($a['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panel">
            <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Announcements</h2><a class="small" href="<?= e(url('admin/announcements/index.php')) ?>">Manage</a></div>
            <?php if (!$announcements): ?>
                <p class="text-muted mb-0">No published announcements.</p>
            <?php else: foreach ($announcements as $ann): ?>
                <div class="mb-3 pb-3 border-bottom">
                    <div class="fw-semibold"><?= e($ann['title']) ?></div>
                    <div class="small text-muted"><?= e(format_datetime($ann['published_at'])) ?></div>
                    <div class="small mt-1"><?= e(mb_strimwidth($ann['body'], 0, 160, '…')) ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
