# AdaTempat

Platform hybrid *Public Discovery Booking Marketplace* dan *Venue Management OS* untuk penyewaan lapangan olahraga, ruang rapat, studio kreatif, ruang acara, dan *coworking space*.

MVP berfokus pada alur transaksi end-to-end yang solid:  
**Pencarian venue → Pemilihan slot waktu real-time → Checkout & Add-on → Pembayaran QRIS → Tiket digital (QR) → Scan validasi di pintu masuk (Gate Scanner)**.

---

## ⚡ Fitur Utama

- **Public Discovery & Catalog:**
  - Pencarian dan filter ruang berdasarkan kota, kategori (*sports*, *meeting*, *creative*, *coworking*, *events*), rentang harga, dan ketersediaan tanggal.
  - Tampilan detail venue dan ruang dengan tata letak visual informatif, spesifikasi fasilitas, dan kebijakan pembatalan.
- **Real-Time Slot Picker & Anti-Double Booking:**
  - Pemilihan slot jam interaktif dengan pencegahan bentrok (*row-level locking* & *temporary reservation hold* 10 menit).
- **Checkout & Pembayaran QRIS:**
  - Pemilihan layanan tambahan (*add-on* per jam atau per sewa), ringkasan biaya transparan, integrasi pembayaran QRIS (Xendit), dan simulasi payment untuk testing lokal.
- **E-Tiket & Scanner Gate:**
  - Tiket digital publik dengan QR code bertoken 64-karakter unik dan status penggunaan real-time.
  - Scanner tiket berbasis kamera browser untuk staf/owner (`/gate/scanner`) dengan verifikasi instan dan proteksi *duplicate check-in*.
- **Portal Pemilik Venue (Owner):**
  - Manajemen informasi venue, ruang (*spaces*), jadwal operasional mingguan, dan katalog layanan tambahan.
- **Panel Superadmin:**
  - Workflow peninjauan dan persetujuan venue sebelum dipublikasikan ke marketplace.
  - Audit log aktivitas administratif dan penegakan otentikasi dua faktor (2FA).

---

## 🛠️ Tech Stack

### Backend
- **Framework:** [Laravel 13](https://laravel.com) (PHP 8.3+)
- **Authentication:** Laravel Fortify (dengan dukungan 2FA & Passkeys)
- **Database:** MySQL 8 / SQLite (pengujian & dev)
- **Cache & Queue:** Redis
- **Testing & QA:** [Pest 5](https://pestphp.com), PHPStan (Level 6), Laravel Pint

### Frontend
- **Adapter:** [Inertia.js v3](https://inertiajs.com) (React 19)
- **Styling:** [Tailwind CSS v4](https://tailwindcss.com) & Tw-Animate
- **Komponen UI:** Radix UI Primitives, Lucide Icons
- **Bundler:** Vite Plus (`vp`)

---

## 📋 Prasyarat Sistem

Pastikan environment lokal telah terpasang:
- **PHP** >= 8.3 dengan ekstensi: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`, `redis`
- **Composer** >= 2.2
- **Node.js** >= 22 & **npm**
- **MySQL** 8.0+ atau **SQLite** (opsional untuk dev cepat)
- **Redis** 6.0+

---

## 🚀 Panduan Instalasi Lokal

### 1. Clone Repository
```bash
git clone https://github.com/RahmadahyaWeb/adatempat.git
cd adatempat
```

### 2. Pasang Dependensi
```bash
composer install
npm install
```

### 3. Konfigurasi Environment
Salin file template environment dan buat kunci aplikasi:
```bash
cp .env.example .env
php artisan key:generate
```

Sesuaikan konfigurasi database dan Redis di dalam file `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=adatempat_v4
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```
> **Tips Cepat:** Jika ingin mencoba tanpa server MySQL/Redis lokal, Anda dapat menyetel `DB_CONNECTION=sqlite`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`, dan `CACHE_STORE=file`.

### 4. Migrasi & Seed Database
Jalankan migrasi tabel beserta data demo katalog:
```bash
php artisan migrate --seed
```

### 5. Jalankan Server Pengembangan
Jalankan backend Laravel dan frontend Vite:
```bash
composer dev
```
Atau jalankan di terminal terpisah:
```bash
php artisan serve
# Terminal lain:
npm run dev
```

Aplikasi dapat diakses di browser melalui: **`http://localhost:8000`**

---

## 👤 Akun Demo Bawaan (Seeder)

Setelah menjalankan `php artisan migrate --seed`, akun pengujian berikut otomatis tersedia (password untuk semua akun: `password`):

| Peran (Role) | Email | Akses & Ruang Lingkup |
| :--- | :--- | :--- |
| **Superadmin** | `superadmin@adatempat.test` | Verifikasi venue baru, moderasi, audit log (`/superadmin/venues`) |
| **Owner** | `owner@adatempat.test` | Kelola venue, slot, add-on, jadwal (`/owner/venues`), scanner gate |
| **Customer** | `customer@adatempat.test` | Jelajah marketplace, booking slot, riwayat tiket |

---

## 🧪 Testing & Code Quality

Proyek ini menerapkan standar pengujian ketat di sisi backend dan frontend:

```bash
# Menjalankan seluruh pengujian backend (Pint + PHPStan + Pest)
composer test

# Menjalankan pipeline CI lengkap lokal
composer ci:check

# Format & linter frontend (Vite Plus)
npm run check
npm run check:fix

# Pengecekan tipe TypeScript
npm run types:check

# Simulasi pembayaran QRIS untuk testing booking
php artisan booking:fake-pay {booking_id}
```

---

## 📁 Struktur Direktori Penting

```text
adatempat/
├── app/
│   ├── Enums/            # Enum status booking, metode bayar, role
│   ├── Http/Controllers/ # Controller publik, owner, superadmin, gate
│   ├── Models/           # Eloquent model (Venue, Space, Booking, dll.)
│   └── Services/         # Logika domain (SlotGenerator, BookingService, Audit)
├── database/
│   ├── migrations/       # Skema database terstruktur
│   └── seeders/          # Data awal demo venue, ruang, dan user
├── resources/
│   ├── js/
│   │   ├── components/   # Komponen React modular & UI publik
│   │   ├── layouts/      # Layout publik, dashboard, auth
│   │   ├── pages/        # Halaman Inertia (public, owner, superadmin, gate)
│   │   └── types/        # Definisi TypeScript
├── routes/
│   ├── web.php           # Routing web Inertia
│   └── console.php       # Perintah cron & artisan
└── tests/
    └── Feature/          # 200+ unit & feature tests (Pest)
```

---

## 📄 Lisensi

Proyek ini dilisensikan di bawah lisensi terbuka [MIT](LICENSE).
