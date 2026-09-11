<?php
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!$isCli && $host !== '' && $host !== 'localhost' && $host !== '127.0.0.1') {
    http_response_code(403);
    echo 'Installer disabled on this host.';
    exit;
}

$configPath = dirname(__DIR__) . '/config/database.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config/database.php\n");
    exit(1);
}
$cfg = require $configPath;
$sqlFile = __DIR__ . '/database.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "Missing database.sql\n");
    exit(1);
}

function out(string $msg): void
{
    echo $msg . (PHP_SAPI === 'cli' ? "\n" : "<br>\n");
}

try {
    $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['charset']);
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);

    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);
    out('Schema imported.');

    $pdo->exec('USE `' . str_replace('`', '``', $cfg['dbname']) . '`');

    $accounts = [
        'admin' => 'Admin@123!',
        'security' => 'Security@123!',
        'resident1' => 'Resident@123!',
    ];
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :h WHERE username = :u');
    foreach ($accounts as $user => $pass) {
        $stmt->execute([':h' => password_hash($pass, PASSWORD_DEFAULT), ':u' => $user]);
    }
    out('Demo passwords set (unique per role).');

    $exists = (int) $pdo->query('SELECT COUNT(*) FROM resident_qr_codes WHERE resident_id = 1 AND is_active = 1')->fetchColumn();
    if ($exists === 0) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare('INSERT INTO resident_qr_codes (resident_id, token, is_active, created_at) VALUES (1, ?, 1, NOW())')
            ->execute([$token]);
        out('Demo resident QR created.');
    }

    out('Install complete.');
    out('admin / Admin@123!');
    out('security / Security@123!');
    out('resident1 / Resident@123!');
} catch (Throwable $e) {
    out('ERROR: ' . $e->getMessage());
    exit(1);
}