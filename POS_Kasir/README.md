# Taman Teras Cafe

Aplikasi Point-of-Sale + self-order QR untuk restoran dengan **pemilik terpisah** (kategori makanan
dan minuman dikelola owner masing-masing), pembayaran **QRIS dinamis**, dan struk digital.

Dibangun dengan **Laravel 13** (sebelumnya PHP murni tanpa framework).

> **Deploy ke hosting?** Lihat **[DEPLOY.md](DEPLOY.md)** — prasyarat PHP 8.3+, langkah cPanel/SSH, cron pengganti `queue:work`, dan backup otomatis.
>
> **Sebelum online-kan:** baca **[SECURITY.md](SECURITY.md)** — audit 27 Sept 2026, termasuk satu kebocoran data pelanggan yang sudah diperbaiki dan 3 variabel `.env` yang wajib diubah.

---

## Menjalankan

```powershell
cd C:\laragon\POS_Kasir
php artisan serve
```

Buka **http://127.0.0.1:8000**

Agar QR Code di `/qr-center` bisa discan dari HP, jalankan di seluruh jaringan:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

Lalu buka `http://<IP-LAN-KOMPUTER>:8000/qr-center` (misal `http://192.168.100.10:8000/qr-center`).
URL pada QR otomatis mengikuti host yang dipakai, jadi HP akan sampai ke menu yang benar.

> `.env` sudah diisi `PHP_CLI_SERVER_WORKERS=4` supaya polling dashboard owner
> tidak memblokir request lain saat memakai `artisan serve`.

### Login demo

Login memakai **username saja** (kolom email tidak dipakai untuk autentikasi).

| Username | Password | Scope | Tombol mulai proses |
|---|---|---|---|
| `owner_makanan` | `password123` | Kategori makanan saja | 👨‍🍳 Mulai Masak |
| `owner_minuman` | `password123` | Kategori minuman saja | 🥤 Mulai Buat Minuman |
| `admin_kasir` | `password123` | **Makanan + minuman** | Mulai Proses |

Kolom password punya tombol 👁️ untuk menampilkan/menyembunyikan isinya.

### Scope per role

`User::ownerTypes()` menentukan cakupan data:

| Role | `ownerTypes()` | Label |
|---|---|---|
| `owner_makanan` | `['makanan']` | Makanan |
| `owner_minuman` | `['minuman']` | Minuman |
| `admin` | `['makanan', 'minuman']` | Semua Kategori |

Cakupan ini dipakai konsisten oleh dashboard, polling, daftar produk, dan laporan —
termasuk nama file export (`Laporan_semua-kategori_...`).

---

## Konfigurasi

`Database` memakai MySQL `pos_kasir` di `127.0.0.1` (user `root`, tanpa password).
Tabel app yang sudah ada tetap dipertahankan — migration memakai penjaga `Schema::hasTable()`,
sehingga `php artisan migrate` aman dijalankan pada database lama maupun database kosong.

```powershell
php artisan migrate         # menambah tabel framework (sessions, cache, jobs) bila belum ada
php artisan migrate:fresh --seed   # HAPUS semua data, lalu isi ulang dengan data demo
php artisan db:seed         # isi data demo saja (aman diulang, idempotent)
```

> ⚠️ **`migrate:fresh` menghapus semua data.** Backup dulu:
> ```powershell
> mysqldump -u root pos_kasir > backup_pos_kasir.sql
> ```
> Backup otomatis juga tersimpan di `storage/backups/`.

### Data demo (seeder)

`migrate:fresh --seed` menghasilkan aplikasi yang siap pakai:

- **3 akun** — `owner_makanan`, `owner_minuman`, `admin_kasir` (password `password123`)
- **2 kategori** — `Makanan` dan `Minuman`
- **14 produk contoh** — 7 makanan + 7 minuman
- **Setting QRIS** — payload statis merchant + nama Merchant

Kalau Anda mau **menu kosong** (hanya akun + kategori, tanpa produk contoh), seed per kelas:

