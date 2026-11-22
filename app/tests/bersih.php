<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

// Kembalikan password akun demo seperti di seed.
foreach ([['user', 'user123'], ['admin', 'admin123'], ['owner', 'owner123']] as [$u, $p]) {
    $st = db()->prepare('SELECT sandi FROM pengguna WHERE username = ?');
    $st->execute([$u]);
    if (!password_verify($p, (string) $st->fetchColumn())) {
        db()->prepare('UPDATE pengguna SET sandi = ? WHERE username = ?')
            ->execute([password_hash($p, PASSWORD_DEFAULT), $u]);
        echo "reset password: $u\n";
    }
}

// Hapus sisa data uji (pesanan & produk yang dibuat tes).
db()->exec("DELETE FROM pesanan WHERE nama_pemesan IN ('Uji QRIS','Pelanggan','Uji')");
db()->exec("DELETE FROM produk WHERE nama IN ('test','Test','Tes','Uji') OR nama LIKE 'Tes %' OR nama LIKE 'Uji %'");
db()->exec('DELETE FROM ulasan');
db()->exec('DELETE FROM percobaan_login');

printf("akun     = %d\n", db()->query('SELECT COUNT(*) FROM pengguna')->fetchColumn());
printf("kategori = %d\n", db()->query('SELECT COUNT(*) FROM kategori')->fetchColumn());
printf("produk   = %d\n", db()->query('SELECT COUNT(*) FROM produk')->fetchColumn());
printf("ulasan   = %d\n", db()->query('SELECT COUNT(*) FROM ulasan')->fetchColumn());
printf("pesanan  = %d\n", db()->query('SELECT COUNT(*) FROM pesanan')->fetchColumn());
printf("notif    = %d\n", db()->query('SELECT COUNT(*) FROM notifikasi')->fetchColumn());

foreach ([['user', 'user123'], ['admin', 'admin123'], ['owner', 'owner123']] as [$u, $p]) {
    $st = db()->prepare('SELECT sandi FROM pengguna WHERE username = ?');
    $st->execute([$u]);
    printf("password %-6s = %s\n", $u, password_verify($p, (string) $st->fetchColumn()) ? 'OK' : 'RUSAK');
}
printf("qris_statis = %s\n", var_export(setting('qris_statis'), true));
printf("qris_statis_payload = %s\n", setting('qris_statis_payload') !== '' ? 'ADA' : 'KOSONG');
