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
- AvailabilityService dasar sedang dikerjakan di PR #29: validasi jam operasional termasuk lintas tengah malam, blokir manual, buffer, hold aktif/reconciling, dan booking yang masih menahan slot. Pemeriksaan ini masih read-only; belum ada lock/booking engine atomik, pemeriksaan subscription aktif, atau integrasi pembayaran. Feature Test ditambahkan tetapi belum dijalankan. PricingAndPromoService sedang dikerjakan di PR #30: kalkulasi hourly/session/daily, harga akhir pekan, pemilihan diskon Owner terbesar dengan tie-break ID terkecil, subsidi platform sesuai aturan kombinasi, dan kalkulasi total yang tidak mengonsumsi kuota. Feature Test ditambahkan tetapi belum dijalankan. PR #31 menambahkan skema `subscriptions` dan `invoices`, model lifecycle subscription, pemeriksaan kelayakan langganan untuk usaha, periode grace, pembatasan retained business, serta batas jumlah usaha/venue sesuai plan. Feature Test ditambahkan tetapi belum dijalankan. PR #32 menambahkan BookingEngineService transaksional, lock venue, validasi subscription/availability/consent, hold 15 menit, idempotensi checkout, audit log, dan cabang booking Rp0. Pembayaran berbayar masih menunggu integrasi QRIS. PR #33 menambahkan PaymentGatewayContract, adapter mock non-production, PaymentService yang membuat charge setelah transaksi checkout commit, serta schema/model `payment_webhook_events`. Gateway nyata belum dipilih; mock hanya untuk lingkungan development/testing. PR #34 menambahkan endpoint API webhook bertanda tangan, normalisasi payload, deduplikasi event per provider/event ID, pemeriksaan jumlah dan paid_at, konfirmasi booking hanya bila hold masih valid dan slot tetap tersedia, serta jalur rekonsiliasi untuk pembayaran terlambat/konflik. Scheduler untuk hold kedaluwarsa dan pemulihan event gagal masih belum ada. PR #35 menambahkan scheduler setiap menit untuk hold kedaluwarsa: hold dipindahkan ke `reconciling` sebelum query gateway di luar transaksi; status gagal/expired yang definitif melepas slot, sedangkan paid/pending/unknown tetap ditahan untuk rekonsiliasi terkontrol. PR #36 menambahkan skema/model `ad_bookings`, `refunds`, `payout_accounts`, `payouts`, dan `ledger_entries`, serta `LedgerService` untuk jurnal debit/kredit seimbang dan reversal tanpa mengubah entri lama. Feature Test ledger ditambahkan tetapi belum dijalankan. PR #37 menambahkan pembatalan booking oleh Customer/Owner, kalkulasi refund dari snapshot kebijakan, kewajiban refund idempoten, serta aturan slot: pembatalan Customer tetap memblokir slot sampai refund sukses, sedangkan pembatalan Owner melepas slot segera. PR #38 menambahkan endpoint webhook refund bertanda tangan, pemrosesan event refund idempoten, finalisasi refund saat sukses, serta penanganan status gagal tanpa melepas slot Customer. PR #39 menambahkan retry/polling refund setiap lima menit dengan idempotency key yang sama; refund yang tetap processing tidak melepas slot, refund gagal tetap menjadi kewajiban terbuka. PR #40 menambahkan lifecycle booking `confirmed → in_progress → awaiting_completion → completed`, auto-completion setelah 24 jam, payout eligibility setelah completion window dan tanpa refund terbuka, verifikasi rekening, pencatatan payout manual oleh Super Admin, serta posting ledger untuk pembayaran booking dan booking Rp0. PR #41 menambahkan Owner MFA TOTP khusus perubahan rekening, penyimpanan nomor rekening terenkripsi, halaman Owner untuk pengajuan rekening, serta dashboard Super Admin untuk verifikasi rekening dan pencatatan payout manual. PR #42 menambahkan sengketa per booking, deadline respons 48 jam, pesan antar pihak, unggah bukti privat, dan halaman sengketa Customer/Owner; booking berstatus disputed dan payout ditahan selama sengketa. PR #43 menambahkan dashboard Super Admin untuk keputusan final full payout/full refund/partial split, membuat refund obligation sesuai keputusan, menahan atau menyiapkan payout sesuai hasil, serta menyimpan keputusan dan nominal ke audit log.
- Pencarian tingkat lanjut menurut rentang harga, kapasitas, fasilitas, tanggal/jam, jarak, dan rating belum lengkap.
- Carousel dan sistem slot iklan belum tersedia.

