# Web Kafe — Demo Ujian (Pembeli / Admin / Owner)

Aplikasi jual-beli kafe sederhana memakai library `qris-dinamis` untuk menampilkan QR.
**Pembayaran disimulasikan** — tidak ada dana yang benar-benar berpindah.

## Menjalankan

``bash
php -S 127.0.0.1:8000 -t .
``

Buka <http://127.0.0.1:8000/app/>

Database dibuat otomatis di `data/panel.sqlite`. Untuk membuat ulang dari nol:
hapus file itu, lalu akses halaman apa pun.

## Akun demo

| Username | Password   | Peran   | Bisa apa                                            |
|----------|------------|---------|-----------------------------------------------------|
| `user`   | `user123`  | pembeli | pesan menu, beri rating/komentar, cetak struk       |
| `admin`  | `admin123` | kasir   | terima pesanan, konfirmasi dana masuk, cetak struk  |
| `owner`  | `owner123` | pemilik | atur toko & QRIS, kelola produk, laporan, balas ulasan |

## Fitur

**Pembeli**
- Menu dengan filter kategori dan **rating bintang** di tiap kartu
- Halaman detail produk + daftar komentar pembeli lain
- Keranjang, checkout, halaman bayar dengan QR
- **Beri rating 1–5 bintang dan komentar** — hanya untuk produk yang sudah dibayar, sekali per produk
- Riwayat pesanan, **struk siap cetak** (80mm), ganti password

**Admin (kasir)**
- Daftar pesanan dengan filter status
- **Konfirmasi dana masuk** → status `dibayar` + notifikasi
- Alur Proses → Siap → Selesai, atau Batalkan
- Notifikasi pesanan baru, dana masuk, dan ulasan baru (badge auto-refresh 5 detik)
- Cetak struk

**Owner**
- Pengaturan nama toko, alamat, kota, kode pos, ucapan struk, pajak
- Kolom **isi teks QRIS statis** (diubah jadi QRIS dinamis bernominal otomatis)
- Kolom URL gambar QRIS toko (cadangan bila konversi dinamis gagal)
- Kelola produk: tambah, ubah, nyalakan/matikan, hapus
- Laporan: omzet harian, omzet per hari (14 terakhir), menu terlaris
- **Kelola ulasan**: lihat semua, balas komentar, hapus ulasan

## API untuk aplikasi lain

Ada API JSON di `/app/api.php` supaya kalau mau dipakai mobile/POS/integrasi
tidak perlu bongkar kode. Aktifkan dengan mengisi **API Key** di panel owner,
lalu kirim header `X-API-Key`.

``bash
curl -H "X-API-Key: kunci-kamu" "http://localhost:8000/app/api.php?r=produk"
``

Endpoint: `info`, `produk`, `pesanan` (GET/POST), `pesanan/{kode}`,
`pesanan/{kode}/qris` (QRIS dinamis + PNG base64), `pesanan/{kode}/bayar`,
`pesanan/{kode}/status`. Dokumentasi lengkap: **[API.md](API.md)**.

Logika pesanan dipakai bersama oleh web dan API lewat `app/pesanan.php`, jadi
perilakunya tidak mungkin berbeda antara dua jalur itu.

## Alur

1. Pembeli pilih menu → keranjang → checkout → kode `ORD-...`
2. Halaman bayar menampilkan **QRIS dinamis** bernominal otomatis, plus QR simulasi `DEMO-PAY|kode|toko|nominal`
3. Pesanan masuk ke Dapur + notifikasi
4. Admin klik **Konfirmasi dana masuk** → `dibayar` + notifikasi dana masuk
5. Admin Proses → Siap → Selesai
6. Pembeli bisa cetak struk dan memberi rating/komentar
7. Owner membaca & membalas ulasan

## Keamanan yang sudah ada

