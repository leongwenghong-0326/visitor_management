<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';

if (auth_check()) {
    redirect(role_home_path());
}
redirect('login.php');
