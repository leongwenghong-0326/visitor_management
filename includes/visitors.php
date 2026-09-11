<?php
declare(strict_types=1);

/**
 * Visitor management, invitations, check-in/out, blacklist.
 */

function validate_visitor_input(array $data): array
{
    $errors = [];
    $name = trim((string) ($data['visitor_name'] ?? ''));
    $plate = normalize_plate($data['car_plate'] ?? '');
    $phone = normalize_phone($data['phone'] ?? '');
    $purpose = trim((string) ($data['purpose'] ?? ''));
    $visitDate = (string) ($data['visit_date'] ?? '');
    $validUntil = (string) ($data['valid_until'] ?? '');

    if ($name === '' || strlen($name) > 150) {
        $errors[] = 'Visitor name is required.';
    }
    if ($visitDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDate)) {
        $errors[] = 'Valid visit date is required.';
    }
    if ($validUntil === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validUntil)) {
        $errors[] = 'Valid until date is required.';
    }
    if ($visitDate && $validUntil && $validUntil < $visitDate) {
        $errors[] = 'Valid until date cannot be before visit date.';
    }

    return [
        'errors' => $errors,
        'data'   => [
            'visitor_name' => $name,
            'car_plate'    => $plate !== '' ? $plate : null,
            'phone'        => $phone !== '' ? $phone : null,
            'purpose'      => $purpose !== '' ? $purpose : null,
            'visit_date'   => $visitDate,
            'valid_until'  => $validUntil,
        ],
    ];
}

function create_visitor(int $residentId, array $data, ?int $inviteLinkId = null, string $status = 'approved'): array
{
    $v = validate_visitor_input($data);
    if ($v['errors']) {
        return ['ok' => false, 'errors' => $v['errors']];
    }
    $d = $v['data'];

    $token = generate_visitor_qr_token();
    $approvedBy = null;
    $approvedAt = null;
    if ($status === 'approved') {
        $user = current_user();
        $approvedBy = $user['id'] ?? null;
        $approvedAt = now();
    }

    $stmt = db()->prepare(
        'INSERT INTO visitors
         (resident_id, invite_link_id, visitor_name, car_plate, phone, purpose, visit_date, valid_until,
          qr_token, status, approved_by, approved_at, created_at)
         VALUES
         (:rid, :iid, :name, :plate, :phone, :purpose, :vdate, :until,
          :token, :status, :aby, :aat, :created)'
    );
    $stmt->execute([
        ':rid'     => $residentId,
        ':iid'     => $inviteLinkId,
        ':name'    => $d['visitor_name'],
        ':plate'   => $d['car_plate'],
        ':phone'   => $d['phone'],
        ':purpose' => $d['purpose'],
        ':vdate'   => $d['visit_date'],
        ':until'   => $d['valid_until'],
        ':token'   => $token,
        ':status'  => $status,
        ':aby'     => $approvedBy,
        ':aat'     => $approvedAt,
        ':created' => now(),
    ]);
    $id = (int) db()->lastInsertId();

    if ($inviteLinkId) {
        db()->prepare('UPDATE visitor_invite_links SET use_count = use_count + 1 WHERE id = :id')
            ->execute([':id' => $inviteLinkId]);
    }

    log_activity('create_visitor', 'visitors', 'visitor', $id, "Visitor {$d['visitor_name']} created");
    notify_visitor_event($residentId, 'Visitor registered', "Visitor {$d['visitor_name']} has been registered.", 'visitor', $id);

    return ['ok' => true, 'id' => $id, 'qr_token' => $token];
}

