<?php
declare(strict_types=1);

/**
 * Resident CRUD and helpers.
 */

function generate_resident_code(): string
{
    $stmt = db()->query('SELECT MAX(id) FROM residents');
    $next = ((int) $stmt->fetchColumn()) + 1;
    return 'R-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function get_resident(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT r.*, u.username AS linked_username
         FROM residents r
         LEFT JOIN users u ON u.id = r.user_id
         WHERE r.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_resident_by_user(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM residents WHERE user_id = :uid LIMIT 1');
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_residents(array $filters = [], int $page = 1, int $perPage = ITEMS_PER_PAGE): array
{
    $where = ['1=1'];
    $params = [];

    if (!empty($filters['q'])) {
        $where[] = '(r.full_name LIKE :q1 OR r.resident_code LIKE :q2 OR r.unit_number LIKE :q3 OR r.phone LIKE :q4 OR r.email LIKE :q5)';
        $q = '%' . $filters['q'] . '%';
        $params[':q1'] = $q;
        $params[':q2'] = $q;
        $params[':q3'] = $q;
        $params[':q4'] = $q;
        $params[':q5'] = $q;
    }
    if (!empty($filters['status'])) {
        $where[] = 'r.status = :status';
        $params[':status'] = $filters['status'];
    }

    $sqlWhere = implode(' AND ', $where);
    $count = db()->prepare("SELECT COUNT(*) FROM residents r WHERE $sqlWhere");
    $count->execute($params);
    $pager = paginate((int) $count->fetchColumn(), $page, $perPage);

    $stmt = db()->prepare(
        "SELECT r.*, u.username AS linked_username
         FROM residents r
         LEFT JOIN users u ON u.id = r.user_id
         WHERE $sqlWhere
         ORDER BY r.id DESC
         LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
    );
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'pager' => $pager];
}

function validate_resident_input(array $data, bool $isUpdate = false): array
{
    $errors = [];
    $fullName = trim((string) ($data['full_name'] ?? ''));
    $gender = (string) ($data['gender'] ?? '');
    $unit = trim((string) ($data['unit_number'] ?? ''));
    $status = (string) ($data['status'] ?? 'pending');
    $email = trim((string) ($data['email'] ?? ''));
    $phone = normalize_phone($data['phone'] ?? '');

    if ($fullName === '' || strlen($fullName) > 150) {
        $errors[] = 'Full name is required (max 150 characters).';
    }
    if (!in_array($gender, ['male', 'female', 'other'], true)) {
        $errors[] = 'Invalid gender.';
    }
    if ($unit === '') {
        $errors[] = 'Unit / house number is required.';
    }
    if (!in_array($status, ['pending', 'active', 'suspended', 'moved_out', 'inactive'], true)) {
        $errors[] = 'Invalid status.';
    }
    if ($email !== '' && !validate_email($email)) {
        $errors[] = 'Invalid email address.';
    }

    return [
        'errors' => $errors,
        'data'   => [
            'full_name'          => $fullName,
            'gender'             => $gender,
            'phone'              => $phone !== '' ? $phone : null,
            'email'              => $email !== '' ? $email : null,
            'address'            => trim((string) ($data['address'] ?? '')) ?: null,
            'unit_number'        => $unit,
            'emergency_contact'  => trim((string) ($data['emergency_contact'] ?? '')) ?: null,
            'emergency_phone'    => normalize_phone($data['emergency_phone'] ?? '') ?: null,
            'status'             => $status,
            'notes'              => trim((string) ($data['notes'] ?? '')) ?: null,
            'user_id'            => !empty($data['user_id']) ? int_id($data['user_id']) : null,
        ],
    ];
}

function create_resident(array $data, int $createdBy): array
{
    $v = validate_resident_input($data);
    if ($v['errors']) {
        return ['ok' => false, 'errors' => $v['errors']];
    }
    $d = $v['data'];
    $code = generate_resident_code();

    if ($d['user_id']) {
        $chk = db()->prepare('SELECT id FROM residents WHERE user_id = :uid LIMIT 1');
        $chk->execute([':uid' => $d['user_id']]);
        if ($chk->fetch()) {
            return ['ok' => false, 'errors' => ['Selected user is already linked to another resident.']];
        }
    }

    $stmt = db()->prepare(
        'INSERT INTO residents
         (user_id, resident_code, full_name, gender, phone, email, address, unit_number,
          emergency_contact, emergency_phone, status, notes, created_by, created_at)
         VALUES
         (:user_id, :code, :full_name, :gender, :phone, :email, :address, :unit_number,
          :emergency_contact, :emergency_phone, :status, :notes, :created_by, :created_at)'
    );
    $stmt->execute([
        ':user_id'           => $d['user_id'],
        ':code'              => $code,
        ':full_name'         => $d['full_name'],
        ':gender'            => $d['gender'],
        ':phone'             => $d['phone'],
        ':email'             => $d['email'],
        ':address'           => $d['address'],
        ':unit_number'       => $d['unit_number'],
        ':emergency_contact' => $d['emergency_contact'],
        ':emergency_phone'   => $d['emergency_phone'],
        ':status'            => $d['status'],
        ':notes'             => $d['notes'],
        ':created_by'        => $createdBy,
        ':created_at'        => now(),
    ]);
    $id = (int) db()->lastInsertId();

    // Auto-generate QR for active residents
    if ($d['status'] === 'active') {
        generate_resident_qr($id);
    }

    log_activity('create_resident', 'residents', 'resident', $id, "Created resident {$code}");
    return ['ok' => true, 'id' => $id];
}

function update_resident(int $id, array $data): array
{
    $existing = get_resident($id);
    if (!$existing) {
        return ['ok' => false, 'errors' => ['Resident not found.']];
    }

    $v = validate_resident_input($data, true);
    if ($v['errors']) {
        return ['ok' => false, 'errors' => $v['errors']];
    }
    $d = $v['data'];

    if ($d['user_id']) {
        $chk = db()->prepare('SELECT id FROM residents WHERE user_id = :uid AND id != :id LIMIT 1');
        $chk->execute([':uid' => $d['user_id'], ':id' => $id]);
        if ($chk->fetch()) {
            return ['ok' => false, 'errors' => ['Selected user is already linked to another resident.']];
        }
    }

    $stmt = db()->prepare(
        'UPDATE residents SET
           user_id = :user_id, full_name = :full_name, gender = :gender, phone = :phone,
           email = :email, address = :address, unit_number = :unit_number,
           emergency_contact = :emergency_contact, emergency_phone = :emergency_phone,
           status = :status, notes = :notes, updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        ':user_id'           => $d['user_id'],
        ':full_name'         => $d['full_name'],
        ':gender'            => $d['gender'],
        ':phone'             => $d['phone'],
        ':email'             => $d['email'],
        ':address'           => $d['address'],
        ':unit_number'       => $d['unit_number'],
        ':emergency_contact' => $d['emergency_contact'],
        ':emergency_phone'   => $d['emergency_phone'],
        ':status'            => $d['status'],
        ':notes'             => $d['notes'],
        ':updated_at'        => now(),
        ':id'                => $id,
    ]);

    log_activity('update_resident', 'residents', 'resident', $id, "Updated resident {$existing['resident_code']}");
    return ['ok' => true];
}

function count_residents(?string $status = null): int
{
    if ($status) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM residents WHERE status = :s');
        $stmt->execute([':s' => $status]);
        return (int) $stmt->fetchColumn();
    }
    return (int) db()->query('SELECT COUNT(*) FROM residents')->fetchColumn();
}

function get_unlinked_resident_users(): array
{
    $stmt = db()->query(
        "SELECT u.id, u.username, u.full_name
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE r.slug = 'resident'
           AND u.status = 'active'
           AND u.id NOT IN (SELECT user_id FROM residents WHERE user_id IS NOT NULL)
         ORDER BY u.full_name"
    );
    return $stmt->fetchAll();
}