```powershell
php artisan migrate:fresh                                        # kosong total
php artisan db:seed --class=UserSeeder                           # 3 akun
php artisan db:seed --class=CategorySeeder                       # 2 kategori
php artisan db:seed --class=SettingSeeder                        # QRIS merchant
# ProductSeeder sengaja tidak dijalankan -> menu mulai dari 0
```

Seeder sengaja **tidak** menanamkan secret: API key QRIS-ify memakai fallback
`QrisifyService::DEFAULT_API_KEY` (bisa diubah di `/owner/qris`), dan webhook secret
dibuat otomatis saat pertama dipakai.

| Seeder | Isi |
|---|---|
| `UserSeeder` | 3 akun demo |
| `CategorySeeder` | 2 kategori owner |
| `ProductSeeder` | 14 produk contoh (opsional) |
| `SettingSeeder` | QRIS statis + nama merchant |

---

## Rute

### Publik
| Method | URL | Keterangan |
|---|---|---|
| GET | `/`, `/menu` | Menu digital pelanggan |
| GET | `/qr-center` | Cetak QR meja + QRIS kasir |
| GET | `/receipt/{invoice}` | Struk digital + QRIS + countdown |
| GET | `/login` · POST `/login` · POST `/logout` | Authenticate |

### Owner (butuh login)
| Method | URL | Keterangan |
|---|---|---|
| GET | `/owner/dashboard` | Antrean dapur real-time (polling 6 detik) |
| GET | `/owner/products` | Kelola menu & stok |
| GET | `/owner/reports` | Laporan + export Excel/PDF |
| GET | `/owner/qris` | Pengaturan QRIS, QRIS-ify, webhook |

### JSON API
Semua memakai session cookie. Endpoint di `routes/api.php` sengaja dijalankan dengan middleware
`web` agar session + CSRF tetap aktif, namun path tetap `/api/*` agar tidak mengubah kode front-end.

| Method | URL |
|---|---|
| POST | `/api/orders/checkout` |
| GET | `/api/orders/status/{invoice}` |
| GET | `/api/orders/check-new?type=&last_id=` |
| GET | `/api/owner/orders` |
| POST | `/api/owner/item/status` · `/api/owner/payment/status` |
| GET/POST | `/api/owner/products` · POST `/api/owner/products/toggle` |
| DELETE | `/api/owner/products/{id}` |
| GET | `/api/owner/reports/data` · `/export-excel` · `/export-pdf` |
| GET/POST | `/api/owner/qris/settings` · POST `/api/owner/qris/test` |
| GET/POST | `/api/owner/qrisify/settings` |
| GET | `/api/owner/webhook/config` |
| POST | `/api/owner/webhook/secret/regenerate` · `/update` |
| POST | `/api/webhook/payment` · `/api/webhook/qris` |

Semua respons memakai satu bentuk JSON:

```json
{ "success": true, "message": "...", "data": {} }
{ "success": false, "message": "...", "errors": {} }
```

Termasuk error validasi — lihat render hook di `bootstrap/app.php`.

---

## QRIS

Dua mode, dipilih otomatis saat checkout:

1. **QRIS-ify Gateway** — bila `qrisify_enabled` = `1`, nominal dibuat pas ( Unique Code )
   dan pembayaran terverifikasi otomatis lewat webhook.
2. **Fallback lokal** — `App\Support\QrisHelper` mengubah string QRIS statis jadi dinamis
   (tag `54` + checksum CRC16-CCITT EMVCo) bila gateway tidak tersedia.

Keduanya menghasilkan string QRIS yang valid dan bisa discan e-wallet apa pun.

## Webhook pembayaran

`POST /api/webhook/payment` menerima callback dari Payment Gateway, bot forwarder notifikasi
(MacroDroid/Tasker), atau QRIS-ify. Autentikasi memakai:

- header `X-Webhook-Secret` (shared secret, diatur di `/owner/qris`), atau
- HMAC-SHA256 pada `X-Callback-Signature`, atau
- event QRIS-ify (`X-QRIS-Event`).

Pesanan dicari berurutan lewat `transaction_id` → `invoice_number` → nominal → nominal yang
diparse dari teks notifikasi. Rute ini dikecualikan dari CSRF karena dipanggil sistem eksternal.

