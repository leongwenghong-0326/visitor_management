<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
if (!is_admin()) {
    redirect(role_home_path());
}
redirect('admin/dashboard.php');
