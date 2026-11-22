<?php

declare(strict_types=1);

use QrisDinamis\Crc16;
use QrisDinamis\QrCode\PngRenderer;
use QrisDinamis\QrCode\QrEncoder;
use QrisDinamis\QrisConverter;
use QrisDinamis\QrisException;
use QrisDinamis\Tlv;

function fx(string $key): string
{
    static $fixtures = null;
    $fixtures ??= require __DIR__ . '/fixtures.php';
    return $fixtures[$key];
}

/** Bangun payload valid dari badan TLV (tanpa Tag 63) untuk kasus uji tepi. */
function withCrc(string $bodyWithout63): string
{
    $body = $bodyWithout63 . '6304';
    return $body . Crc16::hex($body);
}

/** @return array<string, string> tag => nilai */
function tagMap(string $payload): array
{
    $map = [];
    foreach (Tlv::parse($payload) as [$tag, $value]) {
        $map[$tag] = $value;
    }
    return $map;
}

// ------------------------------------------------------------------ CRC16

test('CRC16-CCITT: vektor uji standar "123456789" = 29B1', function () {
    assertSame('29B1', Crc16::hex('123456789'));
});

test('CRC16: selalu 4 karakter hex huruf besar (ada nol di depan)', function () {
    assertSame(4, strlen(Crc16::hex('')));
    assertSame('FFFF', Crc16::hex(''));
    assertSame(1, preg_match('/^[0-9A-F]{4}$/', Crc16::hex('abc')));
});

test('Fixture dummy valid menurut validator (CRC dihitung independen via Python)', function () {
    foreach (['static', 'static_with_54', 'static_with_fee', 'expect_25000', 'expect_1500_50'] as $key) {
        QrisConverter::validate(fx($key));
    }
    assertTrue(true);
});

// ------------------------------- (a) QRIS statis valid -> dinamis valid

test('[a] statis valid -> dinamis valid, sama persis dengan hasil referensi independen', function () {
    assertSame(fx('expect_25000'), QrisConverter::toDynamic(fx('static'), 25000));
});

test('[a] Tag 01 berubah 11 -> 12, Tag 54 terisi, CRC hasil akhir benar', function () {
    $dynamic = QrisConverter::toDynamic(fx('static'), 25000);
    $map = tagMap($dynamic);

    assertSame('11', tagMap(fx('static'))['01']);
    assertSame('12', $map['01']);
    assertSame('25000', $map['54']);
    assertSame(Crc16::hex(substr($dynamic, 0, -4)), substr($dynamic, -4));
    assertSame('6304', substr($dynamic, -8, 4));
});

test('[a] tag lain tidak berubah (hanya 01, 54, 63 yang berbeda)', function () {
    $before = tagMap(fx('static'));
    $after = tagMap(QrisConverter::toDynamic(fx('static'), 25000));

    foreach ($before as $tag => $value) {
        $tag = (string) $tag; // kunci numerik seperti '63' otomatis jadi int di PHP
        if (in_array($tag, ['01', '63'], true)) {
            continue;
        }
        assertSame($value, $after[$tag], "Tag {$tag} berubah padahal seharusnya tidak.");
    }
    assertSame(count($before) + 1, count($after), 'Hanya Tag 54 yang boleh bertambah.');
});

test('[a] Tag 54 disisipkan berurutan: setelah Tag 53, sebelum Tag 58', function () {
    $tags = array_column(Tlv::parse(QrisConverter::toDynamic(fx('static'), 25000)), 0);
    $i = array_search('54', $tags, true);
    assertSame('53', $tags[$i - 1]);
    assertSame('58', $tags[$i + 1]);
    assertSame('63', end($tags));
});

test('[a] Tag 54 lama diganti, tidak dobel', function () {
    $dynamic = QrisConverter::toDynamic(fx('static_with_54'), 25000);
    assertSame(fx('expect_25000'), $dynamic);
    assertSame(1, substr_count(implode(',', array_column(Tlv::parse($dynamic), 0)), '54'));
});

test('[a] Tag 55/57 (biaya) tetap utuh dan Tag 54 masuk sebelum Tag 55', function () {
    $before = tagMap(fx('static_with_fee'));
    $dynamic = QrisConverter::toDynamic(fx('static_with_fee'), 25000);
    $tags = array_column(Tlv::parse($dynamic), 0);

    $i = array_search('54', $tags, true);
    assertSame('53', $tags[$i - 1]);
    assertSame('55', $tags[$i + 1]);
    assertSame($before['55'], tagMap($dynamic)['55']);
    assertSame($before['57'], tagMap($dynamic)['57']);
    QrisConverter::validate($dynamic);
});