---

## Struktur

```
app/
  Http/Controllers/     Order, OwnerDashboard, Product, Report, Qris, Qrisify,
                        Webhook, Polling, CustomerMenu, QrCenter, Auth/Login
  Models/               User, Category, Product, Sale, SaleItem, Setting
  Services/             QrisifyService
  Support/              ApiResponse, QrisHelper, WebhookHelper, helpers.php
resources/views/
  customer/             menu, receipt
  owner/                login, dashboard, products, reports, qris, qr-center
  reports/pdf           template PDF (Dompdf)
  layouts/app           wrapper HTML + meta CSRF token + @stack('head'/'scripts')
public/assets/          CSS & JS front-end (tanpa build step)
routes/                 web.php, api.php
database/migrations/    users, categories, products, sales, sale_items, settings
```

> **Catatan Blade:** layout memakai `@stack('scripts')`, jadi halaman wajib memakai
> `@push('scripts') ... @endpush` — bukan `@section`. `@stack` hanya membaca konten
> dari `@push`/`@prepend`; kalau dicampur, script halaman hilang tanpa error.
> Di Covered oleh `tests/Feature/ViewAssetsTest.php`.

### Front-end
Tidak memakai Vite/npm. Aset berada di `public/assets` dan dimuat langsung dengan
`<link>` / `<script>`. `public/assets/js/csrf.js` membungkus `window.fetch` agar otomatis
mengirim `X-CSRF-TOKEN` pada request non-GET — itu sebabnya kode JS asli tidak perlu berubah.

---

## 🧹 Menghapus Menu (Produk)

Tombol 🗑️ di halaman **Kelola Menu** menghapus produk secara permanen, dengan aturan:

- **Belum pernah terjual** → dihapus (setelah konfirmasi).
- **Sudah pernah terjual** → ditolak `409` dengan penjelasan. Ini wajib: `sale_items`
  memakai FK `ON DELETE RESTRICT` ke `products`, jadi menghapusnya akan merusak riwayat
  semua laporan. Untuk menyembunyikannya dari menu pelanggan, pakai tombol **✅ Aktif / Off**.

API `GET /api/owner/products` ikut mengembalikan `sold_count` supaya UI bisa memberi
peringatan sebelum produk dihapus.

> ⚠️ Saat menguji endpoint hapus, **jangan pakai produk nyata sebagai target** — buat
> produk dummy dulu, atau andalkan test suite (`tests/Feature/ProductDeletionTest.php`
> memakai database in-memory dan tidak menyentuh data asli).

## 📱 PWA (Progressive Web App)

Aplikasi bisa dipasang ke layar utama HP (**Add to Home Screen**) dan berjalan fullscreen
tanpa address bar. Menu digital tetap terbuka saat offline; **checkout tetap wajib online**.

### File PWA

| File | Fungsi |
|---|---|
| `public/manifest.webmanifest` | Metadata aplikasi: nama, ikon, warna, shortcut |
| `public/sw.js` | Service worker: cache aset, cache halaman, push handler |
| `public/assets/js/pwa.js` | Registrasi SW, install prompt, subscribe push, deteksi offline |
| `public/assets/icons/*.png` | Ikon 192/512 + maskable (dihasilkan dengan GD) |
| `config/push.php` | Konfigurasi VAPID |
| `app/Http/Controllers/PushSubscriptionController.php` | API subscribe / unsubscribe / test |
| `app/Services/PushNotificationService.php` | Pengiriman push + pembersihan subscription mati |
| `app/Jobs/SendPushNotification.php` | Job antrean agar push tidak memperlambat checkout |
| `app/Listeners/NotifyOwnersOfNewOrder.php` | Trigger saat ada pesanan baru |
| `database/generate_pwa_icons.php` | Generator ikon (`php database/generate_pwa_icons.php`) |

### Strategi cache service worker

