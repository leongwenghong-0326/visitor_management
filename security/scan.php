<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
require_permission('checkin.scan');

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$hostOnly = preg_replace('/:\d+$/', '', $host);
$isLocalHost = in_array($hostOnly, ['localhost', '127.0.0.1'], true);
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$cameraOkHost = $isLocalHost || $isHttps;
$localhostScan = 'http://localhost/visitor_management/security/scan.php';

$pageTitle = 'QR Scanner';
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>'
    . '<script>window.VMS_SCAN_URL=' . json_encode(url('api/scan.php')) . ';window.VMS_CSRF=' . json_encode(csrf_token()) . ';</script>'
    . '<script src="' . e(asset('js/scanner.js')) . '?v=4"></script>';

require __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">QR Scanner</h1>
    <a href="<?= e(url('security/dashboard.php')) ?>" class="btn btn-outline-secondary">Dashboard</a>
</div>

<div id="secureWarning" class="alert alert-warning <?= $cameraOkHost ? 'd-none' : '' ?>">
    <strong>Camera cannot open on this address.</strong>
    Browsers only allow camera on <code>localhost</code> or <strong>HTTPS</strong>.
    <div class="mt-2">
        <a class="btn btn-teal btn-sm" href="<?= e($localhostScan) ?>">Open scanner on localhost</a>
    </div>
    <div class="small mt-2 mb-0">
        Log in again on localhost if needed, then keep this scanner page open for auto scan.
    </div>
</div>

<div class="row g-3">
<div class="col-lg-8">
    <div class="panel">
        <div class="mb-3">
            <label class="form-label">Camera</label>
            <select id="cameraSelect" class="form-select"></select>
        </div>
        <div id="scanner-preview" class="mb-3 position-relative bg-dark">
            <video id="video" playsinline muted autoplay></video>
            <canvas id="canvas" class="d-none"></canvas>
            <div class="scan-frame"></div>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <button type="button" id="btnStart" class="btn btn-teal">Start Camera</button>
            <button type="button" id="btnStop" class="btn btn-outline-secondary" disabled>Stop</button>
        </div>
        <div id="scanStatus" class="small text-muted">Starting auto-scan...</div>
    </div>
</div>
<div class="col-lg-4">
    <div class="panel">
        <h2 class="h5">Scan Result</h2>
        <p class="small text-muted">Auto-scan visitor QR (check-in) or resident QR (show resident info).</p>
        <div id="resultBox" class="alert alert-secondary mb-0">Waiting for QR...</div>
        <div id="visitorDetails" class="mt-3 small d-none"></div>
    </div>
</div>
</div>
<style>
#scanner-preview { min-height: 320px; border-radius: .75rem; overflow: hidden; }
#scanner-preview video { width: 100%; min-height: 320px; object-fit: cover; background: #000; }
.scan-frame {
  pointer-events: none;
  position: absolute;
  inset: 18%;
  border: 2px solid rgba(31, 138, 112, .85);
  border-radius: .5rem;
  box-shadow: 0 0 0 9999px rgba(0,0,0,.25);
}
</style>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>