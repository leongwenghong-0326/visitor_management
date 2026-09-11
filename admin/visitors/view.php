<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
require_permission('visitors.view');

$id = int_id(get('id'));
$visitor = get_visitor($id);
if (!$visitor) {
    flash('error', 'Visitor not found.');
    redirect('admin/visitors/index.php');
}

if (is_post() && can('visitors.edit')) {
    require_csrf();
    $action = (string) post('action');
    if ($action === 'approve') {
        update_visitor_status($id, 'approved');
        flash('success', 'Visitor approved.');
    } elseif ($action === 'reject') {
        update_visitor_status($id, 'rejected', trim((string) post('rejection_reason')));
        flash('success', 'Visitor rejected.');
    }
    redirect('admin/visitors/view.php?id=' . $id);
}

$pageTitle = 'Visitor Details';
$extraScripts = '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>';
require __DIR__ . '/../../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= e($visitor['visitor_name']) ?></h1>
    <a class="btn btn-outline-secondary" href="<?= e(url('admin/visitors/index.php')) ?>">Back</a>
</div>
<div class="row g-3">
<div class="col-lg-7">
<div class="panel">
    <dl class="row mb-0">
        <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><?= status_badge($visitor['status']) ?></dd>
        <dt class="col-sm-4">Host</dt><dd class="col-sm-8"><?= e($visitor['resident_name'].' · '.$visitor['unit_number']) ?></dd>
        <dt class="col-sm-4">Car Plate</dt><dd class="col-sm-8"><?= e($visitor['car_plate'] ?? '—') ?></dd>
        <dt class="col-sm-4">Phone</dt><dd class="col-sm-8"><?= e($visitor['phone'] ?? '—') ?></dd>
        <dt class="col-sm-4">Purpose</dt><dd class="col-sm-8"><?= e($visitor['purpose'] ?? '—') ?></dd>
        <dt class="col-sm-4">Visit Date</dt><dd class="col-sm-8"><?= e(format_date($visitor['visit_date'])) ?> → <?= e(format_date($visitor['valid_until'])) ?></dd>
        <dt class="col-sm-4">Check-in</dt><dd class="col-sm-8"><?= e(format_datetime($visitor['checked_in_at'])) ?></dd>
        <dt class="col-sm-4">Check-out</dt><dd class="col-sm-8"><?= e(format_datetime($visitor['checked_out_at'])) ?></dd>
        <?php if ($visitor['rejection_reason']): ?>
        <dt class="col-sm-4">Rejection</dt><dd class="col-sm-8"><?= e($visitor['rejection_reason']) ?></dd>
        <?php endif; ?>
    </dl>
    <?php if (can('visitors.edit') && in_array($visitor['status'], ['pending'], true)): ?>
    <div class="mt-3 d-flex gap-2">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><button class="btn btn-success btn-sm">Approve</button></form>
        <form method="post" class="d-flex gap-2"><?= csrf_field() ?>
            <input type="hidden" name="action" value="reject">
            <input name="rejection_reason" class="form-control form-control-sm" placeholder="Reason" required>
            <button class="btn btn-danger btn-sm">Reject</button>
        </form>
    </div>
    <?php endif; ?>
</div>
</div>
<div class="col-lg-5">
<div class="panel">
    <h2 class="h5">Visitor QR</h2>
    <div class="qr-box mb-2" id="qrcode"></div>
    <div class="small text-break"><code><?= e($visitor['qr_token']) ?></code></div>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        new QRCode(document.getElementById('qrcode'), {text: <?= json_encode($visitor['qr_token']) ?>, width:180, height:180});
    });
    </script>
</div>
</div>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