| Request | Strategi | Alasan |
|---|---|---|
| `/assets/*`, manifest | stale-while-revalidate | Cepat, tapi diri-repair sendiri |
| Halaman HTML | network-first, cache fallback | Menu tetap terbuka offline, data tetap segar saat online |
| `/api/*` | **tidak pernah di-cache** | Data order & harga basi akan menyesatkan |
| `/receipt/*` | **tidak pernah di-cache** | Berisi status pembayaran langsung |
| `/owner/*`, `/login` | **tidak pernah di-cache** | Halaman owner harus selalu segar |
| Non-GET (checkout) | **tidak pernah di-cache** | Butuh server |

### Menghindari aset basi di HP (PWA)

Kesalahan klasik: service worker memakai **cache-first** untuk aset, sehingga begitu
`style.css` tersimpan, **selama-lamanya** file itu dilayani dari cache — perubahan di
server tidak pernah terlihat. Gejalanya persis seperti yang terjadi: hamburger tidak
muncul di HP, tapi muncul di DevTools.

Dua lapis perlindungan yang dipakai:

1. **`asset_versioned()`** di layout & view — menambahkan `?v=<mtime>` ke URL aset.
   Begitu file berubah, URL-nya berubah, jadi **tidak ada strategi cache** yang bisa
   menahan file lama.
2. **stale-while-revalidate** (bukan cache-first) — melayani dari cache dengan segera,
   lalu diam-diam memperbarui untuk kunjungan berikutnya.

Selain itu:

- `VERSION` di `sw.js` harus **dinaikkan setiap kali `sw.js` diubah** agar aplikasi yang
  sudah terpasang membangun ulang cache-nya.
- Apache menyajikan `sw.js` dengan `Cache-Control: no-cache, no-store, must-revalidate`
  supaya browser benar-benar mengecek versi baru.
- Semua file di daftar precache harus benar-benar ada (ada test yang memverifikasinya).

Kalau setelah update masih terasa lama di HP: tutup paksa aplikasi, buka lagi, lalu
tutup-buka sekali lagi — kunjungan kedua yang menampilkan aset baru.

### Mengaktifkan push notification

```powershell
# 1. Generate kunci VAPID (sekali saja, tersimpan di .env)
php artisan push:vapid-keys
php artisan config:clear

# 2. Jalankan worker antrean (wajib, agar notifikasi benar-benar terkirim)
php artisan queue:work
```

Lalu login ke dashboard owner → kartu **"Notifikasi & Aplikasi"** → **🔔 Aktifkan Notifikasi**
→ izinkan di browser → **📨 Tes Notifikasi**.

> ⚠️ `VAPID_PRIVATE_KEY` adalah rahasia — jangan di-commit.
> `push:vapid-keys --force` membuat kunci baru dan **membatalkan semua subscription** yang
> sudah terdaftar.

### Batasan yang perlu diketahui

- **Push hanya jalan di HTTPS atau `localhost`.** Untuk HP di jaringan lokal,
  `http://192.168.x.x:8000` **tidak** cukup — butuh HTTPS (reverse proxy / Laragon HTTPS).
  Install ke Home Screen tetap bisa jalan tanpa HTTPS; hanya notifikasinya yang butuh.
- **iOS**: Safari 16.4+, dan aplikasi harus sudah dipasang ke Home Screen dulu sebelum
  notifikasi diizinkan.
- **Checkout offline tidak didukung** (sengaja). QRIS butuh internet untuk verifikasi,
  dan mengunci stok harus lewat server.
- Notifikasi dikirim lewat antrean. Tanpa `queue:work`, job menumpuk di tabel `jobs`.

## 🔐 Menjalankan (HTTPS untuk HP)

Ada **dua** cara menjalankan aplikasi:

| URL | Untuk | Catatan |
|---|---|---|
| `https://192.168.100.10:8443` | **HP / tablet** | HTTPS + sertifikat valid → push notification jalan |
| `http://127.0.0.1:8000` | PC lokal | `php artisan serve`, HTTP, push tidak tersedia |

### HTTPS lewat Apache (port 8443)

