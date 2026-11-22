<?php

declare(strict_types=1);

// Halaman publik: menu kafe + keranjang (disimpan di session).
require __DIR__ . '/layout.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValid()) {
    $aksi = (string) ($_POST['aksi'] ?? '');
    $pid = (int) ($_POST['produk_id'] ?? 0);
    $jml = max(1, min(99, (int) ($_POST['jumlah'] ?? 1)));

    if ($aksi === 'tambah' && $pid > 0) {
        $st = db()->prepare('SELECT id, nama, harga FROM produk WHERE id = ? AND aktif = 1');
        $st->execute([$pid]);
        if ($p = $st->fetch()) {
            $k = (string) $p['id'];
            $_SESSION['kart'] ??= [];
            $lama = $_SESSION['kart'][$k]['jml'] ?? 0;
            $_SESSION['kart'][$k] = [
                'nama' => $p['nama'],
                'harga' => (int) $p['harga'],
                'jml' => min(99, $lama + $jml),
            ];
        }
    } elseif ($aksi === 'hapus' && $pid > 0) {
        unset($_SESSION['kart'][(string) $pid]);
    } elseif ($aksi === 'kosongkan') {
        $_SESSION['kart'] = [];
    }
    header('Location: /app/');
    exit;
}

$kart = $_SESSION['kart'] ?? [];
$totalKart = 0;
$jumlahKart = 0;
foreach ($kart as $it) {
    $totalKart += $it['harga'] * $it['jml'];
    $jumlahKart += $it['jml'];
}

$katAktif = (string) ($_GET['kategori'] ?? '');
$kategori = db()->query('SELECT * FROM kategori ORDER BY urutan, id')->fetchAll();

$sql = 'SELECT p.*, k.nama AS kategori FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id
        WHERE p.aktif = 1';
$param = [];
if ($katAktif !== '') {
    $sql .= ' AND p.kategori_id = ?';
    $param[] = (int) $katAktif;
}
$sql .= ' ORDER BY k.urutan, p.nama';
$st = db()->prepare($sql);
$st->execute($param);
$produk = $st->fetchAll();

$rating = ringkasanRating();

kepala('Menu');
?>

<div class="hero">
  <h1><?= e(setting('nama_toko')) ?></h1>
  <p>Pesan langsung dari meja — kopi, camilan, dan makanan siap diantar.</p>
</div>

<div class="kategori">
  <a href="/app/" class="<?= $katAktif === '' ? 'on' : '' ?>">Semua</a>
  <?php foreach ($kategori as $k): ?>
    <a href="/app/?kategori=<?= (int) $k['id'] ?>" class="<?= $katAktif === (string) $k['id'] ? 'on' : '' ?>">
      <?= e($k['nama']) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="row">
  <div>
    <?php if (!$produk): ?>
      <div class="empty">Belum ada menu di kategori ini.</div>
    <?php else: ?>
      <div class="grid-produk">
        <?php foreach ($produk as $p): ?>
          <div class="produk">
            <div class="nama">
              <a href="/app/produk.php?id=<?= (int) $p['id'] ?>"><?= e($p['nama']) ?></a>
            </div>
            <?php $r = $rating[(int) $p['id']] ?? null; ?>
            <div class="rating-kecil">
              <?php if ($r): ?>
                <span class="b"><?= str_repeat('★', (int) round($r['rata'])) ?></span>
                <?= number_format($r['rata'], 1, ',', '.') ?> (<?= $r['jml'] ?>)
              <?php else: ?>
                <span style="color:#4a5265">belum ada ulasan</span>
              <?php endif; ?>
            </div>
            <div class="desc"><?= e($p['deskripsi']) ?></div>
            <div class="harga"><?= rupiah((int) $p['harga']) ?></div>
            <form method="post">
              <?= csrfField() ?>
              <input type="hidden" name="aksi" value="tambah">
              <input type="hidden" name="produk_id" value="<?= (int) $p['id'] ?>">
              <input name="jumlah" type="number" min="1" max="99" value="1" aria-label="Jumlah">
              <button class="btn mini" type="submit" style="flex:1">Tambah</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card keranjang">
      <header><h2>Keranjang<?= $jumlahKart ? ' (' . $jumlahKart . ')' : '' ?></h2></header>
      <div>
        <?php if (!$kart): ?>
          <div class="empty">Keranjang masih kosong. Pilih menu di sebelah kiri.</div>
        <?php else: ?>
          <?php foreach ($kart as $id => $it): ?>
            <div class="item-kart">
              <div>
                <div class="nm"><?= e($it['nama']) ?> &times;<?= (int) $it['jml'] ?></div>
                <div class="hr"><?= rupiah($it['harga'] * $it['jml']) ?></div>
              </div>
              <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="produk_id" value="<?= (int) $id ?>">
                <button class="kart-hapus" type="submit">Hapus</button>
              </form>
            </div>
          <?php endforeach; ?>

          <div class="total-baris">
            <span class="lbl">Total</span>
            <span class="val"><?= rupiah($totalKart) ?></span>
          </div>

          <a class="btn block" href="/app/checkout.php">Lanjut ke pembayaran</a>
          <form method="post" style="margin-top:8px">
            <?= csrfField() ?>
            <input type="hidden" name="aksi" value="kosongkan">
            <button class="btn ghost mini block" type="submit">Kosongkan</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php kaki(); ?>
