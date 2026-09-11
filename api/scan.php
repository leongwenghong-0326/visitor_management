<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json; charset=utf-8');

function api_require_login(): void
{
    if (!auth_check()) {
        json_response(['ok' => false, 'message' => 'Please log in again.'], 401);
    }
    $last = $_SESSION['last_activity'] ?? time();
    if ((time() - (int) $last) > SESSION_IDLE_TIMEOUT) {
        logout_user(false);
        json_response(['ok' => false, 'message' => 'Session expired. Please log in again.'], 401);
    }
    $_SESSION['last_activity'] = time();
    $user = current_user();
    if (($user['status'] ?? '') !== 'active') {
        logout_user(false);
        json_response(['ok' => false, 'message' => 'Account is not active.'], 401);
    }
}

api_require_login();

if (!is_post()) {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!verify_csrf()) {
    json_response(['ok' => false, 'message' => 'Invalid CSRF token. Refresh the page and try again.'], 403);
}

$action = (string) post('action');
$user = current_user();
$uid = (int) $user['id'];

try {
    if ($action === 'checkin' || $action === 'scan') {
        if (!can('checkin.scan') && !can('visitors.checkin')) {
            json_response(['ok' => false, 'message' => 'Permission denied.'], 403);
        }
        $token = trim((string) post('token'));
        $result = process_security_qr_scan($token, $uid);
        json_response($result, $result['ok'] ? 200 : 422);
    }

    if ($action === 'checkout') {
        if (!can('visitors.checkout')) {
            json_response(['ok' => false, 'message' => 'Permission denied.'], 403);
        }
        $visitorId = int_id(post('visitor_id'));
        $result = process_visitor_checkout($visitorId, $uid);
        $result['type'] = 'visitor';
        json_response($result, $result['ok'] ? 200 : 422);
    }

    json_response(['ok' => false, 'message' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    error_log('api/scan.php: ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'Server error while processing request.'], 500);
}
