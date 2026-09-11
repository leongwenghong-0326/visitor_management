<?php
declare(strict_types=1);

/**
 * Application configuration
 * Adjust BASE_URL / PUBLIC_BASE_URL for your environment.
 */

define('APP_NAME', 'Visitor Management System');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'local'); // local | production

date_default_timezone_set('Asia/Kuala_Lumpur');

$docRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));
$webRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$relPath = '';
if ($webRoot !== '' && str_starts_with($docRoot, $webRoot)) {
    $relPath = substr($docRoot, strlen($webRoot));
}
$relPath = rtrim(str_replace('\\', '/', $relPath), '/');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $scheme . '://' . $host . $relPath);
define('BASE_PATH', $docRoot);

/**
 * Public URL for shareable visitor invitation links.
 * Leave empty to auto-detect LAN IP when running on localhost.
 * Examples:
 *   http://10.49.211.220/visitor_management
 *   https://yourdomain.com
 */
define('PUBLIC_BASE_URL', '');

define('SESSION_NAME', 'vms_session');
define('SESSION_IDLE_TIMEOUT', 1800);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('PASSWORD_RESET_EXPIRY_MINUTES', 60);
define('CSRF_TOKEN_NAME', '_csrf');

define('QR_TOKEN_BYTES', 32);
define('INVITE_TOKEN_BYTES', 32);
define('DEFAULT_INVITE_DAYS', 7);

define('ITEMS_PER_PAGE', 20);

define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024);
define('UPLOAD_DIR', BASE_PATH . '/uploads');

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}