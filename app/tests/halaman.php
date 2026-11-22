<?php
declare(strict_types=1);

// Cek semua halaman utama merespons 200 untuk peran yang tepat.
$base = 'http://127.0.0.1:8000';

// Payload QRIS contoh untuk form owner (harus sah agar tervalidasi).
const PAYLOAD = '00020101021126520013ID.CONTOH.WWW011593600000000000902'
    . '1200000000000151440014ID.CO.QRIS.WWW0215ID00000000000010303UMI'
    . '5204581253033605802ID5915Contoh Merchant6011KOTA CONTOH6105123456304D766';


function ambil(string $u, ?string $jar): array
{
    global $base;
    $c = curl_init();
    curl_setopt_array($c, [
        CURLOPT_URL => $base . $u,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($c);
    $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    return [$code, is_string($body) ? $body : ''];
}

function kirim(string $u, array $d, string $jar): array
{
    global $base;
    $c = curl_init();
    curl_setopt_array($c, [
        CURLOPT_URL => $base . $u,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($d),
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($c);
    $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    return [$code, is_string($body) ? $body : ''];
}

function tok(string $h): string
{
    return preg_match('/name="csrf" value="([^"]+)"/', $h, $m) ? $m[1] : '';
}

function masuk(string $user, string $sandi): string
{
    $jar = tempnam(sys_get_temp_dir(), 'sesi');
    [, $h] = ambil('/app/masuk.php', $jar);
    kirim('/app/masuk.php', ['csrf' => tok($h), 'username' => $user, 'sandi' => $sandi], $jar);
    return $jar;
}

$uji = [];

// Publik
[$c,] = ambil('/app/', null);
$uji[] = ['Menu (publik)', $c === 200];

[$c,] = ambil('/app/masuk.php', null);
$uji[] = ['Halaman masuk', $c === 200];

// Pembeli
$jp = masuk('user', 'user123');
[$c, $h] = ambil('/app/pesanan-saya.php', $jp);
$uji[] = ['Pesanan saya (pembeli)', $c === 200 && str_contains($h, 'Riwayat')];

// Pembeli TIDAK boleh buka dapur
[$c,] = ambil('/app/admin/', $jp);
$uji[] = ['Pembeli ditolak dari dapur', !str_contains(ambil('/app/admin/', $jp)[1], 'Daftar Pesanan')];

// Admin
$ja = masuk('admin', 'admin123');
[$c, $h] = ambil('/app/admin/', $ja);
$uji[] = ['Dapur (admin)', $c === 200 && str_contains($h, 'Daftar Pesanan')];

[$c, $h] = ambil('/app/admin/notifikasi.php', $ja);
$uji[] = ['Notifikasi (admin)', $c === 200];

// Admin TIDAK boleh buka panel owner
[, $h] = ambil('/app/owner/', $ja);
$uji[] = ['Admin ditolak dari owner', !str_contains($h, 'Panel Owner')];

// Owner
$jo = masuk('owner', 'owner123');
[$c, $h] = ambil('/app/owner/', $jo);
$uji[] = ['Panel owner', $c === 200 && str_contains($h, 'Identitas Toko')];

[$c, $h] = ambil('/app/owner/produk.php', $jo);
$uji[] = ['Kelola produk', $c === 200 && str_contains($h, 'Daftar Produk')];

// Owner menambah produk, lalu cek muncul di menu publik
[, $h] = ambil('/app/owner/produk.php', $jo);
[$c,] = kirim('/app/owner/produk.php', [
    'csrf' => tok($h), 'aksi' => 'tambah', 'nama' => 'Uji Produk',
    'harga' => '15000', 'deskripsi' => 'tes', 'kategori_id' => '1',
], $jo);
[, $menu] = ambil('/app/', null);
$uji[] = ['Produk baru tampil di menu', str_contains($menu, 'Uji Produk')];

// Owner menyimpan nama toko, lalu cek tampil di menu
[, $h] = ambil('/app/owner/', $jo);
kirim('/app/owner/', [
    'csrf' => tok($h), 'aksi' => 'simpan', 'nama_toko' => 'Kafe Uji Nama',
    'alamat' => 'Jl. Uji', 'kota' => 'Bandung', 'kode_pos' => '40111',
    'catatan' => 'tes', 'pajak_persen' => '0', 'qris_statis' => '/app/assets/img/qris-statis.png', 'qris_statis_payload' => PAYLOAD,
], $jo);
[, $menu] = ambil('/app/', null);
$uji[] = ['Nama toko berubah di menu', str_contains($menu, 'Kafe Uji Nama')];

// Kembalikan nama toko
[, $h] = ambil('/app/owner/', $jo);
kirim('/app/owner/', [
    'csrf' => tok($h), 'aksi' => 'simpan', 'nama_toko' => 'Kopi Senja',
    'alamat' => 'Jl. Braga No. 12', 'kota' => 'Bandung', 'kode_pos' => '40111',
    'catatan' => 'Terima kasih sudah mampir!', 'pajak_persen' => '0', 'qris_statis' => '/app/assets/img/qris-statis.png', 'qris_statis_payload' => PAYLOAD,
], $jo);

// Validasi: nama toko kosong harus ditolak
[, $h] = ambil('/app/owner/', $jo);
[, $h2] = kirim('/app/owner/', [
    'csrf' => tok($h), 'aksi' => 'simpan', 'nama_toko' => '', 'alamat' => '', 'kota' => '',
    'kode_pos' => '', 'catatan' => '', 'pajak_persen' => '0', 'qris_statis' => '', 'qris_statis_payload' => PAYLOAD,
], $jo);
$uji[] = ['Nama toko kosong ditolak', str_contains($h2, 'wajib diisi')];

// Validasi: payload QRIS rusak (CRC salah) harus ditolak
[, $h] = ambil('/app/owner/', $jo);
[, $h3] = kirim('/app/owner/', [
    'csrf' => tok($h), 'aksi' => 'simpan', 'nama_toko' => 'Kopi Senja', 'alamat' => 'Jl. Braga No. 12',
    'kota' => 'Bandung', 'kode_pos' => '40111', 'catatan' => 'tes', 'pajak_persen' => '0',
    'qris_statis' => '', 'qris_statis_payload' => substr(PAYLOAD, 0, -4) . '0000',
], $jo);
$uji[] = ['Payload QRIS CRC salah ditolak', str_contains($h3, 'tidak valid')];

// Validasi: payload bukan QRIS harus ditolak
[, $h] = ambil('/app/owner/', $jo);
[, $h4] = kirim('/app/owner/', [
    'csrf' => tok($h), 'aksi' => 'simpan', 'nama_toko' => 'Kopi Senja', 'alamat' => 'Jl. Braga No. 12',
    'kota' => 'Bandung', 'kode_pos' => '40111', 'catatan' => 'tes', 'pajak_persen' => '0',
    'qris_statis' => '', 'qris_statis_payload' => 'bukan qris sama sekali',
], $jo);
$uji[] = ['Payload bukan QRIS ditolak', str_contains($h4, 'tidak valid')];

// Pastikan nilai akhir tetap payload yang benar
[, $h] = ambil('/app/owner/', $jo);
kirim('/app/owner/', [
    'csrf' => tok($h), 'aksi' => 'simpan', 'nama_toko' => 'Kopi Senja', 'alamat' => 'Jl. Braga No. 12',
    'kota' => 'Bandung', 'kode_pos' => '40111', 'catatan' => 'Terima kasih sudah mampir!',
    'pajak_persen' => '0', 'qris_statis' => '/app/assets/img/qris-statis.png',
    'qris_statis_payload' => PAYLOAD,
], $jo);

// Hapus produk uji lewat bootstrap (path DB benar).
require __DIR__ . '/../bootstrap.php';
db()->exec("DELETE FROM produk WHERE nama = 'Uji Produk'");

echo "\nHASIL UJI HALAMAN\n";
$lulus = 0;
foreach ($uji as [$nama, $ok]) {
    printf("  %s %s\n", $ok ? 'OK  ' : 'GAGAL', $nama);
    $lulus += $ok ? 1 : 0;
}
printf("\n%d/%d lulus\n", $lulus, count($uji));
exit($lulus === count($uji) ? 0 : 1);