function get_visitor(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT v.*, r.full_name AS resident_name, r.unit_number, r.resident_code
         FROM visitors v
         INNER JOIN residents r ON r.id = v.resident_id
         WHERE v.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_visitor_by_token(string $token): ?array
{
    $stmt = db()->prepare(
        'SELECT v.*, r.full_name AS resident_name, r.unit_number, r.user_id AS resident_user_id
         FROM visitors v
         INNER JOIN residents r ON r.id = v.resident_id
         WHERE v.qr_token = :token LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_visitors(array $filters = [], int $page = 1, int $perPage = ITEMS_PER_PAGE): array
{
    $where = ['1=1'];
    $params = [];

    if (!empty($filters['q'])) {
        $where[] = '(v.visitor_name LIKE :q1 OR v.car_plate LIKE :q2 OR v.phone LIKE :q3 OR r.full_name LIKE :q4 OR r.unit_number LIKE :q5)';
        $q = '%' . $filters['q'] . '%';
        $params[':q1'] = $q;
        $params[':q2'] = $q;
        $params[':q3'] = $q;
        $params[':q4'] = $q;
        $params[':q5'] = $q;
    }
    if (!empty($filters['status'])) {
        $where[] = 'v.status = :status';
        $params[':status'] = $filters['status'];
    }
    if (!empty($filters['resident_id'])) {
        $where[] = 'v.resident_id = :rid';
        $params[':rid'] = (int) $filters['resident_id'];
    }
    if (!empty($filters['visit_date'])) {
        $where[] = 'v.visit_date = :vd';
        $params[':vd'] = $filters['visit_date'];
    }
    if (!empty($filters['from'])) {
        $where[] = 'v.visit_date >= :from';
        $params[':from'] = $filters['from'];
    }
    if (!empty($filters['to'])) {
        $where[] = 'v.visit_date <= :to';
        $params[':to'] = $filters['to'];
    }
    if (!empty($filters['inside'])) {
        $where[] = "v.status = 'checked_in'";
    }

    $sqlWhere = implode(' AND ', $where);
    $count = db()->prepare(
        "SELECT COUNT(*) FROM visitors v INNER JOIN residents r ON r.id = v.resident_id WHERE $sqlWhere"
    );
    $count->execute($params);
    $pager = paginate((int) $count->fetchColumn(), $page, $perPage);

    $stmt = db()->prepare(
        "SELECT v.*, r.full_name AS resident_name, r.unit_number
         FROM visitors v
         INNER JOIN residents r ON r.id = v.resident_id
         WHERE $sqlWhere
         ORDER BY v.id DESC
         LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
    );
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'pager' => $pager];
}

function update_visitor_status(int $id, string $status, ?string $reason = null): array
{
    $allowed = ['pending', 'approved', 'checked_in', 'checked_out', 'expired', 'rejected'];
    if (!in_array($status, $allowed, true)) {
        return ['ok' => false, 'message' => 'Invalid status.'];
    }
    $user = current_user();
    $fields = 'status = :status, updated_at = :now';
    $params = [':status' => $status, ':now' => now(), ':id' => $id];

    if ($status === 'approved') {
        $fields .= ', approved_by = :aby, approved_at = :aat, rejection_reason = NULL';
        $params[':aby'] = $user['id'] ?? null;
        $params[':aat'] = now();
    }
    if ($status === 'rejected') {
        $fields .= ', rejection_reason = :reason';
        $params[':reason'] = $reason;
    }

    db()->prepare("UPDATE visitors SET $fields WHERE id = :id")->execute($params);
    log_activity('update_visitor', 'visitors', 'visitor', $id, "Visitor status set to {$status}");
    return ['ok' => true];
}

function expire_outdated_visitors(): void
{
    db()->prepare(
        "UPDATE visitors SET status = 'expired', updated_at = :now
         WHERE status IN ('pending','approved') AND valid_until < :today"
    )->execute([':now' => now(), ':today' => today()]);
}

/* ---------- Invite links ---------- */

function create_invite_link(int $residentId, ?int $days = null, ?int $maxUses = null, ?int $createdBy = null): array
{
    $days = $days ?? DEFAULT_INVITE_DAYS;
    $token = generate_token(INVITE_TOKEN_BYTES);
    $expires = date('Y-m-d H:i:s', time() + ($days * 86400));

    $stmt = db()->prepare(
        'INSERT INTO visitor_invite_links
         (resident_id, token, expires_at, is_active, max_uses, use_count, created_by, created_at)
         VALUES (:rid, :token, :exp, 1, :max, 0, :by, :created)'
    );
    $stmt->execute([
        ':rid'     => $residentId,
        ':token'   => $token,
        ':exp'     => $expires,
        ':max'     => $maxUses,
        ':by'      => $createdBy,
        ':created' => now(),
    ]);
    $id = (int) db()->lastInsertId();
    log_activity('create_invite', 'visitors', 'invite_link', $id, 'Visitor invitation link created');

    return [
        'ok'    => true,
        'id'    => $id,
        'token' => $token,
        'url'   => public_url('visitor/fill.php?token=' . urlencode($token)),
    ];
}

function get_invite_link_by_token(string $token): ?array
{
    $stmt = db()->prepare(
        'SELECT i.*, r.full_name AS resident_name, r.unit_number, r.status AS resident_status
         FROM visitor_invite_links i
         INNER JOIN residents r ON r.id = i.resident_id
         WHERE i.token = :token LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function invite_link_is_valid(array $link): array
{
    if (!(int) $link['is_active'] || $link['revoked_at']) {
        return ['ok' => false, 'message' => 'This invitation link has been revoked.'];
    }
    if ($link['expires_at'] < now()) {
        return ['ok' => false, 'message' => 'This invitation link has expired.'];
    }
    if ($link['max_uses'] !== null && (int) $link['use_count'] >= (int) $link['max_uses']) {
        return ['ok' => false, 'message' => 'This invitation link has reached its usage limit.'];
    }
    if (($link['resident_status'] ?? '') !== 'active') {
        return ['ok' => false, 'message' => 'Host resident is not active.'];
    }
    return ['ok' => true];
}

function revoke_invite_link(int $id, int $residentId = 0): bool
{
    $sql = 'UPDATE visitor_invite_links SET is_active = 0, revoked_at = :now WHERE id = :id';
    $params = [':now' => now(), ':id' => $id];
    if ($residentId > 0) {
        $sql .= ' AND resident_id = :rid';
        $params[':rid'] = $residentId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() > 0) {
        log_activity('revoke_invite', 'visitors', 'invite_link', $id, 'Invitation link revoked');
        return true;
    }
    return false;
}

function list_invite_links(int $residentId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM visitor_invite_links WHERE resident_id = :rid ORDER BY id DESC'
    );
    $stmt->execute([':rid' => $residentId]);
    return $stmt->fetchAll();
}

/* ---------- Blacklist ---------- */

function is_blacklisted(?string $plate, ?string $phone): ?array
{
    $plate = normalize_plate($plate);
    $phone = normalize_phone($phone);
    if ($plate === '' && $phone === '') {
        return null;
    }

    $conditions = [];
    $params = [];
    if ($plate !== '') {
        $conditions[] = 'car_plate = :plate';
        $params[':plate'] = $plate;
    }
    if ($phone !== '') {
        $conditions[] = 'phone = :phone';
        $params[':phone'] = $phone;
    }

    $sql = 'SELECT * FROM visitor_blacklist WHERE is_active = 1 AND (' . implode(' OR ', $conditions) . ') LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_blacklist(array $filters = [], int $page = 1): array
{
    $where = ['1=1'];
    $params = [];
    if (!empty($filters['q'])) {
        $where[] = '(full_name LIKE :q1 OR car_plate LIKE :q2 OR phone LIKE :q3 OR reason LIKE :q4)';
        $q = '%' . $filters['q'] . '%';
        $params[':q1'] = $q;
        $params[':q2'] = $q;
        $params[':q3'] = $q;
        $params[':q4'] = $q;
    }
    if (isset($filters['active']) && $filters['active'] !== '') {
        $where[] = 'is_active = :a';
        $params[':a'] = (int) $filters['active'];
    }
    $sqlWhere = implode(' AND ', $where);
    $count = db()->prepare("SELECT COUNT(*) FROM visitor_blacklist WHERE $sqlWhere");
    $count->execute($params);
    $pager = paginate((int) $count->fetchColumn(), $page);

    $stmt = db()->prepare(
        "SELECT b.*, u.full_name AS created_by_name
         FROM visitor_blacklist b
         LEFT JOIN users u ON u.id = b.created_by
         WHERE $sqlWhere
         ORDER BY b.id DESC
         LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
    );
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'pager' => $pager];
}

function add_blacklist(array $data, int $createdBy): array
{
    $name = trim((string) ($data['full_name'] ?? '')) ?: null;
    $plate = normalize_plate($data['car_plate'] ?? '') ?: null;
    $phone = normalize_phone($data['phone'] ?? '') ?: null;
    $reason = trim((string) ($data['reason'] ?? ''));

    if ($reason === '') {
        return ['ok' => false, 'errors' => ['Reason is required.']];
    }
    if (!$plate && !$phone && !$name) {
        return ['ok' => false, 'errors' => ['Provide at least a name, car plate, or phone.']];
    }

    db()->prepare(
        'INSERT INTO visitor_blacklist (full_name, car_plate, phone, reason, is_active, created_by, created_at)
         VALUES (:n, :p, :ph, :r, 1, :by, :c)'
    )->execute([
        ':n' => $name, ':p' => $plate, ':ph' => $phone, ':r' => $reason,
        ':by' => $createdBy, ':c' => now(),
    ]);
    $id = (int) db()->lastInsertId();
    log_activity('blacklist_add', 'blacklist', 'blacklist', $id, 'Blacklist entry added');
    return ['ok' => true, 'id' => $id];
}

function set_blacklist_active(int $id, bool $active): void
{
    db()->prepare('UPDATE visitor_blacklist SET is_active = :a, updated_at = :now WHERE id = :id')
        ->execute([':a' => $active ? 1 : 0, ':now' => now(), ':id' => $id]);
    log_activity('blacklist_update', 'blacklist', 'blacklist', $id, $active ? 'Activated' : 'Deactivated');
}

/* ---------- Check-in / Check-out ---------- */

function record_scan(?int $visitorId, string $token, string $result, ?string $reason, ?int $scannedBy): void
{
    db()->prepare(
        'INSERT INTO visitor_scans (visitor_id, qr_token, scanned_by, result, reason, scanned_at)
         VALUES (:vid, :token, :by, :result, :reason, :at)'
    )->execute([
        ':vid'    => $visitorId,
        ':token'  => $token,
        ':by'     => $scannedBy,
        ':result' => $result,
        ':reason' => $reason,
        ':at'     => now(),
    ]);
}

/**
 * Server-side visitor check-in validation (order per master prompt).
 */
function process_visitor_checkin(string $token, int $staffId): array
{
    expire_outdated_visitors();

    $token = trim($token);
    if ($token === '' || !preg_match('/^[a-f0-9]{32,128}$/i', $token)) {
        record_scan(null, $token, 'rejected', 'Invalid token format', $staffId);
        return ['ok' => false, 'message' => 'Invalid QR token.'];
    }

    $visitor = get_visitor_by_token($token);
    if (!$visitor) {
        record_scan(null, $token, 'rejected', 'Token not found', $staffId);
        return ['ok' => false, 'message' => 'Visitor not found for this QR code.'];
    }

    $vid = (int) $visitor['id'];

    // Invitation validity if linked
    if (!empty($visitor['invite_link_id'])) {
        $inv = db()->prepare('SELECT * FROM visitor_invite_links WHERE id = :id LIMIT 1');
        $inv->execute([':id' => $visitor['invite_link_id']]);
        $link = $inv->fetch();
        if ($link) {
            $valid = invite_link_is_valid($link);
            // Already used is OK for existing visitors; only block if revoked
            if (!(int) $link['is_active'] || $link['revoked_at']) {
                record_scan($vid, $token, 'rejected', 'Invitation revoked', $staffId);
                return ['ok' => false, 'message' => 'Invitation has been revoked.', 'visitor' => safe_visitor_payload($visitor)];
            }
        }
    }

    $today = today();
    if ($visitor['visit_date'] > $today) {
        record_scan($vid, $token, 'rejected', 'Visit date not yet valid', $staffId);
        return ['ok' => false, 'message' => 'Visit date has not started yet.', 'visitor' => safe_visitor_payload($visitor)];
    }
    if ($visitor['valid_until'] < $today) {
        update_visitor_status($vid, 'expired');
        record_scan($vid, $token, 'rejected', 'Visit expired', $staffId);
        return ['ok' => false, 'message' => 'This visit has expired.', 'visitor' => safe_visitor_payload($visitor)];
    }

    $bl = is_blacklisted($visitor['car_plate'], $visitor['phone']);
    if ($bl) {
        record_scan($vid, $token, 'rejected', 'Blacklisted', $staffId);
        log_activity('checkin_rejected', 'visitors', 'visitor', $vid, 'Check-in rejected: blacklisted');
        return ['ok' => false, 'message' => 'Entry denied. Visitor is not authorized.', 'visitor' => safe_visitor_payload($visitor)];
    }

    if ($visitor['status'] === 'checked_in') {
        record_scan($vid, $token, 'rejected', 'Already checked in', $staffId);
        return ['ok' => false, 'message' => 'Visitor is already checked in.', 'visitor' => safe_visitor_payload($visitor)];
    }

    if (!in_array($visitor['status'], ['approved', 'pending'], true)) {
        record_scan($vid, $token, 'rejected', 'Status: ' . $visitor['status'], $staffId);
        return ['ok' => false, 'message' => 'Visitor status does not allow check-in (' . $visitor['status'] . ').', 'visitor' => safe_visitor_payload($visitor)];
    }

    // Auto-approve pending on successful validation
    db()->prepare(
        "UPDATE visitors SET
           status = 'checked_in',
           approved_by = COALESCE(approved_by, :aby),
           approved_at = COALESCE(approved_at, :aat),
           checked_in_at = :cin,
           checked_in_by = :cby,
           updated_at = :now
         WHERE id = :id"
    )->execute([
        ':aby' => $staffId,
        ':aat' => now(),
        ':cin' => now(),
        ':cby' => $staffId,
        ':now' => now(),
        ':id'  => $vid,
    ]);

    record_scan($vid, $token, 'success', null, $staffId);
    log_activity('visitor_checkin', 'visitors', 'visitor', $vid, "Checked in {$visitor['visitor_name']}");
    notify_visitor_event(
        (int) $visitor['resident_id'],
        'Visitor checked in',
        "{$visitor['visitor_name']} has checked in.",
        'visitor_checkin',
        $vid
    );

    $updated = get_visitor($vid);
    return ['ok' => true, 'message' => 'Check-in successful.', 'visitor' => safe_visitor_payload($updated)];
}

function process_visitor_checkout(int $visitorId, int $staffId): array
{
    $visitor = get_visitor($visitorId);
    if (!$visitor) {
        return ['ok' => false, 'message' => 'Visitor not found.'];
    }
    if ($visitor['status'] !== 'checked_in') {
        return ['ok' => false, 'message' => 'Visitor is not currently checked in.'];
    }

    db()->prepare(
        "UPDATE visitors SET status = 'checked_out', checked_out_at = :out, checked_out_by = :by, updated_at = :now
         WHERE id = :id"
    )->execute([':out' => now(), ':by' => $staffId, ':now' => now(), ':id' => $visitorId]);

    log_activity('visitor_checkout', 'visitors', 'visitor', $visitorId, "Checked out {$visitor['visitor_name']}");
    notify_visitor_event(
        (int) $visitor['resident_id'],
        'Visitor checked out',
        "{$visitor['visitor_name']} has checked out.",
        'visitor_checkout',
        $visitorId
    );

    return ['ok' => true, 'message' => 'Check-out successful.', 'visitor' => safe_visitor_payload(get_visitor($visitorId))];
}

function safe_visitor_payload(?array $v): ?array
{
    if (!$v) {
        return null;
    }
    return [
        'id'            => (int) $v['id'],
        'visitor_name'  => $v['visitor_name'],
        'car_plate'     => $v['car_plate'],
        'phone'         => $v['phone'],
        'purpose'       => $v['purpose'],
        'visit_date'    => $v['visit_date'],
        'valid_until'   => $v['valid_until'],
        'status'        => $v['status'],
        'resident_name' => $v['resident_name'] ?? null,
        'unit_number'   => $v['unit_number'] ?? null,
        'checked_in_at' => $v['checked_in_at'] ?? null,
        'checked_out_at'=> $v['checked_out_at'] ?? null,
    ];
}

function count_visitors_today(): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM visitors WHERE visit_date = :d');
    $stmt->execute([':d' => today()]);
    return (int) $stmt->fetchColumn();
}

function count_visitors_inside(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM visitors WHERE status = 'checked_in'")->fetchColumn();
}