## Tahap 3 — Booking engine, hold, pembayaran, dan ledger

Belum tersedia sebagai alur booking pelanggan yang lengkap. PR #29–#39 menyediakan availability, pricing, subscription eligibility, checkout, payment/webhook, expiry/reconciliation, refund, dan retry. PR #40 menambahkan completion 24 jam, payout eligibility/settlement manual, dan ledger untuk payment confirmed. Belum ada gateway produksi, admin UI rekonsiliasi, UI rekening/payout, refund otomatis untuk late payment, subscription lifecycle lengkap, chat, review, dan iklan.

## Tahap 4 — Pembatalan, refund, payout, sengketa, dan langganan

Parsial: PR #31 menyediakan subscription eligibility/invoice schema; PR #36–#39 menyediakan refund/payout/ledger schema dan refund workflow; PR #40–#41 menyediakan completion/payout eligibility, ledger awal, rekening Owner, dan operasi payout manual; PR #42–#43 menyediakan dispute hold serta keputusan final dan penyesuaian nominal payout/refund. Ledger adjustment resolusi, transfer otomatis, subscription purchase/renewal, trial provisioning, dan scheduler grace/suspension belum selesai.

## Tahap 5 — Chat, ulasan, iklan, moderasi, dan pengujian otomatis

Belum diimplementasikan sebagai alur produk lengkap. PR #36 menyediakan skema ad booking; PR #42–#43 menyediakan siklus sengketa, bukti privat, dan keputusan Super Admin. Ledger adjustment khusus resolusi sengketa/refund masih perlu dilengkapi; chat umum, ulasan, iklan lengkap, moderasi, dan notifikasi masih belum tersedia.

## Urutan pengembangan berikutnya

1. Melanjutkan fondasi Tahap 1: menambah skema database secara terkelola mengikuti inventaris PRD, model/relasi, Policies isolasi tenant, dan seeder konfigurasi yang belum tersedia.
2. Menyelesaikan alur Tahap 2 dan menggabungkan PR #21–#28 sesuai dependensi.
3. Meninjau rangkaian fondasi Tahap 3: PR #29 availability, PR #30 pricing/promosi, PR #31 subscription eligibility, dan PR #32 BookingEngine.
4. Menyelesaikan refund, payout, ledger dan workflow rekonsiliasi Super Admin; menghubungkan jurnal ke pembayaran/booking.
5. Melengkapi subscription lifecycle, chat, ulasan, iklan, sengketa, moderasi, notifikasi, dan seluruh skema/UI yang tersisa.
6. Menjalankan seluruh testing hanya setelah implementasi PRD mencapai 100% sesuai instruksi pengguna.

**Catatan status:** rangkaian 20 poin pengujian manual sebelumnya tetap tercatat lulus. Pengujian tambahan yang dilewati tetap berstatus *belum diuji secara manual*, bukan dianggap lulus. Business/Venue Policies telah di-merge melalui PR #14 tetapi belum diuji; fondasi `subscription_plans` dan `platform_settings` pada perubahan ini juga belum diuji.


## Catatan instruksi pengguna

Testing otomatis, manual, dan browser ditunda sampai implementasi Adatempat terhadap PRD v1.7 dinyatakan 100% selesai. Progress persentase adalah estimasi perencanaan, bukan bukti bahwa kode telah diuji atau di-merge. PR terbuka harus digabungkan sesuai dependensi sebelum rilis.
