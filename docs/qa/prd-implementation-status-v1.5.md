# Adatempat — Status Implementasi terhadap PRD v1.5

Tanggal tinjauan: 26 Oktober 2026  
Sumber acuan: Adatempat_PRD_Konsolidasi_v1.5.md (Single Source of Truth) dan kode pada repository.

Dokumen ini membedakan **terimplementasi**, **parsial**, dan **belum diimplementasikan**. Keberadaan halaman atau skema saja tidak berarti seluruh alur sudah selesai atau sudah diuji.

## Tahap 1 — Fondasi, skema database, dan autentikasi

### Terimplementasi
- Registrasi dan login email/password.
- Verifikasi email dengan tautan bertanda tangan yang terikat pada akun.
- Halaman dan aksi pemulihan/reset password tersedia di kode; pengujian manualnya dilewati atas permintaan pengguna.
- Persetujuan Terms and Conditions dan Privacy Policy dicatat saat registrasi.
- Middleware role dan status akun.
- Dasar profil customer/owner, kategori, usaha, dan venue.
- Kerangka setup dan challenge MFA TOTP Super Admin; pengujian manualnya dilewati atas permintaan pengguna.

### Belum lengkap
- Inventaris skema pada PRD mencakup 41 tabel fisik yang disebut eksplisit, ditambah beberapa entitas pendukung yang perlu direkonsiliasi. Migrasi di `main` saat ini masih mencakup sebagian kecil skema tersebut; tabel `subscription_plans` dan `platform_settings` sudah tersedia, tetapi alur langganannya belum dibangun.
- Laravel Policies untuk seluruh domain belum lengkap. BusinessPolicy dan VenuePolicy kini dibuat serta dihubungkan ke aksi daftar, lihat, buat, dan ubah owner; policy untuk domain lain masih harus ditambahkan.
- Google OAuth belum tersedia di alur login/registrasi dan laravel/socialite tidak tercantum pada dependensi.
- Seeder paket Starter/Business dan nilai awal `platform_settings` tersedia. UI pengaturan Super Admin dan alur siklus langganan belum tersedia.
- Audit log saat ini mencatat keputusan revisi identitas usaha; cakupan audit lintas modul masih harus diterapkan sesuai PRD.

## Tahap 2 — Manajemen usaha, venue dinamis, dan katalog publik

### Terimplementasi
- CRUD dasar usaha owner dengan status awal Draft.
- CRUD dasar venue owner, kategori, nama, dan deskripsi.
- Profil usaha publik untuk usaha aktif.
- Katalog dengan pencarian kata kunci, lokasi berbasis teks, dan kategori.

### Parsial / pekerjaan yang dilanjutkan
- Revisi identitas usaha aktif: owner dapat mengajukan perubahan identitas utama tanpa langsung mengubah data publik; Super Admin dapat menyetujui atau menolak, termasuk kota/provinsi/koordinat, dan keputusan dicatat ke `audit_logs`.
- Alur pengajuan verifikasi usaha baru kini tersedia secara parsial: Owner melengkapi alamat, kota/provinsi, koordinat, dan minimal satu venue Draft lalu mengajukan verifikasi; Super Admin dapat menyetujui atau menolak dengan alasan wajib, dan tindakan dicatat ke `audit_logs`.
- Status verifikasi dan status publikasi dipisahkan secara eksplisit. Persetujuan memberi status terverifikasi, tetapi `published_at` tetap null sampai siklus langganan serta alur publikasi venue memenuhi persyaratan PRD.
- Fondasi template kategori dan field dinamis venue telah di-merge melalui PR #22: template awal untuk kategori Acara, Meeting, Foto, dan Outdoor, validasi server berdasarkan tipe, penyimpanan nilai bertipe, kapasitas/fasilitas, dan tampilan nilai pada profil publik. Test baru belum dijalankan.
- UI Super Admin untuk template/field tersedia melalui PR #23. Custom field Owner sedang dikerjakan pada PR #24; pengujian tambahan belum dijalankan.
- UI Super Admin untuk mengelola template/field dan custom field Owner belum tersedia.
- Media/foto, harga, jam operasional, availability, blok waktu, dan promosi belum tersedia di formulir pengelolaan.
- Pencarian tingkat lanjut menurut rentang harga, kapasitas, fasilitas, tanggal/jam, jarak, dan rating belum lengkap.
- Carousel dan sistem slot iklan belum tersedia.

## Tahap 3 — Booking engine, hold, pembayaran, dan ledger

Belum diimplementasikan sebagai alur produk. PRD mengharuskan availability/pricing service, hold 15 menit, proteksi konkurensi, booking Rp0, payment gateway adapter/mock, webhook idempoten, dan ledger.

## Tahap 4 — Pembatalan, refund, payout, sengketa, dan langganan

Belum diimplementasikan sebagai alur produk. Termasuk subscription lifecycle, invoice, payout account, payout, refund, sengketa, dan scheduler terkait.

## Tahap 5 — Chat, ulasan, iklan, moderasi, dan pengujian otomatis

Belum diimplementasikan sebagai alur produk lengkap. Feature Test sudah mencakup regresi revisi identitas dan proses verifikasi usaha; PR #22 menambahkan test validasi/penyimpanan template venue dan rendering profil publik. Semua test tambahan tersebut belum dijalankan; PR #23 juga menambahkan Feature Test untuk pengelolaan template, dan test tersebut belum dijalankan. Test concurrency untuk booking/payment, serta test domain refund, payout, langganan, chat, review, iklan, dan moderasi belum tersedia sebagai suite lengkap.

## Urutan pengembangan berikutnya

1. Melanjutkan fondasi Tahap 1: menambah skema database secara terkelola mengikuti inventaris PRD, model/relasi, Policies isolasi tenant, dan seeder konfigurasi yang belum tersedia.
2. Merge PR #23, lalu lanjutkan custom fields Owner, media/foto, harga, jadwal, availability, promosi, dan publikasi venue. Verifikasi usaha baru kini tersedia secara parsial; publikasi masih menunggu integrasi siklus langganan.
3. Setelah fondasi data dan pengelolaan venue memadai, membangun booking engine dan pembayaran dengan driver mock sesuai Tahap 3, lalu melanjutkan Tahap 4 dan 5 secara berurutan.

**Catatan status:** rangkaian 20 poin pengujian manual sebelumnya tetap tercatat lulus. Pengujian tambahan yang dilewati tetap berstatus *belum diuji secara manual*, bukan dianggap lulus. Business/Venue Policies telah di-merge melalui PR #14 tetapi belum diuji; fondasi `subscription_plans` dan `platform_settings` pada perubahan ini juga belum diuji.
