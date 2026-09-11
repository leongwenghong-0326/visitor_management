<?php
declare(strict_types=1);

/**
 * QR token helpers for residents and visitors.
 */

function generate_resident_qr(int $residentId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE resident_qr_codes SET is_active = 0, revoked_at = :now
             WHERE resident_id = :rid AND is_active = 1'
        )->execute([':now' => now(), ':rid' => $residentId]);

        $token = generate_token(QR_TOKEN_BYTES);
        $pdo->prepare(
            'INSERT INTO resident_qr_codes (resident_id, token, is_active, created_at)
             VALUES (:rid, :token, 1, :created)'
        )->execute([':rid' => $residentId, ':token' => $token, ':created' => now()]);

        $pdo->commit();
        log_activity('qr_generate', 'residents', 'resident', $residentId, 'Resident QR generated/rotated');
        return ['ok' => true, 'token' => $token];
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        return ['ok' => false, 'message' => 'Unable to generate QR code.'];
    }
}

function revoke_resident_qr(int $residentId): bool
{
    $stmt = db()->prepare(
        'UPDATE resident_qr_codes SET is_active = 0, revoked_at = :now
         WHERE resident_id = :rid AND is_active = 1'
    );
    $stmt->execute([':now' => now(), ':rid' => $residentId]);
    if ($stmt->rowCount() > 0) {
        log_activity('qr_revoke', 'residents', 'resident', $residentId, 'Resident QR revoked');
        return true;
    }
    return false;
}

function get_active_resident_qr(int $residentId): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM resident_qr_codes WHERE resident_id = :rid AND is_active = 1 ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':rid' => $residentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function generate_visitor_qr_token(): string
{
    return generate_token(QR_TOKEN_BYTES);
}

function get_resident_by_qr_token(string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT r.id, r.resident_code, r.full_name, r.gender, r.phone, r.email,
                r.unit_number, r.address, r.status, r.emergency_contact, r.emergency_phone,
                q.token, q.created_at AS qr_created_at
         FROM resident_qr_codes q
         INNER JOIN residents r ON r.id = q.resident_id
         WHERE q.token = :token AND q.is_active = 1
         LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function safe_resident_scan_payload(array $r): array
{
    return [
        'id'                => (int) $r['id'],
        'resident_code'     => $r['resident_code'],
        'full_name'         => $r['full_name'],
        'gender'            => $r['gender'],
        'phone'             => $r['phone'],
        'unit_number'       => $r['unit_number'],
        'address'           => $r['address'],
        'status'            => $r['status'],
        'emergency_contact' => $r['emergency_contact'],
        'emergency_phone'   => $r['emergency_phone'],
    ];
}

function process_security_qr_scan(string $token, int $staffId): array
{
    $token = trim($token);
    if ($token === '' || !preg_match('/^[a-f0-9]{32,128}$/i', $token)) {
        return ['ok' => false, 'type' => 'unknown', 'message' => 'Invalid QR token.'];
    }

    $resident = get_resident_by_qr_token($token);
    if ($resident) {
        if (($resident['status'] ?? '') !== 'active') {
            log_activity('resident_qr_scan', 'residents', 'resident', (int) $resident['id'], 'Resident QR scanned (not active)');
            return [
                'ok'       => false,
                'type'     => 'resident',
                'message'  => 'Resident found but status is not active (' . $resident['status'] . ').',
                'resident' => safe_resident_scan_payload($resident),
            ];
        }
        log_activity('resident_qr_scan', 'residents', 'resident', (int) $resident['id'], 'Resident QR scanned: ' . $resident['full_name']);
        return [
            'ok'       => true,
            'type'     => 'resident',
            'message'  => 'Resident verified.',
            'resident' => safe_resident_scan_payload($resident),
        ];
    }

    $result = process_visitor_checkin($token, $staffId);
    $result['type'] = 'visitor';
    return $result;
}
