<?php
declare(strict_types=1);
// Tes: QRIS dinamis yang tampil di halaman bayar benar-benar berisi nominal pesanan.
require __DIR__ . '/../bootstrap.php';

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

// Pastikan pengaturan QRIS memakai payload CONTOH, bukan payload asli milik
// siapa pun. Tes tidak boleh bergantung pada data pribadi di database lokal.
const PAYLOAD_CONTOH = '00020101021126520013ID.CONTOH.WWW011593600000000000902'
    . '1200000000000151440014ID.CO.QRIS.WWW0215ID00000000000010303UMI'
    . '5204581253033605802ID5915Contoh Merchant6011KOTA CONTOH6105123456304D766';

$jo = tempnam(sys_get_temp_dir(), 'o');
$h = go('GET', '/app/masuk.php', [], $jo);
preg_match('/name="csrf" value="([^"]+)"/', $h, $mo);
go('POST', '/app/masuk.php', ['csrf' => $mo[1], 'username' => 'owner', 'sandi' => 'owner123'], $jo);
$h = go('GET', '/app/owner/', [], $jo);
preg_match('/name="csrf" value="([^"]+)"/', $h, $mo);
go('POST', '/app/owner/', [
    'csrf' => $mo[1], 'aksi' => 'simpan', 'nama_toko' => 'Kopi Senja',
    'alamat' => 'Jl. Contoh', 'kota' => 'Kota Contoh', 'kode_pos' => '12345',
    'catatan' => 'tes', 'pajak_persen' => '0',
    'qris_statis' => '', 'qris_statis_payload' => PAYLOAD_CONTOH,
], $jo);
@unlink($jo);

$h = go('GET', '/app/masuk.php', [], $jar);
preg_match('/name="csrf" value="([^"]+)"/', $h, $m);
go('POST', '/app/masuk.php', ['csrf' => $m[1], 'username' => 'user', 'sandi' => 'user123'], $jar);

// Pesan 2 item agar totalnya tidak bulat
$h = go('GET', '/app/', [], $jar);
preg_match('/name="csrf" value="([^"]+)"/', $h, $m);
go('POST', '/app/', ['csrf' => $m[1], 'aksi' => 'tambah', 'produk_id' => '1', 'jumlah' => '2'], $jar);
go('POST', '/app/', ['csrf' => $m[1], 'aksi' => 'tambah', 'produk_id' => '5', 'jumlah' => '1'], $jar);

$h = go('GET', '/app/checkout.php', [], $jar);
preg_match('/name="csrf" value="([^"]+)"/', $h, $m);
go('POST', '/app/checkout.php', ['csrf' => $m[1], 'nama' => 'Uji Dinamis', 'tipe' => 'dine-in', 'catatan' => ''], $jar);

$st = db()->prepare("SELECT kode, total FROM pesanan WHERE nama_pemesan = 'Uji Dinamis' ORDER BY id DESC LIMIT 1");
$st->execute();
$row = $st->fetch();

if (!$row) {
    echo "GAGAL: pesanan uji tidak terbentuk\n";
    exit(1);
}
$kode = $row['kode'];
$total = (int) $row['total'];
printf("pesanan: %s  total: Rp%s\n\n", $kode, number_format($total, 0, ',', '.'));

$bayar = go('GET', '/app/bayar.php?kode=' . urlencode($kode), [], $jar);

$uji = [];
$uji[] = ['Halaman memuat label QRIS Dinamis', str_contains($bayar, 'QRIS Dinamis')];
$uji[] = ['Nominal tampil di halaman', str_contains($bayar, number_format($total, 0, ',', '.'))];
$uji[] = ['Tidak ada galat konversi', !str_contains($bayar, 'gagal dibuat')];

// Ambil data URI PNG QR dinamis dari HTML
preg_match('/src="data:image\/png;base64,([A-Za-z0-9+\/=]+)"/', $bayar, $m);
$adaQr = isset($m[1]);
$uji[] = ['QR dinamis dirender sebagai PNG', $adaQr];

if ($adaQr) {
    $png = base64_decode($m[1], true);
    $uji[] = ['PNG valid', is_string($png) && substr($png, 0, 8) === "\x89PNG\r\n\x1a\n"];
    $tmp = sys_get_temp_dir() . '/qr-' . $kode . '.png';
    file_put_contents($tmp, $png);

    // Decode ulang PNG yang dirender -> harus kembali ke payload dinamis
    $py = <<<PY
import sys, cv2
from pyzbar.pyzbar import decode
from PIL import Image
img = Image.open(sys.argv[1])
res = decode(img)
print(res[0].data.decode() if res else '')
PY;
    $pyFile = sys_get_temp_dir() . '/dec.py';
    file_put_contents($pyFile, $py);
    $payload = trim((string) shell_exec('python ' . escapeshellarg($pyFile) . ' ' . escapeshellarg($tmp)));
    @unlink($tmp);

    $uji[] = ['QR hasil render bisa di-decode ulang', $payload !== ''];

    if ($payload !== '') {
        // Parse payload hasil decode
        $tag = [];
        $i = 0;
        while ($i + 4 <= strlen($payload)) {
            $t = substr($payload, $i, 2);
            $l = (int) substr($payload, $i + 2, 2);
            $tag[$t] = substr($payload, $i + 4, $l);
            $i += 4 + $l;
        }
        $uji[] = ['Tag 01 = 12 (dinamis)', ($tag['01'] ?? '') === '12'];
        $uji[] = ['Tag 54 = total pesanan (' . ($tag['54'] ?? '-') . ')', ($tag['54'] ?? '') === (string) $total];
        $uji[] = ['CRC cocok', strtoupper(substr($payload, -4)) === QrisDinamis\Crc16::hex(substr($payload, 0, -4))];
        $uji[] = ['Merchant tetap sama', str_contains($payload, 'Contoh Merchant')];
    }
}

echo "HASIL\n";
$ok = 0;
foreach ($uji as [$n, $l]) {
    printf("  %s %s\n", $l ? 'OK  ' : 'GAGAL', $n);
    $ok += $l ? 1 : 0;
}
printf("\n%d/%d lulus\n", $ok, count($uji));
exit($ok === count($uji) ? 0 : 1);
