<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
if (!is_resident() || !can('visitors.create')) {
    redirect(role_home_path());
}
$residentId = current_resident_id();
$errors = [];

if (is_post()) {
    require_csrf();
    $result = create_visitor($residentId, $_POST, null, 'approved');
    if ($result['ok']) {
        flash('success', 'Visitor registered. Share the QR with your guest.');
        redirect('resident/visitors/view.php?id=' . $result['id']);
    }
    $errors = $result['errors'];
}

$pageTitle = 'Register Visitor';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="h3 mb-3">Register Visitor</h1>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="panel">
<form method="post" class="row g-3">
<?= csrf_field() ?>
<div class="col-md-6"><label class="form-label">Visitor Name *</label><input name="visitor_name" class="form-control" required></div>
<div class="col-md-3"><label class="form-label">Car Plate</label><input name="car_plate" class="form-control"></div>
<div class="col-md-3"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
<div class="col-12"><label class="form-label">Purpose</label><input name="purpose" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Visit Date *</label><input type="date" name="visit_date" class="form-control" required value="<?= e(today()) ?>"></div>
<div class="col-md-6"><label class="form-label">Valid Until *</label><input type="date" name="valid_until" class="form-control" required value="<?= e(today()) ?>"></div>
<div class="col-12">
    <button class="btn btn-teal" type="submit">Save</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('resident/visitors/index.php')) ?>">Cancel</a>
</div>
</form>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
