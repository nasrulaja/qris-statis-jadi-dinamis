# qris-dinamis

Ubah **QRIS statis** menjadi **QRIS dinamis** dengan nominal tertentu, langsung dari string payload-nya (EMVCo TLV). Tanpa API, tanpa dependensi, ditulis dalam PHP.

Yang dilakukan modul ini:

1. Parse payload menjadi TLV dan validasi (Tag 00, 01, 63, struktur, dan CRC input).
2. Ubah Tag 01 dari `11` menjadi `12`.
3. Isi/ganti Tag 54 (nominal) di urutan yang benar (setelah Tag 53, sebelum Tag 58).
4. Hitung ulang CRC16-CCITT (poly `0x1021`, init `0xFFFF`) untuk Tag 63.
5. Opsional: buat gambar QR dalam PNG.

Tag lain **tidak diubah sama sekali** (diuji di unit test).

## Persyaratan

- PHP 8.1 atau lebih baru
- Ekstensi `gd` (hanya untuk membuat PNG)
- Tidak perlu `composer install`; tes memakai runner mini bawaan, bukan PHPUnit.

## Struktur

```
bin/qris                  CLI
src/QrisConverter.php     API utama: toDynamic(), validate(), normalizeAmount()
src/Tlv.php               parser/serializer TLV
src/Crc16.php             CRC16-CCITT (FALSE)
src/QrisException.php     error dengan kode stabil
src/QrCode/QrEncoder.php  encoder QR Code (byte mode, versi 1-40) tanpa dependensi
src/QrCode/PngRenderer.php  matriks QR -> PNG via GD
src/autoload.php          autoloader (alternatif Composer)
tests/                    unit test (php tests/run.php)
examples/contoh.php       contoh pemakaian library
tools/verify_png.py       opsional: scan PNG dengan OpenCV untuk memastikan terbaca
```

## Pemakaian CLI

```bash
# string lewat argumen
php bin/qris -a 25000 -q "000201010211...6304XXXX"

# dari file, sekalian simpan PNG
php bin/qris -a 25000 -f qris-statis.txt -o qris-25000.png

# dari stdin, nominal desimal dengan 2 angka di belakang koma
cat qris-statis.txt | php bin/qris -a 1500.50 --format decimal
```

String QRIS dinamis dicetak ke stdout; pesan error ke stderr.
Exit code: `0` sukses, `1` input/validasi gagal, `2` salah pemakaian.

| Opsi | Keterangan |
|---|---|
| `-a, --amount` | Nominal rupiah (wajib), mis. `25000` atau `1500.50` |
| `-q, --qris` / `-f, --file` / stdin | Sumber string QRIS statis |
| `-o, --png` | Simpan gambar QR ke file PNG |
| `--format` | `auto` (default), `integer`, `decimal` |
| `--scale` | Piksel per modul PNG (default 8) |
| `--ecc` | Level koreksi error QR: `L`, `M` (default), `Q`, `H` |
| `--no-verify-crc` | Lewati cek CRC input (tidak disarankan) |
| `--allow-dynamic` | Izinkan input yang sudah dinamis; nominal lama diganti |

## Pemakaian library

```php
require __DIR__ . '/src/autoload.php';

use QrisDinamis\QrisConverter;
use QrisDinamis\QrisException;
use QrisDinamis\QrCode\PngRenderer;

try {
    $dynamic = QrisConverter::toDynamic($staticQris, 25000);
    file_put_contents('qris.png', PngRenderer::fromText($dynamic));
} catch (QrisException $e) {
    echo "[{$e->errorCode}] {$e->getMessage()}";
}
```

