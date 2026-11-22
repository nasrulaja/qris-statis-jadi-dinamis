<?php

declare(strict_types=1);

namespace QrisDinamis;

/**
 * Parser/serializer TLV level atas untuk payload EMVCo.
 *
 * Format tiap elemen: ID (2 digit) + panjang (2 digit desimal) + nilai.
 * Hanya level atas yang diurai; isi template bersarang (mis. Tag 26-51, 62)
 * dibiarkan apa adanya sebagai string mentah, jadi tidak ikut berubah.
 * Panjang dihitung dalam byte (QRIS umumnya ASCII, jadi sama dengan karakter).
 */
final class Tlv
{
    /**
     * @return list<array{0: string, 1: string}> daftar [tag, nilai] sesuai urutan asli
     * @throws QrisException
     */
    public static function parse(string $payload): array
    {
        $items = [];
        $seen = [];
        $pos = 0;
        $total = strlen($payload);

        while ($pos < $total) {
            if ($total - $pos < 4) {
                throw new QrisException(
                    QrisException::BAD_TLV,
                    "Payload rusak di posisi {$pos}: sisa data kurang dari 4 karakter (header tag+panjang tidak lengkap)."
                );
            }

            $tag = substr($payload, $pos, 2);
            $lenStr = substr($payload, $pos + 2, 2);

            if (!ctype_digit($tag)) {
                throw new QrisException(
                    QrisException::BAD_TLV,
                    "ID tag di posisi {$pos} bukan 2 digit angka (\"{$tag}\")."
                );
            }
            if (!ctype_digit($lenStr)) {
                throw new QrisException(
                    QrisException::BAD_TLV,
                    "Panjang untuk Tag {$tag} bukan 2 digit angka (\"{$lenStr}\")."
                );
            }

            $len = (int) $lenStr;
            $valueStart = $pos + 4;
            if ($valueStart + $len > $total) {
                throw new QrisException(
                    QrisException::BAD_TLV,
                    "Tag {$tag} menyatakan panjang {$len}, tetapi data hanya tersisa " . ($total - $valueStart) . ' karakter.'
                );
            }

            if (isset($seen[$tag])) {
                throw new QrisException(
                    QrisException::DUPLICATE_TAG,
                    "Tag {$tag} muncul lebih dari sekali; EMVCo mewajibkan tag level atas unik."
                );
            }
            $seen[$tag] = true;

            $items[] = [$tag, substr($payload, $valueStart, $len)];
            $pos = $valueStart + $len;
        }

        return $items;
    }

    /**
     * @param list<array{0: string, 1: string}> $items
     */
    public static function serialize(array $items): string
    {
        $out = '';
        foreach ($items as [$tag, $value]) {
            $out .= $tag . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
        }
        return $out;
    }
}
