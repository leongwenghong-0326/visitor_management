<?php
declare(strict_types=1);

/**
 * Announcements.
 */

function list_announcements(array $filters = [], int $page = 1): array
{
    $where = ['1=1'];
    $params = [];

    if (isset($filters['published']) && $filters['published'] !== '') {
        $where[] = 'a.is_published = :pub';
        $params[':pub'] = (int) $filters['published'];
    }
    if (!empty($filters['audience'])) {
        $where[] = '(a.audience = :aud OR a.audience = :all)';
        $params[':aud'] = $filters['audience'];
        $params[':all'] = 'all';
    }
    if (!empty($filters['q'])) {
        $where[] = '(a.title LIKE :q1 OR a.body LIKE :q2)';
        $q = '%' . $filters['q'] . '%';
        $params[':q1'] = $q;
        $params[':q2'] = $q;
    }

    $sqlWhere = implode(' AND ', $where);
    $count = db()->prepare("SELECT COUNT(*) FROM announcements a WHERE $sqlWhere");
    $count->execute($params);
    $pager = paginate((int) $count->fetchColumn(), $page);

    $stmt = db()->prepare(
        "SELECT a.*, u.full_name AS author_name
         FROM announcements a
         LEFT JOIN users u ON u.id = a.created_by
         WHERE $sqlWhere
         ORDER BY COALESCE(a.published_at, a.created_at) DESC
         LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
    );
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'pager' => $pager];
}

function get_announcement(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM announcements WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function save_announcement(array $data, int $userId, ?int $id = null): array
{
    $title = trim((string) ($data['title'] ?? ''));
    $body = trim((string) ($data['body'] ?? ''));
    $audience = (string) ($data['audience'] ?? 'all');
    $publish = !empty($data['is_published']);

    $errors = [];
    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($body === '') {
        $errors[] = 'Body is required.';
    }
    if (!in_array($audience, ['all', 'residents', 'security', 'admin'], true)) {
        $errors[] = 'Invalid audience.';
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }

    if ($id) {
        $existing = get_announcement($id);
        if (!$existing) {
            return ['ok' => false, 'errors' => ['Announcement not found.']];
        }
        $publishedAt = $existing['published_at'];
        if ($publish && !$existing['is_published']) {
            $publishedAt = now();
        }
        if (!$publish) {
            $publishedAt = null;
        }
        db()->prepare(
            'UPDATE announcements SET title = :t, body = :b, audience = :a, is_published = :p,
             published_at = :pa, updated_at = :u WHERE id = :id'
        )->execute([
            ':t' => $title, ':b' => $body, ':a' => $audience,
            ':p' => $publish ? 1 : 0, ':pa' => $publishedAt, ':u' => now(), ':id' => $id,
        ]);
        log_activity('edit_announcement', 'announcements', 'announcement', $id, 'Announcement updated');
        if ($publish) {
            notify_audience_of_announcement($audience, $title, $id);
        }
        return ['ok' => true, 'id' => $id];
    }

    db()->prepare(
        'INSERT INTO announcements (title, body, audience, is_published, published_at, created_by, created_at)
         VALUES (:t, :b, :a, :p, :pa, :by, :c)'
    )->execute([
        ':t' => $title, ':b' => $body, ':a' => $audience,
        ':p' => $publish ? 1 : 0, ':pa' => $publish ? now() : null,
        ':by' => $userId, ':c' => now(),
    ]);
    $newId = (int) db()->lastInsertId();
    log_activity('create_announcement', 'announcements', 'announcement', $newId, 'Announcement created');
    if ($publish) {
        notify_audience_of_announcement($audience, $title, $newId);
    }
    return ['ok' => true, 'id' => $newId];
}

function published_announcements_for_role(?string $roleSlug, int $limit = 10): array
{
    $audience = match ($roleSlug) {
        'admin'    => 'admin',
        'security' => 'security',
        'resident' => 'residents',
        default    => 'all',
    };
    $stmt = db()->prepare(
        "SELECT * FROM announcements
         WHERE is_published = 1 AND (audience = 'all' OR audience = :aud)
         ORDER BY published_at DESC LIMIT :lim"
    );
    $stmt->bindValue(':aud', $audience);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function notify_audience_of_announcement(string $audience, string $title, int $announcementId): void
{
    $sql = "SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status = 'active'";
    if ($audience === 'residents') {
        $sql .= " AND r.slug = 'resident'";
    } elseif ($audience === 'security') {
        $sql .= " AND r.slug = 'security'";
    } elseif ($audience === 'admin') {
        $sql .= " AND r.slug = 'admin'";
    }
    // audience === 'all' => every active user

    foreach (db()->query($sql) as $row) {
        notify_user(
            (int) $row['id'],
            'Announcement: ' . $title,
            'A new announcement has been published.',
            'announcement',
            'announcement',
            $announcementId
        );
    }
}
