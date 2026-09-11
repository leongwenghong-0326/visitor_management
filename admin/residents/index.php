<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
require_permission('residents.view');
require __DIR__ . '/../../includes/layout/pagination.php';

$filters = [
    'q'      => trim((string) get('q', '')),
    'status' => (string) get('status', ''),
];
$page = max(1, int_id(get('page', 1)));
$data = list_residents($filters, $page);

$pageTitle = 'Residents';
require __DIR__ . '/../../includes/layout/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Residents</h1>
    <?php if (can('residents.create')): ?>
        <a href="<?= e(url('admin/residents/create.php')) ?>" class="btn btn-teal"><i class="fa-solid fa-plus me-1"></i>Add Resident</a>
    <?php endif; ?>
</div>
<div class="panel mb-3">
    <form class="row g-2" method="get">
        <div class="col-md-5">
            <input type="search" name="q" class="form-control" placeholder="Search name, code, unit, phone…" value="<?= e($filters['q']) ?>">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <?php foreach (['pending','active','suspended','moved_out','inactive'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filters['status']===$s?'selected':'' ?>><?= e(ucwords(str_replace('_',' ',$s))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-outline-secondary w-100" type="submit">Filter</button></div>
    </form>
</div>
<div class="panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Code</th><th>Name</th><th>Unit</th><th>Phone</th><th>Status</th><th>Account</th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$data['rows']): ?>
                <tr><td colspan="7" class="text-muted">No residents found.</td></tr>
            <?php else: foreach ($data['rows'] as $r): ?>
                <tr>
                    <td><?= e($r['resident_code']) ?></td>
                    <td><?= e($r['full_name']) ?></td>
                    <td><?= e($r['unit_number']) ?></td>
                    <td><?= e($r['phone'] ?? '—') ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td><?= e($r['linked_username'] ?? '—') ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/residents/view.php?id='.$r['id'])) ?>">View</a>
                        <?php if (can('residents.edit')): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/residents/edit.php?id='.$r['id'])) ?>">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-3 d-flex justify-content-between align-items-center">
        <span class="small text-muted"><?= (int)$data['pager']['total'] ?> total</span>
        <?php
        $qs = http_build_query(array_filter($filters));
        render_pagination($data['pager'], url('admin/residents/index.php' . ($qs ? '?'.$qs : '')));
        ?>
    </div>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
