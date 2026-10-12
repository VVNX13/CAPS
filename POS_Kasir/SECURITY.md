# Audit Keamanan - Taman Teras Cafe

Audit dilakukan **27 September 2026**, dengan aplikasi berjalan lokal di
`https://192.168.100.10:8443`. Semua temuan diuji dengan 요청 HTTP sungguhan,
bukan hanya membaca kode.

Ringkas: **aplikasi sudah aman untuk di-hosting, dengan 3 perubahan wajib** yang
sudah dikerjakan dan 3 hal yang harus Anda isi saat deploy.

---

## 1. Yang sudah diperbaiki

### 1.1 Endpoint polling bocor data pelanggan (KRITIS)

`GET /api/orders/check-new` berada di luar middleware `auth`. Setiap item
pesanan di balasan memuat nama pelanggan, nomor WhatsApp, nomor invoice, metode
dan status pembayaran.

Karena parameter `last_id` bisa diubah bebas, siapa pun di internet bisa
mengambil seluruh riwayat pesanan kafe. Terbukti sebelum diperbaiki:

```
$ curl -k "https://ip-kafe/api/orders/check-new?type=makanan,minuman&last_id=0"
{"success":true,...,"items":[{
  "customer_name":"Budi Rahasia",
  "customer_phone":"081298765432",
  "invoice_number":"INV-SECURITY-TEST",
  "payment_method":"qris",
  "payment_status":"unpaid"}]}
```

Harusnya: **403/401**, karena endpoint ini hanya dipakai polling dashboard owner.

**Perbaikan:** rute dipindahkan ke dalam grup `auth`. Diverifikasi live:

| Akses | Sebelum | Sesudah |
|---|---|---|
| Tanpa login | 200 + data pelanggan | **401 Unauthenticated** |
| Admin | 200 | 200 (berfungsi) |
| owner makanan | 200 | 200 (berfungsi) |
| owner minuman | 200 | 200 (berfungsi) |

### 1.2 Dump SQL bisa ikut ter-deploy

`storage/backups/` berisi dump seluruh database - nama pelanggan, nomor HP,
riwayat pembayaran - tapi **tidak ada di `.gitignore`**. Kalau deploy lewat git,
file itu ikut terunggah ke server publik.

**Perbaikan:** `/storage/backups` ditambahkan ke `.gitignore`.

### 1.3 Pencarian riwayat tanpa batas

`GET /api/orders/history?phone=08xx` dan `/api/orders/status/{invoice}` memang
harus publik (tidak ada akun pelanggan), tapi tanpa pembatasan siapa pun bisa
menebak nomor satu per satu dan membaca riwayat orang lain.

**Perbaikan:** `throttle:10,1` pada kedua endpoint.

Diverifikasi live:

```
200,200,200,200,200,200,200,200,200,200,429,429,429,429
```

### 1.4 Control lain yang sudah benar

Sudah diperiksa dan aman:

| Area | Status |
|---|---|
| SQL injection | Aman - semua query memakai query builder / Eloquent dengan binding |
| XSS | Aman - Blade `{{ }}` meng-escape otomatis; `addToCart`/preview memakai `escapeHtml()` |
| CSRF | Aktif - `@csrf` di form, `X-CSRF-TOKEN` di fetch; webhook dikecualikan dengan sengaja |
| Password | Bcrypt 12 rounds, tidak pernah ditampilkan |
| Sesi | Cookie session, `auth` middleware di semua rute owner |
| Otorisasi role | Admin saja untuk QRIS/gateway/webhook; scoping kategori untuk owner |
| Upload file | Validasi mime (JPG/PNG/WebP), maks 4 MB, nama file di-randomize |
| Otorisasi pembayaran | `markPaid` & `updatePaymentStatus` menolak di luar cakupan kategori |

---

## 2. WAJIB diubah saat deploy

`.env` saat ini masih konfigurasi pengembangan:

