<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
require_permission('announcements.edit');

$id = int_id(get('id'));
$ann = get_announcement($id);
if (!$ann) {
    flash('error', 'Not found.');
    redirect('admin/announcements/index.php');
}
$errors = [];
if (is_post()) {
    require_csrf();
    $r = save_announcement($_POST, (int) current_user()['id'], $id);
    if ($r['ok']) {
        flash('success', 'Announcement updated.');
        redirect('admin/announcements/index.php');
    }
    $errors = $r['errors'];
    $ann = array_merge($ann, $_POST);
}
$pageTitle = 'Edit Announcement';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="h3 mb-3">Edit Announcement</h1>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="panel">
<form method="post" class="row g-3">
<?= csrf_field() ?>
<div class="col-12"><label class="form-label">Title *</label><input name="title" class="form-control" required value="<?= e($ann['title']) ?>"></div>
<div class="col-12"><label class="form-label">Body *</label><textarea name="body" class="form-control" rows="6" required><?= e($ann['body']) ?></textarea></div>
<div class="col-md-4">
    <label class="form-label">Audience</label>
    <select name="audience" class="form-select">
        <?php foreach (['all','residents','security','admin'] as $a): ?>
            <option value="<?= $a ?>" <?= $ann['audience']===$a?'selected':'' ?>><?= ucfirst($a) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-4 d-flex align-items-end">
    <div class="form-check"><input class="form-check-input" type="checkbox" name="is_published" value="1" id="pub" <?= (int)$ann['is_published']?'checked':'' ?>><label class="form-check-label" for="pub">Published</label></div>
</div>
<div class="col-12"><button class="btn btn-teal" type="submit">Update</button> <a class="btn btn-outline-secondary" href="<?= e(url('admin/announcements/index.php')) ?>">Cancel</a></div>
</form>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
