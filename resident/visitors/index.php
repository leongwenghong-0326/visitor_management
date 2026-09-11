<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
if (!is_resident()) {
    redirect(role_home_path());
}
require __DIR__ . '/../../includes/layout/pagination.php';

$residentId = current_resident_id();
if (!$residentId) {
    flash('error', 'Resident profile not linked.');
    redirect('resident/dashboard.php');
}

if (is_post()) {
    require_csrf();
    $action = (string) post('action');
    if ($action === 'create_invite') {
        $days = max(1, min(30, int_id(post('days', DEFAULT_INVITE_DAYS))));
        $r = create_invite_link($residentId, $days, null, (int) current_user()['id']);
        flash('success', 'Invitation link created.');
        redirect('resident/visitors/index.php');
    } elseif ($action === 'revoke_invite') {
        revoke_invite_link(int_id(post('id')), $residentId);
        flash('success', 'Invitation revoked.');
        redirect('resident/visitors/index.php');
    }
}

$data = list_visitors(['resident_id' => $residentId], max(1, int_id(get('page', 1))));
$invites = list_invite_links($residentId);

$pageTitle = 'My Visitors';
require __DIR__ . '/../../includes/layout/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">My Visitors</h1>
    <a class="btn btn-teal" href="<?= e(url('resident/visitors/create.php')) ?>">Register Visitor</a>
</div>

<div class="panel mb-3">
    <h2 class="h5">Invitation Links</h2>
    <div class="alert alert-info small py-2">
        Share this link with your visitor (WhatsApp / SMS). Visitor and your PC must be on the <strong>same Wi-Fi</strong>.
        Links use your network IP so phones can open them (not localhost).
    </div>
    <form method="post" class="row g-2 align-items-end mb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_invite">
        <div class="col-auto"><label class="form-label">Valid days</label><input type="number" name="days" class="form-control" value="7" min="1" max="30"></div>
        <div class="col-auto"><button class="btn btn-outline-primary" type="submit">Generate Link</button></div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Link</th><th>Expires</th><th>Uses</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$invites): ?><tr><td colspan="5" class="text-muted">No invitation links yet.</td></tr>
            <?php else: foreach ($invites as $inv):
                $url = public_url('visitor/fill.php?token=' . urlencode($inv['token']));
                $active = (int)$inv['is_active'] && !$inv['revoked_at'] && $inv['expires_at'] >= now();
            ?>
                <tr>
                    <td class="small text-break">
                        <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"><?= e($url) ?></a>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-1" onclick='copyToClipboard(<?= json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, this)'>Copy</button>
                    </td>
                    <td><?= e(format_datetime($inv['expires_at'])) ?></td>
                    <td><?= (int)$inv['use_count'] ?><?= $inv['max_uses'] !== null ? ' / '.(int)$inv['max_uses'] : '' ?></td>
                    <td><?= $active ? status_badge('active') : status_badge('inactive') ?></td>
                    <td>
                        <?php if ($active): ?>
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="revoke_invite">
                            <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Revoke this link?')">Revoke</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <h2 class="h5">Visitor Records</h2>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Name</th><th>Plate</th><th>Visit</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$data['rows']): ?><tr><td colspan="5" class="text-muted">No visitors.</td></tr>
            <?php else: foreach ($data['rows'] as $v): ?>
                <tr>
                    <td><?= e($v['visitor_name']) ?></td>
                    <td><?= e($v['car_plate'] ?? '—') ?></td>
                    <td><?= e(format_date($v['visit_date'])) ?></td>
                    <td><?= status_badge($v['status']) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('resident/visitors/view.php?id='.$v['id'])) ?>">View</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-3"><?php render_pagination($data['pager'], url('resident/visitors/index.php')); ?></div>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
