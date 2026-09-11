<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
if (!is_resident()) {
    redirect(role_home_path());
}
$residentId = current_resident_id();
$resident = get_resident($residentId);
if (!$resident) {
    flash('error', 'Resident profile not found.');
    redirect('resident/dashboard.php');
}
$qr = get_active_resident_qr($residentId);
$pageTitle = 'My QR Code';
$extraScripts = '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>';
require __DIR__ . '/../includes/layout/header.php';
?>
<h1 class="h3 mb-3">My QR Code</h1>
<div class="panel">
    <p class="text-muted">This QR contains only a secure random token. It does not include your personal information.</p>
    <?php if ($qr): ?>
        <div class="qr-box mb-3" id="qrcode"></div>
        <script>document.addEventListener('DOMContentLoaded',function(){new QRCode(document.getElementById('qrcode'),{text:<?= json_encode($qr['token']) ?>,width:220,height:220});});</script>
        <p class="small text-muted">Generated: <?= e(format_datetime($qr['created_at'])) ?></p>
    <?php else: ?>
        <div class="alert alert-warning">No active QR. Please ask an administrator to generate one.</div>
    <?php endif; ?>
    <a class="btn btn-outline-secondary" href="<?= e(url('resident/dashboard.php')) ?>">Back</a>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
