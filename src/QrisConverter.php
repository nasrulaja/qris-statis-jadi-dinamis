<?php

declare(strict_types=1);

namespace QrisDinamis;

/**
 * Mengubah payload QRIS statis (Tag 01 = "11") menjadi QRIS dinamis
 * (Tag 01 = "12") dengan nominal tertentu di Tag 54, lalu menghitung ulang CRC.
 *
 * Yang diubah HANYA: Tag 01, Tag 54, dan Tag 63 (CRC). Semua tag lain
 * dipertahankan persis seperti aslinya.
 *
 * Catatan: ini modifikasi payload di sisi klien. Hasilnya tidak membawa ID
 * transaksi unik dari acquirer/PJP, jadi rekonsiliasi pembayaran tetap lewat
 * nominal dan notifikasi dari penyedia pembayaranmu.
 */
final class QrisConverter
{
    public const FORMAT_AUTO = 'auto';       // 25000 -> "25000", 1500.5 -> "1500.50"
    public const FORMAT_INTEGER = 'integer'; // selalu bulat; desimal ditolak
    public const FORMAT_DECIMAL = 'decimal'; // selalu 2 desimal: "25000.00"

    private const MAX_AMOUNT_LENGTH = 13; // batas panjang Tag 54 menurut EMVCo
    private const ALLOWED_OPTIONS = ['amountFormat', 'verifyCrc', 'allowDynamicInput'];

    /**
     * @param int|float|string $amount Nominal rupiah. Disarankan int atau string ("25000", "1500.50").
     * @param array{amountFormat?: string, verifyCrc?: bool, allowDynamicInput?: bool} $options
     *        - amountFormat      : 'auto' (default) | 'integer' | 'decimal'
     *        - verifyCrc         : cek CRC string input sebelum diubah (default true)
     *        - allowDynamicInput : izinkan input yang sudah dinamis (Tag 01 = 12), nominal lama diganti (default false)
     * @throws QrisException
     */
    public static function toDynamic(string $staticQris, int|float|string $amount, array $options = []): string
    {
        $opt = self::resolveOptions($options);

        $items = self::validate($staticQris, $opt['verifyCrc']);

        $poi = self::valueOf($items, '01');
        if ($poi === '12' && !$opt['allowDynamicInput']) {
            throw new QrisException(
                QrisException::ALREADY_DYNAMIC,
                'QRIS ini sudah dinamis (Tag 01 = 12). Pakai QRIS statis (Tag 01 = 11), atau aktifkan opsi allowDynamicInput.'
            );
        }

        $amountStr = self::normalizeAmount($amount, $opt['amountFormat']);

        // Buang Tag 63 lama; akan dihitung ulang.
        $items = array_values(array_filter($items, static fn(array $i): bool => $i[0] !== '63'));

        $has54 = false;
        foreach ($items as $idx => [$tag]) {
            if ($tag === '01') {
                $items[$idx][1] = '12';
            } elseif ($tag === '54') {
                $items[$idx][1] = $amountStr;
                $has54 = true;
            }
        }

        if (!$has54) {
            // Sisipkan sebelum tag pertama yang ID-nya > 54 (urutan ID naik sesuai EMVCo).
            $insertAt = count($items);
            foreach ($items as $idx => [$tag]) {
                if ((int) $tag > 54) {
                    $insertAt = $idx;
                    break;
                }
            }
            array_splice($items, $insertAt, 0, [['54', $amountStr]]);
        }

        $body = Tlv::serialize($items) . '6304';
        return $body . Crc16::hex($body);
    }

    /**
     * Validasi struktur QRIS dan kembalikan hasil parse TLV level atas.
     *
     * @return list<array{0: string, 1: string}>
     * @throws QrisException
     */
    public static function validate(string $payload, bool $verifyCrc = true): array
    {
        $payload = trim($payload);
        if ($payload === '') {
            throw new QrisException(QrisException::EMPTY_INPUT, 'Input kosong. Berikan string QRIS statis.');
        }

        // Semua payload EMVCo MPM diawali Tag 00 (Payload Format Indicator) = "01".
        if (!str_starts_with($payload, '000201')) {
            throw new QrisException(
                QrisException::NOT_QRIS,
                'Input bukan payload QRIS/EMVCo: harus diawali "000201" (Tag 00 = 01). '
                . 'Pastikan yang dimasukkan adalah isi teks QR, bukan URL atau gambar.'
            );
        }

        $items = Tlv::parse($payload);

        foreach (['00', '01', '63'] as $required) {
            if (self::valueOf($items, $required) === null) {
                throw new QrisException(QrisException::MISSING_TAG, "Tag wajib {$required} tidak ditemukan.");
            }
        }

        $poi = self::valueOf($items, '01');
        if ($poi !== '11' && $poi !== '12') {
            throw new QrisException(
                QrisException::BAD_TAG_VALUE,
                "Tag 01 (Point of Initiation) harus \"11\" (statis) atau \"12\" (dinamis), bukan \"{$poi}\"."
            );
        }

        $last = $items[count($items) - 1];
        if ($last[0] !== '63') {
            throw new QrisException(QrisException::BAD_TAG_VALUE, 'Tag 63 (CRC) harus menjadi tag paling akhir.');
        }
        if (!preg_match('/^[0-9A-Fa-f]{4}$/', $last[1])) {
            throw new QrisException(
                QrisException::BAD_TAG_VALUE,
                'Tag 63 (CRC) harus 4 karakter heksadesimal, bukan "' . $last[1] . '".'
            );
        }

        if ($verifyCrc) {
            $expected = Crc16::hex(substr($payload, 0, -4));
            $actual = strtoupper($last[1]);
            if ($expected !== $actual) {
                throw new QrisException(
                    QrisException::BAD_CRC,
                    "CRC tidak cocok: di payload {$actual}, hasil hitung {$expected}. String QRIS kemungkinan rusak atau sudah diubah."
                );
            }
        }

        return $items;
    }

