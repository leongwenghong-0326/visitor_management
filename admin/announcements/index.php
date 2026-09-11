<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
require_permission('announcements.view');
require __DIR__ . '/../../includes/layout/pagination.php';

$data = list_announcements(['q' => trim((string)get('q',''))], max(1, int_id(get('page',1))));
$pageTitle = 'Announcements';
require __DIR__ . '/../../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Announcements</h1>
    <?php if (can('announcements.create')): ?>
        <a class="btn btn-teal" href="<?= e(url('admin/announcements/create.php')) ?>">New Announcement</a>
    <?php endif; ?>
</div>
<div class="panel">
<div class="table-responsive">
<table class="table align-middle mb-0">
<thead><tr><th>Title</th><th>Audience</th><th>Status</th><th>Published</th><th></th></tr></thead>
<tbody>
<?php foreach ($data['rows'] as $a): ?>
<tr>
    <td><?= e($a['title']) ?></td>
    <td><?= e(ucfirst($a['audience'])) ?></td>
    <td><?= (int)$a['is_published'] ? '<span class="badge text-bg-success">Published</span>' : '<span class="badge text-bg-secondary">Draft</span>' ?></td>
    <td><?= e(format_datetime($a['published_at'])) ?></td>
    <td class="text-end">
        <?php if (can('announcements.edit')): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/announcements/edit.php?id='.$a['id'])) ?>">Edit</a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="mt-3"><?php render_pagination($data['pager'], url('admin/announcements/index.php')); ?></div>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
