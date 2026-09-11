<?php
declare(strict_types=1);

/**
 * Internal notifications.
 */

function notify_user(
    int $userId,
    string $title,
    string $message,
    string $type = 'system',
    ?string $relatedType = null,
    ?int $relatedId = null
): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO notifications (user_id, title, message, type, related_type, related_id, created_at)
             VALUES (:uid, :title, :msg, :type, :rtype, :rid, :created)'
        );
        $stmt->execute([
            ':uid'     => $userId,
            ':title'   => $title,
            ':msg'     => $message,
            ':type'    => $type,
            ':rtype'   => $relatedType,
            ':rid'     => $relatedId,
            ':created' => now(),
        ]);
    } catch (Throwable $e) {
        error_log('Notification failed: ' . $e->getMessage());
    }
}

function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
    $stmt->execute([':uid' => $userId]);
    return (int) $stmt->fetchColumn();
}

function get_notifications(int $userId, int $page = 1, int $perPage = ITEMS_PER_PAGE): array
{
    $count = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid');
    $count->execute([':uid' => $userId]);
    $pager = paginate((int) $count->fetchColumn(), $page, $perPage);

    $stmt = db()->prepare(
        "SELECT * FROM notifications WHERE user_id = :uid
         ORDER BY id DESC LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
    );
    $stmt->execute([':uid' => $userId]);
    return ['rows' => $stmt->fetchAll(), 'pager' => $pager];
}

function mark_notification_read(int $userId, int $id): bool
{
    $stmt = db()->prepare(
        'UPDATE notifications SET is_read = 1, read_at = :now
         WHERE id = :id AND user_id = :uid'
    );
    $stmt->execute([':now' => now(), ':id' => $id, ':uid' => $userId]);
    return $stmt->rowCount() > 0;
}

function mark_all_notifications_read(int $userId): void
{
    db()->prepare(
        'UPDATE notifications SET is_read = 1, read_at = :now WHERE user_id = :uid AND is_read = 0'
    )->execute([':now' => now(), ':uid' => $userId]);
}

function notify_resident_user(int $residentId, string $title, string $message, string $type = 'visitor', ?int $relatedId = null): void
{
    $stmt = db()->prepare('SELECT user_id FROM residents WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $residentId]);
    $uid = (int) $stmt->fetchColumn();
    if ($uid > 0) {
        notify_user($uid, $title, $message, $type, 'visitor', $relatedId);
    }
}

function notify_users_by_role(string $roleSlug, string $title, string $message, string $type = 'system', ?string $relatedType = null, ?int $relatedId = null): void
{
    $stmt = db()->prepare(
        "SELECT u.id
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE r.slug = :slug AND u.status = 'active'"
    );
    $stmt->execute([':slug' => $roleSlug]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        notify_user((int) $uid, $title, $message, $type, $relatedType, $relatedId);
    }
}

function notify_admins(string $title, string $message, string $type = 'system', ?string $relatedType = null, ?int $relatedId = null): void
{
    notify_users_by_role('admin', $title, $message, $type, $relatedType, $relatedId);
}

/**
 * Notify host resident and all admins.
 */
function notify_visitor_event(int $residentId, string $title, string $message, string $type = 'visitor', ?int $relatedId = null): void
{
    notify_resident_user($residentId, $title, $message, $type, $relatedId);

    $stmt = db()->prepare('SELECT full_name, unit_number FROM residents WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $residentId]);
    $r = $stmt->fetch();
    $adminMessage = $message;
    if ($r) {
        $adminMessage .= ' Host: ' . $r['full_name'] . ' (Unit ' . $r['unit_number'] . ').';
    }
    notify_admins($title, $adminMessage, $type, 'visitor', $relatedId);
}
