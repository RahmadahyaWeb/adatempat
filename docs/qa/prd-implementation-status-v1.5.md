# Adatempat — Status Implementasi terhadap PRD v1.7

Tanggal tinjauan: 26 Oktober 2026  
Sumber acuan: Adatempat PRD Konsolidasi v1.7 (Single Source of Truth) dan kode pada repository.

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
- Inventaris PRD v1.7 menetapkan 45 tabel fisik. Migrasi yang tersedia masih mencakup sebagian kecil skema tersebut; tabel `subscription_plans` dan `platform_settings` sudah tersedia, tetapi alur siklus langganan belum diimplementasikan.
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
- Revisi identitas usaha aktif: Owner dapat mengajukan perubahan identitas tanpa langsung mengubah data publik; Super Admin dapat menyetujui/menolak, termasuk kota/provinsi/koordinat, dan keputusan dicatat ke `audit_logs`.
- Alur verifikasi usaha baru sedang dikerjakan di PR #21 (belum merged): pengajuan Owner, antrean Super Admin, keputusan dengan alasan wajib, dan audit log. Persetujuan tetap tidak menerbitkan profil ke publik sampai aturan langganan/publikasi tersedia.
- Fondasi template kategori dan field dinamis venue sedang dikerjakan di PR #22 (belum merged): template default untuk kategori Acara, Meeting, Foto, dan Outdoor; validasi server; penyimpanan nilai bertipe; kapasitas/fasilitas; dan tampilan nilai pada profil publik. Test baru belum dijalankan.
- UI Super Admin untuk mengelola template dan field sedang dikerjakan di PR #23 (bertumpuk di atas PR #22).
- Custom field per venue yang dibuat Owner sedang dikerjakan di PR #24 (bertumpuk di atas PR #23): tipe terbatas `text`, `number`, `boolean`, `select`; validasi server; penyimpanan nilai bertipe melalui `venue_field_values`; dan nilai dari field berstatus `hidden_by_admin` tidak ditampilkan pada profil publik. Custom field tidak otomatis menjadi filter marketplace. Test baru belum dijalankan.
- Pengelolaan media/foto venue sedang dikerjakan di PR #25 (bertumpuk di atas PR #24): upload JPEG/PNG/WebP maksimal 5 MB, metadata alt text, foto sampul, urutan galeri, penghapusan file dari storage, dan tampilan foto pada katalog/profil publik. Test baru belum dijalankan.
- Harga, jam operasional, availability, blok waktu, dan promosi belum tersedia di formulir pengelolaan.
- Pencarian tingkat lanjut menurut rentang harga, kapasitas, fasilitas, tanggal/jam, jarak, dan rating belum lengkap.
- Carousel dan sistem slot iklan belum tersedia.

## Tahap 3 — Booking engine, hold, pembayaran, dan ledger

Belum diimplementasikan sebagai alur produk. PRD mengharuskan availability/pricing service, hold 15 menit, proteksi konkurensi, booking Rp0, payment gateway adapter/mock, webhook idempoten, dan ledger.

## Tahap 4 — Pembatalan, refund, payout, sengketa, dan langganan

Belum diimplementasikan sebagai alur produk. Termasuk subscription lifecycle, invoice, payout account, payout, refund, sengketa, dan scheduler terkait.

## Tahap 5 — Chat, ulasan, iklan, moderasi, dan pengujian otomatis

Belum diimplementasikan sebagai alur produk lengkap. Feature Test telah tersedia untuk regresi revisi identitas; PR ini menambahkan test validasi/penyimpanan template venue dan rendering profil publik. Test baru belum dijalankan. Test concurrency untuk booking/payment dan suite domain finansial masih belum tersedia.

## Urutan pengembangan berikutnya

1. Melanjutkan fondasi Tahap 1: menambah skema database secara terkelola mengikuti inventaris PRD, model/relasi, Policies isolasi tenant, dan seeder konfigurasi yang belum tersedia.
2. Menyelesaikan alur Tahap 2: tinjau dan merge PR #21, #22, #23, #24, lalu #25 sesuai dependensi; lanjutkan harga, jadwal, availability, dan promosi.
3. Setelah fondasi data dan pengelolaan venue memadai, membangun booking engine dan pembayaran dengan driver mock sesuai Tahap 3, lalu melanjutkan Tahap 4 dan 5 secara berurutan.

**Catatan status:** rangkaian 20 poin pengujian manual sebelumnya tetap tercatat lulus. Pengujian tambahan yang dilewati tetap berstatus *belum diuji secara manual*, bukan dianggap lulus. Business/Venue Policies telah di-merge melalui PR #14 tetapi belum diuji; fondasi `subscription_plans` dan `platform_settings` pada perubahan ini juga belum diuji.
