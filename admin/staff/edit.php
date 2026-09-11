<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
require_permission('staff.edit');

$id = int_id(get('id'));
$stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$staff = $stmt->fetch();
if (!$staff) {
    flash('error', 'User not found.');
    redirect('admin/staff/index.php');
}

$errors = [];
$roles = db()->query('SELECT id, name FROM roles ORDER BY id')->fetchAll();

if (is_post()) {
    require_csrf();
    $email = trim((string) post('email'));
    $fullName = trim((string) post('full_name'));
    $phone = normalize_phone(post('phone'));
    $roleId = int_id(post('role_id'));
    $status = (string) post('status');
    $newPass = (string) post('new_password');

    if ($email === '' || $fullName === '') {
        $errors[] = 'Email and full name are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email.';
    }
    if (!in_array($status, ['active','inactive','suspended'], true)) {
        $errors[] = 'Invalid status.';
    }
    if ($newPass !== '' && strlen($newPass) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }

    if (!$errors) {
        try {
            db()->prepare(
                'UPDATE users SET email=:email, full_name=:name, phone=:phone, role_id=:role, status=:status, updated_at=:now WHERE id=:id'
            )->execute([
                ':email' => $email, ':name' => $fullName, ':phone' => $phone ?: null,
                ':role' => $roleId, ':status' => $status, ':now' => now(), ':id' => $id,
            ]);
            if ($newPass !== '') {
                db()->prepare('UPDATE users SET password_hash=:h, must_change_password=1 WHERE id=:id')
                    ->execute([':h' => password_hash($newPass, PASSWORD_DEFAULT), ':id' => $id]);
            }
            log_activity('edit_staff', 'staff', 'user', $id, "Updated user {$staff['username']}");
            flash('success', 'Staff user updated.');
            redirect('admin/staff/index.php');
        } catch (PDOException $e) {
            $errors[] = 'Email may already be in use.';
        }
    }
    $staff = array_merge($staff, $_POST);
}

$pageTitle = 'Edit Staff';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="h3 mb-3">Edit Staff — <?= e($staff['username']) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="panel">
<form method="post" class="row g-3" autocomplete="off">
<?= csrf_field() ?>
<div class="col-md-6"><label class="form-label">Full Name *</label><input name="full_name" class="form-control" required value="<?= e($staff['full_name']) ?>"></div>
<div class="col-md-6"><label class="form-label">Username</label><input class="form-control" value="<?= e($staff['username']) ?>" disabled></div>
<div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required value="<?= e($staff['email']) ?>"></div>
<div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e((string)$staff['phone']) ?>"></div>
<div class="col-md-4">
    <label class="form-label">Role *</label>
    <select name="role_id" class="form-select">
        <?php foreach ($roles as $r): ?><option value="<?= (int)$r['id'] ?>" <?= (int)$staff['role_id']===(int)$r['id']?'selected':'' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
        <?php foreach (['active','inactive','suspended'] as $s): ?><option value="<?= $s ?>" <?= $staff['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select>
</div>
<div class="col-md-4"><label class="form-label">Reset Password (optional)</label><input type="password" name="new_password" class="form-control" minlength="8" placeholder="Leave blank to keep"></div>
<div class="col-12">
    <button class="btn btn-teal" type="submit">Update</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('admin/staff/index.php')) ?>">Cancel</a>
</div>
</form>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>