<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
require_permission('visitors.create');

$errors = [];
$residents = db()->query("SELECT id, full_name, unit_number, resident_code FROM residents WHERE status='active' ORDER BY full_name")->fetchAll();

if (is_post()) {
    require_csrf();
    $rid = int_id(post('resident_id'));
    if ($rid <= 0) {
        $errors[] = 'Host resident is required.';
    } else {
        $result = create_visitor($rid, $_POST, null, 'approved');
        if ($result['ok']) {
            flash('success', 'Visitor registered.');
            redirect('admin/visitors/view.php?id=' . $result['id']);
        }
        $errors = $result['errors'];
    }
}

$pageTitle = 'Register Visitor';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="h3 mb-3">Register Visitor</h1>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="panel">
<form method="post" class="row g-3">
<?= csrf_field() ?>
<div class="col-md-6">
    <label class="form-label">Host Resident *</label>
    <select name="resident_id" class="form-select" required>
        <option value="">Select…</option>
        <?php foreach ($residents as $r): ?>
            <option value="<?= (int)$r['id'] ?>"><?= e($r['full_name'].' · '.$r['unit_number'].' ('.$r['resident_code'].')') ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-6">
    <label class="form-label">Visitor Name *</label>
    <input name="visitor_name" class="form-control" required>
</div>
<div class="col-md-4"><label class="form-label">Car Plate</label><input name="car_plate" class="form-control"></div>
<div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
<div class="col-md-4"><label class="form-label">Purpose</label><input name="purpose" class="form-control"></div>
<div class="col-md-4"><label class="form-label">Visit Date *</label><input type="date" name="visit_date" class="form-control" required value="<?= e(today()) ?>"></div>
<div class="col-md-4"><label class="form-label">Valid Until *</label><input type="date" name="valid_until" class="form-control" required value="<?= e(today()) ?>"></div>
<div class="col-12">
    <button class="btn btn-teal" type="submit">Save</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('admin/visitors/index.php')) ?>">Cancel</a>
</div>
</form>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