test('[a] spasi/newline di sekitar input diabaikan; CRC huruf kecil diterima', function () {
    assertSame(fx('expect_25000'), QrisConverter::toDynamic("  \n" . fx('static') . "\r\n", 25000));
    $lower = substr(fx('static'), 0, -4) . strtolower(substr(fx('static'), -4));
    assertSame(fx('expect_25000'), QrisConverter::toDynamic($lower, 25000));
});

// ------------------------------------------------- (b) nominal berbeda

test('[b] nominal berbeda menghasilkan Tag 54 dan CRC berbeda', function () {
    $a = QrisConverter::toDynamic(fx('static'), 10000);
    $b = QrisConverter::toDynamic(fx('static'), 25000);

    assertTrue($a !== $b);
    assertSame('10000', tagMap($a)['54']);
    assertSame('25000', tagMap($b)['54']);
    assertTrue(substr($a, -4) !== substr($b, -4), 'CRC harus ikut berubah.');
    QrisConverter::validate($a);
    QrisConverter::validate($b);
});

test('[b] nominal desimal 1500.50 sama dengan hasil referensi independen', function () {
    assertSame(fx('expect_1500_50'), QrisConverter::toDynamic(fx('static'), '1500.50'));
    assertSame(fx('expect_1500_50'), QrisConverter::toDynamic(fx('static'), 1500.5));
    assertSame(fx('expect_1500_50'), QrisConverter::toDynamic(fx('static'), '1500.5'));
});

test('[b] berbagai bentuk input nominal 25000 dinormalisasi sama (mode auto)', function () {
    foreach ([25000, '25000', '25000.00', 25000.0, ' 25000 ', '025000'] as $input) {
        assertSame(fx('expect_25000'), QrisConverter::toDynamic(fx('static'), $input), 'Input: ' . var_export($input, true));
    }
});

test('[b] amountFormat: integer, decimal, auto', function () {
    assertSame(fx('expect_25000'), QrisConverter::toDynamic(fx('static'), '25000.00', ['amountFormat' => 'integer']));
    assertSame('25000.00', tagMap(QrisConverter::toDynamic(fx('static'), 25000, ['amountFormat' => 'decimal']))['54']);
    assertSame('1500.50', tagMap(QrisConverter::toDynamic(fx('static'), '1500.50', ['amountFormat' => 'auto']))['54']);
    assertSame('1500.50', tagMap(QrisConverter::toDynamic(fx('static'), '1500.50', ['amountFormat' => 'decimal']))['54']);
});

test('[b] mode integer menolak nominal berdesimal', function () {
    assertQrisError(QrisException::INVALID_AMOUNT, fn() => QrisConverter::toDynamic(fx('static'), '1500.50', ['amountFormat' => 'integer']));
});

test('[b] normalizeAmount langsung', function () {
    assertSame('7', QrisConverter::normalizeAmount('7'));
    assertSame('7.00', QrisConverter::normalizeAmount('7', 'decimal'));
    assertSame('0.50', QrisConverter::normalizeAmount('0.5'));
    assertSame('1234567890123', QrisConverter::normalizeAmount('1234567890123')); // tepat 13 karakter
});

test('[b] nominal tidak valid ditolak dengan INVALID_AMOUNT', function () {
    $invalid = [0, '0', '0.00', -5, '-5', 'abc', '', '25.000', '25,000', '1e5', '25000.555', '12345678901234', NAN, INF, 1.234];
    foreach ($invalid as $amount) {
        assertQrisError(
            QrisException::INVALID_AMOUNT,
            fn() => QrisConverter::toDynamic(fx('static'), $amount)
        );
    }
    // Mode decimal menambah ".00" sehingga bisa melewati batas 13 karakter
    assertQrisError(QrisException::INVALID_AMOUNT, fn() => QrisConverter::normalizeAmount('99999999999', 'decimal'));
});

