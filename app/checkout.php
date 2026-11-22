<?php

declare(strict_types=1);

// Checkout: dari keranjang -> pesanan tersimpan + notifikasi ke admin.
require __DIR__ . '/layout.php';

session_start();

$kart = $_SESSION['kart'] ?? [];
if (!$kart) {
    header('Location: /app/');
    exit;
}

$u = penggunaSekarang();
$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        $galat = 'Sesi kedaluwarsa, coba lagi.';
    } else {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        if ($nama === '' && $u) {
            $nama = (string) $u['nama'];
        }
        $tipe = (string) ($_POST['tipe'] ?? 'dine-in');
        $catatan = trim((string) ($_POST['catatan'] ?? ''));

        if ($nama === '') {
            $galat = 'Nama pemesan wajib diisi.';
        } elseif (!in_array($tipe, ['dine-in', 'take-away'], true)) {
            $galat = 'Tipe pesanan tidak dikenal.';
        } elseif ($kart === []) {
            $galat = 'Keranjang masih kosong.';
        } else {
            try {
                $items = [];
                foreach ($kart as $it) {
                    $items[] = ['nama' => $it['nama'], 'harga' => $it['harga'], 'jumlah' => $it['jml']];
                }
                $hasil = buatPesanan($items, [
                    'nama_pemesan' => $nama,
                    'tipe' => $tipe,
                    'catatan' => $catatan,
                    'pengguna_id' => $u['id'] ?? null,
                ]);
                // Kosongkan keranjang lalu ke halaman bayar.
                $_SESSION['kart'] = [];
                header('Location: /app/bayar.php?kode=' . urlencode($hasil['kode']));
                exit;
            } catch (Throwable $e) {
                $galat = 'Gagal menyimpan pesanan: ' . $e->getMessage();
            }
        }
    }
}

$totalKart = 0;
foreach ($kart as $it) {
    $totalKart += $it['harga'] * $it['jml'];
}

kepala('Checkout');
?>
<h1>Checkout</h1>
<p class="muted" style="margin-bottom:20px">Periksa pesanan, lalu lanjut ke pembayaran.</p>

<?php if ($galat !== ''): ?>
  <div class="alert bad"><?= e($galat) ?></div>
<?php endif; ?>

<div class="row">
  <div>
    <div class="card">
      <header><h2>Detail Pemesan</h2></header>
      <div>
        <form method="post">
          <?= csrfField() ?>
          <div class="field">
            <label for="nama">Nama pemesan</label>
            <input id="nama" name="nama" value="<?= e($u['nama'] ?? '') ?>" required>
          </div>
          <div class="row">
            <div class="field">
              <label for="tipe">Tipe</label>
              <select id="tipe" name="tipe">
                <option value="dine-in">Makan di tempat</option>
                <option value="take-away">Bungkus</option>
              </select>
            </div>
            <div class="field">
              <label for="catatan">Catatan (opsional)</label>
              <input id="catatan" name="catatan" placeholder="mis. tanpa gula">
            </div>
          </div>
          <button class="btn block" type="submit">Buat pesanan &amp; bayar</button>
        </form>
      </div>
    </div>
  </div>

  <div>
    <div class="card">
      <header><h2>Rincian</h2></header>
      <div>
        <table>
          <?php foreach ($kart as $it): ?>
            <tr>
              <td><?= e($it['nama']) ?> &times;<?= (int) $it['jml'] ?></td>
              <td class="num"><?= rupiah($it['harga'] * $it['jml']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
        <div class="total-baris">
          <span class="lbl">Total</span>
          <span class="val"><?= rupiah($totalKart) ?></span>
        </div>
        <a href="/app/" style="color:var(--muted);font-size:13px">&larr; Ubah pesanan</a>
      </div>
    </div>
  </div>
</div>

<?php kaki(); ?>