```env
APP_ENV=local          #  -> production
APP_DEBUG=true         #  -> false      <-- paling penting
APP_URL=https://192.168.100.10:8443   #  -> https://domain-anda.com
DB_USERNAME=root       #  -> user khusus database
LOG_LEVEL=debug        #  -> warning
```

### Kenapa `APP_DEBUG=false` itu wajib

Kalau ada satu error saja di server publik, Laravel menampilkan stack trace
lengkap **beserta isi `.env`**: API key QRIS-ify, webhook secret, private key
VAPID. Dengan itu, siapa pun yang menemukan halaman error bisa mengambil
kunci pembayaran kafe Anda. Bukan risiko teoretis - `APP_DEBUG=true` memang
memuntahkan `.env`.

---

## 3. Perlu dicek setelah deploy

| Yang dicek | Cara | Kalau gagal |
|---|---|---|
| `.env` tidak bisa diakses | buka `https://domain-anda.com/.env` | Document root salah, arahkan ke `public/` |
| `storage/` tidak bisa diakses | buka `https://domain-anda.com/storage/logs/laravel.log` | Sama seperti di atas |
| `storage:link` aktif | buka `https://domain-anda.com/storage/products/<file>.jpg` | Jalankan `php artisan storage:link` |
| HTTPS + PWA | pasang PWA, harus installer | SSL belum aktif |
| Cron jalan | tunggu 1 menit, cek ada push masuk | Cron salah path / PHP CLI bukan 8.3 |
| Hapus file cek | `https://domain-anda.com/deploy-check.php` harus 404 | File masih ada di `public/` |

---

## 4. Pertimbangan yang tersisa (risiko diterima)

Ini bukan bug, tapi keputusan desain yang perlu Anda tahu:

### 4.1 Siapa pun bisa melihat history orang lain kalau tahu nomornya

 Ini muncul karena tidak adanya akun pelanggan: kalau pelanggan memasukkan
`0812...`, siapa pun yang mengetik nomor itu di `/riwayat` akan melihat
riwayat pesanannya.

**Mitigasi sekarang:** rate limit 10 permintaan/menit, jadi tidak bisa di-enumerate
massal.

**Kalau nanti terlalu berisiko:** opsi yang masuk akal adalah menambah kode OTP
via WhatsApp, atau membuat halaman riwayat hanya menampilkan pesanan terakhir
  yang belum lunas - itu justru yang dibutuhkan di kasir.

### 4.2 Nomor invoice bisa ditebak

Formatnya `INV-YYYYMMDD-XXXX` dengan 4 karakter acak (36^4 ≈ 1,7 juta per hari).
Kemungkinan menebak satu invoice tertentu cukup kecil, tapi `/receipt/{invoice}`
adalah publik.

**Kalau mau lebih ketat:** tambah UUID ke format invoice.

### 4.3 Rate limit memakai cache database

`CACHE_STORE=database`, jadi throttle memakai tabel `cache`. Kalau cron tidak
  jalan, entri cache tetap kedaluwarsa sendiri, jadi tidak jadi masalah jangka panjang.

---

## 5. Ringkasan

| Temuan | Severity | Status |
|---|---|---|
| `check-new` bocorkan nama + nomor HP | **KRITIS** | Sudah diperbaiki |
| Dump SQL bisa ikut deploy | **TINGGI** | Sudah diperbaiki |
| Pencarian riwayat tanpa rate limit | SEDANG | Sudah diperbaiki |
| `APP_DEBUG=true` di produksi | **KRITIS** | Tunggu deploy |
| `DB_USERNAME=root` | TINGGI | Tunggu deploy |
| Riwayat bisa dilihat pemilik nomor | RENDAH | Diterima, ada rate limit |
| Invoice bisa ditebak | RENDAH | Diterima |

Test yang mengunci semua ini: `tests/Feature/PublicSurfaceTest.php` (7 test).
