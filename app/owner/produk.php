<?php

declare(strict_types=1);

// Owner: kelola produk (tambah / ubah / nyalakan-matikan / hapus).
require __DIR__ . '/../layout.php';

session_start();
$u = wajibPeran('owner');

$pesan = '';
$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValid()) {
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'tambah' || $aksi === 'ubah') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $harga = (int) ($_POST['harga'] ?? 0);
        $desc = trim((string) ($_POST['deskripsi'] ?? ''));
        $kat = (int) ($_POST['kategori_id'] ?? 0);

        if ($nama === '') {
            $galat = 'Nama produk wajib diisi.';
        } elseif ($harga < 1 || $harga > 100000000) {
            $galat = 'Harga harus antara 1 dan 100 juta.';
        } else {
            if ($aksi === 'tambah') {
                $st = db()->prepare(
                    'INSERT INTO produk (nama, deskripsi, harga, kategori_id, aktif, dibuat) VALUES (?,?,?,?,1,?)'
                );
                $st->execute([$nama, $desc, $harga, $kat ?: null, date('Y-m-d H:i:s')]);
                $pesan = 'Produk "' . $nama . '" ditambahkan.';
            } else {
                $id = (int) ($_POST['id'] ?? 0);
                $st = db()->prepare(
                    'UPDATE produk SET nama = ?, deskripsi = ?, harga = ?, kategori_id = ? WHERE id = ?'
                );
                $st->execute([$nama, $desc, $harga, $kat ?: null, $id]);
                $pesan = 'Produk "' . $nama . '" diperbarui.';
            }
        }
    } elseif ($aksi === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('UPDATE produk SET aktif = 1 - aktif WHERE id = ?')->execute([$id]);
        $pesan = 'Status produk diubah.';
    } elseif ($aksi === 'hapus') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM produk WHERE id = ?')->execute([$id]);
        $pesan = 'Produk dihapus.';
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = db()->prepare('SELECT * FROM produk WHERE id = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch() ?: null;
}

$kategori = db()->query('SELECT * FROM kategori ORDER BY urutan, id')->fetchAll();
$produk = db()->query(
    'SELECT p.*, k.nama AS kategori FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id
     ORDER BY p.aktif DESC, k.urutan, p.nama'
)->fetchAll();

require __DIR__ . '/../views/owner-produk.php';
