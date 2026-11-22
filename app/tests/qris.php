<?php
declare(strict_types=1);
// Verifikasi QRIS statis tampil di halaman bayar.

$base = 'http://127.0.0.1:8000';
$jar = tempnam(sys_get_temp_dir(), 'q');

function go(string $m, string $u, array $d = [], string $jar = ''): string
{
    global $base;
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
    curl_close($c);
    return is_string($b) ? $b : '';
}

$h = go('GET', '/app/masuk.php', [], $jar);
preg_match('/name="csrf" value="([^"]+)"/', $h, $m);
go('POST', '/app/masuk.php', ['csrf' => $m[1], 'username' => 'user', 'sandi' => 'user123'], $jar);

require __DIR__ . '/../bootstrap.php';

// Buat pesanan baru yang pasti berstatus menunggu.
$tok = null;
$h = go('GET', '/app/', [], $jar);
preg_match('/name="csrf" value="([^"]+)"/', $h, $m);
$tok = $m[1] ?? '';

go('POST', '/app/', ['csrf' => $tok, 'aksi' => 'tambah', 'produk_id' => '1', 'jumlah' => '1'], $jar);
$h = go('GET', '/app/checkout.php', [], $jar);
preg_match('/name="csrf" value="([^"]+)"/', $h, $m);
go('POST', '/app/checkout.php', [
    'csrf' => $m[1] ?? '', 'nama' => 'Uji QRIS', 'tipe' => 'dine-in', 'catatan' => '',
], $jar);

$kode = db()->query("SELECT kode FROM pesanan WHERE nama_pemesan = 'Uji QRIS' ORDER BY id DESC LIMIT 1")->fetchColumn();
echo "kode pesanan uji: " . var_export($kode, true) . "\n\n";

$hasil = [];

if (!$kode) {
    echo "Tidak ada pesanan menunggu. Buat dulu lewat tes flow.\n";
    exit(1);
}

$bayar = go('GET', '/app/bayar.php?kode=' . urlencode((string) $kode), [], $jar);

// Diagnosa: apa status pesanan & apakah halaman menganggapnya lunas
$st = db()->prepare('SELECT status, dibayar FROM pesanan WHERE kode = ?');
$st->execute([$kode]);
$row = $st->fetch();
echo "status di DB: " . var_export($row['status'] ?? null, true)
   . " dibayar=" . var_export($row['dibayar'] ?? null, true) . "\n";
echo "halaman berisi 'Lunas': " . (str_contains($bayar, 'Lunas') ? 'ya' : 'tidak') . "\n";
echo "halaman berisi 'QRIS': " . (str_contains($bayar, 'QRIS') ? 'ya' : 'tidak') . "\n";
echo "panjang halaman: " . strlen($bayar) . "\n\n";
$hasil[] = ['Halaman bayar terbuka', str_contains($bayar, (string) $kode)];
$hasil[] = ['QRIS dinamis tampil (PNG data URI)', (bool) preg_match('/src="data:image\/png;base64,/', $bayar)];
$hasil[] = ['Label QRIS Dinamis', str_contains($bayar, 'QRIS Dinamis')];
$hasil[] = ['Nominal ditampilkan', str_contains($bayar, 'Rp')];
$hasil[] = ['Ada QR demo juga', str_contains($bayar, 'DEMO-PAY')];

// Gambar statis tetap tersedia sebagai cadangan
$c = curl_init($base . '/app/assets/img/qris-statis.png');
curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$img = curl_exec($c);
$kodeHttp = curl_getinfo($c, CURLINFO_HTTP_CODE);
curl_close($c);
$hasil[] = ['Gambar QRIS statis tetap bisa diakses', $kodeHttp === 200];
$hasil[] = ['Gambar QRIS statis ukuran benar', is_string($img) && strlen($img) > 200000];

echo "\nHASIL\n";
$ok = 0;
foreach ($hasil as [$n, $l]) {
    printf("  %s %s\n", $l ? 'OK  ' : 'GAGAL', $n);
    $ok += $l ? 1 : 0;
}
printf("\n%d/%d lulus\n", $ok, count($hasil));
exit($ok === count($hasil) ? 0 : 1);
