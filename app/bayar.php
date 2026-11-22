<?php

declare(strict_types=1);

// Halaman bayar: QR simulasi + (opsional) gambar QRIS asli milik owner.
//
// PENTING: QR di halaman ini adalah DEMO. Isinya kode pesanan + nominal, dan
// pelunasan dicatat oleh sistem ini sendiri lewat tombol admin. Tidak ada
// dana yang benar-benar berpindah. QRIS asli (jika diunggah owner) hanya
// ditampilkan untuk dibayar manual — sistem tidak bisa memverifikasinya.
require __DIR__ . '/layout.php';

session_start();

$kode = (string) ($_GET['kode'] ?? $_POST['kode'] ?? '');

$st = db()->prepare('SELECT * FROM pesanan WHERE kode = ?');
$st->execute([$kode]);
$o = $st->fetch();

if (!$o) {
    kepala('Tidak ditemukan');
    echo '<div class="alert bad">Pesanan tidak ditemukan: ' . e($kode) . '</div>';
    echo '<a class="btn ghost" href="/app/">Kembali ke menu</a>';
    kaki();
    exit;
}

$st = db()->prepare('SELECT * FROM item_pesanan WHERE pesanan_id = ?');
$st->execute([(int) $o['id']]);
$items = $st->fetchAll();

// Isi QR demo.
$isiQr = sprintf('DEMO-PAY|%s|%s|%d', $o['kode'], setting('nama_toko'), (int) $o['total']);
$png = '';
if (function_exists('imagecreatetruecolor')) {
    try {
        $png = 'data:image/png;base64,' . base64_encode(
            QrisDinamis\QrCode\PngRenderer::fromText($isiQr, 'M', 7)
        );
    } catch (Throwable $e) {
        $png = '';
    }
}

$lunas = $o['status'] !== 'menunggu';
$qrisAsli = trim(setting('qris_statis'));

// QRIS dinamis dengan nominal pesanan ini (logika bersama di app/pesanan.php).
// Cukup butuh payload statis; URL gambar hanya cadangan bila konversi gagal.
$nominalQris = (int) $o['total'];
$qrisDinamis = '';
$qrisPayload = '';
$qrisGalat = '';

if (!$lunas && trim(setting('qris_statis_payload')) !== '') {
    $q = qrisUntukNominal($nominalQris);
    $qrisPayload = $q['payload'];
    $qrisDinamis = $q['png'];
    $qrisGalat = $q['galat'];
}

kepala('Bayar ' . $o['kode']);
?>
<h1>Pembayaran</h1>
<p class="muted" style="margin-bottom:20px">Kode <code><?= e($o['kode']) ?></code></p>

<div class="row">
  <div>
    <div class="card">
      <header><h2>Rincian Pesanan</h2></header>
      <div>
        <table>
          <?php foreach ($items as $it): ?>
            <tr>
              <td><?= e($it['nama_produk']) ?> &times;<?= (int) $it['jumlah'] ?></td>
              <td class="num"><?= rupiah((int) $it['harga'] * (int) $it['jumlah']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
        <div class="total-baris">
          <span class="lbl">Total</span>
          <span class="val"><?= rupiah((int) $o['total']) ?></span>
        </div>
        <table style="font-size:13px">
          <tr><td class="muted">Pemesan</td><td class="right"><?= e($o['nama_pemesan']) ?></td></tr>
          <tr><td class="muted">Tipe</td><td class="right"><?= e($o['tipe']) ?></td></tr>
          <?php if ($o['catatan'] !== ''): ?>
            <tr><td class="muted">Catatan</td><td class="right"><?= e($o['catatan']) ?></td></tr>
          <?php endif; ?>
          <tr><td class="muted">Status</td><td class="right"><?= badgeStatus($o['status']) ?></td></tr>
        </table>
      </div>
    </div>
  </div>

  <div>
    <div class="card">
      <header><h2><?= $lunas ? 'Pembayaran Diterima' : 'Scan untuk Bayar' ?></h2></header>
      <div class="qrbox">
        <?php if ($lunas): ?>
          <div class="alert">Lunas pada <?= e((string) $o['dibayar']) ?>. <?= e(setting('catatan')) ?></div>
        <?php endif; ?>

        <?php if ($qrisDinamis !== '' && !$lunas): ?>
          <div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--accent);margin-bottom:10px">
            QRIS Dinamis — Nominal Terisi Otomatis
          </div>
          <img src="<?= e($qrisDinamis) ?>" alt="QRIS dinamis Rp<?= number_format($nominalQris, 0, ',', '.') ?>" class="qris-asli">
          <div class="qrlabel" style="margin-top:10px">
            Nominal sudah terisi <b><?= rupiah($nominalQris) ?></b> — pembeli tinggal pindai
            dan konfirmasi, tidak perlu mengetik nominal.
          </div>
          <details style="margin-top:10px">
            <summary style="cursor:pointer;font-size:12px;color:var(--muted)">Lihat isi payload QRIS</summary>
            <div class="qrlabel" style="word-break:break-all;font-family:monospace;font-size:11px"><?= e($qrisPayload) ?></div>
          </details>
        <?php elseif ($qrisGalat !== '' && !$lunas): ?>
          <div class="alert bad">
            QRIS dinamis gagal dibuat: <?= e($qrisGalat) ?>
          </div>
          <?php if ($qrisAsli !== ''): ?>
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:16px 0 10px">
              QRIS Statis (cadangan — nominal diketik manual)
            </div>
            <img src="<?= e($qrisAsli) ?>" alt="QRIS statis toko" class="qris-asli">
            <div class="qrlabel">Masukkan nominal <b><?= rupiah($nominalQris) ?></b> secara manual.</div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($png !== '' && !$lunas): ?>
          <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border)">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:10px">
              QR Demo (simulasi)
            </div>
            <img src="<?= $png ?>" alt="QR pembayaran" width="150" height="150" style="opacity:.75">
            <div class="qrlabel"><?= e($isiQr) ?></div>
          </div>
        <?php elseif (!$lunas && $qrisAsli === ''): ?>
          <div class="alert bad">QR tidak bisa dibuat (ekstensi GD tidak aktif).</div>
        <?php endif; ?>

        <?php if (!$lunas): ?>
          <p class="qrlabel" style="margin-top:16px">
            <?php if ($qrisDinamis !== ''): ?>
              QRIS di atas adalah QRIS dinamis dengan nominal pesanan ini. Status pesanan
              tetap dikonfirmasi kasir setelah dana masuk.
            <?php else: ?>
              QR ini <b>bukan QRIS resmi</b>. Kasir akan mengonfirmasi pembayaran secara manual.
            <?php endif; ?>
          </p>
        <?php endif; ?>

        <p style="margin-top:16px"><a href="/app/pesanan-saya.php" style="color:var(--accent);font-size:13px">Lihat status pesanan &rarr;</a></p>
        <?php if ($lunas): ?>
          <p style="margin-top:8px"><a href="/app/struk.php?kode=<?= urlencode($o['kode']) ?>"
             style="color:var(--muted);font-size:13px">&#128438; Cetak struk</a></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php kaki(); ?>
