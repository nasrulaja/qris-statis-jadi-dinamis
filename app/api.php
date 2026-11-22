<?php

declare(strict_types=1);

// API JSON untuk aplikasi lain (mobile/POS/integrasi).
//
// Autentikasi: header  X-API-Key: <kunci>
// Kunci diatur owner di panel: Pengaturan -> API Key.
//
// Endpoint:
//   GET  /app/api.php?r=produk                 daftar produk aktif
//   GET  /app/api.php?r=pesanan/{kode}         detail satu pesanan
//   GET  /app/api.php?r=pesanan                daftar pesanan (filter ?status=)
//   POST /app/api.php?r=pesanan                buat pesanan
//   GET  /app/api.php?r=pesanan/{kode}/qris    QRIS dinamis untuk pesanan itu
//   POST /app/api.php?r=pesanan/{kode}/bayar   tandai lunas (konfirmasi manual)
//   POST /app/api.php?r=pesanan/{kode}/status  ubah status (diproses/siap/selesai/batal)
//   GET  /app/api.php?r=info                   info toko + status konfigurasi

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/** Kirim JSON lalu berhenti. */
function jsonOut(int $kode, array $data): never
{
    http_response_code($kode);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function galat(int $kode, string $pesan, string $kodeGalat = ''): never
{
    jsonOut($kode, ['ok' => false, 'error' => $kodeGalat ?: 'GALAT', 'message' => $pesan]);
}

// ---------------------------------------------------------------- keamanan
// API key dibandingkan dengan hash_equals untuk mencegah timing attack.
$kunciTersimpan = trim(setting('api_key'));
$kunciDikirim = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));

if ($kunciTersimpan === '') {
    galat(503, 'API belum diaktifkan. Owner harus mengatur API Key di panel pengaturan.', 'API_MATI');
}
if ($kunciDikirim === '' || !hash_equals($kunciTersimpan, $kunciDikirim)) {
    header('WWW-Authenticate: X-API-Key');
    galat(401, 'API Key tidak valid. Kirim header X-API-Key.', 'UNAUTHORIZED');
}

// ---------------------------------------------------------------- routing
$rute = trim((string) ($_GET['r'] ?? ''), '/');
$metode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$body = [];
if ($metode === 'POST') {
    $raw = (string) file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        // Izinkan juga form-urlencoded agar gampang dites dari curl/Postman.
        $body = $_POST;
    }
    if ($raw !== '' && !is_array(json_decode($raw, true)) && $body === []) {
        galat(400, 'Body bukan JSON yang valid.', 'JSON_RUSAK');
    }
}

$bagian = $rute === '' ? [] : explode('/', $rute);
$resource = $bagian[0] ?? '';
$arg1 = $bagian[1] ?? '';
$arg2 = $bagian[2] ?? '';

// ------------------------------------------------------------------ info
if ($resource === 'info') {
    jsonOut(200, ['ok' => true, 'data' => [
        'toko' => setting('nama_toko'),
        'kota' => setting('kota'),
        'versi_api' => '1.0',
        'pajak_persen' => (int) setting('pajak_persen', '0'),
        'qris_dinamis_siap' => trim(setting('qris_statis_payload')) !== '',
        'catatan_pembayaran' => 'QRIS dinamis dibuat dari QRIS statis merchant. '
            . 'Status lunas WAJIB dikonfirmasi manual (kasir atau endpoint /bayar), '
            . 'karena tidak ada callback otomatis dari bank/acquirer.',
    ]]);
}

// ---------------------------------------------------------------- produk
if ($resource === 'produk') {
    if ($metode !== 'GET') {
        galat(405, 'Gunakan GET.', 'METODE_SALAH');
    }
    $rows = db()->query(
        'SELECT id, nama, deskripsi, harga, kategori_id FROM produk WHERE aktif = 1 ORDER BY kategori_id, nama'
    )->fetchAll();
    $data = array_map(static fn(array $r): array => [
        'id' => (int) $r['id'],
        'nama' => $r['nama'],
        'deskripsi' => $r['deskripsi'],
        'harga' => (int) $r['harga'],
        'kategori_id' => $r['kategori_id'] === null ? null : (int) $r['kategori_id'],
    ], $rows);
    jsonOut(200, ['ok' => true, 'jumlah' => count($data), 'data' => $data]);
}

// --------------------------------------------------------------- pesanan
if ($resource !== 'pesanan') {
    galat(404, 'Endpoint tidak ditemukan. Lihat dokumentasi di /app/API.md', 'TIDAK_ADA');
}

