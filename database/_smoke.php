<?php
declare(strict_types=1);

/**
 * Lightweight smoke checks (CLI).
 * php database/_smoke.php
 */

require_once dirname(__DIR__) . '/includes/init.php';

$failed = 0;
function check(string $label, bool $ok): void
{
    global $failed;
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . "\n";
    if (!$ok) {
        $failed++;
    }
}

try {
    $pdo = db();
    check('DB connection', true);
    check('roles table', (int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn() >= 3);
    check('permissions seeded', (int)$pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn() >= 20);
    check('admin user', (bool)$pdo->query("SELECT id FROM users WHERE username='admin'")->fetch());
    check('demo resident', (bool)$pdo->query("SELECT id FROM residents WHERE id=1")->fetch());

    $hash = $pdo->query("SELECT password_hash FROM users WHERE username='admin'")->fetchColumn();
    check('admin password Admin@123!', password_verify('Admin@123!', (string)$hash));

    $token = generate_token(16);
    check('secure token length', strlen($token) === 32);

    check('timezone Asia/Kuala_Lumpur', date_default_timezone_get() === 'Asia/Kuala_Lumpur');
} catch (Throwable $e) {
    echo '[FAIL] Exception: ' . $e->getMessage() . "\n";
    $failed++;
}

echo $failed === 0 ? "All smoke checks passed.\n" : "Failed checks: {$failed}\n";
exit($failed === 0 ? 0 : 1);
