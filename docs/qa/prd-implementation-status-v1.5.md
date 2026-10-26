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
- Inventaris PRD v1.7 menetapkan 45 tabel fisik. Skema yang tersedia masih bertahap; PR #31 menambahkan tabel `subscriptions` dan `invoices`, sementara payment, refund, payout, ledger, engagement, iklan, sengketa, dan notifikasi masih harus dilengkapi.
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
- Konfigurasi jam operasional mingguan dan harga sedang dikerjakan di PR #26 (bertumpuk di atas PR #25): tujuh hari operasional, jam lintas tengah malam, buffer persiapan, model harga hourly/session/daily, harga akhir pekan, dan satu model harga aktif per venue. Zona waktu usaha tersedia sebagai pilihan `Asia/Jakarta`, `Asia/Makassar`, atau `Asia/Jayapura`; jam operasional dan harga aktif ditampilkan pada profil publik. Feature Test mencakup penyimpanan jam/harga, validasi konfigurasi, perubahan model harga, dan tampilan publik; test belum dijalankan.
- Blokir jadwal manual Owner sedang dikerjakan di PR #27 (bertumpuk di atas PR #26): tabel `venue_blocks`, pencatatan waktu dalam UTC dengan input sesuai zona waktu usaha, alasan blokir, dan kontrol tambah/hapus yang dibatasi oleh kepemilikan venue. Blokir belum terhubung ke availability engine atau booking karena domain booking/hold belum tersedia. Feature Test ditambahkan tetapi belum dijalankan.
- Manajemen promosi Owner sedang dikerjakan di PR #28: diskon persentase/nominal, kode opsional, periode aktif, kuota, status jeda/aktif, konversi waktu ke UTC, dan audit log. Promo belum digunakan untuk kalkulasi checkout atau konsumsi kuota booking sampai Booking Engine tersedia. Feature Test ditambahkan tetapi belum dijalankan.
- AvailabilityService dasar sedang dikerjakan di PR #29: validasi jam operasional termasuk lintas tengah malam, blokir manual, buffer, hold aktif/reconciling, dan booking yang masih menahan slot. Pemeriksaan ini masih read-only; belum ada lock/booking engine atomik, pemeriksaan subscription aktif, atau integrasi pembayaran. Feature Test ditambahkan tetapi belum dijalankan. PricingAndPromoService sedang dikerjakan di PR #30: kalkulasi hourly/session/daily, harga akhir pekan, pemilihan diskon Owner terbesar dengan tie-break ID terkecil, subsidi platform sesuai aturan kombinasi, dan kalkulasi total yang tidak mengonsumsi kuota. Feature Test ditambahkan tetapi belum dijalankan. PR #31 menambahkan skema `subscriptions` dan `invoices`, model lifecycle subscription, pemeriksaan kelayakan langganan untuk usaha, periode grace, pembatasan retained business, serta batas jumlah usaha/venue sesuai plan. Feature Test ditambahkan tetapi belum dijalankan. PR #32 menambahkan BookingEngineService transaksional, lock venue, validasi subscription/availability/consent, hold 15 menit, idempotensi checkout, audit log, dan cabang booking Rp0. Pembayaran berbayar masih menunggu integrasi QRIS.
- Pencarian tingkat lanjut menurut rentang harga, kapasitas, fasilitas, tanggal/jam, jarak, dan rating belum lengkap.
- Carousel dan sistem slot iklan belum tersedia.

## Tahap 3 — Booking engine, hold, pembayaran, dan ledger

Belum tersedia sebagai alur booking pelanggan yang lengkap. PR #29 menyediakan skema booking/hold dan AvailabilityService; PR #30 kalkulasi harga/promosi; PR #31 subscription eligibility dan skema invoice; PR #32 transaksi lokal untuk checkout berbayar dan booking Rp0. QRIS, webhook, expiry/reconciliation, konfirmasi booking berbayar, ledger, refund, payout, dan settlement masih belum diimplementasikan. Test ditambahkan tetapi belum dijalankan.

## Tahap 4 — Pembatalan, refund, payout, sengketa, dan langganan

Parsial: PR #31 menambahkan skema/model subscription dan invoice serta eligibility awal. Belum ada pembelian/perpanjangan langganan, provisioning trial, scheduler grace/suspension, payout account/payout, refund, sengketa, ledger, dan scheduler operasional.

## Tahap 5 — Chat, ulasan, iklan, moderasi, dan pengujian otomatis

Belum diimplementasikan sebagai alur produk lengkap. Feature Test telah ditambahkan di beberapa PR untuk regresi revisi identitas, template, availability, pricing, subscription eligibility, dan booking engine. Atas instruksi pengguna, seluruh test dan browser testing ditunda sampai implementasi mencapai 100%; belum ada hasil lulus baru. Test concurrency booking/payment dan suite domain finansial masih perlu ditambahkan.

## Urutan pengembangan berikutnya

1. Melanjutkan fondasi Tahap 1: menambah skema database secara terkelola mengikuti inventaris PRD, model/relasi, Policies isolasi tenant, dan seeder konfigurasi yang belum tersedia.
2. Menyelesaikan alur Tahap 2 dan menggabungkan PR #21–#28 sesuai dependensi.
3. Meninjau rangkaian fondasi Tahap 3: PR #29 availability, PR #30 pricing/promosi, PR #31 subscription eligibility, dan PR #32 BookingEngine.
4. Menghubungkan PaymentGatewayContract dan mock adapter; melengkapi webhook idempoten, expiry/reconciliation, dan konfirmasi booking.
5. Melanjutkan refund, payout, ledger, subscription lifecycle, chat, ulasan, iklan, sengketa, moderasi, notifikasi, serta sisa skema dan UI PRD.
6. Menjalankan seluruh testing hanya setelah implementasi PRD mencapai 100% sesuai instruksi pengguna.

**Catatan status:** rangkaian 20 poin pengujian manual sebelumnya tetap tercatat lulus. Pengujian tambahan yang dilewati tetap berstatus *belum diuji secara manual*, bukan dianggap lulus. Business/Venue Policies telah di-merge melalui PR #14 tetapi belum diuji; fondasi `subscription_plans` dan `platform_settings` pada perubahan ini juga belum diuji.


## Catatan instruksi pengguna

Testing otomatis, manual, dan browser ditunda sampai implementasi Adatempat terhadap PRD v1.7 dinyatakan 100% selesai. Progress persentase adalah estimasi perencanaan, bukan bukti bahwa kode telah diuji atau di-merge. PR terbuka harus digabungkan sesuai dependensi sebelum rilis.