// GET /pesanan  (daftar)
if ($arg1 === '' && $metode === 'GET') {
    $status = trim((string) ($_GET['status'] ?? ''));
    $batas = max(1, min(100, (int) ($_GET['limit'] ?? 20)));

    $sql = 'SELECT kode, nama_pemesan, tipe, total, status, dibuat, dibayar FROM pesanan';
    $par = [];
    if ($status !== '') {
        if (!in_array($status, ['menunggu', 'dibayar', 'diproses', 'siap', 'selesai', 'batal'], true)) {
            galat(400, 'Status tidak dikenal.', 'STATUS_SALAH');
        }
        $sql .= ' WHERE status = ?';
        $par[] = $status;
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . $batas;

    $st = db()->prepare($sql);
    $st->execute($par);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['total'] = (int) $r['total'];
    }
    unset($r);
    jsonOut(200, ['ok' => true, 'jumlah' => count($rows), 'data' => $rows]);
}

// POST /pesanan  (buat)
if ($arg1 === '' && $metode === 'POST') {
    $items = $body['items'] ?? null;
    if (!is_array($items) || $items === []) {
        galat(400, 'Field "items" wajib berupa array minimal satu item.', 'ITEMS_KOSONG');
    }
    $namaPemesan = is_string($body['nama_pemesan'] ?? null) ? trim($body['nama_pemesan']) : '';
    if ($namaPemesan === '') {
        galat(400, 'Field "nama_pemesan" wajib diisi.', 'NAMA_KOSONG');
    }

    try {
        $hasil = buatPesanan($items, [
            'nama_pemesan' => $namaPemesan,
            'tipe' => (string) ($body['tipe'] ?? 'dine-in'),
            'catatan' => (string) ($body['catatan'] ?? ''),
        ]);
    } catch (InvalidArgumentException $e) {
        galat(400, $e->getMessage(), 'DATA_TIDAK_VALID');
    }

    jsonOut(201, ['ok' => true, 'message' => 'Pesanan dibuat.', 'data' => ambilPesanan($hasil['kode'])]);
}

if ($arg1 === '') {
    galat(405, 'Metode tidak didukung untuk /pesanan.', 'METODE_SALAH');
}

// Sisa rute butuh kode pesanan yang ada.
$kode = $arg1;

// GET /pesanan/{kode}/qris
if ($arg2 === 'qris') {
    if ($metode !== 'GET') {
        galat(405, 'Gunakan GET.', 'METODE_SALAH');
    }
    $o = ambilPesanan($kode);
    if (!$o) {
        galat(404, 'Pesanan tidak ditemukan.', 'TIDAK_ADA');
    }
    $q = qrisUntukNominal((int) $o['total']);
    if ($q['galat'] !== '') {
        galat(409, $q['galat'], 'QRIS_GAGAL');
    }
    // PNG base64 tanpa prefix, agar mudah dipakai di mobile.
    $pngB64 = $q['png'] !== '' ? substr($q['png'], strlen('data:image/png;base64,')) : '';
    jsonOut(200, ['ok' => true, 'data' => [
        'kode' => $kode,
        'nominal' => (int) $o['total'],
        'status' => $o['status'],
        'payload' => $q['payload'],
        'png_base64' => $pngB64,
        'catatan' => 'Nominal sudah terisi di dalam QR. Status lunas tidak otomatis terdeteksi.',
    ]]);
}

// POST /pesanan/{kode}/bayar
if ($arg2 === 'bayar') {
    if ($metode !== 'POST') {
        galat(405, 'Gunakan POST.', 'METODE_SALAH');
    }
    $h = konfirmasiBayar($kode);
    if (!$h['ok']) {
        galat(409, $h['pesan'], 'TIDAK_BISA');
    }
    jsonOut(200, ['ok' => true, 'message' => $h['pesan'], 'data' => ambilPesanan($kode)]);
}

// POST /pesanan/{kode}/status
if ($arg2 === 'status') {
    if ($metode !== 'POST') {
        galat(405, 'Gunakan POST.', 'METODE_SALAH');
    }
    $baru = trim((string) ($body['status'] ?? ''));
    if ($baru === '') {
        galat(400, 'Field "status" wajib diisi.', 'STATUS_KOSONG');
    }
    $h = ubahStatusPesanan($kode, $baru);
    if (!$h['ok']) {
        galat(409, $h['pesan'], 'TIDAK_BISA');
    }
    jsonOut(200, ['ok' => true, 'message' => $h['pesan'], 'data' => ambilPesanan($kode)]);
}

// GET /pesanan/{kode}
if ($arg2 === '') {
    if ($metode !== 'GET') {
        galat(405, 'Gunakan GET.', 'METODE_SALAH');
    }
    $o = ambilPesanan($kode);
    if (!$o) {
        galat(404, 'Pesanan tidak ditemukan.', 'TIDAK_ADA');
    }
    jsonOut(200, ['ok' => true, 'data' => $o]);
}

galat(404, 'Endpoint tidak ditemukan.', 'TIDAK_ADA');

