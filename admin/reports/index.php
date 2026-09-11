<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/init.php';
require_login();
require_permission('reports.view');

$type = (string) get('type', '');
$from = (string) get('from', date('Y-m-01'));
$to = (string) get('to', today());
$format = (string) get('format', '');

if ($type !== '' && $format !== '' && can('reports.export')) {
    $rows = [];
    $headers = [];
    $title = 'Report';

    if ($type === 'residents') {
        $title = 'Resident Report';
        $headers = ['Code','Name','Unit','Phone','Email','Status'];
        $stmt = db()->query('SELECT resident_code, full_name, unit_number, phone, email, status FROM residents ORDER BY id');
        foreach ($stmt as $r) {
            $rows[] = [$r['resident_code'],$r['full_name'],$r['unit_number'],$r['phone'],$r['email'],$r['status']];
        }
    } elseif ($type === 'visitors' || $type === 'today' || $type === 'inside' || $type === 'checkin' || $type === 'checkout') {
        $title = ucfirst($type) . ' Visitors';
        $headers = ['Visitor','Host','Unit','Plate','Visit Date','Status','Check-in','Check-out'];
        $where = 'v.visit_date BETWEEN :from AND :to';
        $params = [':from' => $from, ':to' => $to];
        if ($type === 'today') {
            $where = 'v.visit_date = :d';
            $params = [':d' => today()];
        } elseif ($type === 'inside') {
            $where = "v.status = 'checked_in'";
            $params = [];
        } elseif ($type === 'checkin') {
            $where = 'DATE(v.checked_in_at) BETWEEN :from AND :to';
        } elseif ($type === 'checkout') {
            $where = 'DATE(v.checked_out_at) BETWEEN :from AND :to';
        }
        $sql = "SELECT v.*, r.full_name AS resident_name, r.unit_number
                FROM visitors v INNER JOIN residents r ON r.id = v.resident_id WHERE $where ORDER BY v.id DESC";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt as $v) {
            $rows[] = [$v['visitor_name'],$v['resident_name'],$v['unit_number'],$v['car_plate'],$v['visit_date'],$v['status'],$v['checked_in_at'],$v['checked_out_at']];
        }
    } elseif ($type === 'blacklist') {
        $title = 'Blacklist Report';
        $headers = ['Name','Plate','Phone','Reason','Active'];
        foreach (db()->query('SELECT * FROM visitor_blacklist ORDER BY id DESC') as $b) {
            $rows[] = [$b['full_name'],$b['car_plate'],$b['phone'],$b['reason'],$b['is_active']?'Yes':'No'];
        }
    } elseif ($type === 'activity') {
        $title = 'Activity Report';
        $headers = ['Time','User','Action','Description'];
        $stmt = db()->prepare(
            'SELECT a.*, u.full_name FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id
             WHERE a.created_at BETWEEN :from AND :to ORDER BY a.id DESC LIMIT 5000'
        );
        $stmt->execute([':from' => $from.' 00:00:00', ':to' => $to.' 23:59:59']);
        foreach ($stmt as $a) {
            $rows[] = [$a['created_at'],$a['full_name'],$a['action'],$a['description']];
        }
    }

    log_activity('export_report', 'reports', null, null, "Exported {$type} as {$format}");

    if ($format === 'csv') {
        export_csv($type . '_' . date('Ymd_His') . '.csv', $headers, $rows);
    }
    if ($format === 'pdf') {
        $lines = [implode(' | ', $headers)];
        foreach ($rows as $row) {
            $lines[] = implode(' | ', array_map('strval', $row));
        }
        export_simple_pdf($type . '_' . date('Ymd_His') . '.pdf', $title, $lines);
    }
}

$pageTitle = 'Reports';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="h3 mb-3">Reports</h1>
<div class="panel">
<form method="get" class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Report Type</label>
        <select name="type" class="form-select" required>
            <option value="">Select…</option>
            <?php
            $types = [
                'residents' => 'Resident report',
                'visitors' => 'Visitor report',
                'today' => "Today's visitors",
                'checkin' => 'Check-in report',
                'checkout' => 'Check-out report',
                'inside' => 'Currently inside',
                'blacklist' => 'Blacklist report',
                'activity' => 'Activity report',
            ];
            foreach ($types as $k => $label): ?>
                <option value="<?= $k ?>" <?= $type===$k?'selected':'' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
    <div class="col-md-3"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
    <div class="col-md-2"><label class="form-label">Format</label>
        <select name="format" class="form-select">
            <option value="">Preview</option>
            <?php if (can('reports.export')): ?>
            <option value="csv">CSV / Excel</option>
            <option value="pdf">PDF</option>
            <?php endif; ?>
        </select>
    </div>
    <div class="col-12"><button class="btn btn-teal" type="submit">Run Report</button></div>
</form>
</div>

<?php if ($type !== '' && $format === ''): ?>
<?php
$preview = list_visitors(
    $type === 'inside' ? ['inside' => true] : ($type === 'today' ? ['visit_date' => today()] : ['from' => $from, 'to' => $to]),
    1,
    50
);
if ($type === 'residents') {
    $preview = list_residents([], 1, 50);
}
?>
<div class="panel mt-3">
    <h2 class="h5">Preview (first page)</h2>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
            <?php if ($type === 'residents'): ?>
                <tr><th>Code</th><th>Name</th><th>Unit</th><th>Status</th></tr>
            <?php else: ?>
                <tr><th>Visitor/Item</th><th>Host/Unit</th><th>Status</th><th>Date</th></tr>
            <?php endif; ?>
            </thead>
            <tbody>
            <?php if ($type === 'residents'): foreach ($preview['rows'] as $r): ?>
                <tr><td><?= e($r['resident_code']) ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['unit_number']) ?></td><td><?= status_badge($r['status']) ?></td></tr>
            <?php endforeach; elseif (in_array($type, ['visitors','today','inside','checkin','checkout'], true)): foreach ($preview['rows'] as $v): ?>
                <tr><td><?= e($v['visitor_name']) ?></td><td><?= e($v['resident_name'].' / '.$v['unit_number']) ?></td><td><?= status_badge($v['status']) ?></td><td><?= e(format_date($v['visit_date'])) ?></td></tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-muted">Use CSV/PDF export for this report type.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
