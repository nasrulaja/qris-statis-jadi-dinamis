<?php

declare(strict_types=1);

// Struk siap cetak (lebar kertas thermal 58/80mm).
require __DIR__ . '/layout.php';

session_start();

$kode = (string) ($_GET['kode'] ?? '');
$st = db()->prepare('SELECT * FROM pesanan WHERE kode = ?');
$st->execute([$kode]);
$o = $st->fetch();

$bolehLihat = false;
if ($o) {
    $u = penggunaSekarang();
    $st = db()->prepare('SELECT 1 FROM item_pesanan WHERE pesanan_id = ? LIMIT 1');
    $st->execute([(int) $o['id']]);
    $adaItem = (bool) $st->fetchColumn();
    $bolehLihat = $adaItem && (
        ($u && in_array($u['peran'], ['admin', 'owner'], true))
        || ($u && (int) $o['pengguna_id'] === (int) $u['id'])
    );
}

if (!$o || !$bolehLihat) {
    kepala('Struk');
    echo '<div class="alert bad">Struk tidak ditemukan, atau Anda tidak berhak melihatnya.</div>';
    echo '<a class="btn ghost" href="/app/">Kembali</a>';
    kaki();
    exit;
}

$st = db()->prepare('SELECT * FROM item_pesanan WHERE pesanan_id = ? ORDER BY id');
$st->execute([(int) $o['id']]);
$items = $st->fetchAll();

$toko = setting('nama_toko');
$pajakPersen = (int) setting('pajak_persen', '0');
$subtotal = (int) $o['total'];
$pajak = (int) round($subtotal * $pajakPersen / 100);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Struk <?= e($o['kode']) ?> — <?= e($toko) ?></title>
<style>
  @page { size: 80mm auto; margin: 4mm; }
  * { box-sizing: border-box; }
  body {
    font-family: ui-monospace, "Courier New", monospace; font-size: 12px;
    color: #000; background: #fff; margin: 0; padding: 16px;
    display: flex; flex-direction: column; align-items: center;
  }
  .struk { width: 72mm; }
  .c { text-align: center; }
  .toko { font-size: 15px; font-weight: 700; letter-spacing: .5px; }
  .kecil { font-size: 10.5px; line-height: 1.5; }
  hr { border: 0; border-top: 1px dashed #000; margin: 7px 0; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 1.5px 0; vertical-align: top; font-size: 11.5px; }
  .r { text-align: right; }
  .total { font-size: 14px; font-weight: 700; }
  .catatan { margin-top: 8px; font-size: 10.5px; text-align: center; font-style: italic; }
  .aksi { margin-top: 18px; text-align: center; }
  .aksi button, .aksi a {
    font-family: system-ui, sans-serif; font-size: 13px; padding: 8px 16px;
    border-radius: 7px; border: 1px solid #999; background: #f0f0f0;
    cursor: pointer; text-decoration: none; color: #000; margin: 0 3px; display: inline-block;
  }
  @media print { .aksi { display: none; } body { padding: 0; } }
</style>
</head>
<body>
<div class="struk">
  <div class="c">
    <div class="toko"><?= e($toko) ?></div>
    <div class="kecil">
      <?= e(setting('alamat')) ?><br>
      <?= e(setting('kota')) ?> <?= e(setting('kode_pos')) ?>
    </div>
  </div>

  <hr>

  <table class="kecil">
    <tr><td>No</td><td class="r"><?= e($o['kode']) ?></td></tr>
    <tr><td>Tanggal</td><td class="r"><?= e((string) ($o['dibayar'] ?: $o['dibuat'])) ?></td></tr>
    <tr><td>Pemesan</td><td class="r"><?= e($o['nama_pemesan']) ?></td></tr>
    <tr><td>Tipe</td><td class="r"><?= e($o['tipe']) ?></td></tr>
    <tr><td>Status</td><td class="r"><?= $o['status'] === 'menunggu' ? 'BELUM BAYAR' : strtoupper(e($o['status'])) ?></td></tr>
  </table>

  <hr>

  <table>
    <?php foreach ($items as $it): ?>
      <tr>
        <td colspan="2"><?= e($it['nama_produk']) ?></td>
      </tr>
      <tr>
        <td style="padding-left:8px;color:#333">
          <?= (int) $it['jumlah'] ?> x <?= number_format((int) $it['harga'], 0, ',', '.') ?>
        </td>
        <td class="r"><?= number_format((int) $it['harga'] * (int) $it['jumlah'], 0, ',', '.') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <hr>

  <table>
    <tr>
      <td>Subtotal</td>
      <td class="r"><?= number_format($subtotal, 0, ',', '.') ?></td>
    </tr>
    <?php if ($pajak > 0): ?>
      <tr>
        <td>Pajak <?= $pajakPersen ?>%</td>
        <td class="r"><?= number_format($pajak, 0, ',', '.') ?></td>
      </tr>
    <?php endif; ?>
    <tr class="total">
      <td>TOTAL</td>
      <td class="r">Rp<?= number_format($subtotal + $pajak, 0, ',', '.') ?></td>
    </tr>
  </table>

  <?php if ($o['catatan'] !== ''): ?>
    <hr>
    <div class="kecil">Catatan: <?= e($o['catatan']) ?></div>
  <?php endif; ?>

  <div class="catatan"><?= e(setting('catatan')) ?></div>
  <div class="c kecil" style="margin-top:6px">— terima kasih —</div>
</div>

<div class="aksi">
  <button onclick="window.print()">Cetak</button>
  <a href="/app/pesanan-saya.php">Kembali</a>
</div>
</body>
</html>
