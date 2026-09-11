<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';

$token = trim((string) get('token', ''));
$link = $token !== '' ? get_invite_link_by_token($token) : null;
$valid = $link ? invite_link_is_valid($link) : ['ok' => false, 'message' => 'Invalid invitation link.'];

$errors = [];
$created = null;

if ($link && $valid['ok'] && is_post()) {
    // Public form — CSRF still recommended
    if (!verify_csrf()) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $result = create_visitor((int) $link['resident_id'], $_POST, (int) $link['id'], 'approved');
        if ($result['ok']) {
            $created = get_visitor($result['id']);
        } else {
            $errors = $result['errors'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visitor Registration — <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
    <?php if ($created): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <?php endif; ?>
</head>
<body class="app-body">
<main class="container py-4" style="max-width:640px">
    <div class="text-center mb-4">
        <h1 class="h3"><?= e(APP_NAME) ?></h1>
        <p class="text-muted mb-0">Visitor registration</p>
    </div>

    <?php if (!$link || !$valid['ok']): ?>
        <div class="alert alert-danger"><?= e($valid['message'] ?? 'Invalid invitation link.') ?></div>
    <?php elseif ($created): ?>
        <div class="panel text-center">
            <div class="alert alert-success">Registration successful. Please keep this QR for check-in.</div>
            <div class="fw-semibold mb-1"><?= e($created['visitor_name']) ?></div>
            <div class="small text-muted mb-3">Visit <?= e(format_date($created['visit_date'])) ?> · Host unit <?= e($created['unit_number']) ?></div>
            <div class="qr-box mx-auto mb-3" id="qrcode"></div>
            <script>new QRCode(document.getElementById('qrcode'),{text:<?= json_encode($created['qr_token']) ?>,width:200,height:200});</script>
            <p class="small text-muted">Screenshot or save this page. Do not share the QR publicly.</p>
        </div>
    <?php else: ?>
        <div class="panel">
            <p class="small text-muted">You are registering as a visitor for unit <strong><?= e($link['unit_number']) ?></strong>.</p>
            <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form method="post" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-12"><label class="form-label">Full Name *</label><input name="visitor_name" class="form-control form-control-lg" required></div>
                <div class="col-md-6"><label class="form-label">Car Plate</label><input name="car_plate" class="form-control form-control-lg"></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control form-control-lg" inputmode="tel"></div>
                <div class="col-12"><label class="form-label">Purpose</label><input name="purpose" class="form-control form-control-lg"></div>
                <div class="col-md-6"><label class="form-label">Visit Date *</label><input type="date" name="visit_date" class="form-control form-control-lg" required value="<?= e(today()) ?>"></div>
                <div class="col-md-6"><label class="form-label">Valid Until *</label><input type="date" name="valid_until" class="form-control form-control-lg" required value="<?= e(today()) ?>"></div>
                <div class="col-12"><button class="btn btn-teal btn-lg w-100" type="submit">Submit</button></div>
            </form>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
