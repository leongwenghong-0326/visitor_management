<?php
declare(strict_types=1);

/**
 * Application bootstrap.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/qr.php';
require_once __DIR__ . '/residents.php';
require_once __DIR__ . '/visitors.php';
require_once __DIR__ . '/announcements.php';
require_once __DIR__ . '/export.php';

start_app_session();

// Security headers (lightweight)
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
