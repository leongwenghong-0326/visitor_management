<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';

if (auth_check()) {
    redirect(role_home_path());
}

$message = '';
$resetUrl = null;

if (is_post()) {
    require_csrf();
    $email = trim((string) post('email'));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } else {
        $result = create_password_reset($email);
        $message = $result['message'];
        if (APP_ENV === 'local' && !empty($result['reset_url'])) {
            $resetUrl = $result['reset_url'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <h1 class="h4 mb-3">Forgot Password</h1>
        <?php if ($message): ?>
            <div class="alert alert-info"><?= e($message) ?></div>
            <?php if ($resetUrl): ?>
                <div class="alert alert-warning small">
                    Local mode reset link:<br>
                    <a href="<?= e($resetUrl) ?>"><?= e($resetUrl) ?></a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>
            <button class="btn btn-teal w-100" type="submit">Send Reset Link</button>
        </form>
        <div class="text-center mt-3"><a href="<?= e(url('login.php')) ?>" class="small">Back to login</a></div>
    </div>
</div>
</body>
</html>