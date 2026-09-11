<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
require_permission('residents.edit');

$id = int_id(get('id'));
$resident = get_resident($id);
if (!$resident) {
    flash('error', 'Resident not found.');
    redirect('admin/residents/index.php');
}

$errors = [];
$users = get_unlinked_resident_users();
if ($resident['user_id']) {
    $stmt = db()->prepare("SELECT id, username, full_name FROM users WHERE id = :id");
    $stmt->execute([':id' => $resident['user_id']]);
    $linked = $stmt->fetch();
    if ($linked) {
        array_unshift($users, $linked);
    }
}

if (is_post()) {
    require_csrf();
    $result = update_resident($id, $_POST);
    if ($result['ok']) {
        flash('success', 'Resident updated.');
        redirect('admin/residents/view.php?id=' . $id);
    }
    $errors = $result['errors'];
    $resident = array_merge($resident, $_POST);
}

$pageTitle = 'Edit Resident';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="h3 mb-3">Edit Resident — <?= e($resident['resident_code']) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="panel">
<form method="post" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-md-6">
        <label class="form-label">Full Name *</label>
        <input name="full_name" class="form-control" required value="<?= e($resident['full_name']) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Gender *</label>
        <select name="gender" class="form-select" required>
            <?php foreach (['male','female','other'] as $g): ?>
                <option value="<?= $g ?>" <?= $resident['gender']===$g?'selected':'' ?>><?= ucfirst($g) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Status *</label>
        <select name="status" class="form-select">
            <?php foreach (['pending','active','suspended','moved_out','inactive'] as $s): ?>
                <option value="<?= $s ?>" <?= $resident['status']===$s?'selected':'' ?>><?= e(ucwords(str_replace('_',' ',$s))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Unit / House No *</label>
        <input name="unit_number" class="form-control" required value="<?= e($resident['unit_number']) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Phone</label>
        <input name="phone" class="form-control" value="<?= e((string)$resident['phone']) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= e((string)$resident['email']) ?>">
    </div>
    <div class="col-12">
        <label class="form-label">Address</label>
        <input name="address" class="form-control" value="<?= e((string)$resident['address']) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Emergency Contact</label>
        <input name="emergency_contact" class="form-control" value="<?= e((string)$resident['emergency_contact']) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Emergency Phone</label>
        <input name="emergency_phone" class="form-control" value="<?= e((string)$resident['emergency_phone']) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Link Resident Account</label>
        <select name="user_id" class="form-select">
            <option value="">— None —</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>" <?= (int)$resident['user_id']===(int)$u['id']?'selected':'' ?>>
                    <?= e($u['full_name'].' ('.$u['username'].')') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">
            Only <strong>unlinked</strong> users with role <strong>Resident</strong> appear here.
            <?php if (count($users) <= ((int)$resident['user_id'] > 0 ? 1 : 0)): ?>
                Need another account?
                <a href="<?= e(url('admin/staff/create.php')) ?>" target="_blank">Create a Resident login</a>, then refresh.
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?= e((string)$resident['notes']) ?></textarea>
    </div>
    <div class="col-12">
        <button class="btn btn-teal" type="submit">Update</button>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/residents/view.php?id='.$id)) ?>">Cancel</a>
    </div>
</form>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
