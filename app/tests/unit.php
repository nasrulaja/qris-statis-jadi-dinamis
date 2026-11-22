<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

echo "Migrasi:\n";
foreach (['ulasan', 'percobaan_login'] as $t) {
    printf("  %-16s = %s\n", $t, db()->query('SELECT COUNT(*) FROM ' . $t)->fetchColumn());
}

echo "\nCek\n";
$hasil = [];

// rating helper
$hasil[] = ['ringkasanRating() array kosong', ringkasanRating() === []];
$hasil[] = ['bintang(3) ada 3 ★ penuh', substr_count(bintang(3), '★') === 5];
$hasil[] = ['sisaPercobaanLogin() = 5', sisaPercobaanLogin() === 5];

// catat gagal login lalu cek berkurang
catatGagalLogin('uji');
$hasil[] = ['setelah 1 gagal, sisa = 4', sisaPercobaanLogin() === 4];
bersihkanGagalLogin();
$hasil[] = ['setelah bersih, sisa = 5', sisaPercobaanLogin() === 5];

// ulasan: insert lalu hapus
$pid = (int) db()->query('SELECT id FROM produk LIMIT 1')->fetchColumn();
$st = db()->prepare(
    "INSERT INTO ulasan (produk_id, pengguna_id, nama, bintang, komentar, balasan, dibuat)
     VALUES (?,NULL,'Uji',4,'enak','','" . date('Y-m-d H:i:s') . "')"
);
$st->execute([$pid]);
$r = ringkasanRating()[$pid] ?? null;
$hasil[] = ['ringkasanRating hitung 1 ulasan', $r !== null && $r['jml'] === 1 && abs($r['rata'] - 4.0) < 0.01];

// CHECK constraint bintang harus menolak 6
try {
    $st = db()->prepare("INSERT INTO ulasan (produk_id,pengguna_id,nama,bintang,komentar,balasan,dibuat) VALUES (?,NULL,'X',6,'','','" . date('Y-m-d H:i:s') . "')");
    $st->execute([$pid]);
    $hasil[] = ['bintang 6 ditolak', false];
} catch (PDOException $e) {
    $hasil[] = ['bintang 6 ditolak', true];
}

db()->exec("DELETE FROM ulasan WHERE nama = 'Uji'");
$hasil[] = ['ulasan uji terhapus', ringkasanRating() === []];

echo "\nHASIL\n";
$ok = 0;
foreach ($hasil as [$n, $l]) {
    printf("  %s %s\n", $l ? 'OK  ' : 'GAGAL', $n);
    $ok += $l ? 1 : 0;
}
printf("\n%d/%d lulus\n", $ok, count($hasil));
exit($ok === count($hasil) ? 0 : 1);
