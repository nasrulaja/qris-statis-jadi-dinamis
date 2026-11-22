<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';

use QrisDinamis\QrCode\PngRenderer;
use QrisDinamis\QrisConverter;
use QrisDinamis\QrisException;

// String DUMMY (bukan merchant sungguhan). Ganti dengan isi teks QRIS statismu,
// idealnya dibaca dari file/env, jangan ditulis permanen di kode.
$static = '00020101021126630014ID.CO.QRIS.WWW0115ID12345678901230215ID00000000000010303UMI'
    . '51440014ID.CO.QRIS.WWW0215ID10200000000010303UMI5204549953033605802ID5917TOKO CONTOH DUMMY'
    . '6007JAKARTA61051011062070703A016304FD16';

try {
    $dynamic = QrisConverter::toDynamic($static, 25000);
    echo "QRIS dinamis Rp25.000:\n{$dynamic}\n\n";

    // Nominal desimal, format 2 angka di belakang koma dipaksa
    echo QrisConverter::toDynamic($static, '1500.50', ['amountFormat' => 'decimal']), "\n\n";

    // Gambar QR (PNG)
    $out = __DIR__ . '/qris-25000.png';
    file_put_contents($out, PngRenderer::fromText($dynamic, 'M', 8));
    echo "PNG disimpan di {$out}\n";
} catch (QrisException $e) {
    fwrite(STDERR, "[{$e->errorCode}] {$e->getMessage()}\n");
    exit(1);
}
