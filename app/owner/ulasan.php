<?php

declare(strict_types=1);

// Owner: pantau semua ulasan & balas komentar pembeli.
require __DIR__ . '/../layout.php';

session_start();
$u = wajibPeran('owner');

$pesan = '';
$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValid()) {
    $aksi = (string) ($_POST['aksi'] ?? '');
    $uid = (int) ($_POST['id'] ?? 0);

    if ($aksi === 'balas') {
        $teks = trim((string) ($_POST['balasan'] ?? ''));
        if (mb_strlen($teks) > 600) {
            $galat = 'Balasan maksimal 600 karakter.';
        } else {
            $st = db()->prepare('UPDATE ulasan SET balasan = ? WHERE id = ?');
            $st->execute([$teks, $uid]);
            $pesan = $teks === '' ? 'Balasan dihapus.' : 'Balasan disimpan.';
        }
    } elseif ($aksi === 'hapus') {
        db()->prepare('DELETE FROM ulasan WHERE id = ?')->execute([$uid]);
        $pesan = 'Ulasan dihapus.';
    }
}

$daftar = db()->query(
    'SELECT u.*, p.nama AS produk FROM ulasan u
     JOIN produk p ON p.id = u.produk_id
     ORDER BY u.id DESC LIMIT 100'
)->fetchAll();

$rata = db()->query('SELECT AVG(bintang) r, COUNT(*) n FROM ulasan')->fetch();

require __DIR__ . '/../views/owner-ulasan.php';
