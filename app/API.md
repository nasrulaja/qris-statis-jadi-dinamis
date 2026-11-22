# API qris-dinamis

API JSON supaya sistem ini gampang dipakai aplikasi lain (mobile, POS, integrasi).

Base URL: `http://localhost:8000/app/api.php`

## Mengaktifkan

1. Buka panel owner `/app/owner/`
2. Isi kolom **API Key** dengan kata sandi acak yang panjang, mis.
   `openssl rand -hex 24` atau `abc123rahasia`
3. Simpan. Kosongkan kolom itu untuk mematikan API.

## Autentikasi

Setiap request wajib mengirim header:

```
X-API-Key: <kunci-kamu>
```

| Situasi | Kode HTTP | `error` |
|---|---|---|
| API belum diaktifkan | 503 | `API_MATI` |
| Kunci salah / tidak dikirim | 401 | `UNAUTHORIZED` |

## Format respons

Semua respons JSON. Sukses: `{"ok": true, "data": ...}`. Gagal: `{"ok": false, "error": "...", "message": "..."}`.

## Endpoint

### `GET ?r=info`
Info toko + status konfigurasi.

### `GET ?r=produk`
Daftar produk aktif.

```json
{"ok": true, "jumlah": 6, "data": [{"id": 1, "nama": "Espresso", "harga": 18000}]}
```

### `POST ?r=pesanan`
Buat pesanan. Kirim JSON:

```json
{
  "nama_pemesan": "Budi",
  "tipe": "dine-in",
  "catatan": "tanpa gula",
  "items": [{"produk_id": 1, "jumlah": 2}]
}
```

**Harga selalu dihitung server** dari `produk_id` — jangan kirim harga sendiri,
nanti diabaikan. Boleh juga pakai `nama` + `harga` bila produknya tidak ada di DB.

Balasan `201` berisi pesanan lengkap dengan `kode` (mis. `ORD-261122-5F53`).

### `GET ?r=pesanan/{kode}`
Detail satu pesanan + item.

### `GET ?r=pesanan?status=menunggu&limit=20`
Daftar pesanan. `status` opsional (`menunggu`, `dibayar`, `diproses`, `siap`,
`selesai`, `batal`). `limit` 1–100, default 20.

### `GET ?r=pesanan/{kode}/qris`
QRIS dinamis untuk pesanan itu — nominal sudah terisi.

```json
{
  "ok": true,
  "data": {
    "kode": "ORD-261122-5F53",
    "nominal": 36000,
    "payload": "00020101021226...6304ABCD",
    "png_base64": "iVBORw0KGgo..."
  }
}
```

`png_base64` bisa langsung dipakai sebagai `<img src="data:image/png;base64,...">`.

### `POST ?r=pesanan/{kode}/bayar`
Tandai lunas (sama seperti kasir klik "Konfirmasi dana masuk"). Ditolak `409`
bila belum `menunggu`.

### `POST ?r=pesanan/{kode}/status`
Ubah status. Body: `{"status": "diproses"}`. Nilai: `diproses`, `siap`,
`selesai`, `batal`. Pesanan yang belum dibayar tidak bisa diproses.

## Contoh lengkap

```bash
K="kunci-rahasia-kamu"
BASE="http://localhost:8000/app/api.php"

# 1. Lihat menu
curl -H "X-API-Key: $K" "$BASE?r=produk"

# 2. Buat pesanan
curl -X POST -H "X-API-Key: $K" -H "Content-Type: application/json" \
  -d '{"nama_pemesan":"Budi","items":[{"produk_id":1,"jumlah":2}]}' \
  "$BASE?r=pesanan"

# 3. Ambil QRIS (nominal otomatis)
curl -H "X-API-Key: $K" "$BASE?r=pesanan/ORD-261122-5F53/qris"

# 4. Setelah dana masuk, tandai lunas
curl -X POST -H "X-API-Key: $K" "$BASE?r=pesanan/ORD-261122-5F53/bayar"
```

## Batasan penting

`POST /bayar` adalah **konfirmasi manual**. Sistem ini tidak menerima notifikasi
otomatis dari bank/acquirer, karena QRIS-nya berasal dari QRIS statis merchant
sendiri (bukan hasil pendaftaran PJP).

Artinya:

- Nominal **otomatis** terisi di QR ✅
- Status lunas **tidak otomatis** terdeteksi ❌

Kalau butuh lunas otomatis, harus pakai PJP resmi (Midtrans/Xendit/DANA) yang
mengirim webhook. Struktur API ini sudah siap menerima itu: tinggal ganti isi
`konfirmasiBayar()` di `app/pesanan.php` agar dipanggil dari webhook PJP.

## Keamanan

- API Key dibandingkan dengan `hash_equals` (anti timing attack)
- Semua query pakai prepared statement
- Harga dihitung server, tidak percaya input klien
- Transaksi DB pakai `beginTransaction` + rollback saat gagal
- Rate limit **belum** ada — kalau API dibuka ke internet, pasang di
  reverse proxy (nginx `limit_req`) atau tambahkan sendiri
