# Deploy Taman Teras Cafe

Panduan ini untuk **shared hosting cPanel** (Hostinger, Niagahoster, web.my.php.id, dll) yang menyediakan **PHP 8.3+**, **SSH**, **cron**, dan **SSL Let's Encrypt**.

Kalau hosting Anda tidak punya salah satu dari empat hal itu, aplikasi **tidak akan jalan** — lihat [Prasyarat](#prasyarat) di bawah.

---

## Prasyarat

| Kebutuhan | Kenapa | Cara cek |
|---|---|---|
| **PHP 8.3 atau lebih tinggi** | `composer.json` mewajibkan `^8.3`. Kalau hanya 8.2, aplikasi gagal saat boot. | cPanel → **MultiPHP Manager** atau **Select PHP Version** → pilih 8.3. |
| **MySQL 5.7+ / MariaDB 10.3+** | database | cPanel → **MySQL Databases** |
| **Akses SSH** | `composer install`, `artisan`, izin folder | cPanel → **Terminal** |
| **Cron job** | pengganti proses yang jalan terus-menerus | cPanel → **Cron Jobs** |
| **SSL aktif** | PWA install + push notification **wajib** HTTPS | cPanel → **SSL/TLS Status** |

### Ekstensi PHP yang harus aktif

```
pdo_mysql  mbstring  openssl  curl  json  fileinfo  zip  gd  bcmath  intl
```

Cek dengan:

```bash
php -m | grep -E 'pdo_mysql|mbstring|openssl|curl|gd|zip|intl|bcmath|fileinfo'
```

Kalau ada yang hilang, aktifkan lewat cPanel → **Select PHP Version** → Extensions.

---

## Jebakan penting: `ea-php83` vs `alt-php83`, dan tiga versi PHP sekaligus

Ini penyebab kegagalan deploy paling umum di cPanel, jadi baca sebelum upload apa pun.

### Dua build PHP 8.3

| Prefix | Asal | Server yang memakainya |
|---|---|---|
| `ea-php83` | EasyApache build | Apache |
| `alt-php83` | Alternative PHP (CageFS) | **LiteSpeed** |

Kalau paket hosting Anda menyebut **"Cloud Hosting"**, cPanel-nya berjalan di atas **LiteSpeed**, jadi yang benar-benar melayani halaman web adalah **`alt-php83`**.

Keduanya sama-sama 8.3, tapi **ekstensinya bisa berbeda** — `alt-` sering punya `intl`/`bcmath` yang tidak ada di `ea-`, atau sebaliknya. Jadi jangan campur: pilih satu, konsisten.

Untuk hosting berbasis LiteSpeed, pilih **`alt-php83`**.

### Tiga versi PHP di satu server yang sama

```
Browser   →  alt-php83              ← yang melayani pengunjung
SSH       →  php 8.x (bisa berbeda!) ← yang dipakai composer
cPanel UI →  ea-php83 / alt-php83
```

Akibatnya `composer install` bisa gagal dengan pesan yang menyesatkan:

```
your php version (7.4.x) does not satisfy requirement php ^8.3
```

padahal situsnya sendiri berjalan normal. Ini terjadi karena Composer membaca PHP **CLI**, bukan PHP yang melayani web.

### Pemeriksaan penentu: cek lewat browser, bukan SSH

`php -v` di terminal **tidak bisa dijadikan acuan**. Satu-satunya sumber kebenaran adalah PHP yang benar-benar melayani halaman.

Sudah ada file-cek yang bisa dipakai:

```bash
# 1. upload file ini ke folder public/ di server
public/deploy-check.php

# 2. buka di browser
https://domain-anda.com/deploy-check.php
```

File-nya sudah ada di proyek ini, tapi **sengaja tidak ikut ter-deploy** (masuk `.gitignore`) supaya tidak pernah terbawa ke server tanpa Anda sadari. Kalau perlu, ambil dari komputer Anda lalu hapus lagi setelah dibaca.

File itu mencetak versi PHP, daftar ekstensi, fungsi yang diblokir, dan memberi kesimpulan:

```
HASIL: siap deploy.
```

atau

```
HASIL: ada yang perlu diperbaiki dulu (lihat tanda GAGAL di atas).
```

Kalau ada ekstensi bertanda `GAGAL`, aktifkan lewat **MultiPHP Manager** → **Extensions**, lalu muat ulang halamannya.

> **Hapus `deploy-check.php` segera setelah dibaca.** File itu menampilkan detail server dan tidak boleh tetap online. File ini juga sudah ada di repo — jangan ikut ter-upload ke server tanpa dicermati.

### Kalau Composer menolak versi PHP

```bash
# 1. cek dulu PHP CLI yang dipakai
php -v

# 2. kalau < 8.3, install composer via cPanel (bukan manual)
#    cPanel → Terminal → "Setup PHP Composer"

# 3. atau paksa versi lewat flag
composer install --no-dev --optimize-autoloader --ignore-platform-req=php
```

`--ignore-platform-req=php` hanya dipakai kalau Anda **sudah yakin** PHP web-nya 8.3 (dicek lewat browser) sementara PHP CLI-nya tertinggal.

---

## Langkah 1 — Upload aplikasi

**Jangan** upload `vendor/` (63 MB). Lebih cepat install di server.

Kalau upload lewat FTP/ File Manager:

```
yang TIDAK ikut:
  vendor/
  node_modules/
  .env
  public/deploy-check.php      ← hanya untuk cek, jangan di-online-kan
  storage/framework/views/*.php
  storage/logs/*.log
```

Kalau Anda pakai Git (lebih disarankan, bisa update cepat nanti):

```bash
cd /home/akun/domain-anda
git clone <url-repo> .
```

### Install dependensi

```bash
cd /home/akun/domain-anda
composer install --no-dev --optimize-autoloader
```

Kalau `composer` belum ada di server:

```bash
curl -sS https://getcomposer.org/installer | php
# atau lewat cPanel → "Setup PHP Composer"
```

> `--no-dev` membuang 33 paket testing, termasuk PHPUnit. Kalau nanti mau jalanankan test, pakai `composer install` tanpa `--no-dev`.

---

## Langkah 2 — Database dan konfigurasi

### Buat database

cPanel → **MySQL Databases** → buat database dan user, lalu **grant all privileges**.

### Isi `.env`

Salin `.env` dari komputer Anda ke server, lalu ubah:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com

DB_HOST=127.0.0.1
DB_DATABASE=nama_database_anda
DB_USERNAME=user_database_anda
DB_PASSWORD=password_database_anda

LOG_LEVEL=warning
```

**Penting — jangan sampai hilang nilai rahasia ini:**

| Variabel | Dampak kalau hilang |
|---|---|
| `APP_KEY` | semua sesi & token enkripsi invalid, user logout paksa |
| `VAPID_PRIVATE_KEY` | **semua HP harus uninstall & install ulang PWA** supaya notifikasi jalan lagi |
| `QRISIFY_API_KEY` | auto-konfirmasi QRIS mati, QRIS statis fallback |
| `WEBHOOK_SECRET` | pembayaran dari gateway tidak akan diterima |

Ambil nilai ini dari `.env` di komputer Anda sekarang, simpan di tempat aman (mis. password manager), **sebelum** mulai deploy.

Kalau `VAPID_PRIVATE_KEY` tidak ada di server, generate baru dengan:

```bash
php artisan push:vapid-keys
```

Lalu salin nilai `VAPID_PUBLIC_KEY` dan `VAPID_PRIVATE_KEY` ke `.env`, dan **hapus** `push_subscriptions` di database, karena HP harus subscribe ulang.

---

## Langkah 3 — Arahkan domain

Di cPanel → **Domains**, pastikan document root menunjuk ke folder `public`:

```
/home/akun/domain-anda/public
```

**Jangan** arahkan ke root proyek — kalau begitu `.env` dan `storage/` bisa diakses lewat browser.

Aktifkan SSL (Let's Encrypt) di **SSL/TLS Status**. Setelah aktif, force HTTPS di **Domains** → centang **Force HTTPS Redirect**.

---

## Langkah 4 — Izin folder

```bash
cd /home/akun/domain-anda
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

Kalau masih error "Laravel does not have permission", coba `chmod -R 777 storage bootstrap/cache` untuk memastikan, lalu kembalikan ke 775 setelah tidak ada error.

---

## Langkah 5 — Migrasi & cache

```bash
php artisan migrate --force
php artisan optimize
```

`optimize` membuat cache config & route supaya halaman lebih cepat.

### ⚠️ Jangan jalankan `db:seed`

`DatabaseSeeder` berisi akun demo, 14 produk contoh, dan 2 kategori. Menjalankannya akan **mengganti nama akun** (`Chef Sukma`, `Barista Akmal`) dan **menimpa produk asli** Anda dengan menu contoh.

Kalau memang perlu data awal, jalankan hanya yang tidak menimpa:

```bash
# hanya jika tabel Setting kosong
php artisan tinker --execute="echo DB::table('settings')->count();"
```

---

## Langkah 6 — Cron (pengganti `queue:work`)

Di shared hosting tidak ada proses yang jalan terus-menerus. Karena itu dua fitur berikut dijalankan dari cron tiap menit:

- **Auto-konfirmasi QRIS** → `php artisan qris:sync`
- **Push notification** → `php artisan queue:work --stop-when-empty`

cPanel → **Cron Jobs** → **Add New Cron Job**, isi:

```cron
* * * * * cd /home/akun/domain-anda && php artisan qris:sync && php artisan queue:work --stop-when-empty --tries=1
```

> Sesuaikan path. Cek path absolut Anda dengan `pwd`.

**Kenapa tidak pakai `qris:sync --watch`?** Mode itu membuat job yang mendaftarkan diri sendiri terus-menerus — persis fitur cron, tapi butuh proses yang tidak pernah berhenti. Di shared hosting proses itu akan dibunuh begitu Anda menutup sesi SSH.

**Kenapa urutannya `qris:sync` lalu `queue:work`?** Perintah `qris:sync` kadang mengirim push notification. Kalau `queue:work` jalan lebih dulu, notifikasi tersebut baru terkirim di menit berikutnya. Dengan urutan ini, notifikasi terkirim di menit yang sama.

**Batasan cron:** shared hosting biasanya mengizinkan 1x per menit. Beberapa paket membatasi durasi eksekusi (mis. 30 detik). Kalau `qris:sync` terasa lambat, tambahkan `--window=5` agar hanya mengecek 5 menit terakhir:

```cron
* * * * * cd /home/akun/domain-anda && php artisan qris:sync --window=5 && php artisan queue:work --stop-when-empty --tries=1
```

---

## Langkah 7 — Backup otomatis

Backup **sebelum** melakukan apa pun. Database hanya 0,5 MB, jadi backup harian hampir tidak memberi beban.

### Backup harian (cron)

```bash
mkdir -p /home/akun/backups
```

```cron
30 2 * * * cd /home/akun/domain-anda && mysqldump --single-transaction --host=127.0.0.1 --user=user_db --password='password_db' nama_db | gzip > /home/akun/backups/pos-$(date +\%Y\%m\%d).sql.gz
```

> Di cron, `%` harus ditulis `\%`. Dan pakai `-u` yang terpisah, bukan `--user=` gabung dengan password.

### Cara restore

```bash
gunzip < /home/akun/backups/pos-20260101.sql.gz | mysql -u user_db -p nama_db
```

---

## Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| Halaman putih / "whoops" | `APP_DEBUG=false` menyembunyikan detail. Cek `storage/logs/laravel.log`. |
| `419 Page Expired` | `APP_KEY` berbeda dari yang dipakai saat cookie dibuat. User harus logout/login. |
| PWA tidak bisa install | SSL belum aktif, atau document root salah (harus `public`). |
| Push tidak sampai | `VAPID_PRIVATE_KEY` berubah, atau cron `queue:work` tidak jalan, atau HP belum subscribe ulang. |
| `could not find driver` | Ekstensi `pdo_mysql` belum aktif di cPanel. |
| `.env` bisa diakses via browser | Document root menunjuk root proyek, bukan `public`. |
| QRIS tidak auto-konfirmasi | `QRISIFY_API_KEY` kosong, atau cron tidak jalan, atau paket QRIS-ify habis masa aktifnya. |
| Blank setelah update | `php artisan optimize:clear` lalu `php artisan optimize`. |
| `php version does not satisfy ^8.3` saat composer | PHP **CLI** di SSH lebih lama dari 8.3, padahal PHP web sudah benar. Lihat [Jebakan penting](#jebakan-penting-ea-php83-vs-alt-php83-dan-tiga-versi-php-sekaligus). |
| Situs jalan tapi `artisan` gagal | Sama seperti di atas — PHP CLI beda dengan PHP web. Jalankan `php artisan` lewat **cPanel → Terminal**, atau set `alt-php83` sebagai default CLI. |
| Ekstensi ada di SSH tapi tidak di browser | `ea-` dan `alt-` build punya ekstensi berbeda. Switch domain ke `alt-php83` (LiteSpeed), lalu cek ulang via `deploy-check.php`. |

---

## Ringkasan perintah

```bash
# sekali saja
composer install --no-dev --optimize-autoloader
chmod -R 775 storage bootstrap/cache
php artisan migrate --force
php artisan optimize

# tiap menit (cron)
php artisan qris:sync && php artisan queue:work --stop-when-empty --tries=1
```
