<?php

declare(strict_types=1);

// Simulasi flow pembeli + admin via HTTP (dua sesi cookie terpisah).
$base = 'http://127.0.0.1:8000';

function req(string $m, string $u, array $d = [], ?string $jar = null): string
{
    global $base;
    static $def = [];
    $jar ??= $def['jar'] ??= tempnam(sys_get_temp_dir(), 'cafe');

    $c = curl_init();
    curl_setopt_array($c, [
        CURLOPT_URL => $base . $u,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($m === 'POST') {
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($d));
    }
    $out = curl_exec($c);
    curl_close($c);
    return is_string($out) ? $out : '';
}

function csrf(string $html): string
{
    return preg_match('/name="csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
}

echo "1. Buka menu\n";
$tok = csrf(req('GET', '/app/'));

echo "2. Tambah Kopi Susu x2\n";
req('POST', '/app/', ['csrf' => $tok, 'aksi' => 'tambah', 'produk_id' => '2', 'jumlah' => '2']);

echo "3. Checkout\n";
$tok = csrf(req('GET', '/app/checkout.php'));
$html = req('POST', '/app/checkout.php', [
    'csrf' => $tok, 'nama' => 'Budi', 'tipe' => 'dine-in', 'catatan' => 'Sedikit gula',
]);

if (!preg_match('/DEMO-PAY\|([^|]+)\|/', $html, $m)) {
    echo "GAGAL dapat kode. Cuplikan:\n" . substr(strip_tags($html), 0, 800) . "\n";
    exit(1);
}
$kode = $m[1];
echo "   Kode: {$kode}\n";
echo "   Halaman bayar: " . (str_contains($html, 'data:image/png;base64') ? 'PNG ada' : 'PNG TIDAK ADA') . "\n";

// Sesi admin (jar berbeda)
$jarAdmin = tempnam(sys_get_temp_dir(), 'adm');
$tok = csrf(req('GET', '/app/masuk.php', [], $jarAdmin));
req('POST', '/app/masuk.php', ['csrf' => $tok, 'username' => 'admin', 'sandi' => 'admin123'], $jarAdmin);

echo "4. Konfirmasi dana masuk\n";
$tok = csrf(req('GET', '/app/admin/', [], $jarAdmin));
$html = req('POST', '/app/admin/', ['csrf' => $tok, 'aksi' => 'konfirmasi_bayar', 'kode' => $kode], $jarAdmin);
echo "   " . (str_contains($html, 'Dana masuk Rp44.000') ? 'OK: dana masuk tercatat' : 'GAGAL konfirmasi') . "\n";

echo "5. Notifikasi\n";
$html = req('GET', '/app/admin/notifikasi.php', [], $jarAdmin);
echo "   " . (str_contains($html, 'Dana masuk') ? 'OK: notifikasi dana masuk muncul' : 'GAGAL: tidak ada notifikasi') . "\n";

echo "6. Status pesanan di halaman bayar\n";
$html = req('GET', '/app/bayar.php?kode=' . urlencode($kode));
echo "   " . (str_contains($html, 'Pembayaran Diterima') ? 'OK: lunas' : 'GAGAL: masih menunggu') . "\n";

echo "7. Endpoint JSON\n";
echo "   " . substr(req('GET', '/app/admin/cek-baru.php', [], $jarAdmin), 0, 120) . "\n";

