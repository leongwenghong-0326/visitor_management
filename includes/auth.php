<?php
declare(strict_types=1);

/**
 * Authentication and session management.
 */

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function auth_check(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!auth_check()) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }

    // Idle timeout
    $last = $_SESSION['last_activity'] ?? time();
    if ((time() - (int) $last) > SESSION_IDLE_TIMEOUT) {
        logout_user(false);
        flash('error', 'Your session has expired. Please log in again.');
        redirect('login.php');
    }
    $_SESSION['last_activity'] = time();

    $user = current_user();
    if (($user['status'] ?? '') !== 'active') {
        logout_user(false);
        flash('error', 'Your account is not active.');
        redirect('login.php');
    }

    // Forced password change
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!empty($user['must_change_password']) && $script !== 'change-password.php' && $script !== 'logout.php') {
        flash('warning', 'You must change your password before continuing.');
        redirect('change-password.php');
    }
}

function login_user(string $username, string $password): array
{
    $username = trim($username);
    $ip = client_ip();

    if ($username === '' || $password === '') {
        return ['ok' => false, 'message' => 'Username and password are required.'];
    }

    if (is_login_locked($username, $ip)) {
        record_login_attempt($username, $ip, false);
        return ['ok' => false, 'message' => 'Too many failed attempts. Please try again later.'];
    }

    $stmt = db()->prepare(
        'SELECT u.*, r.slug AS role_slug, r.name AS role_name
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE u.username = :username
         LIMIT 1'
    );
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_login_attempt($username, $ip, false);
        return ['ok' => false, 'message' => 'Invalid username or password.'];
    }

    if ($user['status'] !== 'active') {
        record_login_attempt($username, $ip, false);
        return ['ok' => false, 'message' => 'Your account is not active.'];
    }

    record_login_attempt($username, $ip, true);

    session_regenerate_id(true);

    unset($user['password_hash']);
    $_SESSION['user'] = $user;
    $_SESSION['permissions'] = rbac_load_permissions((int) $user['role_id']);
    $_SESSION['last_activity'] = time();

    // Link resident profile if applicable
    if ($user['role_slug'] === 'resident') {
        $r = db()->prepare('SELECT id FROM residents WHERE user_id = :uid LIMIT 1');
        $r->execute([':uid' => $user['id']]);
        $_SESSION['resident_id'] = (int) ($r->fetchColumn() ?: 0);
    }

    db()->prepare('UPDATE users SET last_login_at = :now WHERE id = :id')
        ->execute([':now' => now(), ':id' => $user['id']]);

    log_activity('login', 'auth', 'user', (int) $user['id'], 'User logged in');

    return ['ok' => true, 'user' => $user];
}

function logout_user(bool $log = true): void
{
    if ($log && auth_check()) {
        $u = current_user();
        log_activity('logout', 'auth', 'user', (int) ($u['id'] ?? 0), 'User logged out');
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
    start_app_session();
}

function is_login_locked(string $username, string $ip): bool
{
    $since = date('Y-m-d H:i:s', time() - (LOGIN_LOCKOUT_MINUTES * 60));
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE success = 0 AND attempted_at >= :since
           AND (username = :username OR ip_address = :ip)'
    );
    $stmt->execute([
        ':since'    => $since,
        ':username' => $username,
        ':ip'       => $ip,
    ]);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

function record_login_attempt(string $username, string $ip, bool $success): void
{
    $stmt = db()->prepare(
        'INSERT INTO login_attempts (username, ip_address, attempted_at, success)
         VALUES (:username, :ip, :at, :success)'
    );
    $stmt->execute([
        ':username' => $username,
        ':ip'       => $ip,
        ':at'       => now(),
        ':success'  => $success ? 1 : 0,
    ]);
}

function change_password(int $userId, string $current, string $new, string $confirm): array
{
    if ($new !== $confirm) {
        return ['ok' => false, 'message' => 'New passwords do not match.'];
    }
    if (strlen($new) < 8) {
        return ['ok' => false, 'message' => 'Password must be at least 8 characters.'];
    }

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($current, $hash)) {
        return ['ok' => false, 'message' => 'Current password is incorrect.'];
    }

    $newHash = password_hash($new, PASSWORD_DEFAULT);
    db()->prepare(
        'UPDATE users SET password_hash = :h, must_change_password = 0, updated_at = :now WHERE id = :id'
    )->execute([':h' => $newHash, ':now' => now(), ':id' => $userId]);

    if (isset($_SESSION['user']) && (int) $_SESSION['user']['id'] === $userId) {
        $_SESSION['user']['must_change_password'] = 0;
    }

    log_activity('password_change', 'auth', 'user', $userId, 'Password changed');
    return ['ok' => true, 'message' => 'Password updated successfully.'];
}

function create_password_reset(string $email): array
{
    $stmt = db()->prepare('SELECT id, email, full_name FROM users WHERE email = :email AND status = :status LIMIT 1');
    $stmt->execute([':email' => $email, ':status' => 'active']);
    $user = $stmt->fetch();

    // Always return success message to avoid account enumeration
    $generic = ['ok' => true, 'message' => 'If that email exists, a reset link has been generated.', 'token' => null];

    if (!$user) {
        return $generic;
    }

    $token = generate_token(32);
    $hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + (PASSWORD_RESET_EXPIRY_MINUTES * 60));

    db()->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:uid, :th, :exp)'
    )->execute([':uid' => $user['id'], ':th' => $hash, ':exp' => $expires]);

    log_activity('password_reset_request', 'auth', 'user', (int) $user['id'], 'Password reset requested');

    $generic['token'] = $token; // Shown on local/dev forgot page; production should email
    $generic['reset_url'] = url('reset-password.php?token=' . urlencode($token));
    return $generic;
}

function reset_password_with_token(string $token, string $new, string $confirm): array
{
    if ($new !== $confirm) {
        return ['ok' => false, 'message' => 'Passwords do not match.'];
    }
    if (strlen($new) < 8) {
        return ['ok' => false, 'message' => 'Password must be at least 8 characters.'];
    }

    $hash = hash('sha256', $token);
    $stmt = db()->prepare(
        'SELECT * FROM password_resets
         WHERE token_hash = :th AND used_at IS NULL AND expires_at >= :now
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':th' => $hash, ':now' => now()]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'message' => 'Invalid or expired reset token.'];
    }

    $newHash = password_hash($new, PASSWORD_DEFAULT);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password_hash = :h, must_change_password = 0, updated_at = :now WHERE id = :id')
            ->execute([':h' => $newHash, ':now' => now(), ':id' => $row['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used_at = :now WHERE id = :id')
            ->execute([':now' => now(), ':id' => $row['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'message' => 'Unable to reset password. Please try again.'];
    }

    log_activity('password_reset', 'auth', 'user', (int) $row['user_id'], 'Password reset completed');
    return ['ok' => true, 'message' => 'Password has been reset. You may now log in.'];
}

function current_resident_id(): int
{
    return (int) ($_SESSION['resident_id'] ?? 0);
}
