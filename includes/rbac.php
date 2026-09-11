<?php
declare(strict_types=1);

/**
 * Role-based access control.
 */

function rbac_load_permissions(int $roleId): array
{
    static $cache = [];
    if (isset($cache[$roleId])) {
        return $cache[$roleId];
    }

    $stmt = db()->prepare(
        'SELECT p.slug
         FROM permissions p
         INNER JOIN role_permissions rp ON rp.permission_id = p.id
         WHERE rp.role_id = :role_id'
    );
    $stmt->execute([':role_id' => $roleId]);
    $cache[$roleId] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    return $cache[$roleId];
}

function can(string $permission): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    $perms = $_SESSION['permissions'] ?? [];
    return in_array($permission, $perms, true);
}

function can_any(array $permissions): bool
{
    foreach ($permissions as $p) {
        if (can($p)) {
            return true;
        }
    }
    return false;
}

function require_permission(string $permission): void
{
    if (!can($permission)) {
        http_response_code(403);
        flash('error', 'You do not have permission to perform this action.');
        redirect(role_home_path());
    }
}

function require_any_permission(array $permissions): void
{
    if (!can_any($permissions)) {
        http_response_code(403);
        flash('error', 'You do not have permission to perform this action.');
        redirect(role_home_path());
    }
}

function user_role_slug(): ?string
{
    $user = current_user();
    return $user['role_slug'] ?? null;
}

function is_admin(): bool
{
    return user_role_slug() === 'admin';
}

function is_security(): bool
{
    return user_role_slug() === 'security';
}

function is_resident(): bool
{
    return user_role_slug() === 'resident';
}

function role_home_path(): string
{
    return match (user_role_slug()) {
        'admin'    => 'admin/dashboard.php',
        'security' => 'security/dashboard.php',
        'resident' => 'resident/dashboard.php',
        default    => 'login.php',
    };
}
