<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
if (!is_resident()) {
    redirect(role_home_path());
}
$rows = published_announcements_for_role('resident', 50);
$pageTitle = 'Announcements';
require __DIR__ . '/../includes/layout/header.php';
?>
<h1 class="h3 mb-3">Announcements</h1>
<div class="panel">
<?php if (!$rows): ?>
    <p class="text-muted mb-0">No published announcements.</p>
<?php else: foreach ($rows as $a): ?>
    <article class="mb-4 pb-3 border-bottom">
        <h2 class="h5 mb-1"><?= e($a['title']) ?></h2>
        <div class="small text-muted mb-2"><?= e(format_datetime($a['published_at'])) ?></div>
        <div style="white-space:pre-wrap"><?= e($a['body']) ?></div>
    </article>
<?php endforeach; endif; ?>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