    /**
     * Normalisasi nominal menjadi isi Tag 54.
     *
     * @throws QrisException
     */
    public static function normalizeAmount(int|float|string $amount, string $format = self::FORMAT_AUTO): string
    {
        if (!in_array($format, [self::FORMAT_AUTO, self::FORMAT_INTEGER, self::FORMAT_DECIMAL], true)) {
            throw new QrisException(
                QrisException::INVALID_OPTION,
                "amountFormat \"{$format}\" tidak dikenal. Pilih: auto, integer, atau decimal."
            );
        }

        if (is_float($amount)) {
            if (!is_finite($amount) || abs($amount - round($amount, 2)) > 1e-9) {
                throw new QrisException(
                    QrisException::INVALID_AMOUNT,
                    'Nominal float tidak valid atau punya lebih dari 2 desimal. Gunakan string, mis. "1500.50".'
                );
            }
            $str = sprintf('%.2F', $amount);
        } else {
            $str = trim((string) $amount);
        }

        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $str, $m)) {
            throw new QrisException(
                QrisException::INVALID_AMOUNT,
                "Nominal \"{$str}\" tidak valid. Pakai angka polos tanpa pemisah ribuan/koma, "
                . 'maksimal 2 desimal, mis. 25000 atau 25000.50.'
            );
        }

        $intPart = ltrim($m[1], '0');
        if ($intPart === '') {
            $intPart = '0';
        }
        $frac = str_pad($m[2] ?? '', 2, '0');

        if ($intPart === '0' && $frac === '00') {
            throw new QrisException(QrisException::INVALID_AMOUNT, 'Nominal harus lebih dari 0.');
        }

        switch ($format) {
            case self::FORMAT_INTEGER:
                if ($frac !== '00') {
                    throw new QrisException(
                        QrisException::INVALID_AMOUNT,
                        "Mode integer tidak menerima desimal (nominal: {$intPart}.{$frac})."
                    );
                }
                $result = $intPart;
                break;
            case self::FORMAT_DECIMAL:
                $result = "{$intPart}.{$frac}";
                break;
            default:
                $result = $frac === '00' ? $intPart : "{$intPart}.{$frac}";
        }

        if (strlen($result) > self::MAX_AMOUNT_LENGTH) {
            throw new QrisException(
                QrisException::INVALID_AMOUNT,
                'Nominal terlalu besar: Tag 54 maksimal ' . self::MAX_AMOUNT_LENGTH . " karakter (hasil: {$result})."
            );
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $options
     * @return array{amountFormat: string, verifyCrc: bool, allowDynamicInput: bool}
     */
    private static function resolveOptions(array $options): array
    {
        foreach (array_keys($options) as $key) {
            if (!in_array($key, self::ALLOWED_OPTIONS, true)) {
                throw new QrisException(
                    QrisException::INVALID_OPTION,
                    "Opsi \"{$key}\" tidak dikenal. Opsi yang tersedia: " . implode(', ', self::ALLOWED_OPTIONS) . '.'
                );
            }
        }

        $format = $options['amountFormat'] ?? self::FORMAT_AUTO;
        if (!is_string($format) || !in_array($format, [self::FORMAT_AUTO, self::FORMAT_INTEGER, self::FORMAT_DECIMAL], true)) {
            throw new QrisException(QrisException::INVALID_OPTION, 'amountFormat harus salah satu dari: auto, integer, decimal.');
        }

        return [
            'amountFormat' => $format,
            'verifyCrc' => (bool) ($options['verifyCrc'] ?? true),
            'allowDynamicInput' => (bool) ($options['allowDynamicInput'] ?? false),
        ];
    }

    /**
     * @param list<array{0: string, 1: string}> $items
     */
    private static function valueOf(array $items, string $tag): ?string
    {
        foreach ($items as [$t, $v]) {
            if ($t === $tag) {
                return $v;
            }
        }
        return null;
    }
}
