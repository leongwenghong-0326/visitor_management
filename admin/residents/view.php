<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
require_permission('residents.view');

$id = int_id(get('id'));
$resident = get_resident($id);
if (!$resident) {
    flash('error', 'Resident not found.');
    redirect('admin/residents/index.php');
}

if (is_post() && can('residents.qr')) {
    require_csrf();
    $action = (string) post('action');
    if ($action === 'generate_qr') {
        $r = generate_resident_qr($id);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'QR code generated.' : ($r['message'] ?? 'Failed'));
    } elseif ($action === 'revoke_qr') {
        flash(revoke_resident_qr($id) ? 'success' : 'error', 'QR revoked.');
    }
    redirect('admin/residents/view.php?id=' . $id);
}

$qr = get_active_resident_qr($id);
$history = list_visitors(['resident_id' => $id], 1, 10);
$logs = get_activity_logs(['q' => $resident['resident_code']], 1, 10);

$pageTitle = 'Resident Details';
$extraScripts = '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>';
require __DIR__ . '/../../includes/layout/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0"><?= e($resident['full_name']) ?> <small class="text-muted"><?= e($resident['resident_code']) ?></small></h1>
    <div>
        <?php if (can('residents.edit')): ?>
            <a class="btn btn-outline-secondary" href="<?= e(url('admin/residents/edit.php?id='.$id)) ?>">Edit</a>
        <?php endif; ?>
        <a class="btn btn-outline-primary" href="<?= e(url('admin/residents/index.php')) ?>">Back</a>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="panel mb-3">
            <h2 class="h5">Profile</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><?= status_badge($resident['status']) ?></dd>
                <dt class="col-sm-4">Gender</dt><dd class="col-sm-8"><?= e(ucfirst($resident['gender'])) ?></dd>
                <dt class="col-sm-4">Unit</dt><dd class="col-sm-8"><?= e($resident['unit_number']) ?></dd>
                <dt class="col-sm-4">Phone</dt><dd class="col-sm-8"><?= e($resident['phone'] ?? '—') ?></dd>
                <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= e($resident['email'] ?? '—') ?></dd>
                <dt class="col-sm-4">Address</dt><dd class="col-sm-8"><?= e($resident['address'] ?? '—') ?></dd>
                <dt class="col-sm-4">Emergency</dt><dd class="col-sm-8"><?= e(($resident['emergency_contact'] ?? '—') . ' / ' . ($resident['emergency_phone'] ?? '—')) ?></dd>
                <dt class="col-sm-4">Account</dt><dd class="col-sm-8"><?= e($resident['linked_username'] ?? '—') ?></dd>
            </dl>
        </div>
        <div class="panel">
            <h2 class="h5">Recent Visitors</h2>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Name</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$history['rows']): ?>
                        <tr><td colspan="3" class="text-muted">No visitors yet.</td></tr>
                    <?php else: foreach ($history['rows'] as $v): ?>
                        <tr>
                            <td><a href="<?= e(url('admin/visitors/view.php?id='.$v['id'])) ?>"><?= e($v['visitor_name']) ?></a></td>
                            <td><?= e(format_date($v['visit_date'])) ?></td>
                            <td><?= status_badge($v['status']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panel mb-3">
            <h2 class="h5">Resident QR</h2>
            <p class="small text-muted">QR contains only a secure token — no personal data.</p>
            <?php if ($qr): ?>
                <div class="qr-box mb-2" id="qrcode"></div>
                <div class="small text-break mb-2"><code><?= e($qr['token']) ?></code></div>
                <script>
                document.addEventListener('DOMContentLoaded', function(){
                    new QRCode(document.getElementById('qrcode'), {text: <?= json_encode($qr['token']) ?>, width:180, height:180});
                });
                </script>
            <?php else: ?>
                <p class="text-muted">No active QR code.</p>
            <?php endif; ?>
            <?php if (can('residents.qr')): ?>
            <form method="post" class="d-inline"><?= csrf_field() ?>
                <input type="hidden" name="action" value="generate_qr">
                <button class="btn btn-teal btn-sm" type="submit"><?= $qr ? 'Rotate QR' : 'Generate QR' ?></button>
            </form>
            <?php if ($qr): ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Revoke this QR?');"><?= csrf_field() ?>
                <input type="hidden" name="action" value="revoke_qr">
                <button class="btn btn-outline-danger btn-sm" type="submit">Revoke</button>
            </form>
            <?php endif; endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
