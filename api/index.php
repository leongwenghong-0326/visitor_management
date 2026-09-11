<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
json_response([
    'ok' => true,
    'app' => APP_NAME,
    'version' => APP_VERSION,
    'time' => now(),
    'timezone' => date_default_timezone_get(),
]);
