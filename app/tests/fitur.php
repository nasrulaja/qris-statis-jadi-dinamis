<?php
declare(strict_types=1);

// Uji fitur baru: rating, rate limit, struk, ganti password.
$base = 'http://127.0.0.1:8000';
$hasil = [];

function rq(string $m, string $u, array $d = [], ?string $jar = null): array
{
    global $base;
    static $def = [];
    $jar ??= $def['jar'] ??= tempnam(sys_get_temp_dir(), 'f2');
    $c = curl_init();
    curl_setopt_array($c, [
        CURLOPT_URL => $base . $u, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 15,
    ]);
    if ($m === 'POST') {
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($d));
    }
    $b = curl_exec($c);
    $k = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    return [$k, is_string($b) ? $b : ''];
}

function tk(string $h): string
{
    return preg_match('/name="csrf" value="([^"]+)"/', $h, $m) ? $m[1] : '';
}

function login(string $u, string $s): string
{
    $j = tempnam(sys_get_temp_dir(), 's');
    [, $h] = rq('GET', '/app/masuk.php', [], $j);
    rq('POST', '/app/masuk.php', ['csrf' => tk($h), 'username' => $u, 'sandi' => $s], $j);
    return $j;
}

require __DIR__ . '/../bootstrap.php';
db()->exec('DELETE FROM percobaan_login');

// 1. detail produk
[, $h] = rq('GET', '/app/produk.php?id=2');
$hasil[] = ['Detail produk tampil', str_contains($h, 'Kopi Susu') && str_contains($h, 'Tulis Ulasan')];
$hasil[] = ['Detail: tombol keranjang', str_contains($h, 'Tambah ke keranjang')];
$hasil[] = ['Tamu diminta masuk', str_contains($h, 'Masuk sebagai pembeli')];

// 2. pembeli yang belum beli tidak boleh mengulas
$jp = login('user', 'user123');
[, $h] = rq('GET', '/app/produk.php?id=1', [], $jp);
$hasil[] = ['Belum beli -> terkunci', str_contains($h, 'Ulasan terbuka untuk pembeli')];

// 3. pesan Kopi Susu lalu admin konfirmasi
$tok = tk(rq('GET', '/app/', [], $jp)[1]);
rq('POST', '/app/', ['csrf' => $tok, 'aksi' => 'tambah', 'produk_id' => '2', 'jumlah' => '1'], $jp);
$tok = tk(rq('GET', '/app/checkout.php', [], $jp)[1]);
[, $bayar] = rq('POST', '/app/checkout.php', [
    'csrf' => $tok, 'nama' => 'Pelanggan', 'tipe' => 'dine-in', 'catatan' => '',
], $jp);
preg_match('/DEMO-PAY\|([^|]+)\|/', $bayar, $m);
$kode = $m[1] ?? '';

$ja = login('admin', 'admin123');
$tok = tk(rq('GET', '/app/admin/', [], $ja)[1]);
rq('POST', '/app/admin/', ['csrf' => $tok, 'aksi' => 'konfirmasi_bayar', 'kode' => $kode], $ja);

// 4. sekarang boleh mengulas
[, $h] = rq('GET', '/app/produk.php?id=2', [], $jp);
$hasil[] = ['Sudah bayar -> form terbuka', str_contains($h, 'Kirim ulasan')];

[, $h] = rq('POST', '/app/produk.php?id=2', [
    'csrf' => tk($h), 'aksi' => 'ulas', 'bintang' => '5', 'komentar' => 'Kopinya enak sekali!',
], $jp);
$hasil[] = ['Ulasan tersimpan', str_contains($h, 'Terima kasih! Ulasan Anda tersimpan')];
$hasil[] = ['Komentar tampil', str_contains($h, 'Kopinya enak sekali!')];

// 5. ulasan ganda dicegah
$tok = tk(rq('GET', '/app/produk.php?id=2', [], $jp)[1]);
[, $h] = rq('POST', '/app/produk.php?id=2', [
    'csrf' => $tok, 'aksi' => 'ulas', 'bintang' => '1', 'komentar' => 'lagi',
], $jp);
$hasil[] = ['Ulasan ganda dicegah', str_contains($h, 'sudah mengulas')];

// 6. rating di menu
[, $menu] = rq('GET', '/app/', [], $jp);
$hasil[] = ['Rating tampil di menu', str_contains($menu, '(1)')];