Dengan Composer, cukup `require 'vendor/autoload.php'` (PSR-4 namespace `QrisDinamis\`).

### Format nominal (Tag 54)

Input boleh `int`, `string`, atau `float` (string paling aman). Format output diatur lewat opsi `amountFormat`:

| Mode | `25000` | `"25000.00"` | `"1500.5"` |
|---|---|---|---|
| `auto` (default) | `25000` | `25000` | `1500.50` |
| `integer` | `25000` | `25000` | **error** (desimal ditolak) |
| `decimal` | `25000.00` | `25000.00` | `1500.50` |

Nominal harus > 0, angka polos (tanpa `25.000` atau `25,000`), maksimal 2 desimal, dan hasilnya maksimal 13 karakter.

```php
QrisConverter::toDynamic($static, '1500.50', ['amountFormat' => 'decimal']);
```

### Opsi lain

- `verifyCrc` (default `true`): cek CRC string input sebelum diubah.
- `allowDynamicInput` (default `false`): terima input yang sudah dinamis dan ganti nominalnya.

### Kode error

`QrisException::$errorCode` bernilai tetap, jadi aman dipakai di `switch`:

| Kode | Arti |
|---|---|
| `EMPTY_INPUT` | Input kosong |
| `NOT_QRIS` | Bukan payload QRIS (tidak diawali `000201`) |
| `BAD_TLV` | Struktur TLV rusak/terpotong |
| `DUPLICATE_TAG` | Ada tag level atas yang muncul lebih dari sekali |
| `MISSING_TAG` | Tag wajib (01 atau 63) tidak ada |
| `BAD_TAG_VALUE` | Tag 01 bukan 11/12, atau Tag 63 tidak di akhir/bukan 4 hex |
| `BAD_CRC` | CRC input tidak cocok (pesan menyebut nilai di payload dan hasil hitung) |
| `ALREADY_DYNAMIC` | Input sudah dinamis (Tag 01 = 12) |
| `INVALID_AMOUNT` | Nominal tidak valid |
| `INVALID_OPTION` | Opsi/parameter tidak dikenal atau di luar rentang |
| `QR_TOO_LONG` | Data terlalu panjang untuk QR Code |
| `GD_MISSING` | Ekstensi GD tidak aktif (PNG tidak bisa dibuat) |

## Tes

```bash
php tests/run.php        # atau: composer test
```

Mencakup: (a) statis valid menjadi dinamis valid, (b) nominal berbeda, (c) CRC salah, (d) input bukan QRIS, ditambah kasus tepi (Tag 54 lama diganti, Tag 55/57 tetap utuh, TLV rusak, tag duplikat, format nominal), vektor uji standar CRC16 dan Reed-Solomon, struktur QR/PNG, dan CLI.

Semua string di tes adalah **data dummy**. CRC dan hasil yang diharapkan dihitung terpisah dengan Python (`binascii.crc_hqx`), bukan dengan kode PHP yang diuji.

Untuk memastikan PNG benar-benar bisa dipindai (butuh `pip install opencv-python-headless`):

```bash
python3 tools/verify_png.py qris-25000.png
```

## Yang perlu kamu tahu

- **Ini modifikasi payload di sisi klien**, bukan QRIS dinamis resmi dari acquirer/PJP. Hasilnya tidak membawa ID transaksi unik, jadi pencocokan pembayaran tetap lewat nominal dan notifikasi dari penyedia pembayaranmu. Aplikasi pembayaran memverifikasi merchant di sisi server; sebelum dipakai ke pelanggan, **coba dulu dengan nominal kecil** di aplikasi yang kamu targetkan.
- Pakai hanya untuk QRIS milikmu sendiri.
- Tag 55/56/57 (biaya layanan/tip) yang sudah ada di QRIS statis dibiarkan apa adanya. Modul ini tidak menambah atau mengubahnya.
- Panjang TLV dihitung dalam byte. QRIS umumnya ASCII, jadi sama dengan jumlah karakter.
- **Jangan commit string QRIS asli** ke repo publik (berisi NMID, nama, dan data merchant). Simpan di file/env di luar repo. `.gitignore` sudah mengabaikan `*.png` dan `qris-statis*.txt`.
- Level ECC default `M` (cukup untuk QRIS dan ukuran gambarnya tetap ringkas). Untuk dicetak di tempat berdebu/lecet, pakai `Q` atau `H`.