| File | Isi |
|---|---|
| `C:\laragon\etc\apache2\sites-enabled\tamanteras.conf` | vhost HTTPS 8443 + redirect dari 8080 |
| `C:\laragon\etc\apache2\ports.conf` | `Listen 8443` dan `Listen 8080` |
| `C:\laragon\etc\apache2\ssl\tamanteras\` | CA + sertifikat server |

Port **8443** dipilih agar tidak bentrok dengan site Laragon lain di 80/443, dan agar
aplikasi berada di root `/` (scope service worker & manifest jadi benar).

```powershell
# reload Apache setelah mengubah konfigurasi
& "C:\laragon\bin\apache\httpd-2.4.66-260107-Win64-VS18\bin\httpd.exe" `
    -d "C:/laragon/bin/apache/httpd-2.4.66-260107-Win64-VS18" -k restart
```

> ⚠️ Directive `Listen` baru **tidak** berlaku dengan `-k graceful`; harus `-k restart`.

### Mengaktifkan push notification di HP

Browser hanya mengizinkan push di **secure context**. Sertifikat di sini ditandatangani
CA lokal, jadi **CA harus di-install ke perangkat** lebih dulu.

Berkas CA (salin ke HP):

- `C:\laragon\tamanteras-ca.crt`
- `C:\laragon\etc\apache2\ssl\tamanteras\ca.crt`

Fingerprint SHA-256: `6C:81:76:A4:02:29:B3:BA:EE:D3:6D:69:0C:E9:97:ED:5F:68:F2:10:4C:26:56:0C:9B:C0:F9:2D:BB:AE:A1:FE`

**Android (Chrome):**
1. Kirim `tamanteras-ca.crt` ke HP (Bluetooth/kabel/WA).
2. Settings → Keamanan & privasi → Enkripsi & kredensial → **Pasang sertifikat** → **Sertifikat CA**.
3. Pilih file `tamanteras-ca.crt`.
4. Buka `https://192.168.100.10:8443` — peringatan sertifikat tidak akan muncul.
5. Login → dashboard → **🔔 Aktifkan Notifikasi** → **📨 Tes Notifikasi**.

**iOS (Safari):**
1. Kirim `tamanteras-ca.crt` ke HP, buka filenya, **Install Profile**.
2. Settings → General → About → **Certificate Trust Settings** → nyalakan
   *Taman Teras Cafe Dev CA*.
3. Buka `https://192.168.100.10:8443` di Safari → Share → **Add to Home Screen**.
4. Buka aplikasi dari Home Screen (bukan dari browser) agar notifikasi diizinkan.

> Tanpa CA terpasang, halaman tetap terbuka dan bisa di-install, tetapi **push tidak akan
> pernah sampai**.

### Worker antrean

Notifikasi dikirim lewat antrean, jadi worker harus jalan:

```powershell
php artisan queue:work
```

## 📱 Tampilan Mobile (role owner)

Navbar owner punya banyak tombol (Dashboard, QRIS, Menu, Laporan, Cetak QR, Keluar) yang
tidak muat di layar HP, jadi di bawah **768px** navbar berubah jadi **hamburger menu**:

```
┌──────────────────────────┐  ┐
│ 🍲 Dashboard Makanan   │  ☰
└──────────────────────────┘  ┘
        ↓ tap ☰
┌──────────────────────────┐
│ 🔔 Audio                │
│ 📱 QRIS Dinamis         │
│ 📦 Menu                 │
│ 📊 Laporan              │
│ 🖨️ Cetak QR             │
│ Keluar                  │
└──────────────────────────┘
```

| File | Isi |
|---|---|
| `public/assets/js/nav.js` | Toggle hamburger (dimuat di layout, berlaku semua halaman) |
| `public/assets/css/style.css` | `.nav-toggle`, `.nav-open`, breakpoint 768px |

Tombol hamburger **diinjeksi otomatis** oleh `nav.js` pada setiap `.navbar-container` yang
punya panel navigasi, jadi halaman owner baru tidak bisa lupa vetted.

Catatan mobile lain yang sudah diperbaiki:

- Empat grid (`orders`, `products`, `qris`) memakai `minmax(min(320px, 100%), 1fr)` agar
  tidak meluber di HP sempit.
- `html, body { overflow-x: hidden }` sebagai pengaman agar satu elemen lebar tidak
  membuat seluruh halaman bisa di-scroll horizontal.
