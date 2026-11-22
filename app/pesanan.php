<?php

declare(strict_types=1);

// Logika inti pesanan — dipakai bersama oleh halaman web DAN API.
// Ditaruh di sini supaya web & API tidak pernah berbeda perilaku.

/** Buat kode pesanan unik, mis. ORD-261122-7B7C. */
function buatKodePesanan(): string
{
    do {
        $k = 'ORD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $st = db()->prepare('SELECT 1 FROM pesanan WHERE kode = ?');
        $st->execute([$k]);
    } while ($st->fetchColumn() !== false);
    return $k;
}

/**
 * Buat pesanan dari daftar item.
 *
 * @param list<array{produk_id?: int, nama?: string, harga?: int, jumlah: int}> $items
 *        Boleh pakai produk_id (harga diambil dari DB, aman) atau nama+harga bebas.
 * @param array{nama_pemesan: string, tipe?: string, catatan?: string, pengguna_id?: ?int} $data
 * @return array{kode: string, total: int, id: int}
 * @throws InvalidArgumentException bila data tidak valid
 */
function buatPesanan(array $items, array $data): array
{
    $nama = trim((string) ($data['nama_pemesan'] ?? ''));
    $tipe = (string) ($data['tipe'] ?? 'dine-in');
    $catatan = trim((string) ($data['catatan'] ?? ''));

    if ($nama === '') {
        throw new InvalidArgumentException('Nama pemesan wajib diisi.');
    }
    if (!in_array($tipe, ['dine-in', 'take-away'], true)) {
        throw new InvalidArgumentException('Tipe pesanan harus dine-in atau take-away.');
    }
    if ($items === []) {
        throw new InvalidArgumentException('Pesanan harus punya minimal satu item.');
    }

    // Hitung baris item; harga dari DB bila produk_id diberikan.
    $baris = [];
    $total = 0;
    foreach ($items as $it) {
        $jml = (int) ($it['jumlah'] ?? 0);
        if ($jml < 1) {
            throw new InvalidArgumentException('Jumlah item minimal 1.');
        }

        if (isset($it['produk_id']) && $it['produk_id'] !== '') {
            $st = db()->prepare('SELECT nama, harga FROM produk WHERE id = ? AND aktif = 1');
            $st->execute([(int) $it['produk_id']]);
            $p = $st->fetch();
            if (!$p) {
                throw new InvalidArgumentException('Produk id ' . (int) $it['produk_id'] . ' tidak ada atau nonaktif.');
            }
            $namaProduk = (string) $p['nama'];
            $harga = (int) $p['harga'];
        } else {
            $namaProduk = trim((string) ($it['nama'] ?? ''));
            $harga = (int) ($it['harga'] ?? 0);
            if ($namaProduk === '') {
                throw new InvalidArgumentException('Item tanpa produk_id harus punya nama.');
            }
            if ($harga < 0) {
                throw new InvalidArgumentException('Harga tidak boleh negatif.');
            }
        }

        $baris[] = ['nama' => $namaProduk, 'harga' => $harga, 'jml' => $jml];
        $total += $harga * $jml;
    }

    if ($total < 1) {
        throw new InvalidArgumentException('Total pesanan harus lebih dari 0.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $kode = buatKodePesanan();
        $st = $pdo->prepare(
            'INSERT INTO pesanan (kode, pengguna_id, nama_pemesan, tipe, catatan, total, status, dibuat)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $kode, $data['pengguna_id'] ?? null, $nama, $tipe, $catatan, $total,
            'menunggu', date('Y-m-d H:i:s'),
        ]);
        $pid = (int) $pdo->lastInsertId();

        $stItem = $pdo->prepare(
            'INSERT INTO item_pesanan (pesanan_id, nama_produk, harga, jumlah) VALUES (?,?,?,?)'
        );
        foreach ($baris as $b) {
            $stItem->execute([$pid, $b['nama'], $b['harga'], $b['jml']]);
        }

        tambahNotifikasi(
            'pesanan_baru',
            'Pesanan baru ' . $kode,
            $nama . ' · ' . rupiah($total) . ' · ' . $tipe,
            $kode
        );

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['kode' => $kode, 'total' => $total, 'id' => $pid];
}

/**
 * Konfirmasi dana masuk untuk pesanan.
 *
 * @return array{ok: bool, pesan: string}
 */
function konfirmasiBayar(string $kode): array
{
    $st = db()->prepare('SELECT * FROM pesanan WHERE kode = ?');
    $st->execute([$kode]);
    $o = $st->fetch();

    if (!$o) {
        return ['ok' => false, 'pesan' => 'Pesanan tidak ditemukan.'];
    }
    if ($o['status'] !== 'menunggu') {
        return ['ok' => false, 'pesan' => 'Pesanan ini sudah dibayar.'];
    }

    $up = db()->prepare("UPDATE pesanan SET status = 'dibayar', dibayar = ? WHERE kode = ?");
    $up->execute([date('Y-m-d H:i:s'), $kode]);

    tambahNotifikasi(
        'dana_masuk',
        'Dana masuk ' . rupiah((int) $o['total']),
        'Pembayaran ' . $kode . ' dari ' . $o['nama_pemesan'] . ' terkonfirmasi.',
        $kode
    );

    return ['ok' => true, 'pesan' => 'Pembayaran ' . $kode . ' dikonfirmasi. Dana masuk ' . rupiah((int) $o['total']) . '.'];
}

/**
 * Ubah status pesanan (diproses/siap/selesai/batal).
 *
 * @return array{ok: bool, pesan: string}
 */
function ubahStatusPesanan(string $kode, string $baru): array
{
    $st = db()->prepare('SELECT * FROM pesanan WHERE kode = ?');
    $st->execute([$kode]);
    $o = $st->fetch();

    if (!$o) {
        return ['ok' => false, 'pesan' => 'Pesanan tidak ditemukan.'];
    }

    if ($baru === 'batal') {
        $up = db()->prepare("UPDATE pesanan SET status = 'batal' WHERE kode = ?");
        $up->execute([$kode]);
        return ['ok' => true, 'pesan' => 'Pesanan ' . $kode . ' dibatalkan.'];
    }

    if (!in_array($baru, ['diproses', 'siap', 'selesai'], true)) {
        return ['ok' => false, 'pesan' => 'Status tidak dikenal.'];
    }
    if ($o['status'] === 'menunggu') {
        return ['ok' => false, 'pesan' => 'Konfirmasi pembayaran dulu sebelum memproses.'];
    }

    $up = db()->prepare('UPDATE pesanan SET status = ? WHERE kode = ?');
    $up->execute([$baru, $kode]);
    return ['ok' => true, 'pesan' => 'Status ' . $kode . ' diubah menjadi ' . $baru . '.'];
}

/**
 * Ambil pesanan lengkap dengan item, atau null.
 *
 * @return array<string, mixed>|null
 */
function ambilPesanan(string $kode): ?array
{
    $st = db()->prepare('SELECT * FROM pesanan WHERE kode = ?');
    $st->execute([$kode]);
    $o = $st->fetch();
    if (!$o) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM item_pesanan WHERE pesanan_id = ?');
    $st->execute([(int) $o['id']]);
    $o['items'] = $st->fetchAll();
    return $o;
}

/**
 * QRIS dinamis untuk nominal tertentu: payload + PNG (data URI).
 *
 * @return array{payload: string, png: string, galat: string}
 */
function qrisUntukNominal(int $nominal, int $skala = 8): array
{
    $hasil = ['payload' => '', 'png' => '', 'galat' => ''];
    $statis = trim(setting('qris_statis_payload'));

    if ($statis === '') {
        $hasil['galat'] = 'Isi teks QRIS statis belum diatur owner.';
        return $hasil;
    }

    try {
        $payload = QrisDinamis\QrisConverter::toDynamic($statis, $nominal);
        $hasil['payload'] = $payload;
        if (function_exists('imagecreatetruecolor')) {
            $hasil['png'] = 'data:image/png;base64,' . base64_encode(
                QrisDinamis\QrCode\PngRenderer::fromText($payload, 'M', $skala)
            );
        }
    } catch (Throwable $e) {
        $hasil['galat'] = $e->getMessage();
    }

    return $hasil;
}

