<?php

declare(strict_types=1);

// API kecil untuk halaman web (demo, bukan produksi).
//   GET  ?action=preview&amount=25000&format=auto   -> JSON hasil konversi
//   POST action=png dengan body JSON {text, ecc, scale} -> PNG mentah
//
// PENTING: hanya untuk dijalankan lokal (php -S 127.0.0.1:8000). Tidak ada
// autentikasi, rate limit, atau validasi input tingkat produksi di sini.

require __DIR__ . '/../src/autoload.php';

use QrisDinamis\QrCode\PngRenderer;
use QrisDinamis\QrisConverter;
use QrisDinamis\QrisException;

/** String DUMMY — bukan merchant sungguhan. */
const QRIS_DUMMY = '00020101021126630014ID.CO.QRIS.WWW0115ID12345678901230215ID00000000000010303UMI'
    . '51440014ID.CO.QRIS.WWW0215ID10200000000010303UMI5204549953033605802ID5917TOKO CONTOH DUMMY'
    . '6007JAKARTA61051011062070703A016304FD16';

/** @return list<array{tag: string, value: string, label: string}> */
function parseTags(string $payload): array
{
    $labels = [
        '00' => 'Payload Format Indicator',
        '01' => 'Point of Initiation (11 statis / 12 dinamis)',
        '51' => 'Merchant Account (domestik)',
        '52' => 'Merchant Category Code',
        '53' => 'Mata uang (360 = IDR)',
        '54' => 'Nominal transaksi',
        '55' => 'Indikator biaya',
        '57' => 'Biaya persen',
        '58' => 'Negara',
        '59' => 'Nama merchant',
        '60' => 'Kota',
        '61' => 'Kode pos',
        '62' => 'Data tambahan',
        '63' => 'CRC16',
    ];

    $tags = [];
    foreach (QrisDinamis\Tlv::parse($payload) as [$tag, $value]) {
        $tags[] = ['tag' => $tag, 'value' => $value, 'label' => $labels[$tag] ?? ''];
    }
    return $tags;
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

if ($isPost) {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input) || ($input['action'] ?? '') !== 'png') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'code' => 'BAD_REQUEST', 'message' => 'Aksi tidak dikenal.']);
        exit;
    }
    try {
        $png = PngRenderer::fromText(
            (string) ($input['text'] ?? ''),
            (string) ($input['ecc'] ?? 'M'),
            (int) ($input['scale'] ?? 8)
        );
        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($png));
        header('Cache-Control: no-store');
        echo $png;
    } catch (QrisException $e) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'code' => $e->errorCode, 'message' => $e->getMessage()]);
    }
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$amount = (string) ($_GET['amount'] ?? '25000');
$format = (string) ($_GET['format'] ?? 'auto');

try {
    $dynamic = QrisConverter::toDynamic(QRIS_DUMMY, $amount, ['amountFormat' => $format]);
} catch (QrisException $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'code' => $e->errorCode,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'static' => QRIS_DUMMY,
    'dynamic' => $dynamic,
    'staticTags' => parseTags(QRIS_DUMMY),
    'dynamicTags' => parseTags($dynamic),
    'length' => ['static' => strlen(QRIS_DUMMY), 'dynamic' => strlen($dynamic)],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
