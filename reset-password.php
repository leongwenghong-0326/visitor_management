<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';

if (auth_check()) {
    redirect(role_home_path());
}

$token = (string) get('token', post('token', ''));
$error = '';

if (is_post()) {
    require_csrf();
    $result = reset_password_with_token(
        (string) post('token'),
        (string) post('password'),
        (string) post('password_confirm')
    );
    if ($result['ok']) {
        flash('success', $result['message']);
        redirect('login.php');
    }
    $error = $result['message'];
    $token = (string) post('token');
}

if ($token === '') {
    $error = 'Missing reset token.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <h1 class="h4 mb-3">Reset Password</h1>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <?php if ($token !== ''): ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="mb-3">
                <label class="form-label" for="password">New Password</label>
                <input type="password" name="password" id="password" class="form-control" minlength="8" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirm">Confirm Password</label>
                <input type="password" name="password_confirm" id="password_confirm" class="form-control" minlength="8" required>
            </div>
            <button class="btn btn-teal w-100" type="submit">Reset Password</button>
        </form>
        <?php endif; ?>
        <div class="text-center mt-3"><a href="<?= e(url('login.php')) ?>" class="small">Back to login</a></div>
    </div>
</div>
</body>
</html>