<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
require_permission('blacklist.view');
require __DIR__ . '/../../includes/layout/pagination.php';

if (is_post() && can('blacklist.manage')) {
    require_csrf();
    $action = (string) post('action');
    if ($action === 'add') {
        $r = add_blacklist($_POST, (int) current_user()['id']);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Blacklist entry added.' : implode(' ', $r['errors']));
    } elseif ($action === 'toggle') {
        set_blacklist_active(int_id(post('id')), (int) post('active') === 1);
        flash('success', 'Blacklist entry updated.');
    }
    redirect('admin/blacklist/index.php');
}

$filters = ['q' => trim((string) get('q', '')), 'active' => (string) get('active', '')];
$data = list_blacklist($filters, max(1, int_id(get('page', 1))));

$pageTitle = 'Visitor Blacklist';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="h3 mb-3">Visitor Blacklist</h1>
<?php if (can('blacklist.manage')): ?>
<div class="panel mb-3">
    <h2 class="h6">Add Entry</h2>
    <form method="post" class="row g-2">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div class="col-md-3"><input name="full_name" class="form-control" placeholder="Name"></div>
        <div class="col-md-2"><input name="car_plate" class="form-control" placeholder="Car plate"></div>
        <div class="col-md-2"><input name="phone" class="form-control" placeholder="Phone"></div>
        <div class="col-md-3"><input name="reason" class="form-control" placeholder="Reason *" required></div>
        <div class="col-md-2"><button class="btn btn-teal w-100" type="submit">Add</button></div>
    </form>
</div>
<?php endif; ?>
<div class="panel mb-3">
<form method="get" class="row g-2">
    <div class="col-md-5"><input type="search" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Search…"></div>
    <div class="col-md-3">
        <select name="active" class="form-select">
            <option value="">All</option>
            <option value="1" <?= $filters['active']==='1'?'selected':'' ?>>Active</option>
            <option value="0" <?= $filters['active']==='0'?'selected':'' ?>>Inactive</option>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div>
</form>
</div>
<div class="panel">
<div class="table-responsive">
<table class="table align-middle mb-0">
<thead><tr><th>Name</th><th>Plate</th><th>Phone</th><th>Reason</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php if (!$data['rows']): ?>
<tr><td colspan="6" class="text-muted">No blacklist entries.</td></tr>
<?php else: foreach ($data['rows'] as $b): ?>
<tr>
    <td><?= e($b['full_name'] ?? '—') ?></td>
    <td><?= e($b['car_plate'] ?? '—') ?></td>
    <td><?= e($b['phone'] ?? '—') ?></td>
    <td><?= e($b['reason']) ?></td>
    <td><?= (int)$b['is_active'] ? status_badge('active') : status_badge('inactive') ?></td>
    <td class="text-end">
        <?php if (can('blacklist.manage')): ?>
        <form method="post" class="d-inline"><?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <input type="hidden" name="active" value="<?= (int)$b['is_active'] ? 0 : 1 ?>">
            <button class="btn btn-sm btn-outline-secondary"><?= (int)$b['is_active'] ? 'Deactivate' : 'Activate' ?></button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
<div class="mt-3"><?php render_pagination($data['pager'], url('admin/blacklist/index.php?'.http_build_query(array_filter($filters)))); ?></div>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
