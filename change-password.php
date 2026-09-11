<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
require_login();

$user = current_user();
$error = '';

if (is_post()) {
    require_csrf();
    $result = change_password(
        (int) $user['id'],
        (string) post('current_password'),
        (string) post('new_password'),
        (string) post('confirm_password')
    );
    if ($result['ok']) {
        flash('success', $result['message']);
        redirect(role_home_path());
    }
    $error = $result['message'];
}

$pageTitle = 'Change Password';
require __DIR__ . '/includes/layout/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="panel">
            <h1 class="h4 mb-3">Change Password</h1>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                </div>
                <button class="btn btn-teal" type="submit">Update Password</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