- **CSRF token** di semua form POST
- **Password di-hash** dengan `password_hash` (bcrypt)
- **Rate limiting login**: maksimal 5 percobaan gagal per IP dalam 10 menit
- **Cek peran** di setiap halaman (`wajibPeran`) — pembeli & admin tidak bisa membuka area lain
- **Escape HTML** (`e()`) di semua output
- **Struk hanya untuk pemilik pesanan** atau admin/owner
- **Prepared statement** di semua query

Yang belum (di luar cakupan demo): HTTPS, session cookie `Secure`, dan logging audit.

## Penting soal QRIS

Halaman bayar menampilkan **QRIS dinamis** sebagai QR utama, plus QR demo kecil di bawah.

**1. QRIS Dinamis (utama, di atas)** — dibuat otomatis per pesanan

Aplikasi menyimpan **isi teks** QRIS statis toko, lalu mengubahnya menjadi QRIS
dinamis dengan `QrisConverter` (`src/QrisConverter.php`):

| Tag | Sebelum (statis) | Sesudah (dinamis) |
|---|---|---|
| `01` Point of Initiation | `11` | **`12`** |
| `54` Nominal | *(tidak ada)* | **total pesanan**, mis. `56000` |
| `63` CRC | `B761` | **dihitung ulang** |

Tag lain (merchant, NMID, MCC, kota) dipertahankan persis. Jadi pembeli cukup
memindai — **nominal sudah terisi**, tidak perlu mengetik.

Asal QRIS yang dipakai (contoh, sudah dianonimkan):

| Field | Nilai |
|---|---|
| Format | EMVCo QRIS, statis (`01` = `11`) |
| Merchant | Contoh Merchant |
| Kota | KOTA CONTOH (12345) |
| MCC | 5812 |
| Acquirer | PJP CONTOH (`ID.CONTOH.WWW`), NMID `ID0000000000001` |
| CRC16 | `D766` — contoh payload di tes, sah |

**Batas penting:** ini konversi payload di sisi klien, **bukan** QRIS dinamis resmi
dari acquirer. Tidak ada ID transaksi unik dari PJP, jadi kasir tetap harus klik
**"Konfirmasi dana masuk"** secara manual — sistem tidak bisa membaca mutasi
rekening. Verifikasi otomatis butuh QRIS dinamis dari PJP + API callback.

**Mengganti QRIS:** buka panel owner (`/app/owner/`) → kolom
**"Isi teks QRIS statis"**, tempel isi teks QRIS (diawali `000201...`). Payload
divalidasi saat disimpan (struktur EMVCo + CRC) dan ditolak bila salah. Kolom
**"URL gambar QRIS toko"** hanya dipakai sebagai cadangan bila konversi gagal.

**2. QR Demo (sekunder, kecil di bawah)** — bukan QRIS

Isinya `DEMO-PAY|kode|toko|nominal`. Hanya simulasi internal, tidak bisa dipakai bayar.


## Struktur

``
app/
  bootstrap.php        skema DB, seed, helper (setting, CSRF, rating, rate limit)
  layout.php           header/nav/footer + cek peran
  index.php            menu publik + keranjang
  produk.php           detail produk + rating/komentar
  checkout.php         buat pesanan
  bayar.php            QR + status pembayaran
  pesanan-saya.php     riwayat pesanan pembeli
  struk.php            struk siap cetak
  akun.php             ganti password
  masuk.php / keluar.php
  admin/index.php      dapur & kasir
  admin/notifikasi.php daftar notifikasi
  admin/cek-baru.php   endpoint JSON badge
  owner/index.php      pengaturan toko + laporan
  owner/produk.php     kelola produk
  owner/ulasan.php     kelola & balas ulasan
  views/               template halaman
  assets/              kafe.css, kafe.js
  tests/               unit, flow, halaman, fitur
``

## Uji

Server harus jalan untuk tes HTTP.

``bash
php tests/run.php          # 36 tes library QR
php app/tests/unit.php     # 8  tes helper (rating, rate limit, migrasi)
php app/tests/flow.php     # alur pesan -> bayar -> konfirmasi
php app/tests/halaman.php  # 12 cek halaman + otorisasi peran
php app/tests/fitur.php    # 21 tes rating, struk, password, rate limit
``
