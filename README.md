# Taman Teras Cafe - POS & Self-Order System

Aplikasi Point of Sale (POS) dan Self-Order berbasis web untuk kafe/restoran dengan konsep multi-owner (pemisahan operasional dapur makanan dan bar minuman), pembayaran QRIS dinamis, notifikasi Web Push (PWA), serta pelaporan transaksi lengkap.

Dibangun menggunakan Laravel 13 dan PHP 8.3+.

---

## Fitur Utama

- Self-Order Pelanggan (QR Meja): Pelanggan scan QR code di meja untuk memesan langsung lewat menu digital tanpa antre di kasir.
- QRIS Dinamis & Verifikasi Otomatis:
  - Dukungan gateway QRIS-ify dengan kode unik dan auto-verifikasi via webhook.
  - Fallback lokal EMVCo CRC16 untuk konversi QRIS statis ke nominal dinamis.
- Multi-Owner Kitchen Dashboard:
  - Pemisahan akses pesanan otomatis antara Owner Makanan (owner_makanan) dan Owner Minuman (owner_minuman).
  - Akun Kasir / Admin (admin_kasir) untuk monitoring seluruh transaksi.
  - Polling antrean pesanan real-time.
- Struk Digital: Tampilan invoice interaktif dengan status pembayaran live dan hitung mundur pembayaran.
- Notifikasi Pesanan Baru (PWA): Dukungan Web Push Notification (VAPID) langsung ke smartphone owner/dapur.
- Manajemen Menu & Laporan:
  - Manajemen stok, kategori, dan proteksi hapus produk yang sudah memiliki riwayat penjualan.
  - Rekap penjualan dengan ekspor laporan ke format Excel (.xlsx) dan PDF.

---

## Tech Stack

- Backend: Laravel 13, PHP 8.3+
- Database: MySQL
- Frontend: Blade, Vanilla JS, CSS murni (tanpa Node.js / Vite build step)
- Library Utama:
  - dompdf/dompdf (Cetak struk & laporan PDF)
  - phpoffice/phpspreadsheet (Ekspor Excel)
  - minishlink/web-push (Web Push API)

---

## Prasyarat Sistem

- PHP >= 8.3 (dengan ekstensi pdo_mysql, gd, openssl, mbstring, curl)
- Composer >= 2.x
- MySQL / MariaDB (via XAMPP / Laragon)

---

## Panduan Instalasi

### 1. Clone Repository

```bash
git clone https://github.com/VVNX13/POS_Kasir.git
cd POS_Kasir/POS_Kasir
```

*(Jika membuka langsung folder root repository, masuk ke subdirektori POS_Kasir)*

### 2. Install Dependensi Composer

```bash
composer install
```

### 3. Konfigurasi Environment

Salin file .env.example menjadi .env:

```bash
cp .env.example .env
```

Sesuaikan database di .env:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_kasir
DB_USERNAME=root
DB_PASSWORD=
```

Buat database baru di MySQL bernama pos_kasir.

### 4. Generate Key & Migrasi Database

```bash
php artisan key:generate
php artisan migrate --seed
```

*(Perintah --seed otomatis mengisikan akun demo, kategori, serta produk contoh).*

### 5. Jalankan Server

Penggunaan lokal di PC:

```bash
php artisan serve
```

Akses browser: http://127.0.0.1:8000

Untuk akses dari HP via Wi-Fi lokal:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Buka http://<IP-LAN-PC>:8000/qr-center untuk cetak QR meja.

---

## Akun Demo (Default)

| Role | Username | Password | Cakupan Akses |
|---|---|---|---|
| Owner Makanan | owner_makanan | password123 | Pesanan & produk makanan saja |
| Owner Minuman | owner_minuman | password123 | Pesanan & produk minuman saja |
| Kasir / Admin | admin_kasir | password123 | Semua kategori (makanan & minuman) |

---

## Web Push Notification (Opsional)

1. Generate VAPID Keys:
   ```bash
   php artisan push:vapid-keys
   php artisan config:clear
   ```
2. Jalankan background worker queue:
   ```bash
   php artisan queue:work
   ```
3. Dashboard owner -> Notifikasi & Aplikasi -> Aktifkan Notifikasi.

---

## Lisensi

MIT License
