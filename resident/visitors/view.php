<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
if (!is_resident()) {
    redirect(role_home_path());
}
$residentId = current_resident_id();
$id = int_id(get('id'));
$visitor = get_visitor($id);
if (!$visitor || (int)$visitor['resident_id'] !== $residentId) {
    flash('error', 'Visitor not found.');
    redirect('resident/visitors/index.php');
}

$pageTitle = 'Visitor Details';
$extraScripts = '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>';
require __DIR__ . '/../../includes/layout/header.php';
?>
<div class="d-flex justify-content-between mb-3">
    <h1 class="h3 mb-0"><?= e($visitor['visitor_name']) ?></h1>
    <a class="btn btn-outline-secondary" href="<?= e(url('resident/visitors/index.php')) ?>">Back</a>
</div>
<div class="row g-3">
<div class="col-md-7">
<div class="panel">
    <dl class="row mb-0">
        <dt class="col-4">Status</dt><dd class="col-8"><?= status_badge($visitor['status']) ?></dd>
        <dt class="col-4">Plate</dt><dd class="col-8"><?= e($visitor['car_plate'] ?? '—') ?></dd>
        <dt class="col-4">Phone</dt><dd class="col-8"><?= e($visitor['phone'] ?? '—') ?></dd>
        <dt class="col-4">Purpose</dt><dd class="col-8"><?= e($visitor['purpose'] ?? '—') ?></dd>
        <dt class="col-4">Visit</dt><dd class="col-8"><?= e(format_date($visitor['visit_date'])) ?> → <?= e(format_date($visitor['valid_until'])) ?></dd>
        <dt class="col-4">Check-in</dt><dd class="col-8"><?= e(format_datetime($visitor['checked_in_at'])) ?></dd>
        <dt class="col-4">Check-out</dt><dd class="col-8"><?= e(format_datetime($visitor['checked_out_at'])) ?></dd>
    </dl>
</div>
</div>
<div class="col-md-5">
<div class="panel">
    <h2 class="h5">Visitor QR</h2>
    <div class="qr-box" id="qrcode"></div>
    <script>document.addEventListener('DOMContentLoaded',function(){new QRCode(document.getElementById('qrcode'),{text:<?= json_encode($visitor['qr_token']) ?>,width:180,height:180});});</script>
    <p class="small text-muted mt-2">Share this QR with your visitor for gate check-in.</p>
</div>
</div>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
