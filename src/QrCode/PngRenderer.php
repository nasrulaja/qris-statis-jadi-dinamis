<?php

declare(strict_types=1);

namespace QrisDinamis\QrCode;

use QrisDinamis\QrisException;

/** Menggambar matriks QR ke PNG memakai ekstensi GD bawaan PHP. */
final class PngRenderer
{
    /**
     * @param list<list<bool>> $modules Matriks dari QrEncoder::encode()['modules']
     * @param int $scale  Piksel per modul (1-64)
     * @param int $border Lebar quiet zone dalam modul (standar minimal 4)
     * @return string Isi file PNG (byte mentah)
     * @throws QrisException
     */
    public static function render(array $modules, int $scale = 8, int $border = 4): string
    {
        // Validasi parameter lebih dulu: kesalahan input harus dilaporkan sebagai
        // INVALID_OPTION, tidak boleh tertutup oleh status ekstensi GD.
        if ($scale < 1 || $scale > 64) {
            throw new QrisException(QrisException::INVALID_OPTION, 'scale harus antara 1 dan 64.');
        }
        if ($border < 0 || $border > 32) {
            throw new QrisException(QrisException::INVALID_OPTION, 'border harus antara 0 dan 32.');
        }
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
            throw new QrisException(
                QrisException::GD_MISSING,
                'Ekstensi PHP GD tidak aktif, jadi PNG tidak bisa dibuat. Aktifkan ext-gd, atau pakai string QRIS-nya saja.'
            );
        }

        $n = count($modules);
        $px = ($n + 2 * $border) * $scale;

        $img = imagecreatetruecolor($px, $px);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $px - 1, $px - 1, $white);

        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($modules[$y][$x]) {
                    $x0 = ($x + $border) * $scale;
                    $y0 = ($y + $border) * $scale;
                    imagefilledrectangle($img, $x0, $y0, $x0 + $scale - 1, $y0 + $scale - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($img);
        return (string) ob_get_clean();
    }

    /**
     * Jalan pintas: teks -> PNG.
     *
     * @throws QrisException
     */
    public static function fromText(string $text, string $ecc = 'M', int $scale = 8, int $border = 4): string
    {
        return self::render(QrEncoder::encode($text, $ecc)['modules'], $scale, $border);
    }
}
