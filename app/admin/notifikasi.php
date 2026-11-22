<?php

declare(strict_types=1);

// Notifikasi untuk admin/owner: pesanan baru & dana masuk.
require __DIR__ . '/../layout.php';

session_start();
$u = wajibPeran('admin', 'owner');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValid()) {
    if (($_POST['aksi'] ?? '') === 'baca_semua') {
        db()->exec('UPDATE notifikasi SET dibaca = 1');
        header('Location: /app/admin/notifikasi.php');
        exit;
    }
}

$daftar = db()->query('SELECT * FROM notifikasi ORDER BY id DESC LIMIT 100')->fetchAll();
$baru = hitungNotifikasiBaru();

kepala('Notifikasi');
?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:18px">
  <div>
    <h1>Notifikasi</h1>
    <p class="muted"><?= $baru ?> belum dibaca</p>
  </div>
  <?php if ($baru > 0): ?>
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="aksi" value="baca_semua">
      <button class="btn ghost mini" type="submit">Tandai semua dibaca</button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <header><h2>Riwayat Notifikasi</h2></header>
  <div style="padding:0">
    <?php if (!$daftar): ?>
      <div class="empty" style="padding:16px 18px">Belum ada notifikasi.</div>
    <?php else: ?>
      <?php foreach ($daftar as $n): ?>
        <div class="notif-item <?= $n['dibaca'] ? '' : 'baru' ?>">
          <div class="ikon"><?= $n['jenis'] === 'dana_masuk' ? '&#128176;' : '&#128221;' ?></div>
          <div style="flex:1">
            <div class="jd"><?= e($n['judul']) ?></div>
            <div class="is"><?= e($n['isi']) ?></div>
            <div class="wk"><?= e((string) $n['waktu']) ?></div>
          </div>
          <?php if ($n['kode'] !== ''): ?>
            <a href="/app/bayar.php?kode=<?= urlencode($n['kode']) ?>" style="color:var(--accent);font-size:12px;white-space:nowrap">Lihat</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php kaki(); ?>
