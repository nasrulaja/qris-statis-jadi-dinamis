// Notifikasi kecil di klien: cek pesanan/dana masuk baru tanpa reload.
// Sengaja dibuat sederhana (polling) supaya mudah dijelaskan saat ujian.
(function () {
  var badge = document.querySelector('.bell .dot');
  var hanyaBell = window.location.pathname.indexOf('/app/admin') === 0;
  if (!hanyaBell) return;

  var terakhir = null;

  function cek() {
    fetch('/app/admin/cek-baru.php', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) return;
        var n = Number(d.baru || 0);
        if (terakhir !== null && n > terakhir) {
          document.title = '(' + n + ') ' + document.title.replace(/^\(\d+\)\s*/, '');
        }
        terakhir = n;
        if (badge) {
          badge.textContent = n;
          badge.style.display = n > 0 ? 'grid' : 'none';
        }
      })
      .catch(function () { /* diamkan bila offline */ });
  }

  cek();
  setInterval(cek, 5000);
})();
