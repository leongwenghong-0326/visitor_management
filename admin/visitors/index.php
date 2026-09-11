<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
require_permission('visitors.view');
require __DIR__ . '/../../includes/layout/pagination.php';

expire_outdated_visitors();

$filters = [
    'q'          => trim((string) get('q', '')),
    'status'     => (string) get('status', ''),
    'visit_date' => (string) get('visit_date', ''),
];
$page = max(1, int_id(get('page', 1)));
$data = list_visitors($filters, $page);

$pageTitle = 'Visitors';
require __DIR__ . '/../../includes/layout/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Visitors</h1>
    <?php if (can('visitors.create')): ?>
        <a class="btn btn-teal" href="<?= e(url('admin/visitors/create.php')) ?>"><i class="fa-solid fa-plus me-1"></i>Register Visitor</a>
    <?php endif; ?>
</div>
<div class="panel mb-3">
<form class="row g-2" method="get">
    <div class="col-md-4"><input type="search" name="q" class="form-control" placeholder="Search…" value="<?= e($filters['q']) ?>"></div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">All statuses</option>
            <?php foreach (['pending','approved','checked_in','checked_out','expired','rejected'] as $s): ?>
                <option value="<?= $s ?>" <?= $filters['status']===$s?'selected':'' ?>><?= e(ucwords(str_replace('_',' ',$s))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3"><input type="date" name="visit_date" class="form-control" value="<?= e($filters['visit_date']) ?>"></div>
    <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div>
</form>
</div>
<div class="panel">
<div class="table-responsive">
<table class="table align-middle mb-0">
<thead><tr><th>Visitor</th><th>Host</th><th>Unit</th><th>Plate</th><th>Visit</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php if (!$data['rows']): ?>
<tr><td colspan="7" class="text-muted">No visitors found.</td></tr>
<?php else: foreach ($data['rows'] as $v): ?>
<tr>
    <td><?= e($v['visitor_name']) ?></td>
    <td><?= e($v['resident_name']) ?></td>
    <td><?= e($v['unit_number']) ?></td>
    <td><?= e($v['car_plate'] ?? '—') ?></td>
    <td><?= e(format_date($v['visit_date'])) ?></td>
    <td><?= status_badge($v['status']) ?></td>
    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/visitors/view.php?id='.$v['id'])) ?>">View</a></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
<div class="mt-3 d-flex justify-content-between">
    <span class="small text-muted"><?= (int)$data['pager']['total'] ?> total</span>
    <?php render_pagination($data['pager'], url('admin/visitors/index.php?'.http_build_query(array_filter($filters)))); ?>
</div>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