// 7. owner balas ulasan
$jo = login('owner', 'owner123');
[, $h] = rq('GET', '/app/owner/ulasan.php', [], $jo);
$hasil[] = ['Owner lihat ulasan', str_contains($h, 'Kopinya enak sekali!')];

preg_match('/name="id" value="(\d+)"/', $h, $idm);
$uid = $idm[1] ?? '0';
[, $h] = rq('POST', '/app/owner/ulasan.php', [
    'csrf' => tk($h), 'aksi' => 'balas', 'id' => $uid, 'balasan' => 'Terima kasih sudah mampir!',
], $jo);
$hasil[] = ['Balasan owner tersimpan', str_contains($h, 'Balasan disimpan')];

[, $h] = rq('GET', '/app/produk.php?id=2', [], $jp);
$hasil[] = ['Balasan tampil ke pembeli', str_contains($h, 'Terima kasih sudah mampir!')];

// 8. struk
[$k, $h] = rq('GET', '/app/struk.php?kode=' . urlencode($kode), [], $jp);
$hasil[] = ['Struk pemilik pesanan', $k === 200 && str_contains($h, 'Kopi Susu') && str_contains($h, 'TOTAL')];
$hasil[] = ['Admin boleh lihat struk', str_contains(rq('GET', '/app/struk.php?kode=' . urlencode($kode), [], $ja)[1], 'TOTAL')];

// pembeli lain tidak boleh
$jlain = login('owner', 'owner123');
$hasil[] = ['Kode struk salah ditolak', str_contains(rq('GET', '/app/struk.php?kode=TIDAKADA', [], $jlain)[1], 'tidak ditemukan')];

// 9. ganti password
[, $h] = rq('GET', '/app/akun.php', [], $jp);
[, $h] = rq('POST', '/app/akun.php', [
    'csrf' => tk($h), 'lama' => 'salah', 'baru' => 'baru12345', 'ulang' => 'baru12345',
], $jp);
$hasil[] = ['Password lama salah ditolak', str_contains($h, 'Password lama salah')];

[, $h] = rq('POST', '/app/akun.php', [
    'csrf' => tk($h), 'lama' => 'user123', 'baru' => 'baru12345', 'ulang' => 'beda12345',
], $jp);
$hasil[] = ['Konfirmasi beda ditolak', str_contains($h, 'tidak cocok')];

[, $h] = rq('POST', '/app/akun.php', [
    'csrf' => tk($h), 'lama' => 'user123', 'baru' => 'pendek', 'ulang' => 'pendek',
], $jp);
$hasil[] = ['Password pendek ditolak', str_contains($h, 'minimal 8 karakter')];

[, $h] = rq('POST', '/app/akun.php', [
    'csrf' => tk($h), 'lama' => 'user123', 'baru' => 'baru12345', 'ulang' => 'baru12345',
], $jp);
$hasil[] = ['Password berhasil diubah', str_contains($h, 'berhasil diubah')];

$j2 = login('user', 'baru12345');
$hasil[] = ['Login dengan password baru', str_contains(rq('GET', '/app/akun.php', [], $j2)[1], 'Akun Saya')];

// kembalikan
[, $h] = rq('GET', '/app/akun.php', [], $j2);
rq('POST', '/app/akun.php', [
    'csrf' => tk($h), 'lama' => 'baru12345', 'baru' => 'user123', 'ulang' => 'user123',
], $j2);

// 10. rate limit
db()->exec('DELETE FROM percobaan_login');
$jr = tempnam(sys_get_temp_dir(), 'rl');
$akhir = '';
for ($i = 0; $i < 6; $i++) {
    [, $h] = rq('GET', '/app/masuk.php', [], $jr);
    [, $akhir] = rq('POST', '/app/masuk.php', ['csrf' => tk($h), 'username' => 'user', 'sandi' => 'salah'], $jr);
}
$hasil[] = ['Rate limit aktif', str_contains($akhir, 'Terlalu banyak percobaan')];
db()->exec('DELETE FROM percobaan_login');

// bersihkan data uji
db()->exec("DELETE FROM ulasan WHERE nama IN ('Pelanggan','Uji')");

echo "\nHASIL UJI FITUR BARU\n";
$ok = 0;
foreach ($hasil as [$n, $l]) {
    printf("  %s %s\n", $l ? 'OK  ' : 'GAGAL', $n);
    $ok += $l ? 1 : 0;
}
printf("\n%d/%d lulus\n", $ok, count($hasil));
exit($ok === count($hasil) ? 0 : 1);

