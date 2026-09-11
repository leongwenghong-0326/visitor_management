<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
require_permission('staff.create');

$errors = [];
$roles = db()->query('SELECT id, name, slug FROM roles ORDER BY id')->fetchAll();

if (is_post()) {
    require_csrf();
    $username = trim((string) post('username'));
    $email = trim((string) post('email'));
    $fullName = trim((string) post('full_name'));
    $phone = normalize_phone(post('phone'));
    $roleId = int_id(post('role_id'));
    $password = (string) post('password');
    $status = (string) post('status', 'active');

    if ($username === '' || $email === '' || $fullName === '') {
        $errors[] = 'Username, email, and full name are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!in_array($status, ['active','inactive','suspended'], true)) {
        $errors[] = 'Invalid status.';
    }
    $roleOk = false;
    foreach ($roles as $r) {
        if ((int)$r['id'] === $roleId) { $roleOk = true; break; }
    }
    if (!$roleOk) {
        $errors[] = 'Invalid role.';
    }

    if (!$errors) {
        try {
            db()->prepare(
                'INSERT INTO users (role_id, username, email, password_hash, full_name, phone, status, must_change_password, created_at)
                 VALUES (:role, :user, :email, :hash, :name, :phone, :status, 1, :created)'
            )->execute([
                ':role' => $roleId,
                ':user' => $username,
                ':email' => $email,
                ':hash' => password_hash($password, PASSWORD_DEFAULT),
                ':name' => $fullName,
                ':phone' => $phone ?: null,
                ':status' => $status,
                ':created' => now(),
            ]);
            $id = (int) db()->lastInsertId();
            log_activity('create_staff', 'staff', 'user', $id, "Created user {$username}");
            flash('success', 'Staff user created. They must change password on first login.');
            redirect('admin/staff/index.php');
        } catch (PDOException $e) {
            $errors[] = 'Username or email already exists.';
        }
    }
}

$pageTitle = 'Add Staff';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="h3 mb-3">Add Staff</h1>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="panel">
<form method="post" class="row g-3" autocomplete="off">
<?= csrf_field() ?>
<div class="col-md-6"><label class="form-label">Full Name *</label><input name="full_name" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">Username *</label><input name="username" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
<div class="col-md-4">
    <label class="form-label">Role *</label>
    <select name="role_id" class="form-select" required>
        <?php foreach ($roles as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
        <?php foreach (['active','inactive','suspended'] as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select>
</div>
<div class="col-md-4"><label class="form-label">Temporary Password *</label><input type="password" name="password" class="form-control" minlength="8" required></div>
<div class="col-12">
    <button class="btn btn-teal" type="submit">Create</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('admin/staff/index.php')) ?>">Cancel</a>
</div>
</form>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
