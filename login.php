<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';

if (auth_check()) {
    redirect(role_home_path());
}

$error = '';
if (is_post()) {
    require_csrf();
    $result = login_user((string) post('username'), (string) post('password'));
    if ($result['ok']) {
        redirect(role_home_path());
    }
    $error = $result['message'];
}

$demoAccounts = [];
if (APP_ENV === 'local') {
    $demoAccounts = [
        ['username' => 'admin',     'password' => 'Admin@123!',     'role' => 'Admin'],
        ['username' => 'security',  'password' => 'Security@123!',  'role' => 'Security'],
        ['username' => 'resident1', 'password' => 'Resident@123!',  'role' => 'Resident'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
    <style>
        .btn-login {
            background: #1f8a70;
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 0.65rem 1rem;
        }
        .btn-login:hover,
        .btn-login:focus {
            background: #176e59;
            color: #fff;
        }
        .demo-account {
            display: block;
            width: 100%;
            text-align: left;
            border: 1px solid #d9e2ec;
            background: #f7fafc;
            border-radius: 0.5rem;
            padding: 0.65rem 0.75rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }
        .demo-account:hover {
            background: #e8f5f1;
            border-color: #1f8a70;
        }
        .demo-account .role {
            font-size: 0.75rem;
            color: #516174;
        }
        .demo-account .creds {
            font-size: 0.85rem;
            color: #1a2330;
        }
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="text-center mb-4">
            <div class="mb-2 text-teal"><i class="fa-solid fa-building-shield fa-2x"></i></div>
            <h1 class="h4 mb-1"><?= e(APP_NAME) ?></h1>
            <p class="text-muted small mb-0">Sign in to continue</p>
        </div>
        <?php foreach (get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type'] === 'error' ? 'danger' : 'info') ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off" id="loginForm">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input type="text" name="username" id="username" class="form-control" required autofocus value="<?= e((string) post('username', '')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-login w-100">
                <i class="fa-solid fa-right-to-bracket me-1"></i> Login
            </button>
        </form>
        <div class="text-center mt-3">
            <a href="<?= e(url('forgot-password.php')) ?>" class="small">Forgot password?</a>
        </div>
        <?php if ($demoAccounts): ?>
        <div class="mt-4 small border-top pt-3">
            <div class="fw-semibold mb-2 text-muted">Demo accounts (click to fill)</div>
            <?php foreach ($demoAccounts as $demo): ?>
                <button type="button"
                        class="demo-account"
                        data-username="<?= e($demo['username']) ?>"
                        data-password="<?= e($demo['password']) ?>">
                    <div class="role"><?= e($demo['role']) ?></div>
                    <div class="creds">
                        <strong><?= e($demo['username']) ?></strong>
                        &middot; <?= e($demo['password']) ?>
                    </div>
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
document.querySelectorAll('.demo-account').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('username').value = btn.getAttribute('data-username') || '';
        document.getElementById('password').value = btn.getAttribute('data-password') || '';
        document.getElementById('username').focus();
    });
});
</script>
</body>
</html>