test('[b] opsi tidak dikenal ditolak dengan INVALID_OPTION', function () {
    assertQrisError(QrisException::INVALID_OPTION, fn() => QrisConverter::toDynamic(fx('static'), 1000, ['foo' => true]));
    assertQrisError(QrisException::INVALID_OPTION, fn() => QrisConverter::toDynamic(fx('static'), 1000, ['amountFormat' => 'rupiah']));
});

// ------------------------------------------------------ (c) CRC salah

test('[c] CRC salah di akhir string -> BAD_CRC dengan pesan jelas', function () {
    $bad = substr(fx('static'), 0, -1) . '7'; // FD16 -> FD17
    $e = assertQrisError(QrisException::BAD_CRC, fn() => QrisConverter::toDynamic($bad, 25000));
    assertTrue(str_contains($e->getMessage(), 'FD17') && str_contains($e->getMessage(), 'FD16'), 'Pesan harus menyebut CRC di payload dan hasil hitung.');
});

test('[c] isi payload diubah tanpa memperbarui CRC -> BAD_CRC', function () {
    $tampered = str_replace('TOKO CONTOH DUMMY', 'TOKO CONTOH DUMMX', fx('static'));
    assertQrisError(QrisException::BAD_CRC, fn() => QrisConverter::toDynamic($tampered, 25000));
});

test('[c] verifyCrc=false melewati pengecekan dan tetap menghitung ulang CRC yang benar', function () {
    $bad = substr(fx('static'), 0, -4) . '0000';
    assertSame(fx('expect_25000'), QrisConverter::toDynamic($bad, 25000, ['verifyCrc' => false]));
});

// ------------------------------------------------ (d) input bukan QRIS

test('[d] input bukan QRIS -> NOT_QRIS', function () {
    foreach (['hello', 'https://example.com/pay', '12345', '00020', 'ID.CO.QRIS.WWW'] as $input) {
        assertQrisError(QrisException::NOT_QRIS, fn() => QrisConverter::toDynamic($input, 25000));
    }
});

test('[d] input kosong -> EMPTY_INPUT', function () {
    foreach (['', '   ', "\n\t "] as $input) {
        assertQrisError(QrisException::EMPTY_INPUT, fn() => QrisConverter::toDynamic($input, 25000));
    }
});

test('[d] TLV rusak -> BAD_TLV (terpotong, panjang bukan angka, sisa data kurang)', function () {
    assertQrisError(QrisException::BAD_TLV, fn() => QrisConverter::toDynamic(substr(fx('static'), 0, 60), 25000));
    assertQrisError(QrisException::BAD_TLV, fn() => QrisConverter::toDynamic('00020101AB11', 25000));
    assertQrisError(QrisException::BAD_TLV, fn() => QrisConverter::toDynamic('00020101021', 25000));
});

test('[d] tag wajib hilang -> MISSING_TAG (00 diwajibkan lewat awalan, 01 dan 63 dicek)', function () {
    $e = assertQrisError(QrisException::MISSING_TAG, fn() => QrisConverter::toDynamic('000201' . '010211' . '52045499', 25000));
    assertTrue(str_contains($e->getMessage(), '63'));

    $e = assertQrisError(QrisException::MISSING_TAG, fn() => QrisConverter::toDynamic('0002015204549963040000', 25000));
    assertTrue(str_contains($e->getMessage(), '01'));
});

test('[d] Tag 01 bukan 11/12, Tag 63 tidak di akhir atau bukan hex -> BAD_TAG_VALUE', function () {
    assertQrisError(QrisException::BAD_TAG_VALUE, fn() => QrisConverter::toDynamic(withCrc('000201010299'), 25000));
    assertQrisError(QrisException::BAD_TAG_VALUE, fn() => QrisConverter::toDynamic('000201010211' . '63040000' . '5802ID', 25000));
    assertQrisError(QrisException::BAD_TAG_VALUE, fn() => QrisConverter::toDynamic('000201010211' . '6304ZZZZ', 25000));
    assertQrisError(QrisException::BAD_TAG_VALUE, fn() => QrisConverter::toDynamic('000201010211' . '6303ABC', 25000));
});

test('[d] tag duplikat -> DUPLICATE_TAG', function () {
    assertQrisError(QrisException::DUPLICATE_TAG, fn() => QrisConverter::toDynamic(withCrc('000201010211' . '52045499' . '52045499'), 25000));
});

