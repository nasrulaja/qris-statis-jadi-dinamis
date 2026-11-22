<?php

declare(strict_types=1);

// Koneksi database SQLite (satu file, tanpa server) + skema.
// Jalankan sekali: php app/bootstrap.php  -> membuat data/panel.sqlite

require __DIR__ . '/../src/autoload.php';

// Jam toko memakai WIB, bukan zona timezone bawaan PHP (Europe/Berlin di mesin ini).
date_default_timezone_set('Asia/Jakarta');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $file = $dir . '/panel.sqlite';

    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    migrate($pdo);
    seed($pdo);
    return $pdo;
}

/** Baca pengaturan langsung via PDO — aman dipakai saat seed (tanpa rekursi db()). */
function settingRaw(PDO $pdo, string $key, string $default = ''): string
{
    $st = $pdo->prepare('SELECT nilai FROM pengaturan WHERE kunci = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : (string) $v;
}

function setSettingRaw(PDO $pdo, string $key, string $value): void
{
    $st = $pdo->prepare(
        'INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)
         ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai'
    );
    $st->execute([$key, $value]);
}

function migrate(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS pengaturan (
            kunci TEXT PRIMARY KEY,
            nilai TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS pengguna (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            sandi    TEXT NOT NULL,
            nama     TEXT NOT NULL,
            peran    TEXT NOT NULL CHECK (peran IN ('pembeli','admin','owner')),
            dibuat   TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS kategori (
            id     INTEGER PRIMARY KEY AUTOINCREMENT,
            nama   TEXT NOT NULL,
            urutan INTEGER NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS produk (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            nama        TEXT NOT NULL,
            deskripsi   TEXT NOT NULL DEFAULT '',
            harga       INTEGER NOT NULL,
            kategori_id INTEGER,
            aktif       INTEGER NOT NULL DEFAULT 1,
            dibuat      TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS pesanan (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            kode         TEXT NOT NULL UNIQUE,
            pengguna_id  INTEGER,
            nama_pemesan TEXT NOT NULL DEFAULT '',
            tipe         TEXT NOT NULL DEFAULT 'dine-in',
            catatan      TEXT NOT NULL DEFAULT '',
            total        INTEGER NOT NULL,
            status       TEXT NOT NULL DEFAULT 'menunggu',
            dibuat       TEXT NOT NULL,
            dibayar      TEXT
        );

        CREATE TABLE IF NOT EXISTS item_pesanan (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            pesanan_id  INTEGER NOT NULL,
            nama_produk TEXT NOT NULL,
            harga       INTEGER NOT NULL,
            jumlah      INTEGER NOT NULL,
            FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS notifikasi (
            id     INTEGER PRIMARY KEY AUTOINCREMENT,
            jenis  TEXT NOT NULL,
            judul  TEXT NOT NULL,
            isi    TEXT NOT NULL DEFAULT '',
            kode   TEXT NOT NULL DEFAULT '',
            dibaca INTEGER NOT NULL DEFAULT 0,
            waktu  TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS ulasan (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            produk_id   INTEGER NOT NULL,
            pengguna_id INTEGER,
            nama        TEXT NOT NULL,
            bintang     INTEGER NOT NULL CHECK (bintang BETWEEN 1 AND 5),
            komentar    TEXT NOT NULL DEFAULT '',
            balasan     TEXT NOT NULL DEFAULT '',
            dibuat      TEXT NOT NULL,
            FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS percobaan_login (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL DEFAULT '',
            ip      TEXT NOT NULL DEFAULT '',
            waktu   INTEGER NOT NULL
        );
        SQL);

    // Satu pesanan hanya boleh diulas sekali.
    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_ulasan_pesanan ON ulasan (produk_id, pengguna_id)
         WHERE pengguna_id IS NOT NULL'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_penulis_login ON percobaan_login (ip, waktu)');


    // Kolom tambahan (aman dijalankan berulang).
    $kolom = [
        'produk'  => ['deskripsi' => "TEXT NOT NULL DEFAULT ''", 'kategori_id' => 'INTEGER'],
        'pesanan' => [
            'pengguna_id'  => 'INTEGER',
            'nama_pemesan' => "TEXT NOT NULL DEFAULT ''",
            'tipe'         => "TEXT NOT NULL DEFAULT 'dine-in'",
            'catatan'      => "TEXT NOT NULL DEFAULT ''",
        ],
    ];
    foreach ($kolom as $tabel => $defs) {
        $ada = [];
        foreach ($pdo->query("PRAGMA table_info({$tabel})") as $r) {
            $ada[$r['name']] = true;
        }
        foreach ($defs as $nama => $tipe) {
            if (!isset($ada[$nama])) {
                $pdo->exec("ALTER TABLE {$tabel} ADD COLUMN {$nama} {$tipe}");
            }
        }
    }
}

function seed(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM pengguna')->fetchColumn() === 0) {
        $st = $pdo->prepare('INSERT INTO pengguna (username, sandi, nama, peran, dibuat) VALUES (?,?,?,?,?)');
        $now = date('Y-m-d H:i:s');
        $st->execute(['owner', password_hash('owner123', PASSWORD_DEFAULT), 'Pemilik', 'owner', $now]);
        $st->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Kasir', 'admin', $now]);
        $st->execute(['user', password_hash('user123', PASSWORD_DEFAULT), 'Pelanggan', 'pembeli', $now]);
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM kategori')->fetchColumn() === 0) {
        $st = $pdo->prepare('INSERT INTO kategori (nama, urutan) VALUES (?,?)');
        $st->execute(['Kopi', 1]);
        $st->execute(['Non-Kopi', 2]);
        $st->execute(['Makanan', 3]);
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM produk')->fetchColumn() === 0) {
        $now = date('Y-m-d H:i:s');
        $st = $pdo->prepare(
            'INSERT INTO produk (nama, deskripsi, harga, kategori_id, aktif, dibuat) VALUES (?,?,?,?,1,?)'
        );
        $st->execute(['Espresso', 'Satu shot, pekat', 18000, 1, $now]);
        $st->execute(['Kopi Susu', 'Favorit, manis pas', 22000, 1, $now]);
        $st->execute(['Cappuccino', 'Busa lembut', 25000, 1, $now]);
        $st->execute(['Matcha Latte', 'Teh hijau Jepang', 27000, 2, $now]);
        $st->execute(['Croissant', 'Butter, renyah', 20000, 3, $now]);
        $st->execute(['Nasi Goreng', 'Porsi kafe', 32000, 3, $now]);
    }

    if (settingRaw($pdo, 'nama_toko') === '') {
        setSettingRaw($pdo, 'nama_toko', 'Kopi Senja');
        setSettingRaw($pdo, 'kota', 'Bandung');
        setSettingRaw($pdo, 'alamat', 'Jl. Braga No. 12');
        setSettingRaw($pdo, 'kode_pos', '40111');
        setSettingRaw($pdo, 'catatan', 'Terima kasih sudah mampir!');
        setSettingRaw($pdo, 'pajak_persen', '0');
        setSettingRaw($pdo, 'qris_statis', '/app/assets/img/qris-statis.png');
    }
}


/** Nilai pengaturan dengan default (cache per-request di $GLOBALS). */
function setting(string $key, string $default = ''): string
{
    $cache = $GLOBALS['__setting_cache'] ?? null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT kunci, nilai FROM pengaturan') as $r) {
            $cache[$r['kunci']] = (string) $r['nilai'];
        }
        $GLOBALS['__setting_cache'] = $cache;
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function setSetting(string $key, string $value): void
{
    setSettingRaw(db(), $key, $value);
    unset($GLOBALS['__setting_cache']); // nilai baru langsung terbaca
}

function rupiah(int $n): string
{
    return 'Rp' . number_format($n, 0, ',', '.');
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// ------------------------------------------------------------------ CSRF
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

function csrfValid(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals((string) $_SESSION['csrf'], (string) $_POST['csrf']);
}

// ------------------------------------------------------------ notifikasi
function tambahNotifikasi(string $jenis, string $judul, string $isi = '', string $kode = ''): void
{
    $st = db()->prepare(
        'INSERT INTO notifikasi (jenis, judul, isi, kode, dibaca, waktu) VALUES (?,?,?,?,0,?)'
    );
    $st->execute([$jenis, $judul, $isi, $kode, date('Y-m-d H:i:s')]);
}

function hitungNotifikasiBaru(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM notifikasi WHERE dibaca = 0')->fetchColumn();
}

// ----------------------------------------------------------------- rating
/** Ringkasan rating per produk: [produk_id => ['rata' => 4.5, 'jml' => 12]]. */
function ringkasanRating(): array
{
    $out = [];
    $sql = 'SELECT produk_id, AVG(bintang) rata, COUNT(*) jml FROM ulasan GROUP BY produk_id';
    foreach (db()->query($sql) as $r) {
        $out[(int) $r['produk_id']] = ['rata' => (float) $r['rata'], 'jml' => (int) $r['jml']];
    }
    return $out;
}

/** Bintang siap tampil, mis. "★★★★☆". */
function bintang(int $n): string
{
    $n = max(0, min(5, $n));
    return str_repeat('★', $n) . '<span style="color:#3a4152">' . str_repeat('★', 5 - $n) . '</span>';
}

// --------------------------------------------------------- anti brute-force
function ipSekarang(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** Catat satu percobaan login gagal. */
function catatGagalLogin(string $username): void
{
    $st = db()->prepare('INSERT INTO percobaan_login (username, ip, waktu) VALUES (?,?,?)');
    $st->execute([mb_substr($username, 0, 60), ipSekarang(), time()]);
}

/** Sisa percobaan login yang diizinkan dalam jendela waktu. */
function sisaPercobaanLogin(int $batas = 5, int $jendelaDetik = 600): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM percobaan_login WHERE ip = ? AND waktu > ?');
    $st->execute([ipSekarang(), time() - $jendelaDetik]);
    return max(0, $batas - (int) $st->fetchColumn());
}

function bersihkanGagalLogin(): void
{
    $st = db()->prepare('DELETE FROM percobaan_login WHERE ip = ?');
    $st->execute([ipSekarang()]);
}


if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    db();
    echo "Database siap: data/panel.sqlite\n";
}

// Logika pesanan bersama (web + API). Di-load di akhir agar semua helper
// di atas (db, setting, rupiah, tambahNotifikasi) sudah tersedia.
require_once __DIR__ . '/pesanan.php';