- Grid KPI sudah 2 kolom di mobile, 4 kolom di desktop.

## 💰 Auto-konfirmasi pembayaran QRIS

Ada tiga lapis konfirmasi, dari yang paling cepat:

| Lapis | Cara kerja | Kapan dipakai |
|---|---|---|
| **Webhook** | QRIS-ify mengirim callback ke `/api/webhook/payment` | Instan |
| **Polling struk** | Halaman struk mengecek tiap 3,5 detik selama tab terbuka | Cepat |
| **Background sync** | Job background mengecek tiap 60 detik | Menutup celah |

Lapis ketiga menutup celah: kalau pelanggan membayar lalu **menutup HP**, tidak ada lagi
yang memeriksa, sehingga transaksi menggantung `unpaid` sampai kasir klik manual.

```powershell
# Mulai loop background (cukup satu kali)
php artisan qris:sync --watch

# atau jalankan sekali saja (mis. dari cron / Task Scheduler)
php artisan qris:sync
```

Job `SyncQrisPayments` meng-*re-queue* dirinya sendiri setiap 60 detik, jadi **tidak perlu**
proses `schedule:work` kedua — cukup `queue:work` yang sudah berjalan untuk push notifikasi.
Job memakai `WithoutOverlapping` supaya dua salinan tidak pernah jalan bersamaan.

### Aturan main

| Situasi | Hasil |
|---|---|
| `qris` + ada `qris_transaction_id` + gateway bilang `SUCCESS`/`PAID`/`SETTLED`/ada `paid_at` | → `paid` + push notifikasi ke owner |
| Gateway masih `PENDING` | dibiarkan `unpaid` |
| Pembayaran **tunai** | **tidak pernah** disentuh (butuh konfirmasi fisik kasir) |
| QRIS lokal (tanpa `qris_transaction_id`) | dilewati — mustahil diverifikasi otomatis |
| Lebih tua dari 30 menit | dilewati, diserahkan ke kasir |
| Gateway down / timeout | dicatat di log, transaksi lain tetap diproses |
| `qrisify_enabled = 0` | seluruh sync dilewati |

Deteksi "sudah dibayar" dipakai bersama oleh polling struk dan background sync
(`QrisifyService::isPaid()`), jadi keduanya tidak bisa berbeda pendapat.

Konfigurasi (`.env`):

```
QRIS_SYNC_INTERVAL_SECONDS=60
QRIS_SYNC_WINDOW_MINUTES=30
```

> ⚠️ **Batasan yang tidak bisa dihilangkan:** auto-konfirmasi hanya mungkin bila ada
> gateway yang melapor balik. Kalau memakai QRIS statis yang dikonversi lokal
> (`QrisHelper`), aplikasi ini **bukan** penyedia pembayarannya — tidak ada yang memberi tahu
> server bahwa uang masuk. QRIS lokal tetap bergantung pada webhook bank atau konfirmasi
> manual. Auto-approve bersandar pada QRIS-ify.

## Testing

```powershell
php artisan test      # 55 test: auth, checkout, scoping role, QRIS, webhook, aset view, push
vendor\bin\pint       # format kode
```

Seeder juga bisa diuji tanpa menyentuh database utama:

```powershell
$env:DB_DATABASE = "pos_kasir_test"; php artisan migrate:fresh --seed --force
```

Test memakai SQLite in-memory sehingga tidak menyentuh database MySQL.

---

## Catatan konversi dari PHP murni

- `App\Config\Database` (PDO) → Eloquent + migration
- `App\Helpers\Auth` (`$_SESSION`) → guard `auth` bawaan Laravel, login via kolom `username`
- `App\Helpers\Response` → `App\Support\ApiResponse`
- routing manual di `public/index.php` → `routes/web.php` + `routes/api.php`
- `public/views/*.php` → Blade di `resources/views/`
- `curl` di `QrisifyService` → `Http` facade
- `App\Config\App::getBaseUrl()` → `url()` / `route()`
- `App\Config\App::formatRupiah()` → helper `rupiah()`
