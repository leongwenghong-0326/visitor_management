<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
require_permission('staff.view');
require __DIR__ . '/../../includes/layout/pagination.php';

$q = trim((string) get('q', ''));
$page = max(1, int_id(get('page', 1)));
$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (u.username LIKE :q1 OR u.full_name LIKE :q2 OR u.email LIKE :q3)';
    $like = '%' . $q . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
}
$count = db()->prepare("SELECT COUNT(*) FROM users u WHERE $where");
$count->execute($params);
$pager = paginate((int)$count->fetchColumn(), $page);
$stmt = db()->prepare(
    "SELECT u.id, u.username, u.email, u.full_name, u.phone, u.status, u.last_login_at, r.name AS role_name
     FROM users u INNER JOIN roles r ON r.id = u.role_id
     WHERE $where ORDER BY u.id DESC LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Staff';
require __DIR__ . '/../../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Staff / Users</h1>
    <?php if (can('staff.create')): ?>
        <a class="btn btn-teal" href="<?= e(url('admin/staff/create.php')) ?>">Add Staff</a>
    <?php endif; ?>
</div>
<div class="panel mb-3">
<form method="get" class="row g-2">
    <div class="col-md-6"><input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Search…"></div>
    <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Search</button></div>
</form>
</div>
<div class="panel">
<div class="table-responsive">
<table class="table align-middle mb-0">
<thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $u): ?>
<tr>
    <td><?= e($u['full_name']) ?></td>
    <td><?= e($u['username']) ?></td>
    <td><?= e($u['email']) ?></td>
    <td><?= e($u['role_name']) ?></td>
    <td><?= status_badge($u['status']) ?></td>
    <td><?= e(format_datetime($u['last_login_at'])) ?></td>
    <td class="text-end">
        <?php if (can('staff.edit')): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/staff/edit.php?id='.$u['id'])) ?>">Edit</a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="mt-3"><?php render_pagination($pager, url('admin/staff/index.php'.($q?'?q='.urlencode($q):''))); ?></div>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
