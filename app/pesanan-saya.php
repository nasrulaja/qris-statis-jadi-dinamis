<?php

declare(strict_types=1);

// Pembeli: daftar pesanan miliknya sendiri (atau semua bila tamu pakai kode).
require __DIR__ . '/layout.php';

session_start();
$u = penggunaSekarang();

if ($u) {
    $st = db()->prepare('SELECT * FROM pesanan WHERE pengguna_id = ? ORDER BY id DESC LIMIT 40');
    $st->execute([(int) $u['id']]);
} else {
    $st = db()->query('SELECT * FROM pesanan ORDER BY id DESC LIMIT 40');
}
$daftar = $st->fetchAll();

kepala('Pesanan Saya');
?>
<h1>Pesanan Saya</h1>
<p class="muted" style="margin-bottom:20px">
  <?= $u ? 'Pesanan atas nama ' . e($u['nama']) : 'Anda belum masuk — menampilkan semua pesanan demo.' ?>
</p>

<div class="card">
  <header><h2>Riwayat</h2></header>
  <div>
    <?php if (!$daftar): ?>
      <div class="empty">Belum ada pesanan. <a href="/app/" style="color:var(--accent)">Pesan sekarang</a>.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>Kode</th><th class="num">Total</th><th>Status</th><th>Waktu</th><th class="right"></th></tr></thead>
        <tbody>
        <?php foreach ($daftar as $o): ?>
          <tr>
            <td><code><?= e($o['kode']) ?></code><br><small class="muted"><?= e($o['nama_pemesan']) ?></small></td>
            <td class="num"><?= rupiah((int) $o['total']) ?></td>
            <td><?= badgeStatus($o['status']) ?></td>
            <td class="muted" style="font-size:12px"><?= e((string) $o['dibuat']) ?></td>
            <td class="right" style="white-space:nowrap">
              <a class="btn ghost mini" href="/app/bayar.php?kode=<?= urlencode($o['kode']) ?>">Detail</a>
              <?php if (!in_array($o['status'], ['menunggu', 'batal'], true)): ?>
                <a class="btn ghost mini" href="/app/struk.php?kode=<?= urlencode($o['kode']) ?>">Struk</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php kaki(); ?>
