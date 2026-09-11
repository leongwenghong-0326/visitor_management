<?php
declare(strict_types=1);

/**
 * Activity logging.
 */

function log_activity(
    string $action,
    ?string $module = null,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $description = null
): void {
    try {
        $user = current_user();
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (user_id, action, module, entity_type, entity_id, description, ip_address, created_at)
             VALUES (:uid, :action, :module, :etype, :eid, :desc, :ip, :created)'
        );
        $stmt->execute([
            ':uid'     => $user['id'] ?? null,
            ':action'  => $action,
            ':module'  => $module,
            ':etype'   => $entityType,
            ':eid'     => $entityId,
            ':desc'    => $description,
            ':ip'      => client_ip(),
            ':created' => now(),
        ]);
    } catch (Throwable $e) {
        // Never break main flow for logging failures
        error_log('Activity log failed: ' . $e->getMessage());
    }
}

function get_activity_logs(array $filters = [], int $page = 1, int $perPage = ITEMS_PER_PAGE): array
{
    $where = ['1=1'];
    $params = [];

    if (!empty($filters['q'])) {
        // Native PDO requires a unique placeholder per bind (cannot reuse :q).
        $where[] = '(a.action LIKE :q1 OR a.description LIKE :q2 OR u.username LIKE :q3 OR u.full_name LIKE :q4)';
        $q = '%' . $filters['q'] . '%';
        $params[':q1'] = $q;
        $params[':q2'] = $q;
        $params[':q3'] = $q;
        $params[':q4'] = $q;
    }
    if (!empty($filters['module'])) {
        $where[] = 'a.module = :module';
        $params[':module'] = $filters['module'];
    }
    if (!empty($filters['from'])) {
        $where[] = 'a.created_at >= :from';
        $params[':from'] = $filters['from'] . ' 00:00:00';
    }
    if (!empty($filters['to'])) {
        $where[] = 'a.created_at <= :to';
        $params[':to'] = $filters['to'] . ' 23:59:59';
    }

    $sqlWhere = implode(' AND ', $where);

    $countStmt = db()->prepare(
        "SELECT COUNT(*) FROM activity_logs a
         LEFT JOIN users u ON u.id = a.user_id
         WHERE $sqlWhere"
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $pager = paginate($total, $page, $perPage);

    $stmt = db()->prepare(
        "SELECT a.*, u.username, u.full_name
         FROM activity_logs a
         LEFT JOIN users u ON u.id = a.user_id
         WHERE $sqlWhere
         ORDER BY a.id DESC
         LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
    );
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'pager' => $pager];
}
