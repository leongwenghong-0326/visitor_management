(function () {
  var video = document.getElementById('video');
  var canvas = document.getElementById('canvas');
  var ctx = canvas.getContext('2d', { willReadFrequently: true });
  var cameraSelect = document.getElementById('cameraSelect');
  var btnStart = document.getElementById('btnStart');
  var btnStop = document.getElementById('btnStop');
  var scanStatus = document.getElementById('scanStatus');
  var resultBox = document.getElementById('resultBox');
  var visitorDetails = document.getElementById('visitorDetails');
  var secureWarning = document.getElementById('secureWarning');
  var stream = null;
  var scanning = false;
  var rafId = null;
  var lastToken = '';
  var lastScanAt = 0;
  var cooldownMs = 3500;
  var starting = false;

  function isSecureCameraContext() {
    if (window.isSecureContext) return true;
    var h = location.hostname;
    return h === 'localhost' || h === '127.0.0.1';
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function setResult(ok, message, data) {
    resultBox.className = 'alert mb-0 ' + (ok ? 'alert-success' : 'alert-danger');
    resultBox.textContent = message;
    visitorDetails.classList.add('d-none');
    visitorDetails.innerHTML = '';
    if (!data) return;

    if (data.type === 'resident' && data.resident) {
      var r = data.resident;
      visitorDetails.classList.remove('d-none');
      visitorDetails.innerHTML =
        '<div class="fw-semibold text-teal mb-1"><i class="fa-solid fa-id-card me-1"></i>Resident</div>' +
        '<strong>' + escapeHtml(r.full_name) + '</strong><br>' +
        'Code: ' + escapeHtml(r.resident_code || '-') + '<br>' +
        'Unit: ' + escapeHtml(r.unit_number || '-') + '<br>' +
        'Phone: ' + escapeHtml(r.phone || '-') + '<br>' +
        'Status: ' + escapeHtml(r.status || '-') + '<br>' +
        'Emergency: ' + escapeHtml(r.emergency_contact || '-') +
        ' / ' + escapeHtml(r.emergency_phone || '-');
      return;
    }

    if (data.visitor) {
      var visitor = data.visitor;
      visitorDetails.classList.remove('d-none');
      visitorDetails.innerHTML =
        '<div class="fw-semibold text-primary mb-1"><i class="fa-solid fa-user me-1"></i>Visitor</div>' +
        '<strong>' + escapeHtml(visitor.visitor_name) + '</strong><br>' +
        'Host: ' + escapeHtml(visitor.resident_name || '') + ' / ' + escapeHtml(visitor.unit_number || '') + '<br>' +
        'Plate: ' + escapeHtml(visitor.car_plate || '-') + '<br>' +
        'Status: ' + escapeHtml(visitor.status || '');
    }
  }

  function submitToken(token) {
    var now = Date.now();
    if (token === lastToken && (now - lastScanAt) < cooldownMs) {
      return;
    }
    lastToken = token;
    lastScanAt = now;
    scanStatus.textContent = 'QR detected - validating...';
    resultBox.className = 'alert alert-info mb-0';
    resultBox.textContent = 'Validating QR...';

    var fd = new FormData();
    fd.append('action', 'scan');
    fd.append('token', token);
    fd.append('_csrf', window.VMS_CSRF);

    fetch(window.VMS_SCAN_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        setResult(!!data.ok, data.message || 'Done', data);
        if (data.type === 'resident') {
          scanStatus.textContent = data.ok
            ? 'Resident identified. Ready for next scan...'
            : 'Resident QR issue. Ready for next scan...';
        } else {
          scanStatus.textContent = data.ok
            ? 'Visitor check-in OK. Ready for next scan...'
            : 'Rejected. Ready for next scan...';
        }
      })
      .catch(function () {
        setResult(false, 'Network or server error.', null);
        scanStatus.textContent = 'Ready for next scan...';
      });
  }

  function stopCamera() {
    scanning = false;
    if (rafId) {
      cancelAnimationFrame(rafId);
      rafId = null;
    }
    if (stream) {
      stream.getTracks().forEach(function (t) { t.stop(); });
      stream = null;
    }
    video.srcObject = null;
    if (btnStart) btnStart.disabled = false;
    if (btnStop) btnStop.disabled = true;
    scanStatus.textContent = 'Camera stopped. Click Start Camera to resume.';
  }

  function tick() {
    if (!scanning) return;
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      if (typeof jsQR !== 'undefined') {
        var code = jsQR(imageData.data, imageData.width, imageData.height, {
          inversionAttempts: 'attemptBoth'
        });
        if (code && code.data) {
          submitToken(String(code.data).trim());
        }
      }
    }
    rafId = requestAnimationFrame(tick);
  }

  async function listCameras() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;
    var devices = await navigator.mediaDevices.enumerateDevices();
    var cams = devices.filter(function (d) { return d.kind === 'videoinput'; });
    var current = cameraSelect.value;
    cameraSelect.innerHTML = '';
    cams.forEach(function (cam, i) {
      var opt = document.createElement('option');
      opt.value = cam.deviceId;
      opt.textContent = cam.label || ('Camera ' + (i + 1));
      cameraSelect.appendChild(opt);
    });
    if (current) cameraSelect.value = current;
    if (!cams.length) {
      scanStatus.textContent = 'No cameras detected. Check Windows camera privacy settings.';
    }
  }

  async function getStream() {
    if (cameraSelect.value) {
      return navigator.mediaDevices.getUserMedia({
        audio: false,
        video: {
          deviceId: { ideal: cameraSelect.value },
          width: { ideal: 1280 },
          height: { ideal: 720 }
        }
      });
    }
    try {
      return await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: { width: { ideal: 1280 }, height: { ideal: 720 } }
      });
    } catch (e1) {
      return navigator.mediaDevices.getUserMedia({ audio: false, video: true });
    }
  }

  async function startCamera() {
    if (starting) return;
    starting = true;

    if (!isSecureCameraContext()) {
      if (secureWarning) secureWarning.classList.remove('d-none');
      scanStatus.textContent = 'Camera blocked: open this page via http://localhost/... (not LAN IP).';
      starting = false;
      return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      scanStatus.textContent = 'Camera API not supported in this browser. Use Chrome or Edge.';
      starting = false;
      return;
    }

    stopCamera();
    scanStatus.textContent = 'Requesting camera permission...';

    try {
      stream = await getStream();
      video.srcObject = stream;
      video.setAttribute('playsinline', 'true');
      video.muted = true;
      await video.play();
      scanning = true;
      if (btnStart) btnStart.disabled = true;
      if (btnStop) btnStop.disabled = false;
      scanStatus.textContent = 'Auto-scanning... point visitor or resident QR at the camera.';
      await listCameras();
      rafId = requestAnimationFrame(tick);
    } catch (err) {
      var msg = (err && err.name) ? err.name : 'Error';
      var detail = (err && err.message) ? err.message : 'permission denied';
      scanStatus.textContent = 'Camera failed (' + msg + '): ' + detail;
      if (btnStart) btnStart.disabled = false;
      if (btnStop) btnStop.disabled = true;
    } finally {
      starting = false;
    }
  }

  if (btnStart) btnStart.addEventListener('click', startCamera);
  if (btnStop) btnStop.addEventListener('click', stopCamera);
  if (cameraSelect) cameraSelect.addEventListener('change', startCamera);
  window.addEventListener('beforeunload', stopCamera);

  if (!isSecureCameraContext()) {
    if (secureWarning) secureWarning.classList.remove('d-none');
    scanStatus.textContent = 'Open scanner via localhost to enable camera.';
  } else {
    startCamera();
  }
})();