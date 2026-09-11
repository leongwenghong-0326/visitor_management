<?php
declare(strict_types=1);

if (!isset($pageTitle)) {
    $pageTitle = APP_NAME;
}
$user = current_user();
$unread = $user ? unread_notification_count((int) $user['id']) : 0;
$role = $user['role_slug'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="app-body">
<?php if ($user): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-navy border-bottom border-teal">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="<?= e(url(role_home_path())) ?>">
            <i class="fa-solid fa-building-shield me-1"></i> <?= e(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if ($role === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('admin/dashboard.php')) ?>">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('admin/residents/index.php')) ?>">Residents</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('admin/visitors/index.php')) ?>">Visitors</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('admin/blacklist/index.php')) ?>">Blacklist</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('admin/staff/index.php')) ?>">Staff</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('admin/announcements/index.php')) ?>">Announcements</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('admin/reports/index.php')) ?>">Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('admin/logs/index.php')) ?>">Logs</a></li>
                <?php elseif ($role === 'security'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('security/dashboard.php')) ?>">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('security/scan.php')) ?>">Scan QR</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('security/announcements.php')) ?>">Announcements</a></li>
                <?php elseif ($role === 'resident'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('resident/dashboard.php')) ?>">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('resident/qr.php')) ?>">My QR</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('resident/visitors/index.php')) ?>">My Visitors</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('resident/announcements.php')) ?>">Announcements</a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav align-items-lg-center">
                <li class="nav-item me-lg-2">
                    <a class="nav-link position-relative" href="<?= e(url('notifications.php')) ?>" title="Notifications">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($unread > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= (int) $unread ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <?= e($user['full_name']) ?>
                        <span class="badge text-bg-light text-dark ms-1"><?= e(ucfirst($role)) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= e(url('change-password.php')) ?>">Change Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= e(url('logout.php')) ?>">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="<?= $user ? 'container-fluid py-4 px-3 px-lg-4' : '' ?>">
<?php foreach (get_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'success' ? 'success' : ($flash['type'] === 'warning' ? 'warning' : 'info'))) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>
