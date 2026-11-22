<?php
/** @var array $u @var string $pesan @var string $galat */
/** @var array $semua @var array $hariIni @var array $perHari @var array $terlaris */
kepala('Owner');
?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:18px">
  <div>
    <h1>Panel Owner</h1>
    <p class="muted">Pengaturan toko &amp; laporan penjualan</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn ghost mini" href="/app/owner/produk.php">Kelola produk</a>
    <a class="btn ghost mini" href="/app/owner/ulasan.php">Ulasan</a>
    <a class="btn ghost mini" href="/app/admin/">Buka dapur &rarr;</a>
  </div>
</div>

<?php if ($pesan !== ''): ?><div class="alert"><?= e($pesan) ?></div><?php endif; ?>
<?php if ($galat !== ''): ?><div class="alert bad"><?= e($galat) ?></div><?php endif; ?>

<div class="stats">
  <div class="stat"><div class="k">Total pesanan lunas</div><div class="v"><?= (int) $semua['n'] ?></div></div>
  <div class="stat"><div class="k">Total omzet</div><div class="v" style="color:var(--ok)"><?= rupiah((int) $semua['t']) ?></div></div>
  <div class="stat"><div class="k">Omzet hari ini</div><div class="v"><?= rupiah((int) $hariIni['t']) ?></div></div>
</div>

<div class="row">
  <div>
    <div class="card" style="margin-bottom:16px">
      <header><h2>Identitas Toko</h2></header>
      <div>
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="aksi" value="simpan">
          <div class="field">
            <label for="nama_toko">Nama toko</label>
            <input id="nama_toko" name="nama_toko" value="<?= e(setting('nama_toko')) ?>" required>
          </div>
          <div class="field">
            <label for="alamat">Alamat</label>
            <input id="alamat" name="alamat" value="<?= e(setting('alamat')) ?>">
          </div>
          <div class="row">
            <div class="field">
              <label for="kota">Kota</label>
              <input id="kota" name="kota" value="<?= e(setting('kota')) ?>">
            </div>
            <div class="field">
              <label for="kode_pos">Kode pos</label>
              <input id="kode_pos" name="kode_pos" value="<?= e(setting('kode_pos')) ?>">
            </div>
          </div>
          <div class="field">
            <label for="catatan">Ucapan di struk</label>
            <input id="catatan" name="catatan" value="<?= e(setting('catatan')) ?>">
          </div>
          <div class="field">
            <label for="pajak_persen">Pajak (%)</label>
            <input id="pajak_persen" name="pajak_persen" type="number" min="0" max="50"
                   value="<?= e(setting('pajak_persen', '0')) ?>">
          </div>
          <div class="field">
            <label for="qris_statis_payload">Isi teks QRIS statis</label>
            <textarea id="qris_statis_payload" name="qris_statis_payload" rows="4"
                      placeholder="00020101021126...6304ABCD"
                      style="width:100%;font-family:monospace;font-size:12px"><?= e(setting('qris_statis_payload')) ?></textarea>
            <small class="muted">
              Tempel isi teks QRIS statis Anda (diawali <code>000201...</code>).
              Sistem mengubahnya jadi QRIS <b>dinamis</b>: nominal pesanan terisi otomatis.
              Kosongkan bila belum ada.
            </small>
            <?php if (trim(setting('qris_statis_payload')) !== ''): ?>
              <small class="muted" style="color:var(--ok)">
                &#10003; Payload tersimpan dan valid (CRC cocok).
              </small>
            <?php endif; ?>
          </div>
          <div class="field">
            <label for="qris_statis">URL gambar QRIS toko (opsional)</label>
            <input id="qris_statis" name="qris_statis" placeholder="/app/assets/img/qris-statis.png"
                   value="<?= e(setting('qris_statis')) ?>">
            <small class="muted">Dipakai sebagai cadangan bila konversi dinamis gagal.</small>
          </div>
          <div class="field">
            <label for="api_key">API Key</label>
            <input id="api_key" name="api_key" placeholder="belum aktif"
                   value="<?= e(setting('api_key')) ?>">
            <small class="muted">
              Kunci untuk mengakses API (<code>/app/api.php</code>). Kirim sebagai header
              <code>X-API-Key</code>. Kosongkan untuk mematikan API.
            </small>
            <?php if (trim(setting('api_key')) !== ''): ?>
              <small class="muted" style="color:var(--ok)">
                &#10003; API aktif &mdash; lihat dokumentasi di <code>API.md</code>
              </small>
            <?php endif; ?>
          </div>
          <button class="btn block" type="submit">Simpan pengaturan</button>
        </form>
      </div>
    </div>
  </div>

  <div>
    <div class="card" style="margin-bottom:16px">
      <header><h2>Omzet per Hari</h2></header>
      <div>
        <?php if (!$perHari): ?>
          <div class="empty">Belum ada transaksi lunas.</div>
        <?php else: ?>
          <table>
            <thead><tr><th>Tanggal</th><th class="num">Pesanan</th><th class="num">Omzet</th></tr></thead>
            <tbody>
              <?php foreach ($perHari as $r): ?>
                <tr>
                  <td><?= e((string) $r['tgl']) ?></td>
                  <td class="num"><?= (int) $r['n'] ?></td>
                  <td class="num"><?= rupiah((int) $r['t']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <header><h2>Menu Terlaris</h2></header>
      <div>
        <?php if (!$terlaris): ?>
          <div class="empty">Belum ada penjualan.</div>
        <?php else: ?>
          <table>
            <thead><tr><th>Produk</th><th class="num">Terjual</th><th class="num">Omzet</th></tr></thead>
            <tbody>
              <?php foreach ($terlaris as $r): ?>
                <tr>
                  <td><?= e($r['nama_produk']) ?></td>
                  <td class="num"><?= (int) $r['jml'] ?></td>
                  <td class="num"><?= rupiah((int) $r['omzet']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php kaki(); ?>