test('input yang sudah dinamis ditolak, kecuali allowDynamicInput', function () {
    assertQrisError(QrisException::ALREADY_DYNAMIC, fn() => QrisConverter::toDynamic(fx('expect_25000'), 1500.50));
    assertSame(
        fx('expect_1500_50'),
        QrisConverter::toDynamic(fx('expect_25000'), '1500.50', ['allowDynamicInput' => true])
    );
});

// ------------------------------------------------------------ gambar QR

test('QR: Reed-Solomon cocok dengan vektor uji standar ("HELLO WORLD" 1-M)', function () {
    $data = [32, 91, 11, 120, 209, 114, 220, 77, 67, 64, 236, 17, 236, 17, 236, 17];
    assertSame([196, 35, 39, 119, 235, 215, 231, 226, 93, 23], QrEncoder::reedSolomon($data, 10));
});

test('QR: struktur matriks (ukuran, finder pattern, timing, dark module)', function () {
    $qr = QrEncoder::encode(fx('expect_25000'));
    $m = $qr['modules'];
    $n = $qr['size'];

    assertSame($qr['version'] * 4 + 17, $n);
    assertSame($n, count($m));
    assertSame($n, count($m[0]));

    foreach ([[0, 0], [6, 0], [0, 6], [6, 6], [3, 3], [$n - 1, 0], [0, $n - 1], [$n - 7, 0]] as [$x, $y]) {
        assertTrue($m[$y][$x], "Modul finder ({$x},{$y}) harus gelap.");
    }
    assertTrue(!$m[1][1], 'Cincin dalam finder harus terang.');
    assertTrue($m[6][8] && !$m[6][9], 'Pola timing harus selang-seling.');
    assertTrue($m[$n - 8][8], 'Dark module harus ada.');
});

test('QR: deterministik, dan mask bisa dipaksa', function () {
    assertSame(QrEncoder::encode(fx('expect_25000')), QrEncoder::encode(fx('expect_25000')));
    assertSame(3, QrEncoder::encode(fx('expect_25000'), 'M', 3)['mask']);
});

test('QR: semua level ECC menghasilkan versi yang naik (L <= M <= Q <= H)', function () {
    $v = [];
    foreach (['L', 'M', 'Q', 'H'] as $level) {
        $v[] = QrEncoder::encode(fx('expect_25000'), $level)['version'];
    }
    $sorted = $v;
    sort($sorted);
    assertSame($sorted, $v);
});

test('QR: data terlalu panjang -> QR_TOO_LONG; level ECC salah -> INVALID_OPTION', function () {
    assertQrisError(QrisException::QR_TOO_LONG, fn() => QrEncoder::encode(str_repeat('A', 3000), 'H'));
    assertQrisError(QrisException::INVALID_OPTION, fn() => QrEncoder::encode('abc', 'Z'));
    assertQrisError(QrisException::INVALID_OPTION, fn() => QrEncoder::encode('abc', 'M', 9));
});

test('PNG: header valid dan dimensi = (modul + 2*border) * scale', function () {
    $png = PngRenderer::fromText(fx('expect_25000'), 'M', 8, 4);
    assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));

    $size = QrEncoder::encode(fx('expect_25000'))['size'];
    $info = getimagesizefromstring($png);
    assertSame(($size + 8) * 8, $info[0]);
    assertSame(($size + 8) * 8, $info[1]);
});

test('PNG: parameter tidak valid -> INVALID_OPTION', function () {
    assertQrisError(QrisException::INVALID_OPTION, fn() => PngRenderer::fromText('abc', 'M', 0));
    assertQrisError(QrisException::INVALID_OPTION, fn() => PngRenderer::fromText('abc', 'M', 8, -1));
});

// ------------------------------------------------------------------ CLI

test('CLI: hasil di stdout sama dengan library; error -> exit code 1', function () {
    $bin = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../bin/qris');

    exec($bin . ' -a 25000 -q ' . escapeshellarg(fx('static')) . ' 2>&1', $out, $code);
    assertSame(0, $code);
    assertSame(fx('expect_25000'), trim(implode("\n", $out)));

    $out = [];
    exec($bin . ' -a 25000 -q hello 2>&1', $out, $code);
    assertSame(1, $code);
    assertTrue(str_contains(implode("\n", $out), 'Error:'));

    $out = [];
    exec($bin . ' -q ' . escapeshellarg(fx('static')) . ' 2>&1', $out, $code);
    assertSame(2, $code, 'Tanpa --amount harus exit code 2 (salah pakai).');
});
