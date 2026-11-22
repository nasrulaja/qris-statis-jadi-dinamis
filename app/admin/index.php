<?php

declare(strict_types=1);

// Panel admin/kasir: terima pesanan, konfirmasi dana masuk, ubah status.
require __DIR__ . '/../layout.php';

session_start();
$u = wajibPeran('admin', 'owner');

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValid()) {
    $aksi = (string) ($_POST['aksi'] ?? '');
    $kode = (string) ($_POST['kode'] ?? '');

    $st = db()->prepare('SELECT * FROM pesanan WHERE kode = ?');
    $st->execute([$kode]);
    $o = $st->fetch();

    if (!$o) {
        $pesan = 'Pesanan tidak ditemukan.';
    } elseif ($aksi === 'konfirmasi_bayar') {
        $h = konfirmasiBayar($kode);
        $pesan = $h['pesan'];
    } elseif ($aksi === 'ubah_status') {
        $h = ubahStatusPesanan($kode, (string) ($_POST['status'] ?? ''));
        $pesan = $h['pesan'];
    } elseif ($aksi === 'batal') {
        $h = ubahStatusPesanan($kode, 'batal');
        $pesan = $h['pesan'];
    }
}

$filter = (string) ($_GET['status'] ?? 'aktif');
$sql = 'SELECT * FROM pesanan';
$param = [];
if ($filter === 'aktif') {
    $sql .= " WHERE status IN ('menunggu','dibayar','diproses','siap')";
} elseif ($filter !== 'semua') {
    $sql .= ' WHERE status = ?';
    $param[] = $filter;
}
$sql .= ' ORDER BY id DESC LIMIT 80';
$st = db()->prepare($sql);
$st->execute($param);
$daftar = $st->fetchAll();

$st = db()->prepare(
    "SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM pesanan WHERE status != 'batal' AND dibayar LIKE ?"
);
$st->execute([date('Y-m-d') . '%']);
$hariIni = $st->fetch();
$baru = hitungNotifikasiBaru();

require __DIR__ . '/../views/admin-panel.php';
