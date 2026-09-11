<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
require_login();

$user = current_user();
$page = max(1, int_id(get('page', 1)));

if (is_post()) {
    require_csrf();
    $action = (string) post('action');
    if ($action === 'read' && int_id(post('id'))) {
        mark_notification_read((int) $user['id'], int_id(post('id')));
        flash('success', 'Notification marked as read.');
    } elseif ($action === 'read_all') {
        mark_all_notifications_read((int) $user['id']);
        flash('success', 'All notifications marked as read.');
    }
    redirect('notifications.php');
}

$data = get_notifications((int) $user['id'], $page);
$pageTitle = 'Notifications';
require __DIR__ . '/includes/layout/header.php';
require __DIR__ . '/includes/layout/pagination.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Notifications</h1>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all">
        <button class="btn btn-outline-secondary btn-sm" type="submit">Mark all read</button>
    </form>
</div>
<div class="panel">
    <?php if (!$data['rows']): ?>
        <p class="text-muted mb-0">No notifications yet.</p>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($data['rows'] as $n): ?>
                <div class="list-group-item px-0 <?= !(int)$n['is_read'] ? 'bg-light' : '' ?>">
                    <div class="d-flex justify-content-between gap-2">
                        <div>
                            <div class="fw-semibold"><?= e($n['title']) ?></div>
                            <div class="small"><?= e($n['message']) ?></div>
                            <div class="text-muted small mt-1"><?= e(format_datetime($n['created_at'])) ?></div>
                        </div>
                        <?php if (!(int)$n['is_read']): ?>
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="read">
                            <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                            <button class="btn btn-sm btn-outline-primary">Mark read</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-3"><?php render_pagination($data['pager'], url('notifications.php')); ?></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
