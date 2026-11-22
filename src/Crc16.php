<?php

declare(strict_types=1);

namespace QrisDinamis;

/**
 * CRC-16/CCITT-FALSE: polinomial 0x1021, nilai awal 0xFFFF, tanpa refleksi,
 * tanpa XOR akhir. Ini yang dipakai EMVCo/QRIS untuk Tag 63.
 */
final class Crc16
{
    public static function compute(string $data): int
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }
        return $crc;
    }

    /** CRC sebagai 4 karakter hex huruf besar, mis. "AB34". */
    public static function hex(string $data): string
    {
        return strtoupper(str_pad(dechex(self::compute($data)), 4, '0', STR_PAD_LEFT));
    }
}
