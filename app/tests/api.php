<?php

declare(strict_types=1);

// Tes API: autentikasi, CRUD pesanan, QRIS dinamis, dan validasi input.
require __DIR__ . '/../bootstrap.php';

$base = 'http://127.0.0.1:8000/app/api.php';
$hasil = [];

/**
 * @return array{0:int,1:array<string,mixed>}
 */
function api(string $metode, string $r, array $body = [], ?string $kunci = null): array
{
    global $base;
    $hdr = ['Accept: application/json'];
    if ($kunci !== null) {
        $hdr[] = 'X-API-Key: ' . $kunci;
    }
    $c = curl_init();
    // Pisahkan query string dari nama rute agar &limit= tidak ikut ter-encode.
    $query = '';
    if (str_contains($r, '?')) {
        [$r, $query] = explode('?', $r, 2);
        $query = '&' . $query;
    }
    curl_setopt_array($c, [
        CURLOPT_URL => $base . '?r=' . urlencode($r) . $query,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metode,
        CURLOPT_HTTPHEADER => $hdr,
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($metode === 'POST') {
        $hdr[] = 'Content-Type: application/json';
        curl_setopt($c, CURLOPT_HTTPHEADER, $hdr);
        // Kirim lewat file agar tanda kutip JSON tidak dirusak shell.
        $tmp = tempnam(sys_get_temp_dir(), 'apibody');
        file_put_contents($tmp, json_encode($body));
        curl_setopt($c, CURLOPT_POSTFIELDS, file_get_contents($tmp));
        @unlink($tmp);
    }
    $res = curl_exec($c);
    $kode = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    $json = json_decode((string) $res, true);
    return [$kode, is_array($json) ? $json : ['__raw' => substr((string) $res, 0, 300)]];
}

// Siapkan kunci API untuk tes.
$kunciUji = 'uji-' . bin2hex(random_bytes(8));
setSettingRaw(db(), 'api_key', $kunciUji);
unset($GLOBALS['__setting_cache']);

// --- 1. autentikasi -------------------------------------------------------
[$k, $j] = api('GET', 'info');
$hasil[] = ['Tanpa API key -> 401', $k === 401];
$hasil[] = ['Pesan menyebut API Key', stripos((string) ($j['message'] ?? ''), 'API Key') !== false];

[$k, $j] = api('GET', 'info', [], 'kunci-salah-sekali');
$hasil[] = ['API key salah -> 401', $k === 401];

// --- 2. info --------------------------------------------------------------
[$k, $j] = api('GET', 'info', [], $kunciUji);
$hasil[] = ['Info dengan key benar -> 200', $k === 200 && ($j['ok'] ?? false) === true];
$hasil[] = ['Info memuat nama toko', isset($j['data']['toko'])];
$hasil[] = ['Info menandai QRIS siap', ($j['data']['qris_dinamis_siap'] ?? false) === true];

// --- 3. produk -----------------------------------------------------------
[$k, $j] = api('GET', 'produk', [], $kunciUji);
$hasil[] = ['Daftar produk -> 200', $k === 200 && ($j['ok'] ?? false) === true];
$jmlProduk = count($j['data'] ?? []);
$hasil[] = ['Produk tidak kosong', $jmlProduk > 0];
$hargaPertama = $j['data'][0]['harga'] ?? 0;
$idPertama = $j['data'][0]['id'] ?? 0;
$hasil[] = ['Harga berupa integer', is_int($hargaPertama)];

// --- 4. buat pesanan -----------------------------------------------------
[$k, $j] = api('POST', 'pesanan', [
    'nama_pemesan' => 'Uji API',
    'tipe' => 'take-away',
    'items' => [['produk_id' => $idPertama, 'jumlah' => 2]],
], $kunciUji);
$hasil[] = ['Buat pesanan -> 201', $k === 201];
$kode = $j['data']['kode'] ?? '';
$hasil[] = ['Kode pesanan terbentuk', str_starts_with((string) $kode, 'ORD-')];
$totalHarapan = $hargaPertama * 2;
$hasil[] = ['Total dihitung server (2 x harga)', ($j['data']['total'] ?? 0) === $totalHarapan];
$hasil[] = ['Status awal menunggu', ($j['data']['status'] ?? '') === 'menunggu'];


// --- 5. validasi input ---------------------------------------------------
[$k, $j] = api('POST', 'pesanan', ['nama_pemesan' => '', 'items' => [['produk_id' => $idPertama, 'jumlah' => 1]]], $kunciUji);
$hasil[] = ['Nama kosong -> 400', $k === 400];

[$k, $j] = api('POST', 'pesanan', ['nama_pemesan' => 'X', 'items' => []], $kunciUji);
$hasil[] = ['Items kosong -> 400', $k === 400];

[$k, $j] = api('POST', 'pesanan', ['nama_pemesan' => 'X', 'items' => [['produk_id' => 999999, 'jumlah' => 1]]], $kunciUji);
$hasil[] = ['Produk tidak ada -> 400', $k === 400];

[$k, $j] = api('POST', 'pesanan', ['nama_pemesan' => 'X', 'items' => [['produk_id' => $idPertama, 'jumlah' => 0]]], $kunciUji);
$hasil[] = ['Jumlah 0 -> 400', $k === 400];

[$k, $j] = api('POST', 'pesanan', ['nama_pemesan' => 'X', 'tipe' => 'ngawur', 'items' => [['produk_id' => $idPertama, 'jumlah' => 1]]], $kunciUji);
$hasil[] = ['Tipe ngawur -> 400', $k === 400];

// --- 6. ambil pesanan ----------------------------------------------------
[$k, $j] = api('GET', 'pesanan/' . $kode, [], $kunciUji);
$hasil[] = ['Get pesanan -> 200', $k === 200];
$hasil[] = ['Item pesanan ikut terambil', count($j['data']['items'] ?? []) === 1];

[$k, $j] = api('GET', 'pesanan/ORD-TIDAK-ADA', [], $kunciUji);
$hasil[] = ['Pesanan tidak ada -> 404', $k === 404];

// --- 7. QRIS dinamis -----------------------------------------------------
[$k, $j] = api('GET', 'pesanan/' . $kode . '/qris', [], $kunciUji);
$hasil[] = ['QRIS pesanan -> 200', $k === 200];
$payload = (string) ($j['data']['payload'] ?? '');
$totalStr = (string) $totalHarapan;
$hasil[] = ['Payload dinamis (tag 01 = 12)', str_contains($payload, '010212')];
$hasil[] = ['Nominal masuk ke tag 54', str_contains($payload, '54' . str_pad((string) strlen($totalStr), 2, '0', STR_PAD_LEFT) . $totalStr)];
$hasil[] = ['Nominal di JSON cocok', ($j['data']['nominal'] ?? 0) === $totalHarapan];
$hasil[] = ['PNG base64 tersedia', strlen((string) ($j['data']['png_base64'] ?? '')) > 500];

if ($payload !== '') {
    $crcBenar = QrisDinamis\Crc16::hex(substr($payload, 0, -4)) === strtoupper(substr($payload, -4));
    $hasil[] = ['CRC payload valid', $crcBenar];
}

// --- 8. konfirmasi bayar -------------------------------------------------
[$k, $j] = api('POST', 'pesanan/' . $kode . '/bayar', [], $kunciUji);
$hasil[] = ['Konfirmasi bayar -> 200', $k === 200];
$hasil[] = ['Status jadi dibayar', ($j['data']['status'] ?? '') === 'dibayar'];
$hasil[] = ['Waktu bayar terisi', !empty($j['data']['dibayar'])];

[$k, $j] = api('POST', 'pesanan/' . $kode . '/bayar', [], $kunciUji);
$hasil[] = ['Bayar dua kali ditolak -> 409', $k === 409];

// --- 9. ubah status ------------------------------------------------------
[$k, $j] = api('POST', 'pesanan/' . $kode . '/status', ['status' => 'diproses'], $kunciUji);
$hasil[] = ['Status diproses -> 200', $k === 200 && ($j['data']['status'] ?? '') === 'diproses'];

[$k, $j] = api('POST', 'pesanan/' . $kode . '/status', ['status' => 'ngawur'], $kunciUji);
$hasil[] = ['Status ngawur ditolak -> 409', $k === 409];

[$k, $j] = api('POST', 'pesanan/' . $kode . '/status', [], $kunciUji);
$hasil[] = ['Status kosong -> 400', $k === 400];

// Pesanan menunggu tidak boleh langsung diproses
[$k, $j] = api('POST', 'pesanan', ['nama_pemesan' => 'Uji API 2', 'items' => [['produk_id' => $idPertama, 'jumlah' => 1]]], $kunciUji);
$kode2 = $j['data']['kode'] ?? '';
[$k, $j] = api('POST', 'pesanan/' . $kode2 . '/status', ['status' => 'diproses'], $kunciUji);
$hasil[] = ['Belum bayar tidak bisa diproses -> 409', $k === 409];

// --- 10. daftar pesanan --------------------------------------------------
[$k, $j] = api('GET', 'pesanan?limit=5', [], $kunciUji);
$hasil[] = ['Daftar pesanan -> 200', $k === 200];
$hasil[] = ['Limit dihormati', count($j['data'] ?? []) <= 5];

[$k, $j] = api('GET', 'pesanan?status=ngawur', [], $kunciUji);
$hasil[] = ['Filter status salah -> 400', $k === 400];

// --- 11. routing ---------------------------------------------------------
[$k, $j] = api('GET', 'tidak-ada-endpoint', [], $kunciUji);
$hasil[] = ['Endpoint ngawur -> 404', $k === 404];

// --- 12. matikan API -----------------------------------------------------
setSettingRaw(db(), 'api_key', '');
unset($GLOBALS['__setting_cache']);
[$k, $j] = api('GET', 'info', [], $kunciUji);
$hasil[] = ['API key kosong -> 503', $k === 503];

// Bersihkan data uji
db()->exec("DELETE FROM pesanan WHERE nama_pemesan LIKE 'Uji API%'");
db()->exec("DELETE FROM notifikasi WHERE judul LIKE 'Pesanan baru%' OR judul LIKE 'Dana masuk%'");

echo "HASIL UJI API\n";
$ok = 0;
foreach ($hasil as [$nama, $lulus]) {
    printf("  %s %s\n", $lulus ? 'OK  ' : 'GAGAL', $nama);
    $ok += $lulus ? 1 : 0;
}
printf("\n%d/%d lulus\n", $ok, count($hasil));
exit($ok === count($hasil) ? 0 : 1);
