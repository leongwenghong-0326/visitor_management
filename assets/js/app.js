ocument.addEventListener('DOMContentLoaded', function () {
  // Auto-dismiss alerts after 6s
  document.querySelectorAll('.alert-dismissible').forEach(function (el) {
    setTimeout(function () {
      try {
        bootstrap.Alert.getOrCreateInstance(el).close();
      } catch (e) { /* ignore */ }
    }, 6000);
  });
});

function copyToClipboard(text, btn) {
  if (!navigator.clipboard) {
    var ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
  } else {
    navigator.clipboard.writeText(text);
  }
  if (btn) {
    var original = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
    setTimeout(function () { btn.innerHTML = original; }, 1500);
  }
}
