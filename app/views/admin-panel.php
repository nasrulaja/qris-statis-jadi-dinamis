<?php
/** @var array $u @var string $pesan @var array $daftar @var array $hariIni @var int $baru @var string $filter */
kepala('Dapur');
?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:18px">
  <div>
    <h1>Dapur &amp; Kasir</h1>
    <p class="muted">Masuk sebagai <?= e($u['nama']) ?></p>
  </div>
  <a class="btn blue mini" href="/app/admin/notifikasi.php">Notifikasi<?= $baru ? ' (' . $baru . ')' : '' ?></a>
</div>

<?php if ($pesan !== ''): ?>
  <div class="alert"><?= e($pesan) ?></div>
<?php endif; ?>

<div class="stats">
  <div class="stat"><div class="k">Dibayar hari ini</div><div class="v"><?= (int) $hariIni['n'] ?></div></div>
  <div class="stat"><div class="k">Dana masuk hari ini</div><div class="v" style="color:var(--ok)"><?= rupiah((int) $hariIni['t']) ?></div></div>
  <div class="stat"><div class="k">Notifikasi baru</div><div class="v" style="color:<?= $baru ? 'var(--warn)' : 'var(--muted)' ?>"><?= $baru ?></div></div>
</div>

<div class="kategori">
  <?php foreach (['aktif' => 'Aktif', 'menunggu' => 'Belum bayar', 'dibayar' => 'Sudah bayar', 'diproses' => 'Diproses', 'siap' => 'Siap', 'selesai' => 'Selesai', 'semua' => 'Semua'] as $k => $v): ?>
    <a href="/app/admin/?status=<?= $k ?>" class="<?= $filter === $k ? 'on' : '' ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <header><h2>Daftar Pesanan</h2></header>
  <div>
    <?php if (!$daftar): ?>
      <div class="empty">Tidak ada pesanan pada filter ini.</div>
    <?php else: ?>
      <table>
        <thead><tr>
          <th>Kode</th><th>Pemesan</th><th class="num">Total</th><th>Status</th><th>Jam</th><th class="right">Aksi</th>
        </tr></thead>
        <tbody>
        <?php foreach ($daftar as $o): ?>
          <tr>
            <td><a href="/app/bayar.php?kode=<?= urlencode($o['kode']) ?>" style="color:var(--accent)"><code><?= e($o['kode']) ?></code></a></td>
            <td><?= e($o['nama_pemesan']) ?><br><small class="muted"><?= e($o['tipe']) ?></small></td>
            <td class="num"><?= rupiah((int) $o['total']) ?></td>
            <td><?= badgeStatus($o['status']) ?></td>
            <td class="muted" style="font-size:12px"><?= e(substr((string) $o['dibuat'], 11, 5)) ?></td>
            <td class="right" style="white-space:nowrap">
              <a class="btn ghost mini" href="/app/struk.php?kode=<?= urlencode($o['kode']) ?>">Struk</a>
              <form method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="kode" value="<?= e($o['kode']) ?>">
                <?php if ($o['status'] === 'menunggu'): ?>
                  <input type="hidden" name="aksi" value="konfirmasi_bayar">
                  <button class="btn mini" type="submit">Konfirmasi dana masuk</button>
                <?php elseif ($o['status'] === 'dibayar'): ?>
                  <input type="hidden" name="aksi" value="ubah_status">
                  <input type="hidden" name="status" value="diproses">
                  <button class="btn blue mini" type="submit">Proses</button>
                <?php elseif ($o['status'] === 'diproses'): ?>
                  <input type="hidden" name="aksi" value="ubah_status">
                  <input type="hidden" name="status" value="siap">
                  <button class="btn blue mini" type="submit">Siap</button>
                <?php elseif ($o['status'] === 'siap'): ?>
                  <input type="hidden" name="aksi" value="ubah_status">
                  <input type="hidden" name="status" value="selesai">
                  <button class="btn ghost mini" type="submit">Selesai</button>
                <?php endif; ?>
              </form>
              <?php if (in_array($o['status'], ['menunggu', 'dibayar'], true)): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Batalkan pesanan ini?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="kode" value="<?= e($o['kode']) ?>">
                  <input type="hidden" name="aksi" value="batal">
                  <button class="btn danger mini" type="submit">Batal</button>
                </form>
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
