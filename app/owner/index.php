<?php

declare(strict_types=1);

// Panel owner: pengaturan toko + QRIS + laporan penjualan.
require __DIR__ . '/../layout.php';

session_start();
$u = wajibPeran('owner');

$pesan = '';
$galat = '';

$kunciBoleh = ['nama_toko', 'kota', 'alamat', 'kode_pos', 'catatan', 'pajak_persen', 'qris_statis', 'qris_statis_payload', 'api_key'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValid()) {
    if (($_POST['aksi'] ?? '') === 'simpan') {
        $nama = trim((string) ($_POST['nama_toko'] ?? ''));
        if ($nama === '') {
            $galat = 'Nama toko wajib diisi.';
        } elseif (mb_strlen($nama) > 60) {
            $galat = 'Nama toko maksimal 60 karakter.';
        } else {
            $pajak = (int) ($_POST['pajak_persen'] ?? 0);
            if ($pajak < 0 || $pajak > 50) {
                $galat = 'Pajak harus antara 0 dan 50 persen.';
            }

            // Validasi payload QRIS statis sebelum disimpan: harus QRIS sah & CRC cocok.
            $payload = trim((string) ($_POST['qris_statis_payload'] ?? ''));
            if ($galat === '' && $payload !== '') {
                try {
                    QrisDinamis\QrisConverter::validate($payload);
                } catch (QrisDinamis\QrisException $ex) {
                    $galat = 'Payload QRIS tidak valid: ' . $ex->getMessage();
                }
            }

            if ($galat === '') {
                foreach ($kunciBoleh as $k) {
                    $nilai = trim((string) ($_POST[$k] ?? ''));
                    setSetting($k, $nilai);
                }
                $pesan = 'Pengaturan toko disimpan.';
            }
        }
    }
}

$st = db()->prepare(
    "SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM pesanan WHERE status NOT IN ('batal','menunggu')"
);
$st->execute();
$semua = $st->fetch();

$st = db()->prepare(
    "SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM pesanan
     WHERE status NOT IN ('batal','menunggu') AND dibayar LIKE ?"
);
$st->execute([date('Y-m-d') . '%']);
$hariIni = $st->fetch();

$perHari = db()->query(
    "SELECT substr(dibayar,1,10) tgl, COUNT(*) n, SUM(total) t FROM pesanan
     WHERE status NOT IN ('batal','menunggu') AND dibayar IS NOT NULL
     GROUP BY substr(dibayar,1,10) ORDER BY tgl DESC LIMIT 14"
)->fetchAll();

$terlaris = db()->query(
    "SELECT i.nama_produk, SUM(i.jumlah) jml, SUM(i.harga*i.jumlah) omzet
     FROM item_pesanan i JOIN pesanan p ON p.id = i.pesanan_id
     WHERE p.status NOT IN ('batal','menunggu')
     GROUP BY i.nama_produk ORDER BY jml DESC LIMIT 8"
)->fetchAll();

require __DIR__ . '/../views/owner-panel.php';
