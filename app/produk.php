<?php

declare(strict_types=1);

// Detail produk: rating, komentar, dan form ulasan (hanya pembeli yang sudah pesan).
require __DIR__ . '/layout.php';

session_start();

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare(
    'SELECT p.*, k.nama AS kategori FROM produk p
     LEFT JOIN kategori k ON k.id = p.kategori_id WHERE p.id = ?'
);
$st->execute([$id]);
$p = $st->fetch();

if (!$p) {
    kepala('Tidak ditemukan');
    echo '<div class="alert bad">Produk tidak ditemukan.</div>';
    echo '<a class="btn ghost" href="/app/">Kembali ke menu</a>';
    kaki();
    exit;
}

$u = penggunaSekarang();
$pesan = '';
$galat = '';

// ------------------------------------------------------------ kirim ulasan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValid()) {
    if (($_POST['aksi'] ?? '') === 'ulas') {
        $bintangVal = (int) ($_POST['bintang'] ?? 0);
        $teks = trim((string) ($_POST['komentar'] ?? ''));

        if (!$u) {
            $galat = 'Masuk dulu untuk memberi ulasan.';
        } elseif ($bintangVal < 1 || $bintangVal > 5) {
            $galat = 'Pilih jumlah bintang 1 sampai 5.';
        } elseif (mb_strlen($teks) > 600) {
            $galat = 'Komentar maksimal 600 karakter.';
        } else {
            // Hanya pembeli yang sudah pernah membayar produk ini.
            $cek = db()->prepare(
                "SELECT 1 FROM item_pesanan i
                 JOIN pesanan ps ON ps.id = i.pesanan_id
                 WHERE ps.pengguna_id = ? AND ps.status NOT IN ('batal','menunggu')
                   AND i.nama_produk = ? LIMIT 1"
            );
            $cek->execute([(int) $u['id'], $p['nama']]);

            if (!$cek->fetchColumn()) {
                $galat = 'Ulasan hanya untuk produk yang sudah Anda pesan dan bayar.';
            } else {
                $cekDua = db()->prepare('SELECT 1 FROM ulasan WHERE produk_id = ? AND pengguna_id = ?');
                $cekDua->execute([$id, (int) $u['id']]);
                if ($cekDua->fetchColumn()) {
                    $galat = 'Anda sudah mengulas produk ini.';
                } else {
                    $st = db()->prepare(
                        'INSERT INTO ulasan (produk_id, pengguna_id, nama, bintang, komentar, balasan, dibuat)
                         VALUES (?,?,?,?,?,\'\',?)'
                    );
                    $st->execute([$id, (int) $u['id'], $u['nama'], $bintangVal, $teks, date('Y-m-d H:i:s')]);

                    tambahNotifikasi(
                        'ulasan',
                        'Ulasan baru: ' . $p['nama'],
                        $u['nama'] . ' memberi ' . $bintangVal . ' bintang. ' . mb_substr($teks, 0, 80),
                        ''
                    );
                    $pesan = 'Terima kasih! Ulasan Anda tersimpan.';
                }
            }
        }
    }
}

$st = db()->prepare('SELECT * FROM ulasan WHERE produk_id = ? ORDER BY id DESC LIMIT 50');
$st->execute([$id]);
$ulasan = $st->fetchAll();

$rata = 0.0;
$jml = count($ulasan);
if ($jml > 0) {
    $rata = array_sum(array_column($ulasan, 'bintang')) / $jml;
}

$sudahUlas = false;
$bolehUlas = false;
if ($u) {
    $c1 = db()->prepare('SELECT 1 FROM ulasan WHERE produk_id = ? AND pengguna_id = ?');
    $c1->execute([$id, (int) $u['id']]);
    $sudahUlas = (bool) $c1->fetchColumn();

    $c2 = db()->prepare(
        "SELECT 1 FROM item_pesanan i JOIN pesanan ps ON ps.id = i.pesanan_id
         WHERE ps.pengguna_id = ? AND ps.status NOT IN ('batal','menunggu')
           AND i.nama_produk = ? LIMIT 1"
    );
    $c2->execute([(int) $u['id'], $p['nama']]);
    $bolehUlas = (bool) $c2->fetchColumn();
}

require __DIR__ . '/views/produk-detail.php';
