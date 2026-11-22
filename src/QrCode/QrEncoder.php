<?php

declare(strict_types=1);

namespace QrisDinamis\QrCode;

use QrisDinamis\QrisException;

/**
 * Encoder QR Code (ISO/IEC 18004) mode byte, versi 1-40, tanpa dependensi.
 * Menghasilkan matriks modul (true = gelap); penggambaran ke PNG ada di PngRenderer.
 */
final class QrEncoder
{
    /** Codeword koreksi error per blok. Indeks = versi (indeks 0 tidak dipakai). */
    private const ECC_PER_BLOCK = [
        'L' => [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'M' => [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        'Q' => [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'H' => [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];

    /** Jumlah blok koreksi error. Indeks = versi. */
    private const NUM_BLOCKS = [
        'L' => [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        'M' => [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        'Q' => [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        'H' => [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    /** Bit indikator level koreksi di format info. */
    private const FORMAT_BITS = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];

    /**
     * @param string   $text  Data (diperlakukan sebagai byte mentah).
     * @param string   $ecc   Level koreksi error: L, M (default), Q, atau H.
     * @param int|null $mask  Paksa pola mask 0-7; null = pilih otomatis berdasar penalti terendah.
     * @return array{version: int, mask: int, size: int, modules: list<list<bool>>}
     * @throws QrisException
     */
    public static function encode(string $text, string $ecc = 'M', ?int $mask = null): array
    {
        $ecc = strtoupper($ecc);
        if (!isset(self::ECC_PER_BLOCK[$ecc])) {
            throw new QrisException(QrisException::INVALID_OPTION, "Level ECC \"{$ecc}\" tidak dikenal. Pilih: L, M, Q, H.");
        }
        if ($mask !== null && ($mask < 0 || $mask > 7)) {
            throw new QrisException(QrisException::INVALID_OPTION, 'Mask harus 0-7.');
        }

        $n = strlen($text);
        $version = null;
        for ($v = 1; $v <= 40; $v++) {
            $countBits = $v <= 9 ? 8 : 16;
            if (4 + $countBits + 8 * $n <= self::numDataCodewords($v, $ecc) * 8) {
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            throw new QrisException(
                QrisException::QR_TOO_LONG,
                "Data {$n} byte terlalu panjang untuk QR Code level {$ecc}. Coba level ECC lebih rendah."
            );
        }

        $data = self::buildDataCodewords($text, $version, $ecc);
        $all = self::addEccAndInterleave($data, $version, $ecc);
        return self::buildMatrix($all, $version, $ecc, $mask);
    }

    // ---------------------------------------------------------------- data

    /** @return list<int> */
    private static function buildDataCodewords(string $text, int $version, string $ecc): array
    {
        $bits = [];
        $append = static function (int $value, int $len) use (&$bits): void {
            for ($i = $len - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };

        $n = strlen($text);
        $append(0b0100, 4);                       // mode byte
        $append($n, $version <= 9 ? 8 : 16);      // jumlah karakter
        for ($i = 0; $i < $n; $i++) {
            $append(ord($text[$i]), 8);
        }

        $capacity = self::numDataCodewords($version, $ecc) * 8;
        $append(0, min(4, $capacity - count($bits)));   // terminator
        $append(0, (8 - count($bits) % 8) % 8);          // rata ke byte
        for ($pad = 0xEC; count($bits) < $capacity; $pad ^= 0xEC ^ 0x11) {
            $append($pad, 8);
        }

        $codewords = [];
        for ($i = 0, $c = count($bits); $i < $c; $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $bits[$i + $j];
            }
            $codewords[] = $byte;
        }
        return $codewords;
    }

    /**
     * @param list<int> $data
     * @return list<int>
     */
    private static function addEccAndInterleave(array $data, int $version, string $ecc): array
    {
        $numBlocks = self::NUM_BLOCKS[$ecc][$version];
        $blockEccLen = self::ECC_PER_BLOCK[$ecc][$version];
        $rawCodewords = intdiv(self::numRawDataModules($version), 8);
        $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
        $shortBlockLen = intdiv($rawCodewords, $numBlocks);

        $blocks = [];
        $k = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $datLen = $shortBlockLen - $blockEccLen + ($i < $numShortBlocks ? 0 : 1);
            $dat = array_slice($data, $k, $datLen);
            $k += $datLen;
            $eccBytes = self::reedSolomon($dat, $blockEccLen);
            if ($i < $numShortBlocks) {
                $dat[] = 0; // penanda; dilewati saat interleave
            }
            $blocks[] = array_merge($dat, $eccBytes);
        }

        $result = [];
        for ($i = 0; $i <= $shortBlockLen; $i++) {
            for ($j = 0; $j < $numBlocks; $j++) {
                if ($i !== $shortBlockLen - $blockEccLen || $j >= $numShortBlocks) {
                    $result[] = $blocks[$j][$i];
                }
            }
        }
        return $result;
    }

    private static function numRawDataModules(int $ver): int
    {
        $result = (16 * $ver + 128) * $ver + 64;
        if ($ver >= 2) {
            $numAlign = intdiv($ver, 7) + 2;
            $result -= (25 * $numAlign - 10) * $numAlign - 55;
            if ($ver >= 7) {
                $result -= 36;
            }
        }
        return $result;
    }

    private static function numDataCodewords(int $ver, string $ecc): int
    {
        return intdiv(self::numRawDataModules($ver), 8)
            - self::ECC_PER_BLOCK[$ecc][$ver] * self::NUM_BLOCKS[$ecc][$ver];
    }

    // ------------------------------------------------------- Reed-Solomon

    /**
     * Sisa pembagian polinomial di GF(256) (modulus 0x11D) = codeword koreksi.
     *
     * @internal Dipublikasikan agar bisa diuji dengan vektor uji standar.
     * @param list<int> $data
     * @return list<int>
     */
    public static function reedSolomon(array $data, int $degree): array
    {
        $divisor = self::rsDivisor($degree);
        $result = array_fill(0, $degree, 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            for ($i = 0; $i < $degree; $i++) {
                $result[$i] ^= self::gfMul($divisor[$i], $factor);
            }
        }
        return $result;
    }

    /** @return list<int> */
    private static function rsDivisor(int $degree): array
    {
        $result = array_fill(0, $degree - 1, 0);
        $result[] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMul($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMul($root, 0x02);
        }
        return $result;
    }

    private static function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    }

    // ------------------------------------------------------------- matriks

    /**
     * @param list<int> $codewords
     * @return array{version: int, mask: int, size: int, modules: list<list<bool>>}
     */
    private static function buildMatrix(array $codewords, int $version, string $ecc, ?int $forcedMask): array
    {
        $size = $version * 4 + 17;
        $m = array_fill(0, $size, array_fill(0, $size, false));
        $f = $m; // penanda function module

        self::drawFunctionPatterns($m, $f, $size, $version, $ecc);
        self::drawCodewords($m, $f, $size, $codewords);

        $best = $forcedMask;
        if ($best === null) {
            $minPenalty = PHP_INT_MAX;
            for ($mask = 0; $mask < 8; $mask++) {
                self::applyMask($m, $f, $size, $mask);
                self::drawFormatBits($m, $f, $size, $ecc, $mask);
                $p = self::penalty($m, $size);
                if ($p < $minPenalty) {
                    $minPenalty = $p;
                    $best = $mask;
                }
                self::applyMask($m, $f, $size, $mask); // XOR lagi = batalkan
            }
        }

        self::applyMask($m, $f, $size, $best);
        self::drawFormatBits($m, $f, $size, $ecc, $best);

        return ['version' => $version, 'mask' => $best, 'size' => $size, 'modules' => $m];
    }

    private static function setFn(array &$m, array &$f, int $x, int $y, bool $dark): void
    {
        $m[$y][$x] = $dark;
        $f[$y][$x] = true;
    }

    private static function drawFunctionPatterns(array &$m, array &$f, int $size, int $version, string $ecc): void
    {
        for ($i = 0; $i < $size; $i++) {
            self::setFn($m, $f, 6, $i, $i % 2 === 0);
            self::setFn($m, $f, $i, 6, $i % 2 === 0);
        }

        foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $dist = max(abs($dx), abs($dy));
                    $xx = $cx + $dx;
                    $yy = $cy + $dy;
                    if ($xx >= 0 && $xx < $size && $yy >= 0 && $yy < $size) {
                        self::setFn($m, $f, $xx, $yy, $dist !== 2 && $dist !== 4);
                    }
                }
            }
        }

        $align = self::alignmentPositions($version, $size);
        $n = count($align);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue; // bertabrakan dengan finder pattern
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        self::setFn($m, $f, $align[$i] + $dx, $align[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }

        self::drawFormatBits($m, $f, $size, $ecc, 0); // sekadar memesan area
        self::drawVersion($m, $f, $size, $version);
    }

    /** @return list<int> */
    private static function alignmentPositions(int $ver, int $size): array
    {
        if ($ver === 1) {
            return [];
        }
        $numAlign = intdiv($ver, 7) + 2;
        $step = $ver === 32 ? 26 : ((int) ceil(($ver * 4 + 4) / ($numAlign * 2 - 2))) * 2;
        $result = [6];
        for ($pos = $size - 7; count($result) < $numAlign; $pos -= $step) {
            array_splice($result, 1, 0, [$pos]);
        }
        return $result;
    }

    private static function drawFormatBits(array &$m, array &$f, int $size, string $ecc, int $mask): void
    {
        $data = (self::FORMAT_BITS[$ecc] << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        $bit = static fn(int $i): bool => (($bits >> $i) & 1) === 1;

        for ($i = 0; $i <= 5; $i++) {
            self::setFn($m, $f, 8, $i, $bit($i));
        }
        self::setFn($m, $f, 8, 7, $bit(6));
        self::setFn($m, $f, 8, 8, $bit(7));
        self::setFn($m, $f, 7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            self::setFn($m, $f, 14 - $i, 8, $bit($i));
        }

        for ($i = 0; $i < 8; $i++) {
            self::setFn($m, $f, $size - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            self::setFn($m, $f, 8, $size - 15 + $i, $bit($i));
        }
        self::setFn($m, $f, 8, $size - 8, true); // dark module
    }

    private static function drawVersion(array &$m, array &$f, int $size, int $version): void
    {
        if ($version < 7) {
            return;
        }
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            $a = $size - 11 + $i % 3;
            $b = intdiv($i, 3);
            self::setFn($m, $f, $a, $b, $dark);
            self::setFn($m, $f, $b, $a, $dark);
        }
    }

    /** @param list<int> $data */
    private static function drawCodewords(array &$m, array $f, int $size, array $data): void
    {
        $totalBits = count($data) * 8;
        $i = 0;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $size - 1 - $vert : $vert;
                    if (!$f[$y][$x] && $i < $totalBits) {
                        $m[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private static function applyMask(array &$m, array $f, int $size, int $mask): void
    {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (!$f[$y][$x] && self::maskBit($mask, $x, $y)) {
                    $m[$y][$x] = !$m[$y][$x];
                }
            }
        }
    }

    private static function maskBit(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => ($x + $y) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($x + $y) % 3 === 0,
            4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
            5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
            6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
            default => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
        };
    }

    /** Skor penalti mask (aturan N1-N4 ISO 18004). */
    private static function penalty(array $m, int $size): int
    {
        $result = 0;

        for ($axis = 0; $axis < 2; $axis++) {
            for ($a = 0; $a < $size; $a++) {
                $line = [];
                for ($b = 0; $b < $size; $b++) {
                    $line[] = $axis === 0 ? $m[$a][$b] : $m[$b][$a];
                }

                $run = 1;
                for ($b = 1; $b < $size; $b++) {
                    if ($line[$b] === $line[$b - 1]) {
                        $run++;
                        if ($run === 5) {
                            $result += 3;
                        } elseif ($run > 5) {
                            $result++;
                        }
                    } else {
                        $run = 1;
                    }
                }

                $str = implode('', array_map(static fn(bool $v): string => $v ? '1' : '0', $line));
                $result += 40 * (substr_count($str, '10111010000') + substr_count($str, '00001011101'));
            }
        }

        $dark = 0;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($m[$y][$x]) {
                    $dark++;
                }
                if ($x < $size - 1 && $y < $size - 1) {
                    $c = $m[$y][$x];
                    if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                        $result += 3;
                    }
                }
            }
        }

        $total = $size * $size;
        $k = (int) ceil(abs($dark * 20 - $total * 10) / $total) - 1;
        if ($k > 0) {
            $result += $k * 10;
        }

        return $result;
    }
}
