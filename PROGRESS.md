# AdaTempat — Progress Log

**Last updated:** 2026-11-06
**Current stage:** Sprint 3 (Owner Portal & Gate 3 Review) — Chunk 3.4 & Gate 3 DONE. Gate 3 RESMI LULUS. Siap lanjut ke Sprint 4 (Xendit QRIS + Webhook + Split).
`implementation-plan.md` §18 adalah **sumber utama checkpoint**. File ini adalah mirror kronologis.

Setiap tahap selesai wajib memperbarui:

1. `implementation-plan.md` §18: tabel progress + checkpoint log.
2. File ini: tabel status + satu entri log bertanggal.
3. Berhenti dan tunggu konfirmasi user sebelum tahap berikutnya.

---

## Status Snapshot

| ID  | Status      | Ringkasan                                                                                       |
| --- | ----------- | ----------------------------------------------------------------------------------------------- |
| 0.1 | DONE        | Skeleton Laravel 13 + React/Inertia + Tailwind; MySQL `adatempat_v4`; Redis; CI; `/up`          |
| 0.2 | BLOCKED     | Xendit client + webhook + test lokal selesai; verifikasi sandbox menunggu kredensial            |
| 0.3 | DONE        | Canonical grid MySQL + unique cell + row lock; `slot:concurrency-check` PASS                    |
| 0.4 | BLOCKED     | Pipeline email lokal (Mailpit) + fallback log selesai; provider/domain live pending             |
| 1.1 | DONE        | Auth & RBAC: role, businesses, register/business, policy, middleware, IDOR test                 |
| 1.2 | DONE        | Schema katalog: venue, space, schedule, amenity, add-on; seed 4 venue                           |
| 1.3 | DONE        | Public catalog UI: Home, Browse `/spaces` (filter & sort toolbar), Venue `/v/{venue}`, Space `/spaces/{space}` editorial DOKO 6/6 split + 2 kolom spesifikasi; QA 320–1440 zero-overflow; WCAG AA; CI 81 test/435 assertion. |
| 1.4 | DONE        | Slot engine: in-memory canonical grid (30/60/90/120 min), timezone lokal venue, horizon 30 hari, schedule versioning, diff `booking_slots`; 9 Pest test/59 assertion, CI 90 test/494 assertion hijau. |
| 1.5 | DONE        | Slot UI + Cache: date selector 7 hari, slot grid matrix, multi-slot selection, dynamic order summary rail, Redis cache 60s + auto-invalidation, silent polling 15s; 7 Pest test/43 assertions, CI 97 test/537 assertions hijau, visual QA 320–1440 zero-overflow. |
| 2.1 | DONE        | Checkout & Booking: skema `bookings` & `booking_add_ons`, enums `BookingStatus` & `PaymentMethod`, `BookingService` (kalkulasi add-on multi-type, transaksi DB atomik, row lock `slot_locks`, cache invalidation, ADT-BK-... & qr token), `CheckoutController` & `PaymentController`, UI `checkout.tsx` & `payment-pending.tsx` (editorial DOKO); 13 Pest test baru (total 110 test/636 assertion hijau); browser QA 5 viewport (320–1440) zero-overflow; WCAG AA pass. |
| 2.2 | DONE        | Hold + cleanup: Redis distributed lock Tier 1 (`Cache::lock`) + MySQL row lock Tier 2 (`slot_locks`), all-or-nothing rollback pada multi-slot, pra-pemeriksaan kueri bentrok, release `booking_slots` & cache invalidation otomatis saat expired, `BookingService::expireBooking` & `expirePendingBookings`, command Artisan `bookings:expire-pending` + scheduled cron `everyMinute()`, queued job `ExpirePendingBookingsJob`, on-the-fly expiry di `PaymentController::show`, `SlotGeneratorService::generate`, & `CheckoutController::store`, flash session error handling di `HandleInertiaRequests`, conflict action banner ("Pilih Slot Waktu Lain" → `/spaces/{slug}?date={date}`) di `checkout.tsx` & alert di `space-slot-picker.tsx`, expired UI state (badge Kedaluwarsa, kartu peringatan habis waktu, CTA "Pesan Ulang Lapangan") di `payment-pending.tsx`; 6 test baru (total 116 test / 670 assertions hijau); `slot:concurrency-check` PASS; browser QA Chromium terverifikasi; `composer ci:check` hijau penuh (Pint, PHPStan Level 5 0 error, Pest 100%, `vp check` 100 file, `tsc --noEmit` 0 error, `build` 2.0s). |
| 2.3 | DONE        | Fake payment + payment page: Abstraksi `PaymentGatewayInterface` & `FakePaymentGateway` driver, `BookingService::confirmBooking` dengan transaksi atomik & `lockForUpdate`, endpoints status JSON & simulate POST, Artisan `booking:fake-pay`, UI real-time polling 3s, instant confirmed transition, tiket QR check-in & copy token, widget pengujian lokal; 5 test Feature baru `FakePaymentTest` lulus (total 121 test / 695 assertions hijau); QA browser 390/768/1440 membuktikan alur bayar instan; CI hijau penuh. |
| 2.4 | DONE        | Ticket + email log: Token 64 karakter unik, tabel `notification_logs` & model, mailable `BookingConfirmedMail` (template Blade responsive) & queued job `SendBookingConfirmedNotificationJob`, hook otomatis di `BookingService::confirmBooking`, signed URL route `tickets.show` dengan expiry & tampered rejection, header `X-Robots-Tag: noindex, nofollow`, UI `ticket.tsx` (DOKO pass card, QR check-in, 64-char token box, tombol cetak); 6 test Pest baru `TicketAndNotificationTest` lulus (total 127 test / 732 assertions hijau); browser QA 390/768/1440 pass; CI hijau penuh. |
| 2.5 | DONE        | Gate scanner: In-browser camera scanning + manual token 64 karakter / booking code input; validasi window waktu 30 menit s.d selesai, venue matching, dan single-use atomik; `GateScannerController` & route `/gate/scanner` (role:owner,superadmin); antarmuka DOKO `scanner.tsx` dengan venue selector, tab switch, feedback kartu status seketika (hijau sukses, merah/amber penolakan spesifik: `ALREADY_CHECKED_IN`, `WRONG_VENUE`, `TOO_EARLY`, `TOO_LATE`, `INVALID_TOKEN`), dan riwayat pengunjung terkini; 13 test Pest baru `GateScannerTest` lulus (total 140 test / 782 assertions hijau); browser QA 390/768/1440 pass; CI hijau penuh. Gate 2 Lulus. |
| Auth | DONE        | Redesign UI Auth: Login (`/login`), Register (`/register`), dan Register Business (`/register/business`) diselaraskan penuh dengan tema editorial DOKO AdaTempat (split showcase desktop 5/7, quick tab switcher, 48px touch-friendly input, 0 violation axe-core, responsive 390/768/1440 zero overflow). |
| Copy | DONE        | Penyelarasan Copywriting Inklusif (Tempat Olahraga, Ruang Kerja, & Acara): Menyeimbangkan tone of voice Opsi A agar ramah dan kasual ("kamu", "tanpa ribet chat admin") namun merangkul seluruh kategori venue (ruang meeting, studio, coworking, hall acara, dan lapangan olahraga) hingga ke level layout global (navbar: "Cari tempat", "Untuk pemilik tempat"; footer: "Mitra Pemilik", "Kategori ruang", "Cara pemesanan", tagline platform & harga transparan; auth showcase & settings layout). |
| 3.1 | DONE        | Owner venue CRUD: Create/edit venue, timezone (WIB/WITA/WIT), cover privat + signed URL, submit approval, publish toggle, isolasi tenant & peninjauan ulang saat perubahan material; 13 test Pest baru (total 153 test / 860 assertions), browser QA Chromium 390/768/1440 pass, CI hijau penuh. |
| 3.2 | DONE        | Owner space/schedule/add-on CRUD: CRUD space, cancellation policy & refund, 7-day schedule editor + versioning sync, CRUD add-on (per_hour, per_item, per_booking), SpacePolicy tenant isolation; 28 test Pest baru (total 183 test / 1019 assertions), browser QA Chromium 390/768/1440 15/15 pass zero overflow, CI hijau penuh. |
| 3.3 | DONE        | Superadmin approval + audit log + 2FA enforcement: Skema DB append-only `audit_logs` & model `AuditLog`, `AuditService`, Fortify 2FA listener, middleware `EnsureSuperadminTwoFactorEnforced` (alias `superadmin.2fa`), `VenuePolicy` (approve, reject, unpublish), Form Requests, Controller (`VenueApprovalController` & `AuditLogController`), UI DOKO (`venues/index`, `venues/show` split decision panel & timeline audit, `audit/index`), update navigasi sidebar & dashboard; 18 test Pest baru (total 201 test / 1139 assertions); browser QA 390/768/1440 9/9 pass zero overflow; CI hijau penuh. |
| 3.4 | DONE        | Tenant isolation tests (IDOR/RBAC matrix): Suite pengujian `TenantIsolationMatrixTest` (13 test Feature, 54 assertions) memvalidasi batas tenant lintas peran; anti-IDOR 403 & URL mismatch 404; isolasi katalog publik untuk venue pending/rejected; scanner cross-venue rejection; Gate 3 checklist lulus 100%; CI 214 test/1193 assertion hijau. Gate 3 LULUS. |
**Gate 0:** belum lulus penuh. Concurrency MySQL sudah terbukti; Xendit live, split/KYC/refund live, dan email provider live masih pending.

---

## Log Kronologis

### 2026-11-05 — 0.1 Skeleton repo — DONE

- Laravel 13.17 + PHP 8.5, Inertia 3 + React 19 + TypeScript, Tailwind 4, Pest, Larastan, Pint.
- MySQL `adatempat_v4` (database lama `adatempat` tidak disentuh), Redis cache/queue/session.
- CI GitHub Actions dengan service MySQL 8.4 + Redis 8; health check `/up`.
- Verifikasi: `composer ci:check` hijau, 41 test/139 assertion, `npm run build` sukses.
- Catatan: starter kit resmi sudah membawa auth/Fortify/passkeys.

### 2026-11-05 — 0.2 Xendit spike — BLOCKED

- Implementasi: `XenditClient` (QRIS v3, status, simulate, refund, sub-account), webhook `POST /api/webhooks/xendit` dengan `x-callback-token` + dedupe cache, command `xendit:spike`.
- Verifikasi lokal: 9 test contract/webhook; total 50 test/156 assertion; Pint/PHPStan/check/tsc hijau.
- Temuan: v3 memakai `x-callback-token` (bukan HMAC); split rule 100% gagal → gunakan `for-user-id`; refund `/v3/refunds` memakai `payment_id`.
- Blocker: user mengonfirmasi belum ada `XENDIT_SECRET_KEY`/`XENDIT_CALLBACK_TOKEN`; QRIS sandbox live, webhook live, split/KYC, dan refund live belum dijalankan.
- Memo: `docs/xendit-spike.md`.

### 2026-11-05 — 0.3 MySQL concurrency spike — DONE

- Migrasi `slot_locks` (unique per space/date) + `booking_slots` (unique `space_id, slot_date, start_time`).
- `SlotReservationService`: row lock `FOR UPDATE`, insert semua cell dalam satu transaksi, rollback total saat conflict.
- Verifikasi: `slot:concurrency-check` PASS dua kali (`A exit=0`, `B exit=1 CONFLICT`, `rows=1`); 4 test service; total 54 test/162 assertion.
- Memo: `docs/mysql-concurrency-spike.md`.

### 2026-11-05 — 0.4 Email spike — BLOCKED

- Implementasi: mailable `EmailSpike`, command `mail:spike`, config Mailpit/Resend SMTP, fallback log.
- Verifikasi lokal: Mailpit menerima 2 email; fallback log tercatat; 2 test; total 56 test/167 assertion.
- Blocker: domain + kredensial provider (Resend) belum ada; SPF/DKIM/DMARC live belum diverifikasi.
- Memo: `docs/email-spike.md`.

### 2026-11-05 — 1.1 Auth & RBAC — DONE

- `users.role` enum cast (`customer`/`owner`/`superadmin`), tabel `businesses`, factory, seeder demo.
- Registrasi owner `/register/business` (backend + UI), user + business dalam satu transaksi, event verifikasi email.
- `BusinessPolicy`, middleware `role`, route owner/superadmin, owner listing tenant-scoped.
- Verifikasi: 67 test/211 assertion; login 3 role; IDOR lain-owner 403; customer ditolak; superadmin boleh.
- UI: visual QA 390/768/1440 tanpa overflow, label form terhubung, console 0 error, submit form → `/email/verify`.
- Screenshot: `visual-qa/register-business-390.png`, `-768.png`, `-1440.png`.
- Catatan: global scope Eloquent ditunda ke 3.4; tombol 36–40px (P3, design system).

### 2026-11-05 — 1.2 Schema katalog + seed — DONE

- Migrasi: `venues`, `spaces`, `space_schedule_versions`, `schedules`, `amenities`, `space_amenities`, `space_add_ons`.
- Enum: `VenueApprovalStatus`, `SpaceCategory`, `CancellationPolicy`, `AddOnPricingType`; model + relasi + factory untuk seluruh entitas katalog.
- `CatalogSeeder`: 4 venue approved/published (Samarinda, Balikpapan, Jakarta, Surabaya), 8 space, 56 schedule (7 hari/space), 8 amenity, 15 add-on; idempotent.
- Verifikasi: `migrate:fresh --seed` MySQL → 4 venue/8 space/56 schedule; seed ulang tidak menambah baris; `slot:concurrency-check` tetap PASS; 73 test/248 assertion; `composer ci:check` hijau.
- Catatan: FK `booking_slots.space_id` ditunda ke 2.1 agar probe sintetis 0.3 tidak pecah.

### 2026-11-05 — 1.3 Public catalog — IN PROGRESS

- Backend: route publik `/`, `/spaces`, `/v/{venue}`, `/spaces/{space}`; controller `Catalog`; filter kota/kategori/tanggal/harga; detail space dengan amenitas, add-on, dan jam operasional.
- Test: `PublicCatalogTest` + `ExampleTest`; total 80 test/393 assertion; `composer ci:check` hijau.
- UI hasil designer pertama direvert atas permintaan user (halaman/komponen publik, token tema lama, Google Fonts, mapping layout publik dihapus).

### 2026-11-05 — 1.3 UI Stage 1 Home (rebuild dari nol) — REVIEW

- UI lama dibuang, Stage 1 Home dibangun ulang mengikuti `docs/ui-kit-plan.md` dan `design-reference/gumroad-01..06.png` (DOKO).
- Hasil: 13 komponen publik + `nav.ts` di `resources/js/components/public/`, `resources/js/layouts/public-layout.tsx`, `resources/js/pages/public/home.tsx`, token cream/cobalt/navy + font Bricolage Grotesque/Geist/Geist Mono di `resources/css/app.css`.
- Verifikasi saat itu: `npm run check` hijau (89 file format, 83 lint), `npm run types:check` bersih, `npm run build` sukses 1.61s.
- Catatan: entri ini superseded oleh "1.3 UI Stage 1 Home (final)" di bawah — screenshot `visual-qa/public-*.png`, `ui-kit-*.png`, dan `stage1-home-final-*` berasal dari iterasi lama, abaikan.
- Belum (saat itu): review skill/visual QA manusia; `/spaces`, `/v/{venue}`, `/spaces/{space}` masih render halaman yang belum ada (Stage 2–3).

### 2026-11-05 — 1.3 UI Stage 1 Home (final) — REVIEW

- `better-interface` self-review: 1 HIGH diperbaiki — `outline-none` mengeset `--tw-outline-style: none`, sehingga `focus-visible:outline-2` tidak pernah tampil. Kelas dihapus dari elemen yang dapat difokus Tab (tersisa `focus-visible:outline-none` hanya di `Dialog.Content` drawer, kontainer non-tabbable yang difokus program); 27/27 elemen fokus terverifikasi punya outline 2px (cobalt di cream, butter di navy/cobalt).
- Penyeimbangan hero (konten tengah vertikal di `lg` mengikuti referensi), drawer memakai `colorScheme: light`, hover gambar kartu dimatikan saat `prefers-reduced-motion`.
- Verifikasi final: `npm run check` hijau (89 file format, 83 lint), `npm run types:check` bersih, `npm run build` sukses 1.41s.
- QA browser independen (Chromium, dev server `:8000`), 2026-11-05:
  - 1440/1024/768/390: `scrollWidth === clientWidth`, 0 console error, 5/5 gambar termuat (`naturalWidth 1200`).
  - Header 1024 masih lega (wordmark 64–164, nav 346–711, aksi 760–960) — tidak ada tabrakan sebelum breakpoint `lg`.
  - Fokus keyboard: Tab ke-3 = "Cari venue" dengan `outline: solid 2px rgb(33,84,214)`.
  - `html.dark` (emulasi `prefers-color-scheme: dark`): header tetap `rgb(240,234,217)`, teks utama ink — token auth/shadcn tidak bocor ke halaman publik.
  - Menu 390: `body overflow: hidden`, fokus pindah ke tombol tutup, Escape menutup.
  - Dua cacat QA diperbaiki: (a) hero dan kartu venue pertama memakai foto identik → kartu `futsal` kini `/images/venues/hero-futsal.jpg` + `object-[center_62%]`; (b) separator `·` menggantung di ujung baris pada 390 → trust bar jadi `flex-wrap gap-x-6`, tanpa glyph (sesuai `gumroad-01.png`). Terukur ulang: item y=853/853/877.
- Screenshot bukti: `visual-qa/stage1-final-{390,768,1440}.png`, `stage1-final-390-trust.png`, `stage1-verify-{1024,dark-1440,focus-1440,390-menu}.png`. Screenshot `public-*.png` dan `ui-kit-*.png` berasal dari UI lama yang sudah dibuang — abaikan.
- Copy pass (skill `clarify`, permintaan user): "Cari tempat" → "Cari venue" konsisten di nav, footer, dan hero CTA; trust bar "Konfirmasi instan" → "Tiket digital" karena `instant_book` boolean per space (bukan jaminan platform); langkah 1–3 ditulis ulang mengikuti flow nyata (venue/tanggal/jam → total sebelum bayar → tiket QR setelah pembayaran); jargon "checkout"/"tarif" dihapus; duplikasi klaim "venue diperiksa" di CTA owner dan kalimat kedua empty state dihapus; alt gambar kartu jadi dekoratif karena nama venue + tag kategori sudah terbaca. Screenshot `visual-qa/stage1-copy2-{390,1440}.png`, tanpa overflow, 0 console error, `npm run check`/`types:check`/`build` 1.48s hijau.
- Status 1.3 tetap IN PROGRESS karena Stage 2 Browse (`/spaces`) dan halaman venue/space belum dibangun; `/spaces`, `/v/{venue}`, `/spaces/{space}` saat ini render kosong (komponen halaman belum ada).

### 2026-11-05 — 1.3 UI Stage 1 Home — DONE (disetujui user)

- User menyetujui Stage 1 Home pada 2026-11-05; tidak ada perubahan kode setelah bukti QA terakhir.
- Verifikasi ulang sebelum lanjut: `composer ci:check` hijau (Pint, PHPStan, 80 test/393 assertion), `npm run check` (89 file format, 83 lint), `npm run types:check` bersih, `npm run build` 1.51s.
- Koreksi dokumentasi: `outline-none` dihapus dari elemen yang dapat difokus Tab (tersisa di `Dialog.Content` drawer, kontainer non-tabbable), label "Cari venue" pada catatan fokus keyboard.
- Next: Stage 2 Browse (`/spaces`).

### 2026-11-05 — 1.3 UI Stage 2 Browse (`/spaces`) — REVIEW

- Baru: `resources/js/pages/public/spaces/index.tsx`, `components/public/space-card.tsx`, `components/public/space-filters.tsx`. Diubah: `chip.tsx` (tanpa `href` jadi `<button aria-pressed>`, mode link tetap), `section-heading.tsx` (`headingLevel`, default `h2`).
- Backend: normalisasi payload browse — `base_price` kini `int` dan `category_label` ikut dikirim, agar cocok dengan `SpaceListItem`/`SpacesIndexPageProps` yang sudah ada di `types/catalog.ts`. Tanpa perubahan route/test.
- Interaksi: filter server-side lewat query string (`city`, `category`, `date`, `min_price`, `max_price`), selalu mulai halaman 1, pagination reuse `withQueryString`. Desktop: band filter inline. Mobile/tablet: filter sheet (Radix Dialog) dengan baris aksi sticky.
- Verifikasi: `npm run check` (92 file format, 86 lint), `npm run types:check` bersih, `npm run build` 1.44s, `composer ci:check` hijau (80 test/393 assertion).
- QA browser (Chromium, 2026-11-05): 320/390/768/1440 `scrollWidth === clientWidth`, 0 console error, 8 kartu, 1 `h1`, semua gambar termuat. `?city=Samarinda` → 2 hasil; chip kategori Home → `/spaces?category=futsal` dengan chip aktif dan `Filter (1)`; empty state + "Hapus filter"; sheet 390: fokus pindah ke tombol tutup, Escape menutup dan mengembalikan fokus, `body overflow: hidden` lalu pulih, "Terapkan filter" terlihat tanpa scroll (y 748–796 dari 844), chip terakhir tetap bisa diklik.
- Screenshot: `visual-qa/stage2-browse-{390,768,1440}.png`, `stage2-empty-390.png`, `stage2-filter-sheet-390.png`.
- Revisi setelah feedback user ("section filter tidak responsive, di desktop tidak rapi"): band filter desktop dirapikan — satu tier label, empat kolom sama lebar, field harga jadi dua field sibling, hint tanggal + rentang harga satu baris, 10 chip muat satu baris di 1024, aksi rapat di bawah chip. Sheet mobile ikut memakai grid 2×2 dengan aksi sticky.
- Verifikasi ulang revisi: 1024/1280/1440 tanpa overflow, 10 chip satu baris, tinggi input seragam 48px, helper satu baris (18px); terapkan filter kota → `?city=Samarinda` 2 hasil, "Hapus filter" muncul dan reset kembali ke 8 hasil; sheet 390 scroll 50px, chip terakhir tetap bisa diklik. `npm run check` (92 file/86 lint), `types:check`, `build` 1.64s, `composer ci:check` 80 test/393 assertion. Screenshot saat itu `visual-qa/stage2-filter-{1024,1280}.png` (sudah dihapus; diganti `stage2-toolbar-{1024,1280}.png` setelah rekomposisi).
- Self-review `better-interface` (skill aksesibilitas, layout, writing, typography, colors, ui): 3 temuan diperbaiki — (1) badge "Pesan langsung" dihapus karena semantik `instant_book` tidak didefinisikan PRD dan glossary melarang klaim yang tidak dijamin; (2) H1/judul halaman diselaraskan menjadi "Cari venue" (sebelumnya "Cari lapangan atau ruang" vs nav "Cari venue"); (3) outline heading diberi `h2` sr-only "Hasil pencarian" agar tidak lompat h1→h3. Kontras terukur: stone/cream 5,71:1, ink/orange 4,95:1, cream/cobalt 5,29:1 (semua AA). 20 tab stop memiliki ring `solid 2px` cobalt; 320–1440 tanpa overflow; 0 console error.
- Rekomposisi setelah feedback user (komposisi band filter, header, sheet mobile): band berbingkai diganti toolbar dua tier — chip kategori satu baris; baris kedua jumlah hasil di kiri, "Urutkan" + tombol Filter di kanan. Panel filter desktop memakai Radix Collapsible dengan form yang sama; sheet mobile jadi satu kolom dan chip kategori hanya di toolbar. Header tetap eyebrow + H1 + deskripsi kanan, kini ditutup hairline.
- Backend: `sort=price_asc|price_desc` (default `price_asc`) + prop `sort`; test `test_browse_sorts_by_price`. Verifikasi: `npm run check` (93 file/87 lint), `types:check`, `build` 1.84s, `composer ci:check` 81 test/415 assertion.
- QA browser rekomposisi: 320 chip 4 baris + Filter pindah baris; 390 chip 3 baris, sort+Filter satu baris, sheet 698px tanpa scroll dengan aksi terlihat; 1024/1280/1440 chip satu baris, jumlah hasil di tepi kiri (x=64), aksi rata kanan; `?sort=price_desc` menaruh Rp 250.000 di kartu pertama; terapkan filter mempertahankan sort, reset mempertahankan sort, chip kategori mempertahankan sort; Tab dari trigger panel masuk ke field "Kota" dengan ring 2px; 0 console error, tanpa overflow horizontal.
- Screenshot final Stage 2: `visual-qa/stage2-browse-{390,768,1440}.png`, `stage2-toolbar-{1024,1280}.png`, `stage2-toolbar-panel-1024.png`, `stage2-filter-sheet-390.png`, `stage2-empty-390.png`.
- Status: approval Stage 2 **ditunda user 2026-11-05**; tidak ada perubahan kode setelah bukti di atas. Stage 3 belum dimulai.
- Terbuka (bukan blocker implementasi): nilai filter tidak valid (mis. `1e3`) ditolak server dan di-redirect balik tanpa pesan; input tanggal menerima tanggal lampau; pagination belum teruji visual karena seed hanya 8 space (< 12 per halaman); 3 kartu badminton memakai foto identik karena `cover_photo_path` null dan hanya ada satu aset per kategori.
- Next: approval user, lalu Stage 3 (venue + space detail).

### 2026-11-05 — 1.3 UI Stage 2 Browse (`/spaces`) — DONE (disetujui user)

- User menyetujui Stage 2 pada 2026-11-05; tidak ada perubahan kode setelah bukti QA terakhir.
- Dasar approval: `npm run check` 93 file/87 lint, `types:check` bersih, `build` 1.84s, `composer ci:check` 81 test/415 assertion; QA browser 320–1440 tanpa overflow dengan 0 console error; sort/filter mempertahankan satu sama lain.
- Screenshot bukti (valid): `visual-qa/stage2-browse-{390,768,1440}.png`, `stage2-toolbar-{1024,1280}.png`, `stage2-toolbar-panel-1024.png`, `stage2-filter-sheet-390.png`, `stage2-empty-390.png`.
### 2026-11-05 — 1.3 UI Stage 3 Venue + Space Detail — DONE (superseded)

- **Superseded 2026-11-05:** user meminta Stage 3 dibangun ulang dari nol. Kode v1 dipindah ke `.backup/ui-1.3-stage3-v1-2026-11-05/`; screenshot `visual-qa/stage3-*.png` adalah bukti versi lama dan akan diganti setelah rebuild.

- Baru: `resources/js/pages/public/venues/show.tsx` (`/v/{venue}`) dan `resources/js/pages/public/spaces/show.tsx` (`/spaces/{space}`). Diubah: `resources/js/lib/catalog.ts` (`timezoneLabel`, `dayLabel`, `formatTime`; tipe struktural pada `venueCoverSrc`/`venueCategoryLabels`).
- Reuse-only sistem publik: `PublicLayout`, `Container`, `SectionHeading`, `SpaceCard` (untuk daftar space venue), `Price`, `Chip`, `Button`, `Link`, wayfinder routes `venues.show`/`spaces.show`/`home`.
- Isi venue: hero cover dengan fallback per kategori (`alt=""` saat fallback, informatif saat cover asli), H1 nama venue, info strip (alamat, waktu lokal label WIB/WITA/WIT, telepon `tel:` jika ada), daftar space via `SpaceCard`, empty state "Cek lagi nanti.", ringkasan amenitas gabung dari semua space.
- Isi space: breadcrumb (Beranda > venue > space), hero gambar kategori, header (venue link, H1, tag kategori, kapasitas, harga/jam, telepon `tel:`), sekelompok bagian: Tentang (venue), Amenitas (nama saja, kunci icon tidak diasumsikan sebagai komponen), Tambahan (nama, label jenis harga, harga, stok jika >0), Jam operasional (hari Senin–Minggu, buka–tutup, durasi slot; empty state jika kosong, tidak diarang), Kebijakan pembatalan (label + cutoff jam + persen refund); panel ringkasan sticky desktop (ruang, venue/kota, harga/jam, kapasitas, tombol disabled "Pilih tanggal" — tanpa CTA booking dan tanpa slot grid, milik chunk 1.5).
- Copy mengikuti glosarium (venue, lapangan/ruang, tambahan, harga; tidak ada klaim "konfirmasi instan"/"Pesan langsung"; `instant_book` tidak dirender).
- Verifikasi: `npm run check` (95 file format/89 lint), `npm run types:check` bersih, `npm run build` 1,62s; QA browser Chromium di `:8000` 390/768/1440 untuk venue borneo-futsal-arena, smash-badminton-club, nusantara-meeting-space dan space borneo-futsal-arena-lapangan-a, hoops-basketball-main-court (15 kombinasi): `scrollWidth === clientWidth`, 0 console error, 1 H1, semua gambar termuat, fokus ring `solid 2px` cobalt. Tel-link melalui Inertia `Link` menyebabkan hydration mismatch (server berbeda dari client) → diganti `<a>` murni; lulus setelah diperbaiki.
- Screenshot bukti: `visual-qa/stage3-venue-{borneo-futsal-arena,smash-badminton-club,nusantara-meeting-space}-{390,768,1440}.png` dan `visual-qa/stage3-space-{borneo-futsal-arena-lapangan-a,hoops-basketball-main-court}-{390,768,1440}.png`.
- Catatan: tidak ada perubahan backend/route/test; empty-state UI sudah dibangun tetapi belum tervalidasi live karena semua venue di seed memiliki space aktif.
- Berhenti dan tunggu konfirmasi user sebelum tahap berikutnya.

### 2026-11-05 — 1.3 UI Stage 3 Venue + Space Detail (rebuild) — REVIEW

- Dibangun ulang dari nol (v1 di `.backup/ui-1.3-stage3-v1-2026-11-05/`): `pages/public/venues/show.tsx`, `pages/public/spaces/show.tsx`, `components/public/breadcrumb.tsx`, `components/public/amenity-list.tsx`.
- Diubah: `components/public/space-card.tsx` (tipe data + `showVenue` opsional), `components/public/venue-card.tsx` (`isRoomCategory`), `lib/catalog.ts` (`categoryImageAlt`, `isRoomCategory`).
- Venue: masthead editorial + fakta (alamat, WIB/WITA/WIT, telepon `tel:`), cover 4:3→16:9→21:9, grid `SpaceCard`, fasilitas gabungan, empty state jujur. Space: breadcrumb, gambar sticky 7/12 + rel 5/12 (harga, kapasitas, tentang, panel "Venue & kontak" + tombol disabled "Pilih tanggal", Fasilitas, Tambahan, Jam operasional, Pembatalan). Tanpa CTA booking, slot grid, atau `instant_book`; copy mengikuti glosarium.
- Self-review `better-interface` (aksesibilitas, layout, writing, typography, colors, ui): 0 HIGH; 2 polish diterapkan — link venue di panel kini selalu ber-underline; catatan booking 12px mono → 14px body + langkah kontak (hanya jika telepon ada).
- Verifikasi: `npm run check` 97 file/91 lint, `types:check` bersih, `build` 1.56s; kontras terukur lolos AA (stone/cream 5,71; ink/cream 12,76; orange/ink 4,95; cream/cobalt 5,29); 1 H1/halaman; target 44px; gerak `motion-safe`; tanpa perubahan backend/route/test.
- QA browser 320/390/768/1024/1440 di 6 halaman: `scrollWidth === clientWidth`, 0 console error, 1 H1, semua gambar termuat. CTA disabled sand/stone 4,61:1 (AA); link `tel:` "Hubungi venue" 44px. Menunggu approval user.

### 2026-11-05 — 1.3 UI Stage 3 (revisi QA visual) — REVIEW

- Venue: grid space menyesuaikan jumlah (2 → 2-up penuh, 3+ → 3-up, 1 → `max-w-lg` agar tidak jadi kartu raksasa); masthead `lg:items-end` merapatkan blok judul ke rail fakta; fasilitas 3 kolom selebar konten sehingga counter "6 fasilitas" sejajar tepi kanan.
- Space: CTA disabled diberi perlakuan netral sand/stone (kontras 4,61:1) tanpa mengubah varian Button global; catatan "Pemesanan online belum dibuka." + link `tel:` "Hubungi venue" setinggi 44px bila telepon ada.
- Copy: "Sudah termasuk", "Layanan tambahan", "Kebijakan pembatalan", "Zona waktu", "Pengembalian dana …%", about tanpa "adalah" (mis. "Lapangan futsal berkapasitas 10 orang di …").
- Crop futsal: `object-[80%_15%]` hanya untuk futsal — 80% horizontal menjaga subjek kanan-atas pada crop 4:3, 15% vertikal mengurangi turf kosong pada hero 21:9.
- Verifikasi: `npm run check` 97 file/91 lint, `types:check` bersih, `build` 2,08s. QA browser 320/390/768/1024/1440 di 6 halaman: `scrollWidth === clientWidth`, 0 console error, 1 H1, semua gambar termuat. CTA disabled sand/stone 4,61:1 (AA); link `tel:` "Hubungi venue" 44px. Menunggu approval user.

### 2026-11-05 — 1.3 UI Stage 3 Audit & Polish — DONE

- Rekonstruksi editorial Space Detail (`resources/js/pages/public/spaces/show.tsx`) terinspirasi DOKO (`gumroad-04.png` & `gumroad-06.png`):
  - **Hero Split Seimbang (6/6 Kolom)**: Foto kategori 4:3 proporsional di kiri dengan caption data; di kanan: eyebrow kategori & kota dengan aksen dot oranye, judul H1 Bricolage Grotesque extrabold, harga besar tabular `/ jam` + badge kapasitas, deskripsi ringkas, dan box arsitektural "RESERVASI & KONTAK" berbingkai dengan info venue, alamat, tombol disabled "Pilih tanggal & slot" (kontras sand/stone 4,61:1 AA), dan link kontak cepat `tel:` ("Hubungi venue").
  - **Grid Spesifikasi 2 Kolom**: Menghilangkan kekosongan kolom kiri desktop (*desert void*) dengan menyusun spesifikasi teknis selebar layar:
    - Kolom 1: "Sudah termasuk" (fasilitas via `AmenityList`) & "Layanan tambahan" (daftar add-on dengan harga per jam/item/pemesanan).
    - Kolom 2: "Jam operasional" (tabel jam per hari dengan zona waktu lokal WIB/WITA/WIT) & "Kebijakan pembatalan" (label kebijakan + persentase refund dan cutoff jam).
- Penyelarasan Venue Detail (`resources/js/pages/public/venues/show.tsx`):
  - Penyelarasan vertikal titik dot oranye pada eyebrow kategori, masthead editorial seimbang, framing cover adaptif 4:3→16:9→21:9 dengan crop futsal `object-[80%_15%]`, dan grid space adaptif.
- Verifikasi empiris aksesibilitas (WCAG 2.2 AA) via MCP:
  - `text-ink` (#252525) pada `bg-cream` (#f0ead9): 12.76:1 (PASS AA & AAA).
  - `text-stone` (#5e5a52) pada `bg-cream` (#f0ead9): 5.71:1 (PASS AA).
  - `text-stone` (#5e5a52) pada `bg-sand` (#ddd3bc): 4.61:1 (PASS AA).
  - `text-cobalt` (#2154d6) pada `bg-cream` (#f0ead9): 5.29:1 (PASS AA).
  - `text-ink` (#252525) pada `bg-orange` (#f16839): 4.95:1 (PASS AA).
- Visual QA & Mobile Reflow (Chromium headless 320/390/768/1024/1440):
  - `scrollWidth === clientWidth` di semua 5 viewport (zero horizontal overflow terbukti).
  - Tangkapan layar bukti: `visual-qa/stage3-audit-space-{320,390,768,1024,1440}.png`, `visual-qa/stage3-audit-space-1440-full.png`, `visual-qa/stage3-audit-space-390-full.png`, `visual-qa/stage3-audit-venue-{320,390,768,1024,1440}.png`, `visual-qa/stage3-audit-space-anggrek-1440.png`.
- Pipeline & Integritas:
  - `npm run check:fix`: 97 file diformat, 91 file lolos linter tanpa error.
  - `npm run types:check`: `tsc --noEmit` lolos 0 error.
  - `npm run build`: bundler Inertia selesai dalam 1.59s.
  - `composer ci:check`: Pint pas, PHPStan Level 5 pas (0 error), Pest 81 test / 435 assertions lolos.
- Chunk 1.3 resmi ditandai **DONE**. Tahap berikutnya: Chunk 1.4 Slot Engine (`SlotGeneratorService`).

### 2026-11-05 — 1.4 Slot Engine (`SlotGeneratorService`) — DONE

- Baru: `app/Services/Slots/SlotGeneratorService.php` (`generate(Space $space, string $date, ?CarbonInterface $now = null): array`).
  - **Dynamic In-Memory Canonical Grid**: Grid slot dihitung dinamis tanpa cron harian atau tabel baris MySQL 30 hari (prinsip minimal code Ponytail).
  - **Evaluasi Timezone Venue**: Waktu lokal dievaluasi menggunakan timezone venue (`CarbonImmutable::now($venue->timezone)`), bukan UTC server (contoh: pada 23:30 WIB / 00:30 WITA, venue WITA menolak tanggal kemarin sebagai lampau).
  - **Horizon 30 Hari**: Batas ketat $H+0$ hingga $H+30$ diterima; tanggal di masa lampau ($H-1$) dan melampaui horizon ($H+31$) melempar `InvalidArgumentException`.
  - **Transisi Versi Jadwal**: Membaca versi aktif jadwal (`SpaceScheduleVersion`) yang berlaku pada tanggal target (`effective_from <= date` dan `effective_to >= date` atau `null`).
  - **Slicing Durasi Presisi**: Mendukung durasi 30, 60, 90, dan 120 menit tanpa celah (*zero gap*); sisa waktu yang lebih pendek dari durasi slot diabaikan.
  - **Diff Database `booking_slots`**: Memetakan ketersediaan langsung dari database:
    - Ada di `booking_slots` $\to$ status `booked`.
    - Hari ini dan waktu mulai $\le$ waktu sekarang $\to$ status `past`.
    - Lainnya $\to$ status `available`.
  - **Keselarasan Kontrak Cell**: Format sel kanonikal (`start_time`, `end_time` berformat `H:i`) 100% selaras dengan input `SlotReservationService::reserve()`.
- Baru: `tests/Feature/Slots/SlotGeneratorServiceTest.php` (9 test / 59 assertions):
  - `test_generates_canonical_grid_with_durations_30_60_90_120_minutes`
  - `test_enforces_30_day_horizon_boundaries`
  - `test_rejects_date_exceeding_30_days_horizon`
  - `test_rejects_invalid_date_format`
  - `test_respects_venue_timezone_wita_vs_wib`
  - `test_respects_schedule_version_transitions`
  - `test_marks_booked_slots_from_database`
  - `test_marks_past_slots_for_current_day`
  - `test_returns_empty_slots_when_no_active_schedule_or_version`
- Bukti Verifikasi:
  - Pest: 9 test baru lulus 100% (total 90 test / 494 assertions lulus di seluruh repo).
  - Concurrency Check: `php artisan slot:concurrency-check` PASS.
  - Full CI: `composer ci:check` hijau penuh (Pint lolos, PHPStan Level 5 0 error, Pest 90 test lolos, `npm run check:fix` 97 format / 91 lint lolos, `tsc --noEmit` lolos).
- Chunk 1.4 resmi ditandai **DONE**. Tahap berikutnya: Chunk 1.5 (Slot UI date selector & grid dengan Redis caching).

### 2026-11-05 — 1.5 Slot UI & Cache — DONE

- Baru: `app/Http/Controllers/Catalog/SpaceSlotController.php` (`index(Space $space, Request $request)`).
  - Endpoint JSON: `GET /spaces/{space}/slots?date=YYYY-MM-DD`.
  - **Redis Caching**: Menyimpan hasil kalkulasi slot grid di Redis (`slots:space:{id}:date:{date}`) dengan TTL 60 detik.
  - **Validasi Format & Horizon**: Tanggal lampau ($H-1$) atau melampaui 30 hari ($H+31$) mengembalikan HTTP 422 JSON yang rapi.
  - **Auto-Invalidation**: `SlotReservationService::reserve()` memanggil `Cache::forget("slots:space:{$spaceId}:date:{$slotDate}")` seketika saat reservasi berhasil.
- Baru: `resources/js/components/public/space-slot-picker.tsx`:
  - **Date Selector 7 Hari**: Strip tanggal dengan tombol navigasi `<` dan `>`, batasan horizon 30 hari, tag "Hari ini", dan status `aria-checked`.
  - **Slot Matrix Grid**: Tampilan grid waktu (`start_time – end_time`) dan harga per slot; status `available`, `selected` (Cobalt `#2154d6`), `booked` (sand strike-through), dan `past`.
  - **Multi-Slot Selection**: Memungkinkan pemilihan beberapa slot sekaligus di tanggal yang sama; menghitung otomatis akumulasi durasi waktu dan total harga.
  - **Order Summary Rail**: Menampilkan tanggal terpilih, rentang jam terpilih, total biaya, link cepat telepon venue, dan CTA button "Pilih jam terlebih dahulu" (disabled) atau "Lanjut ke Pembayaran →" (aktif dengan link ke `/checkout`).
  - **Silent Background Polling 15 Detik**: Memperbarui ketersediaan slot secara berkala saat tab aktif; jika slot yang sedang dipilih dipesan pengguna lain, sistem otomatis melepaskan slot tersebut dan menampilkan banner notifikasi alert yang jelas.
- Diperbarui:
  - `resources/js/pages/public/spaces/show.tsx`: Integrasi `SpaceSlotPicker` menggantikan placeholder offline statis.
  - `app/Http/Controllers/Catalog/SpaceController.php`: Mengoper `initial_date` dan `initial_slots` ke props Inertia sehingga slot tampil instan pada muat pertama (zero flash/loading).
  - `resources/js/lib/catalog.ts`: Helper manipulasi dan pemformatan tanggal Indonesia (`parseDate`, `toDateString`, `addDays`, `daysDiff`, `formatDayShort`, `formatDayNum`, `formatMonthShort`, `formatFullIndonesianDate`).
  - `resources/js/types/catalog.ts`: Tipe data `SlotCell`, `SlotResponse`, dan `SpaceShowPageProps`.
- Baru: `tests/Feature/Slots/SpaceSlotControllerTest.php` (7 test / 43 assertions):
  - `test_can_fetch_slots_for_valid_date`: 200 OK dengan struktur data lengkap.
  - `test_caches_slot_results_in_redis`: Redis menyimpan cache hasil.
  - `test_booking_reservation_invalidates_cache`: Cache ter-invalidate saat slot dipesan.
  - `test_rejects_past_date`: 422 Unprocessable Entity.
  - `test_rejects_date_exceeding_30_days`: 422 Unprocessable Entity.
  - `test_rejects_malformed_date`: 422 Unprocessable Entity.
  - `test_returns_404_if_space_inactive`: 404 Not Found.
- Bukti Verifikasi:
  - Browser QA (Chromium headless 320/390/768/1024/1440):
    - `scrollWidth === clientWidth` di semua 5 viewport (zero horizontal overflow terbukti).
    - Multi-slot selection interaktif terverifikasi: 2 slot terpilih $\to$ "19:00 – 21:00 (2 slot)" & "Rp 300.000", CTA berubah menjadi "Lanjut ke Pembayaran →".
    - Penggantian tanggal interaktif terverifikasi: klik tombol "Jum 6 Nov" memuat 15 slot ketersediaan baru via endpoint dan mereset ringkasan pemesanan secara bersih.
    - Tangkapan layar bukti: `visual-qa/stage1-5-slot-selected-1440.png`, `visual-qa/stage1-5-multislot-1440.png`, `visual-qa/stage1-5-tomorrow-1440.png`, `visual-qa/stage1-5-slot-mobile-full.png`, `visual-qa/stage1-5-slot-{320,390,768,1024,1440}.png`.
  - Aksesibilitas Kontras Empiris (WCAG 2.2 AA via MCP):
    - Selected slot text (white `#ffffff` on cobalt `#2154d6`): **6.36:1** (PASS AA).
    - Available slot text (ink `#252525` on cream `#f0ead9`): **12.76:1** (PASS AA).
    - Disabled CTA button (stone `#5e5a52` on sand `#ddd3bc`): **4.61:1** (PASS AA).
  - Concurrency Check: `php artisan slot:concurrency-check` PASS.
  - Full CI Pipeline: `composer ci:check` hijau penuh (Pint, PHPStan Level 5 0 error, Pest 97 test / 537 assertions lolos, `npm run check` 98 format / 92 lint lolos, `tsc --noEmit` lolos, `npm run build` 1.84s).
- Chunk 1.5 resmi ditandai **DONE**. Seluruh Sprint 1 (Chunk 1.1–1.5) selesai 100%. Tahap berikutnya: Sprint 2 — Chunk 2.1 (Checkout + Booking).

### 2026-11-05 — 2.1 Checkout & Booking (Pending Payment) — DONE

- **Enums & Skema Database**:
  - Baru: `app/Enums/BookingStatus.php` (`pending_payment`, `confirmed`, `checked_in`, `expired`, `cancelled`, `no_show`) dengan label bahasa Indonesia.
  - Baru: `app/Enums/PaymentMethod.php` (`qris`).
  - Baru: `database/migrations/2026_11_05_000012_create_bookings_table.php` (kode booking unik `code`, FK `venues`, `spaces`, `users`, `booking_date`, rentang jam, `customer_name`, `customer_phone`, `customer_email`, `subtotal_amount`, `addons_amount`, `total_amount`, status, token QR check-in 64 karakter, `expires_at` hold 10 menit, snapshot kebijakan pembatalan).
  - Baru: `database/migrations/2026_11_05_000013_create_booking_add_ons_table.php` (rincian add-on terpilih per pesanan, FK `bookings` on delete cascade, FK `space_add_ons`, `unit_price`, `quantity`, `total_price`).
  - Baru: Model `app/Models/Booking.php`, `app/Models/BookingSlot.php`, `app/Models/BookingAddOn.php` beserta relasi lengkap di `Space.php`, `User.php`, `Venue.php`.
  - Baru: Factory `database/factories/BookingFactory.php` (state: confirmed, checkedIn, expired, cancelled) & `BookingAddOnFactory.php`.
- **Service Layer (`BookingService.php`)**:
  - `calculatePricing`: Menghitung durasi jam, subtotal slot sewa, subtotal add-on sesuai `pricing_type` (`per_hour`, `per_item`, `per_booking`), validasi stok add-on, dan total tagihan (zero platform fee).
  - `createBooking`: Validasi timezone & horizon 30 hari via `SlotGeneratorService`, verifikasi status slot tersedia, transaksi atomik DB, reservasi row-lock kanonikal via `SlotReservationService::reserve()`, auto-invalidation Redis slot cache, pembuatan kode unik `ADT-BK-YYYYMMDD-XXXXX`, token QR 64 karakter, batas kedaluwarsa 10 menit, dan penyimpanan snapshot add-ons.
- **Controller & Routes (`CheckoutController.php`, `PaymentController.php`)**:
  - Route web dilindungi middleware `auth`:
    - `GET /checkout`: Memverifikasi validitas slot waktu, mengalihkan kembali jika slot sudah dipesan pengguna lain, memuat data add-on aktif & data pemesan prefilled dari auth user.
    - `POST /checkout`: Validasi form request `StoreCheckoutRequest` (termasuk validasi regex nomor telepon Indonesia `^(\+62|62|0)8[1-9][0-9]{6,10}$`), menangani graceful rollback jika terjadi `SlotConflictException`.
    - `GET /payment/{code}`: Menampilkan halaman pembayaran pending dengan otorisasi pemilik pesanan / superadmin.
- **Frontend UI (`checkout.tsx`, `payment-pending.tsx`)**:
  - `resources/js/pages/public/checkout.tsx`: Tema editorial DOKO seimbang 7/12 form (Data Pemesan, Layanan Tambahan dinamis dengan checkbox/counter stepper per tipe harga, Metode Pembayaran QRIS, Kebijakan Pembatalan) dan 5/12 sticky order summary rail dengan live recalculation subtotal slot, add-ons, gratis biaya platform, total harga, dan CTA "Lanjut ke Pembayaran →".
  - `resources/js/pages/public/payment-pending.tsx`: Header status 'Menunggu Pembayaran', badge booking code dengan tombol salin, live countdown timer batas kedaluwarsa hold 10 menit, illustrasi QRIS Bank Indonesia resmi, instruksi pembayaran langkah-demi-langkah, dan rincian pesanan.
- **Automated Tests**:
  - `tests/Feature/Bookings/CheckoutPageTest.php` (6 test): Verifikasi guest redirect to `/login`, authenticated view, redirect if slot already booked, render available add-ons, 404 on inactive space.
  - `tests/Feature/Bookings/CreateBookingTest.php` (7 test): Verifikasi status `pending_payment`, masa kedaluwarsa 10 menit, token QR 64 karakter, formula multi add-on (`per_hour`, `per_item`, `per_booking`), persistensi sel kanonikal di `booking_slots`, rollback bersih saat terjadi konflik slot, invalidasi Redis cache, validasi regex telepon Indonesia, dan otorisasi halaman pembayaran.
- **Bukti Verifikasi**:
  - Concurrency Check: `php artisan slot:concurrency-check` PASS.
  - Full CI Pipeline: `composer ci:check` hijau penuh (Pint lolos, PHPStan Level 5 0 error, Pest 110 test / 636 assertions lolos 100%, `npm run check` 100 file format / 94 lint 0 warning/error, `tsc --noEmit` bersih, `npm run build` sukses 1.77s).
  - Browser QA (Chromium headless 5 viewport: 320, 390, 768, 1024, 1440):
    - Zero horizontal overflow (`scrollWidth === clientWidth`) terbukti di `/checkout` dan `/payment/{code}`.
    - Alur pemesanan end-to-end terverifikasi: Login customer -> Buka checkout -> Isi telepon & catatan -> Submit form -> Terarah ke `/payment/ADT-BK-20261105-13HOP` dengan status 'Menunggu Pembayaran' dan countdown timer aktif.
  - Aksesibilitas Kontras Empiris (WCAG 2.2 AA via MCP):
    - Ink on cream (`#121316` on `#fcfbfa`): **17.97:1** (PASS AA).
    - White on cobalt (`#ffffff` on `#2154d6`): **6.36:1** (PASS AA).
    - Stone on cream (`#64635e` on `#fcfbfa`): **5.82:1** (PASS AA).
    - Ink on sand (`#121316` on `#f4f1ea`): **16.47:1** (PASS AA).
    - Emerald badge text (`#047857` on `#ecfdf5`): **5.21:1** (PASS AA).
    - Amber badge text (`#78350f` on `#fef3c7`): **8.15:1** (PASS AA).
- Chunk 2.1 resmi ditandai **DONE**. Tahap berikutnya: Chunk 2.2 (Hold + Cleanup).

### 2026-11-06 — 2.2 Hold + Cleanup — DONE

- **Tier 1 Distributed Lock & All-or-Nothing Concurrency (`SlotReservationService.php`)**:
  - Serialisasi cepat antrean proses dengan Redis distributed lock `Cache::lock("lock:slot_reserve:{$spaceId}:{$slotDate}", 10)->block(5, ...)` sebelum menyentuh transaksi MySQL.
  - Pra-pemeriksaan kueri bentrok sebelum eksekusi insert: memverifikasi apakah ada irisan `start_time` di `booking_slots` untuk space dan tanggal target; melempar `SlotConflictException` lebih dini jika slot telah terisi.
  - Tangkap `LockTimeoutException` dan bungkus menjadi pesan user-friendly `SlotConflictException('Sistem sedang memproses pemesanan pada jadwal ini. Silakan coba kembali sesaat lagi.')`.
  - Rollback atomik menyeluruh: jika 1 dari $N$ slot multi-slot bentrok, tidak ada satu pun sel yang tersimpan di `booking_slots`.
- **Service Pembersihan Slot & Booking Expired (`BookingService.php`)**:
  - Method `expireBooking(Booking $booking): bool`: Transaksi atomik dengan `lockForUpdate()`, verifikasi status `PendingPayment`, transisi status ke `BookingStatus::Expired`, hapus baris terkait di `booking_slots` untuk melepaskan constraint unik `(space_id, slot_date, start_time)` kembali ke pool ketersediaan publik, dan invalidasi Redis cache ketersediaan slot `slots:space:{$spaceId}:date:{$slotDate}`.
  - Method `expirePendingBookings(?CarbonInterface $now = null, ?int $spaceId = null, ?string $slotDate = null): int`: Mencari seluruh booking berstatus pending payment yang melewati batas waktu hold 10 menit (`expires_at <= $now`), menjalankan pembersihan, dan mengembalikan total booking yang berhasil dilepas.
- **Scheduled Command & Queued Job**:
  - Baru: Artisan Command `bookings:expire-pending` (`app/Console/Commands/ExpirePendingBookingsCommand.php`) yang memanggil `BookingService::expirePendingBookings()` dan mencetak log hasil pembersihan.
  - Baru: Queued Job `ExpirePendingBookingsJob` (`app/Jobs/Bookings/ExpirePendingBookingsJob.php`).
  - Penjadwalan berkala terdaftar di `routes/console.php` via `Schedule::command('bookings:expire-pending')->everyMinute()`.
- **Real-time On-The-Fly Expiry**:
  - Di `PaymentController::show`: Jika status pesanan `PendingPayment` dan `now()->isAfter($booking->expires_at)`, booking otomatis diubah menjadi `expired` dan slot dilepas seketika saat halaman dikunjungi pengguna, tanpa menunggu interval cron runner.
  - Di `SlotGeneratorService::generate`: Menjalankan pembersihan booking kedaluwarsa untuk space dan tanggal target sebelum kalkulasi status slot agar slot yang hold 10 menitnya habis langsung tampil kembali sebagai `available` pada grid matrix jadwal.
  - Di `CheckoutController::store`: Menjalankan pembersihan on-the-fly sebelum transaksi pemesanan baru.
- **Flash Message & Conflict UX Polish**:
  - Di `HandleInertiaRequests.php`: Berbagi flash session props (`success`, `error`, `info`) secara global ke seluruh halaman Inertia.
  - Di `resources/js/pages/public/checkout.tsx`: Saat terjadi konflik slot (`errors.slots` atau `errors.error`), banner amber kini menyertakan tombol aksi langsung: `Pilih Slot Waktu Lain` yang mengarahkan kembali ke halaman detail space (`/spaces/{slug}?date={date}`).
  - Di `resources/js/components/public/space-slot-picker.tsx`: Membaca `flash.error` dari props Inertia saat dimuat dan langsung menampilkan alert banner konflik di atas date strip.
  - Di `resources/js/pages/public/payment-pending.tsx`: Ketika `booking.status === 'expired'` atau timer countdown mencapai 0:
    - Badge status berubah menjadi `Kedaluwarsa` (abu-abu / neutral).
    - Alert banner menampilkan pesan penjelas: *"Batas waktu pembayaran 10 menit telah habis. Slot yang Anda pilih telah otomatis dilepas agar dapat dipesan kembali."*
    - Formulir instruksi dan QR code QRIS disembunyikan, digantikan kartu peringatan kedaluwarsa dengan tombol CTA utama: *"Pesan Ulang Lapangan"* menuju `/spaces/{space.slug}`.
- **Automated Tests**:
  - Baru: `tests/Feature/Bookings/BookingHoldExpiryTest.php` (5 test):
    1. `test_expires_pending_booking_and_releases_canonical_slots`: Booking `pending_payment` yang melewati `expires_at` berhasil diubah menjadi `expired`, baris `booking_slots` terhapus, dan slot kembali berstatus `available` di `SlotGeneratorService`.
    2. `test_does_not_expire_confirmed_or_active_bookings`: Booking berstatus `confirmed` tidak tersentuh dan slotnya tetap terkunci.
    3. `test_artisan_command_expires_pending_bookings`: Menjalankan `php artisan bookings:expire-pending` mengembalikan exit code 0 dan mengeksekusi pembersihan.
    4. `test_payment_page_automatically_expires_past_due_booking_on_visit`: Mengunjungi `/payment/{code}` saat waktu habis otomatis mengubah status menjadi `expired` dan merender tampilan kedaluwarsa.
    5. `test_all_or_nothing_rollback_leaves_no_partial_slots_on_conflict`: Pemesanan 2 slot di mana 1 slot sudah terisi menghasilkan rollback total dan 0 baris parsial tersimpan.
  - Update: `tests/Feature/Bookings/CheckoutPageTest.php` (+1 test: `test_checkout_conflict_redirects_back_with_slots_error`).
- **Bukti Verifikasi**:
  - Concurrency Check: `php artisan slot:concurrency-check` PASS (tepat 1 pemenang, 1 konflik tertolak).
  - Full CI Pipeline: `composer ci:check` hijau penuh (Pint lolos, PHPStan Level 5 0 error, Pest 116 test / 670 assertions lolos 100%, `npm run check` 100 file format / 94 lint 0 warning/error, `tsc --noEmit` bersih, `npm run build` sukses 2.0s).
  - Browser QA (Chromium headless): Mengunjungi `/payment/ADT-BK-20261105-13HOP` terbukti merender status `Pembayaran Kedaluwarsa`, badge `Kedaluwarsa`, banner penjelasan slot telah dilepas, dan tombol aksi `Pesan Ulang Lapangan`.
- Chunk 2.2 resmi ditandai **DONE**. Tahap berikutnya: Chunk 2.3 (Fake Payment + Payment Page).

### 2026-11-06 — 2.3 Fake Payment + Payment Page — DONE

- **Abstraksi Payment Gateway & Fake Driver**:
  - Kontrak `App\Contracts\Payments\PaymentGatewayInterface`: `createPayment`, `getPaymentStatus`, `simulatePaymentSuccess`.
  - Driver lokal `App\Services\Payments\FakePaymentGateway`: menghasilkan payload QRIS dummy standar ASPI, melacak status pembayaran berbasis cache dengan key `fake_payment:status:{reference_id}`, TTL adaptif sesuai batas kedaluwarsa pesanan (minimal 60 detik).
  - File konfigurasi `config/payment.php` dengan default driver `fake` (`PAYMENT_GATEWAY_DRIVER=fake`).
  - Service provider binding `PaymentGatewayInterface` ke `FakePaymentGateway` di `app/Providers/AppServiceProvider.php`.
- **Logika Konfirmasi Pesanan Atomik (`BookingService::confirmBooking`)**:
  - Dieksekusi di dalam `DB::transaction()` dengan `lockForUpdate()` pada record booking.
  - Validasi ketat anti-kedaluwarsa: jika `now()->isAfter($booking->expires_at)`, otomatis memanggil `$this->expireBooking($record)` dan mengembalikan `false` guna mencegah pembayaran pada slot yang telah dilepas.
  - Menolak booking yang tidak berstatus `BookingStatus::PendingPayment`.
  - Mengubah status pesanan menjadi `BookingStatus::Confirmed`.
  - Seluruh baris di `booking_slots` dipertahankan permanen untuk customer.
  - Membersihkan cache ketersediaan slot `slots:space:{$spaceId}:date:{$slotDate}`.
- **Controller Status Polling & Simulasi Pembayaran (`PaymentController`)**:
  - Method `status(Request $request, string $code): JsonResponse`: Otorisasi akses booking, cek kedaluwarsa on-the-fly, sinkronisasi otomatis status gateway (jika gateway melaporkan `PAID` pada pesanan pending, otomatis memicu `confirmBooking`), dan mengembalikan payload JSON status pesanan untuk client polling.
  - Method `simulate(Request $request, string $code): JsonResponse|RedirectResponse`: Dibentengi proteksi environment non-produksi (`abort(403)` jika `app()->isProduction()`), trigger `simulatePaymentSuccess`, panggil `confirmBooking`, mendukung respons JSON maupun redirect Inertia.
  - Route terdaftar di `routes/web.php`: `GET /payment/{code}/status` (`payment.status`) & `POST /payment/{code}/simulate` (`payment.simulate`) di bawah middleware `auth`.
- **Artisan Command Simulasi Pembayaran**:
  - Command baru `App\Console\Commands\FakePaymentCommand` (`booking:fake-pay {code}`).
  - Proteksi lingkungan non-produksi, verifikasi eksistensi booking, eksekusi konfirmasi pesanan, idempotensi (warning saat sudah terkonfirmasi), dan output informatif di terminal.
- **UI Polling, Transisi Terkonfirmasi, & Widget Demo (`resources/js/pages/public/payment-pending.tsx`)**:
  - Polling interval setiap 3 detik via `fetch` ke `/payment/{code}/status` saat pesanan berstatus pending.
  - Transisi state lokal instan tanpa refresh penuh saat pembayaran terkonfirmasi.
  - Tampilan Terkonfirmasi:
    - Header berubah menjadi `Pembayaran Berhasil` dengan badge hijau `Terkonfirmasi` (`CheckCircle2`).
    - Banner status emerald: *"Pembayaran Anda telah berhasil diverifikasi. Pemesanan lapangan telah resmi terkonfirmasi. Tunjukkan tiket QR check-in saat tiba di lokasi venue."* dengan badge `Lunas (QRIS)`.
    - Kolom kiri: Kartu `Tiket Reservasi Terkonfirmasi`, ilustrasi visual QR code berlabel `VERIFIED TICKET`, box token check-in lengkap dengan tombol salin token, dan tombol navigasi `Kembali ke Beranda` serta `Cari Venue Lain`.
  - Widget Demo Simulator (hanya tampil jika `is_demo_mode` aktif):
    - Panel aksen amber `Mode Pengujian Lokal` dengan tombol aksi `⚡ Simulasikan Pembayaran Berhasil`.
    - Menekan tombol memicu `POST /payment/{code}/simulate` via `router.post` dengan penanganan state loading dan error handling.
- **Automated Tests**:
  - Baru: `tests/Feature/Bookings/FakePaymentTest.php` (5 test):
    1. `test_fake_payment_confirms_pending_booking_and_preserves_slots`: Simulasi pembayaran sukses mengubah booking pending menjadi confirmed, 2 baris `booking_slots` tetap ada, dan `SlotGeneratorService` melaporkan slot tetap berstatus `booked`.
    2. `test_cannot_confirm_past_due_or_expired_booking`: Booking yang melewati batas hold ditolak saat disimulasikan (HTTP 422), status berubah ke `expired`, dan baris `booking_slots` dihapus.
    3. `test_payment_status_endpoint_returns_json_and_reflects_gateway_status`: Route polling JSON merefleksikan perubahan status dari pending menjadi confirmed saat gateway terbayar.
    4. `test_simulate_endpoint_disabled_in_production`: Route simulasi melempar HTTP 403 saat `APP_ENV=production`.
    5. `test_artisan_command_fake_pay_confirms_booking`: Menjalankan `php artisan booking:fake-pay` berhasil mengonfirmasi pesanan, menangani idempotensi, dan menolak kode booking yang tidak valid.
- **Bukti Verifikasi**:
  - Full CI Pipeline: `composer ci:check` hijau penuh (Pint lolos, PHPStan Level 5 0 error, Pest 121 test / 695 assertions lolos 100%, `npm run check` 100 file format / 94 lint 0 warning/error, `tsc --noEmit` bersih, `npm run build` sukses 2.67s).
  - Browser QA (Chromium headless 390/768/1440): Login customer `customer@adatempat.test` → kunjungi `/payment/ADT-BK-20261105-NPOWL` → tampil status pending & widget pengujian lokal → klik tombol `Simulasikan Pembayaran Berhasil` → UI seketika beralih ke `Pembayaran Berhasil` dan merender kartu tiket terkonfirmasi dengan QR token aktif.
- Chunk 2.3 resmi ditandai **DONE**. Tahap berikutnya: Chunk 2.4 (Ticket + Email Log).

### 2026-11-06 — 2.4 Ticket + Email Log — DONE

- **Token Check-in 64 Karakter Unik**:
  - Token acak aman `qr_checkin_token` berukuran 64 karakter (`Str::random(64)`) dengan constraint database unik pada tabel `bookings`.
  - Helper method `Booking::getSignedTicketUrl(int $daysValid = 30): string` menggunakan `URL::temporarySignedRoute` dengan parameter nama route `tickets.show` dan kode booking.
- **Database & Model Notifikasi Transaksional (`notification_logs`)**:
  - Migrasi `2026_11_05_000014_create_notification_logs_table.php` sesuai PRD §607-610: `booking_id` (nullable, foreign key), `channel` (`email`), `recipient`, `template` (`booking_confirmed`), `status` (`queued`, `sent`, `failed`), `retry_count`, `error_message`, `sent_at`, dan index pada `status` serta `(booking_id, channel)`.
  - Enum `App\Enums\NotificationChannel` dan `App\Enums\NotificationStatus`.
  - Model `App\Models\NotificationLog` dengan relasi `BelongsTo` ke `Booking` dan `hasMany` pada `Booking`.
- **Mailable & Queued Job Notifikasi**:
  - Mailable `App\Mail\BookingConfirmedMail`: subjek informatif `"Konfirmasi Pemesanan & Tiket — {$booking->code}"`, template Blade `resources/views/emails/booking-confirmed.blade.php` bertema AdaTempat dengan ringkasan jadwal, link signed URL tiket digital, token check-in, dan CTA yang jelas.
  - Queued Job `App\Jobs\Bookings\SendBookingConfirmedNotificationJob`: antrean Redis, `tries = 3`, exponential backoff `[10, 60, 300]`, pencatatan status `NotificationStatus::Queued` lalu `NotificationStatus::Sent` dengan `sent_at` saat email berhasil dikirim, serta pencatatan error dan retry count jika terjadi kegagalan (`NotificationStatus::Failed`).
  - Integrasi hook: `BookingService::confirmBooking()` otomatis mendispatch `SendBookingConfirmedNotificationJob` sesaat setelah transaksi DB konfirmasi booking berhasil di-commit.
- **Controller & Route Tiket Digital (`TicketController`)**:
  - Route publik `GET /tickets/{code}` (`tickets.show`) di `routes/web.php`.
  - Validasi keamanan tanda tangan: `$request->hasValidSignature()` memastikan URL bertanda tangan kriptografis belum kedaluwarsa atau dimanipulasi; fallback akses terotorisasi untuk customer pemilik booking atau staf/superadmin jika login.
  - Perlindungan privasi mesin pencari: menyuntikkan HTTP header `X-Robots-Tag: noindex, nofollow` serta meta tag `<meta name="robots" content="noindex, nofollow" />`.
- **Frontend Tiket Digital (`resources/js/pages/public/ticket.tsx`)**:
  - Tampilan pass reservasi bergaya editorial DOKO:
    - Header: Breadcrumbs navigasi, badge status `Terkonfirmasi & Siap` atau `Sudah Check-in`, tombol aksi `Cetak Tiket` (`window.print()`) dan `Beranda`.
    - Kartu Utama: Info venue (nama, alamat, kontak), jadwal lengkap dengan waktu lokal WIB/WITA/WIT, nama pemesan, garis perforasi tiket, QR code check-in berlabel `VALID PASS`, box token 64 karakter dengan tombol salin instan, rincian biaya lunas, dan panduan check-in pintu masuk.
    - Print stylesheet: `@media print` rapi tanpa navbar/footer.
  - Terhubung langsung dengan tombol `Buka Halaman Tiket` pada halaman pembayaran terkonfirmasi (`payment-pending.tsx`).
- **Automated Tests**:
  - Baru: `tests/Feature/Bookings/TicketAndNotificationTest.php` (6 test, 37 assertions):
    1. `test_booking_confirmation_dispatches_notification_job_and_logs_sent_status`: Konfirmasi pesanan otomatis memicu queued job, mengirim `BookingConfirmedMail`, dan mencatat log dengan status `sent`.
    2. `test_signed_ticket_url_allows_guest_access_with_noindex_header`: Signed URL valid mengizinkan akses publik guest (misal dari tautan email) dengan HTTP 200, komponen `public/ticket`, dan header `X-Robots-Tag: noindex, nofollow`.
    3. `test_expired_or_tampered_signed_ticket_url_returns_403_for_guest`: Akses dengan signature manipulasi atau timestamp kedaluwarsa ditolak tegas dengan HTTP 403.
    4. `test_authenticated_booking_owner_can_access_ticket_without_signed_query`: Pemilik pesanan yang login dapat langsung membuka tiketnya tanpa signature query parameter.
    5. `test_other_customer_cannot_access_ticket_without_valid_signature`: Pengguna lain yang login ditolak (HTTP 403) jika tidak menyertakan tanda tangan URL yang sah.
    6. `test_ticket_data_contains_unique_64_char_checkin_token`: Verifikasi panjang token tepat 64 karakter dan tersalurkan ke props halaman.
- **Bukti Verifikasi**:
  - Full CI Pipeline: `composer ci:check` hijau penuh (Pint lolos, PHPStan Level 5 0 error, Pest 127 test / 732 assertions lolos 100%, `npm run check` 101 file format / 95 lint 0 warning/error, `tsc --noEmit` bersih, `npm run build` sukses 1.78s).
  - Browser QA (Chromium headless 390/768/1440): Akses signed ticket URL terverifikasi di 3 resolusi (desktop, tablet, mobile), zero overflow, meta `robots: noindex, nofollow` terpasang, data tiket & QR code tampil presisi.
- Chunk 2.4 resmi ditandai **DONE**. Tahap berikutnya: Chunk 2.5 (Gate Scanner).

### 2026-11-06 — 2.5 Gate Scanner — DONE

- **Logika Validasi Check-in Gate (`BookingService::checkInBooking`)**:
  - Transaksi DB atomik dengan pessimistic locking `lockForUpdate()` untuk menjamin kriteria **single-use guarantee** dan mencegah race condition jika tiket dipindai bersamaan.
  - Dukungan dwifungsi: memvalidasi token unik 64 karakter `qr_checkin_token` maupun kode booking resmi `code` (`ADT-BK-...`).
  - Validasi Venue Matching: memastikan tiket terdaftar di venue yang sedang dipindai; jika terdaftar di venue lain, mengembalikan kode error `WRONG_VENUE` beserta nama venue yang sesuai.
  - Validasi Status: memastikan pesanan berstatus `confirmed`; menolak pesanan `pending_payment` atau `cancelled` dengan error `NOT_CONFIRMED`.
  - Validasi Single-Use: jika tiket sudah berstatus `checked_in`, menolak pemindaian ulang dengan error `ALREADY_CHECKED_IN` dan mencantumkan waktu check-in sebelumnya.
  - Validasi Window Waktu Operasional: memeriksa waktu scan terhadap batas 30 menit sebelum `start_time` hingga batas akhir `end_time`, dievaluasi akurat dalam zona waktu lokal venue (`$venue->timezone`). Menolak pemindaian yang terlalu dini (`TOO_EARLY`) atau telah melewati jam bermain (`TOO_LATE`).
  - Transisi status: mengupdate status menjadi `checked_in` dan merekam timestamp `checked_in_at`.
- **Controller & Route Gate Scanner (`GateScannerController`)**:
  - Rute `GET /gate/scanner` (`gate.scanner`) dan `POST /gate/scanner/scan` (`gate.scanner.scan`) di bawah middleware `['auth', 'verified', 'role:owner,superadmin']`.
  - Membatasi venue yang dapat diakses: owner hanya dapat memilih venue milik bisnisnya; superadmin dapat mengakses seluruh venue.
  - Mengembalikan data venue terdaftar, selected venue, serta riwayat check-in terkini hari ini untuk venue tersebut.
- **Frontend Gate Scanner (`resources/js/pages/gate/scanner.tsx`)**:
  - Desain antarmuka operasional turnstile/front-desk bergaya editorial DOKO:
    - Header: Wordmark AdaTempat, status gate indicator, dan venue selector dropdown dinamis.
    - Mode Switcher: Tab toggle `Kamera Scanner` vs `Input Manual`.
    - Viewfinder Kamera: Feed video responsif dengan overlay bingkai pemindaian laser berdenyut (animated pulse), integrasi native `BarcodeDetector` Web API untuk auto-detect QR code frame-by-frame, dan fallback informatif jika izin kamera tidak diberikan.
    - Form Input Manual / Barcode Gun: Input teks token/kode booking dengan autofocus, tombol scan submit, dan petunjuk operasional.
    - Panel Hasil Pemindaian Reaktif (`role="status"`):
      - Status Sukses: Kartu hijau emerald berikon `CheckCircle2`, menampilkan nama pengunjung (`font-display`), kode booking, nama lapangan, jam sewa, dan waktu masuk.
      - Status Ditolak: Kartu merah/amber dengan badge alasan spesifik (`ALREADY_CHECKED_IN`, `WRONG_VENUE`, `TOO_EARLY`, `TOO_LATE`, `INVALID_TOKEN`) dan panduan solusi.
    - Feed Aktivitas Check-in Terkini: Menampilkan daftar riwayat pengunjung yang berhasil check-in hari ini secara real-time.
- **Automated Tests**:
  - Baru: `tests/Feature/Gate/GateScannerTest.php` (13 test, 50 assertions):
    1. `test_unauthenticated_user_cannot_access_scanner`: Tamu diarahkan ke login.
    2. `test_customer_role_cannot_access_scanner`: Role customer ditolak HTTP 403.
    3. `test_owner_can_access_scanner_and_views_their_venues`: Owner melihat venue miliknya.
    4. `test_superadmin_can_access_scanner_and_sees_all_venues`: Superadmin melihat seluruh venue.
    5. `test_valid_checkin_by_64_char_qr_token_within_allowed_window`: Check-in token 64 karakter dalam window 30 menit berhasil mengubah status menjadi `checked_in` dan merekam waktu masuk.
    6. `test_valid_checkin_by_booking_code`: Check-in via kode booking `ADT-BK-...` berhasil.
    7. `test_scan_fails_if_booking_already_checked_in_single_use`: Pemindaian kedua ditolak HTTP 422 dengan error `ALREADY_CHECKED_IN`.
    8. `test_scan_fails_if_booking_belongs_to_different_venue`: Pemindaian ditolak HTTP 422 dengan error `WRONG_VENUE`.
    9. `test_scan_fails_if_outside_allowed_window_too_early`: Pemindaian sebelum H-30 menit ditolak HTTP 422 dengan error `TOO_EARLY`.
    10. `test_scan_fails_if_outside_allowed_window_too_late`: Pemindaian setelah jam selesai ditolak HTTP 422 dengan error `TOO_LATE`.
    11. `test_scan_fails_if_booking_not_confirmed`: Pesanan pending payment ditolak HTTP 422 dengan error `NOT_CONFIRMED`.
    12. `test_scan_fails_if_token_not_found`: Token acak ditolak HTTP 422 dengan error `INVALID_TOKEN`.
    13. `test_owner_cannot_scan_for_venue_they_do_not_own`: Akses venue milik bisnis lain ditolak HTTP 403.
- **Bukti Verifikasi**:
  - Full CI Pipeline: `composer ci:check` hijau penuh (Pint lolos, PHPStan Level 5 0 error, Pest 140 test / 782 assertions lolos 100%, `vp check` 102 file format / 96 lint 0 warning/error, `tsc --noEmit` bersih, `npm run build` sukses 1.83s).
  - Browser QA (Chromium headless 390/768/1440): Login owner `owner@adatempat.test` → buka `/gate/scanner` → input token → kartu hijau `CHECK-IN BERHASIL` (Hendra Setiawan, Lapangan Vinyl 1, waktu masuk) muncul instan dan tercatat di aktivitas terkini → pemindaian kedua token yang sama seketika menampilkan kartu penolakan `ALREADY_CHECKED_IN: Akses Masuk Ditolak (Tiket sudah pernah digunakan)`.
- **Kriteria Gate 2 Resmi LULUS**:
  - [x] End-to-end demo: browse → checkout → fake pay → tiket → scan.
  - [x] Konkurensi hold terbukti.
  - [x] Tiket single-use.
  - [x] Belum ada dependency Xendit.
- Chunk 2.5 resmi ditandai **DONE**. Seluruh Sprint 2 tuntas! Tahap berikutnya: Gate 2 Review / Sprint 3 (Owner Portal).

### 2026-11-06 — Sprint 1 UI Audit & Remediasi — DONE

- **Remediasi 10 Isu Audit Empiris Sprint 1 (F-01 s.d F-10)**:
  - **F-01 (Kontras Bulan Slot Picker)**: Teks bulan unselected date pada `space-slot-picker.tsx` dinaikkan dari `text-stone/70` menjadi `text-stone` di atas `bg-sand/20`, rasio kontras melonjak ke 5.46:1 (WCAG AA pass).
  - **F-02 (Kontras dt Section Pemilik)**: Label `<dt>` pada section pemilik venue di `home.tsx` dinaikkan dari `text-cream/70` menjadi `text-cream/90` di atas `bg-cobalt`, rasio kontras naik ke 4.58:1 (WCAG AA pass).
  - **F-03 (Alur Fokus & Tabindex Auth)**: Seluruh atribut `tabIndex` positif (`tabIndex={1..9}`) dihapus dari `login.tsx`, `register.tsx`, dan `register-business.tsx`. Alur tab berpindah mengikuti urutan alami visual DOM tanpa lompatan buatan.
  - **F-04 & F-05 (Semantik & Landmark Auth Layout)**: Kontainer `auth-simple-layout.tsx` diubah menjadi landmark `<main>`, dan accessible name link logo Beranda dikoreksi menjadi `"AdaTempat · Beranda"` (bukan judul form login/register).
  - **F-06 (Keyboard ARIA & Roving Tabindex Slot Picker)**: Strip tanggal 7 hari pada `space-slot-picker.tsx` kini mendukung roving `tabIndex={isFocusable ? 0 : -1}` serta keyboard navigation panah (`ArrowLeft`/`ArrowRight`/`ArrowUp`/`ArrowDown`) yang secara otomatis memindahkan fokus dan memilih tanggal valid berikutnya.
  - **F-07 (Semantik Gambar Link Card)**: Atribut `aria-hidden="true"` dihapus dari elemen `<Link>` gambar di `space-card.tsx` dan digantikan dengan atribut accessible `aria-label={`Lihat detail ${space.name}`}`.
  - **F-08 (Semantik Chip Tautan Aktif)**: Komponen `chip.tsx` pada cabang `<Link>` menambahkan atribut `aria-current={active ? 'page' : undefined}`.
  - **F-09 (Antislop Punctuation Home)**: Judul halaman Beranda (`home.tsx`) diperbarui menggunakan middle dot `·` (`Sewa Lapangan & Ruang Olahraga · AdaTempat`), menggantikan em dash `—`.
  - **F-10 (Tipografi Rentang Harga)**: String petunjuk harga pada `space-filters.tsx` diselaraskan menggunakan en dash `–`.
- **Bukti Verifikasi Empiris**:
  - **Axe-core 4.13.0 via Chromium headless**: 0 violations di seluruh 6 permukaan publik & auth Sprint 1 (`/`, `/spaces`, `/v/borneo-futsal-arena`, `/spaces/lapangan-vinyl-1`, `/login`, `/register`, `/register/business`).
  - **Keyboard Navigation Testing**: Terverifikasi roving focus dan pemilihan tanggal via tombol panah pada strip 7 hari, serta alur tabbing natural pada formulir auth.
  - **Quality & CI**: `npm run check` (102 file format / 96 lint 0 error), `npm run types:check` (0 error), `npm run build` sukses 2.17s, `composer ci:check` hijau penuh (Pint lolos, PHPStan Level 5 0 error, Pest 140 test / 782 assertions lulus 100%).
### 2026-11-06 — Redesign UI Login & Register (Stage 8 Auth) — DONE

- **Redesign Menyeluruh Antarmuka Auth (`/login`, `/register`, `/register/business`)**:
  - **Layout Editorial DOKO (`auth-simple-layout.tsx`)**: Menggantikan latar gelap starter kit dengan kanvas khas AdaTempat (`bg-cream text-ink`), layout responsive split 5/7 di desktop (kolom kiri menampilkan wordmark AdaTempat, headline editorial Bricolage Grotesque, ringkasan nilai, dan preview foto venue berbingkai; kolom kanan menampung kartu formulir dengan batas sand lembut).
  - **Quick Tab Switcher**: Navigasi dua tab di bagian atas kartu form untuk berpindah mulus antara "Masuk" dan "Daftar Akun" tanpa tersesat.
  - **Formulir Login (`login.tsx`)**: Integrasi penuh Passkey ("Masuk dengan Passkey") dengan teks Indonesia, input email/password setinggi 48px (anti auto-zoom iOS Safari), link "Lupa password?", checkbox "Ingat saya", tombol CTA cobalt, serta banner khusus pemilik venue ("Daftar sebagai pemilik venue").
  - **Formulir Register (`register.tsx`)**: Input nama lengkap, email, password, konfirmasi password, helper rules password, CTA pendaftaran, dan kartu tawaran untuk pemilik venue.
  - **Formulir Register Business (`register-business.tsx`)**: Banner penanda portal kemitraan, pemisahan visual dua seksi (Data Pemilik & Profil Bisnis Venue), validasi aria-invalid, dan link kembali ke login/akun pengguna biasa.
  - **Komponen Password & Passkey (`password-input.tsx`, `passkey-verify.tsx`)**: Input 48px dengan tombol intip password yang ramah keyboard/aksesibilitas (`aria-label` dwibahasa terkelola), pemisah email bersih bertema cream.
- **Bukti Verifikasi Empiris**:
  - **Audit Aksesibilitas axe-core 4.10.2 via Chromium headless**: 0 violations (Level A, AA, 2.1 AA, 2.2 AA) pada `/login`, `/register`, dan `/register/business`.
  - **Responsif & Zero Overflow**: Terverifikasi pada 390px, 768px, dan 1440px dengan `scrollWidth === clientWidth` (zero horizontal overflow).
  - **Keyboard Navigation**: Alur tabbing natural terverifikasi via Chromium headless tanpa lompatan tabindex buatan.
  - **Pipeline CI**: `composer ci:check` hijau penuh (Pint lulus, PHPStan Level 5 0 error, Pest 140 test / 782 assertions lulus 100%, `vp check` 102 file format / 96 lint 0 warning/error, `tsc --noEmit` bersih, `npm run build` sukses 2.04s).

### 2026-11-06 — Audit & Sinkronisasi Copywriting UI Menyeluruh — DONE

- **Audit dan Remediasi Inkonsistensi & Misinformasi Antarmuka**:
  - **1. Standarisasi Istilah Biaya Layanan ("One Vocabulary")**: Menghilangkan inkonsistensi terminologi `Biaya Layanan Platform` vs `Biaya admin platform` vs `Biaya Layanan` pada `checkout.tsx` dan `payment-pending.tsx`. Seluruh permukaan kini seragam menggunakan **Biaya Layanan Platform** dan menyatakan transparansi harga tanpa biaya tersembunyi (`spaces/index.tsx`).
  - **2. Penyelarasan Label Rincian Harga & CTA Alur Booking**: Mengoreksi label pada rel pemesanan `space-slot-picker.tsx` dari `Total Biaya` menjadi `Biaya Sewa Unit` (karena add-on baru dipilih pada tahap checkout), serta mengubah label tombol dari `Lanjut ke Pembayaran →` menjadi `Lanjut ke Pemesanan` yang akurat mendeskripsikan tujuan navigasi berikutnya.
  - **3. Eliminasi Klaim Misinformasi Notifikasi WhatsApp**: Memperbaiki copy pada seksi data pemesan `checkout.tsx` dari janji pengiriman tiket ke WhatsApp (belum ada integrasi WhatsApp otomatis) menjadi pernyataan faktual dan jujur: *"Data kontak ini digunakan untuk verifikasi identitas pemesan pada tiket digital dan koordinasi operasional venue."*
  - **4. Penambahan Informasi Esensial Jendela Check-in**: Menyinkronkan kartu tiket digital `ticket.tsx` dengan batasan validasi backend (`BookingService`) dan `scanner.tsx` dengan menambahkan petunjuk operasional konkret: *"Check-in gerbang dibuka 30 menit sebelum jadwal sesi dimulai."*
  - **5. Penghapusan Panah Template Chrome & Tanda Baca Terlarang (Antislop R-02)**: Menghapus seluruh panah template dekoratif (`→` dan `←`) pada tautan venue, tombol checkout, dan navigasi kembali, serta mengganti karakter em dash (`—`) pada nama venue/kota menjadi middle dot (`·`).
  - **6. Lokalisasi Bahasa Indonesia Penuh untuk Seluruh Sub-halaman Auth Fortify**: Menyelaraskan 5 sub-halaman otentikasi bawaan starter kit yang sebelumnya masih berbahasa Inggris mentah (`forgot-password.tsx`, `reset-password.tsx`, `verify-email.tsx`, `confirm-password.tsx`, `two-factor-challenge.tsx`) ke bahasa Indonesia yang tenang, jelas, dan berwibawa, lengkap dengan penyesuaian styling tombol cobalt DOKO dan input 48px touch-friendly.
- **Bukti Verifikasi Empiris**:
  - **Pipeline CI Penuh**: `composer ci:check` hijau penuh (Pint lulus, PHPStan Level 5 0 error, Pest 140 test / 782 assertions lulus 100%, `vp check` 102 file format / 96 lint 0 warning/error, `tsc --noEmit` bersih, `npm run build` sukses 1.86s).
  - **Browser QA (Chromium headless)**: Halaman `forgot-password.tsx`, `spaces/index.tsx`, `checkout.tsx`, dan `ticket.tsx` dirender dan diverifikasi menghasilkan tata letak visual rapi dan kontras warna memenuhi standar WCAG AA.

### 2026-11-06 — Implementasi Copywriting Opsi A (Kasual & Komunitas) — DONE

- **Transformasi Tone of Voice Menyeluruh (Kasual, Enerjik, & Komunitas Olahraga)**:
  - **1. Beranda (`home.tsx`)**:
    - Eyebrow hero diubah menjadi: *"Booking lapangan & ruang main bareng"*.
    - Headline utama: *"Cari lapangan, ajak teman. Langsung main hari ini tanpa ribet chat admin."*
    - Subtitle deskriptif: *"Cek jadwal kosong secara real-time, bayar instan pakai QRIS, dan simpan tiket digital di HP kamu buat langsung dipakai di lapangan."*
    - Section alur cara kerja: *"Pilih lapangan dan jam main"*, *"Harga transparan di awal"*, *"Tunjukkan tiket pas tiba"*.
    - Banner pemilik: *"Punya lapangan atau GOR? Bikin jadwal lapanganmu selalu terisi penuh."*
  - **2. Halaman Autentikasi (`login.tsx`, `register.tsx`, `register-business.tsx`, `auth-simple-layout.tsx`)**:
    - Showcase auth: *"Cari lapangan, ajak teman, langsung main hari ini."* dengan pill badge *"Tiket aktif di HP"*.
    - Judul login: *"Masuk buat Lanjut Booking"* dan ajakan daftar *"Yuk daftar akun baru"*.
    - Judul register: *"Bikin Akun Baru"* (Daftar cuma 1 menit buat mulai booking lapangan favorit kamu).
    - Banner kemitraan: *"Punya lapangan atau GOR olahraga? Daftar jadi mitra venue"*.
  - **3. Katalog Ruang & Lapangan (`spaces/index.tsx`, `spaces/show.tsx`)**:
    - Judul katalog: *"Mau main apa hari ini?"* dengan deskripsi transparan yang ramah.
    - Empty state: *"Belum ada lapangan yang cocok nih. Coba ganti kata kunci pencarian, pilih kota lain, atau reset filter harga kamu ya."*
  - **4. Alur Pemesanan & Pembayaran (`space-slot-picker.tsx`, `checkout.tsx`, `payment-pending.tsx`, `ticket.tsx`)**:
    - Tombol pemilih slot: *"Booking Lapangan Sekarang"* dan total *"Total Sewa Lapangan"*.
    - Checkout: *"Selesaikan Booking Kamu"* dan CTA *"Lanjut ke Pembayaran QRIS"*.
    - Payment pending: *"Yuk selesaikan pembayaran sebelum waktu habis!"* dan konfirmasi *"Hore, pembayaran kamu sudah berhasil!"*.
    - Tiket digital: *"Tiket Masuk Lapangan"* dengan status badge *"Lunas · Siap Main"*.
- **Bukti Verifikasi**:
  - CI Pipeline: `composer ci:check` hijau penuh (Pint, PHPStan Level 5 0 error, Pest 140 test / 782 assertions lulus 100%, `vp check` 102 format / 96 lint lulus, `tsc --noEmit` bersih, `npm run build` sukses 1.89s).
  - Browser QA (Chromium headless): Tangkapan layar pada `/`, `/login`, dan `/spaces` membuktikan tipografi mengalir natural dan bebas dari kekakuan bahasa korporat.

### 2026-11-06 — Penyelarasan Copywriting Inklusif (Ruang Kerja, Studio, & Acara) — DONE

- **Penyelarasan Spektrum Kategori Menyeluruh (Space & Activity)**:
  - **1. Beranda (`home.tsx`)**:
    - Eyebrow hero: *"Booking tempat olahraga, kerja, & kumpul komunitas"*.
    - Headline utama: *"Mau olahraga, kerja, atau bikin acara? Booking tempatnya langsung tanpa ribet chat admin."*
    - Subtitle: *"Cek jadwal kosong secara real-time, bayar instan pakai QRIS, dan simpan tiket digital di HP kamu untuk langsung ditunjukkan di lokasi."*
    - Tiga langkah booking: *"Pilih tempat dan jam sewa"*, *"Harga transparan di awal"*, *"Tunjukkan tiket pas tiba"*.
    - Banner pemilik venue: *"Punya tempat olahraga, studio, atau ruang acara? Bikin jadwal tempatmu selalu terisi penuh."*
  - **2. Halaman Autentikasi (`auth-simple-layout.tsx`, `login.tsx`, `register.tsx`, `register-business.tsx`)**:
    - Showcase auth: *"Mau olahraga, kerja, atau bikin acara? Semua ada tempatnya."*
    - Callout pemilik: *"Punya lapangan, studio, atau ruang meeting? Daftar jadi mitra venue"*.
    - Register deskripsi: *"Daftar cuma 1 menit buat mulai booking lapangan, studio, atau ruangan favorit kamu."*
  - **3. Katalog Ruang & Lapangan (`spaces/index.tsx`, `space-toolbar.tsx`, `spaces/show.tsx`)**:
    - Judul katalog: *"Cari tempat apa hari ini?"*
    - Deskripsi katalog: *"Pilih lapangan olahraga, studio, ruang meeting, atau ruang acara. Jadwal kosong dan harga transparan langsung kelihatan."*
    - Placeholder pencarian toolbar: *"Cari tempat, studio, ruang meeting..."*.
    - Empty state: *"Belum ada tempat yang cocok nih. Coba ganti kata kunci pencarian, pilih kota lain, atau reset filter harga kamu ya."*
  - **4. Slot Picker, Checkout, Payment, & Tiket (`space-slot-picker.tsx`, `checkout.tsx`, `payment-pending.tsx`, `ticket.tsx`)**:
    - Tombol booking slot: *"Pilih jam sewa untuk lanjut booking"* & *"Booking Tempat Sekarang"*.
    - Subtotal slot: *"Total Biaya Sewa"*.
    - Petunjuk kontak checkout: *"Data kontak ini dipakai buat tiket digital kamu dan koordinasi saat tiba di lokasi."*
    - Notifikasi payment pending: *"Slot tempat kamu lagi diamankan selama timer berjalan..."* & *"Pesan Ulang Tempat"*.
    - Tiket digital: *"Tiket Masuk Venue"*, badge *"Lunas · Siap Pakai"*, label *"Tanggal Sewa"*, dan petunjuk gerbang 30 menit sebelum jadwal dimulai.
  - **5. Komponen Layout Global (`nav.ts`, `site-header.tsx`, `site-footer.tsx`, `user-menu-content.tsx`, `settings/layout.tsx`)**:
    - Navbar utama: *"Cari tempat"*, *"Kategori"*, *"Cara kerja"*, *"Untuk pemilik tempat"*, CTA *"Daftarkan venue"*.
    - Footer: Tautan *"Cari tempat"*, *"Kategori ruang"*, *"Cara pemesanan"*, grup navigasi *"Mitra Pemilik"*, serta catatan kaki *"Platform Pemesanan Tempat & Ruang Kegiatan"* & *"Semua harga transparan dalam Rupiah."*.
    - Dropdown profil & layout pengaturan: *"Pengaturan Akun"*, *"Keluar"*, tab *"Profil"*, *"Keamanan"*, dan *"Tampilan"*.
- **Bukti Verifikasi**:
  - CI Pipeline: `composer ci:check` hijau 100% (Pint, PHPStan Level 5 0 error, Pest 140 test / 782 assertions lulus, `vp check` 102 format / 96 lint lulus, `tsc --noEmit` bersih, `npm run build` sukses 1.95s).
  - Browser QA (Chromium headless): Tangkapan layar pada `/`, `/login`, dan `/spaces` terkonfirmasi sangat seimbang, natural, dan ramah untuk seluruh kategori venue.
### 2026-11-06 — 3.1 Owner venue CRUD — DONE

- **Implementasi Backend & Kebijakan Keamanan (RBAC / IDOR)**:
  - **Kebijakan & Otorisasi (`VenuePolicy.php`)**: Membentengi seluruh aksi (`viewAny`, `view`, `create`, `update`, `delete`, `publish`, `submitApproval`). Memastikan owner hanya dapat melihat dan mengelola venue miliknya sendiri berdasarkan kepemilikan bisnis (`$venue->business->user_id === $user->id`). Akses cross-tenant (IDOR) ditolak seketika dengan HTTP 403 Forbidden.
  - **Controller & Routing (`VenueController.php`)**:
    - `GET /owner/venues`: Mengambil dan mempaginasi venue tenant-scoped dengan hitungan ruang (`spaces_count`), nama bisnis, badge status, dan signed cover URL.
    - `GET /owner/venues/create` & `POST /owner/venues`: Validasi form request `StoreVenueRequest` (nama, kota, alamat, timezone WIB/WITA/WIT, regex nomor telepon Indonesia, dan upload cover foto). Otomatis membuat slug unik dan menetapkan default `approval_status = pending` serta `is_published = false`.
    - `GET /owner/venues/{venue}/edit` & `PUT /owner/venues/{venue}`: Validasi `UpdateVenueRequest`. Penerapan aturan PRD §5.6: perubahan material (`name` atau `address`) pada venue berstatus `approved` otomatis memicu peninjauan ulang oleh superadmin, mengembalikan status ke `pending`, dan menarik tayangan (`is_published = false`).
    - `POST /owner/venues/{venue}/publish`: Guard publish toggle yang melarang publikasi venue berstatus `pending` atau `rejected` (hanya venue `approved` yang diizinkan tayang di marketplace).
    - `POST /owner/venues/{venue}/submit-approval`: Memungkinkan owner mengajukan ulang verifikasi jika venue ditolak (`rejected` -> `pending`, membersihkan `rejection_reason`).
    - `DELETE /owner/venues/{venue}`: Proteksi penghapusan venue yang memiliki riwayat pemesanan; menghapus file cover lama di storage privat saat venue dihapus.
  - **Storage Cover Foto Privat & Signed URL (`VenueCoverController.php`)**:
    - File cover foto disimpan di storage privat lokal (`storage/app/private/venues/covers/...`).
    - Akses cover foto dilindungi oleh signed URL route (`/venues/{venue}/cover`) dengan validasi `hasValidSignature()` atau otorisasi kepemilikan owner/superadmin. Akses tanpa tanda tangan sah atau kadaluwarsa ditolak HTTP 403 Forbidden.
    - Model `Venue.php` dilengkapi atribut `cover_url` yang otomatis menghasilkan `URL::temporarySignedRoute(..., now()->addHours(24))` atau URL langsung.
- **Antarmuka Pengguna Owner Portal (React / Inertia / Tailwind)**:
  - `resources/js/pages/owner/venues/index.tsx`: Dashboard daftar venue dengan filter owner, indikator status verifikasi (Badge hijau "Disetujui", kuning "Menunggu Persetujuan", merah "Ditolak"), status publikasi (Badge cobalt "Tayang" / outline "Draft"), pratinjau cover foto, tombol "Ubah Data", toggle "Tayangkan / Nonaktifkan", aksi "Ajukan Ulang", tautan cepat ke marketplace publik, dan empty state ramah jika belum ada venue.
  - `resources/js/pages/owner/venues/create.tsx`: Formulir penambahan venue baru dengan pemilih bisnis pemilik, kota, zona waktu (WIB/WITA/WIT), alamat lengkap, nomor kontak, dan unggah foto cover dengan pratinjau gambar instan.
  - `resources/js/pages/owner/venues/edit.tsx`: Formulir pembaruan data venue lengkap dengan banner status persetujuan, alasan penolakan jika ada, kartu kontrol publikasi marketplace, penggantian foto cover, dan zona berbahaya untuk penghapusan venue.
  - `resources/js/components/app-sidebar.tsx`: Navigasi sidebar terintegrasi otomatis menampilkan menu "Kelola Venue" (`Building2`) dan "Gate Scanner" (`QrCode`) untuk user ber-role `owner` dan `superadmin`.
- **Automated Tests (`tests/Feature/Owner/OwnerVenueTest.php`)**:
  - 13 Pest Feature tests baru lulus 100%:
    - `test_owner_only_lists_own_venues`
    - `test_owner_cannot_view_or_edit_another_owners_venue` (IDOR / cross-tenant isolation)
    - `test_customer_cannot_access_owner_venues`
    - `test_guests_are_redirected_to_login`
    - `test_superadmin_can_view_all_venues`
    - `test_owner_can_create_venue_with_private_cover_photo`
    - `test_private_cover_requires_valid_signature_or_authorized_user` (verifikasi signed URL 403 vs 200)
    - `test_owner_cannot_publish_pending_or_rejected_venue`
    - `test_owner_can_toggle_publish_for_approved_venue`
    - `test_material_change_reverts_approved_venue_to_pending_and_unpublishes`
    - `test_non_material_change_keeps_approved_status`
    - `test_owner_can_submit_rejected_venue_for_reapproval`
    - `test_pending_or_unapproved_venue_does_not_appear_on_public_marketplace`
- **Bukti Verifikasi**:
  - Concurrency Check: `php artisan slot:concurrency-check` PASS (rows=1, exactly one reservation persisted).
  - Full CI Pipeline: `composer ci:check` hijau penuh (Pint lolos, PHPStan Level 5 0 error, Pest 153 test / 860 assertions lolos 100%, `vp check` 105 format / 99 lint 0 warning/error, `tsc --noEmit` bersih, `npm run build` sukses 2.01s).
  - Browser QA (Chromium headless 390, 768, 1440):
    - Zero horizontal overflow terbukti pada seluruh 3 halaman (`/owner/venues`, `/owner/venues/create`, `/owner/venues/borneo-futsal-arena/edit`): `scrollWidth === clientWidth` (overflow = false).
    - Screenshot bukti tersimpan dalam sesi inspeksi Chromium.
  - Gate 3 progress: Chunk 3.1 resmi ditandai **DONE**. Tahap berikutnya: Chunk 3.2 (Owner space/schedule/add-on).


### 2026-11-06 — Remediasi Audit UI & 3.2 Owner Space, Schedule, & Add-On CRUD — DONE

- **Remediasi Temuan Audit UI Owner Panel & Gate Scanner**:
  - **Aksesibilitas Keyboard Dropzone Foto Sampul (`resources/js/pages/owner/venues/create.tsx`)**: Mengganti `div onClick` dengan semantic `<label htmlFor="cover_photo">` dan mengubah file input menjadi `className="sr-only"` dengan `focus-within:ring-2 focus-within:ring-cobalt`. Memungkinkan navigasi keyboard via Tab dan aktivasi native file picker via Space/Enter.
  - **Rasio Kontras Asterisk Wajib**: Mengubah label penanda wajib dari `text-rose-500` menjadi `text-rose-600 dark:text-rose-400` pada formulir tambah & ubah venue, meningkatkan rasio kontras menjadi 4.70:1 pada mode terang dan 6.66:1 pada mode gelap (lulus WCAG AA).
  - **Accessible Name Selector Venue Gate Scanner (`resources/js/pages/gate/scanner.tsx`)**: Menambahkan `aria-label="Pilih lokasi venue gerbang"` pada selector desktop dan `aria-label="Pilih lokasi venue"` pada selector mobile.
  - **Rasio Kontras Placeholder Foto Sampul (`resources/js/pages/owner/venues/edit.tsx`)**: Memperbarui teks "Belum ada foto sampul" menjadi `text-stone-600 dark:text-stone-400` pada latar `bg-stone-100`, meningkatkan rasio kontras menjadi 6.99:1 (lulus WCAG AA).

- **Implementasi Chunk 3.2: Owner Space, Schedule, & Add-On CRUD**:
  - **Kebijakan & Keamanan Tenant Isolation (`SpacePolicy.php`)**: Memastikan user hanya dapat melihat, membuat, mengubah, menghapus, atau mengatur jadwal/add-on untuk space di venue yang dimiliki bisnisnya (`$space->venue->business->user_id === $user->id`), atau jika user bertindak sebagai superadmin.
  - **Form Requests (`app/Http/Requests/Owner/`)**:
    - `StoreSpaceRequest` & `UpdateSpaceRequest`: Validasi nama space, kategori (`SpaceCategory` enum), kapasitas, harga dasar, instant booking boolean, kebijakan pembatalan (`CancellationPolicy` enum: free_cancellation, partial_refund, non_refundable), cutoff jam pembatalan, dan persentase refund.
    - `StoreScheduleRequest`: Validasi jadwal mingguan 7 hari (day_of_week 0-6), open_time, close_time (after open_time), slot_duration_minutes (30/60/90/120 menit), serta custom validator pencegahan tumpang tindih waktu (*non-overlapping time guard*) pada hari yang sama.
    - `StoreAddOnRequest` & `UpdateAddOnRequest`: Validasi nama add-on, harga dasar, tipe harga (`AddOnPricingType` enum: `per_hour`, `per_item`, `per_booking`), dan stok kuantitas minimum 1.
  - **Controllers & Routing (`app/Http/Controllers/Owner/`)**:
    - `SpaceController`: List spaces dengan pagination & penghitung relasi (`add_ons_count`, `bookings_count`), formulir create dengan auto-slug generator dan inisialisasi default schedule version v1 (7 hari 08:00–22:00, slot 60m), edit/update, dan guard penghapusan (menolak hapus space jika memiliki riwayat booking).
    - `SpaceScheduleController`: Editor jadwal mingguan dengan integrasi versi jadwal (`SpaceScheduleVersion`). Jika space belum memiliki booking mendatang, jadwal langsung diperbarui pada versi aktif. Jika sudah ada booking di masa mendatang, sistem otomatis mengarsipkan versi lama dan membuat versi baru (`version + 1`, `effective_from = today`) agar slot pesanan yang telah dibayar pelanggan tidak bergeser.
    - `SpaceAddOnController`: CRUD add-on dengan modal form interaktif, dukungan multi-type harga (`per_hour`, `per_item`, `per_booking`), dan guard proteksi hapus jika add-on tercatat dalam tabel `booking_add_ons`.
  - **Antarmuka Pengguna DOKO (React / Inertia / Tailwind)**:
    - `resources/js/pages/owner/spaces/index.tsx`: Tampilan kartu space berpenanda kategori, tarif per slot, status aktif, instant book, kebijakan batal, counter pemesanan/add-on, serta aksi langsung ke Jadwal dan Add-On.
    - `resources/js/pages/owner/spaces/create.tsx` & `edit.tsx`: Formulir pembuatan dan pengubahan unit space dengan selector kebijakan pembatalan dinamis dan opsi instant booking.
    - `resources/js/pages/owner/spaces/schedules.tsx`: Matriks operasional 7 hari dengan toggle buka/tutup, waktu buka/tutup, durasi slot, tombol salin "Terapkan ke Semua", dan *Live Slot Simulation Preview* interaktif yang menampilkan chip slot kanonikal secara real-time.
    - `resources/js/pages/owner/spaces/add-ons.tsx`: Manajemen layanan tambahan dengan dialog modal tambah/ubah, badge tipe kalkulasi, stok unit, dan konfirmasi hapus.

- **Automated Tests (`tests/Feature/Owner/`)**:
  - 28 test Feature Pest baru lulus 100%:
    - `OwnerSpaceTest.php` (11 test): auth guard, tenant isolation (IDOR 403 & cross-venue 404), create dengan auto-slug & default schedule v1, validasi field, update, proteksi hapus space dengan booking, dan superadmin bypass.
    - `OwnerScheduleTest.php` (8 test): auth guard, tenant isolation, update jadwal tanpa booking (retensi v1), update jadwal dengan booking mendatang (auto-generate v2 & arsip v1), validasi close_time < open_time, validasi durasi slot, dan penolakan jadwal tumpang tindih pada hari yang sama.
    - `OwnerAddOnTest.php` (9 test): auth guard, tenant isolation, CRUD add-on dengan kalkulasi per_hour/per_item/per_booking, validasi field, proteksi hapus add-on ber-booking, dan isolasi antar-venue.

- **Bukti Verifikasi**:
  - Concurrency Check: `php artisan slot:concurrency-check` PASS (rows=1).
  - Full CI Pipeline: `composer ci:check` hijau penuh:
    - Pint: Passed
    - PHPStan Level 5: Passed (0 error)
    - Pest: Passed (183 tests, 1019 assertions, 100% lulus)
    - `npm run check` (vp check): All 110 files correctly formatted, 0 warnings / lint errors
    - `npm run types:check`: TypeScript clean
    - `npm run build`: Build sukses 2.83s.
  - Browser QA (Chromium headless 390, 768, 1440):
    - 15/15 pengujian viewport lulus zero horizontal overflow (`scrollWidth === clientWidth`) pada seluruh halaman Space (Index, Create, Edit, Schedules, Add-Ons).
    - Live Slot Simulation Preview terverifikasi menghasilkan 15 slot kanonikal dinamis secara instan.
  - Gate 3 progress: Chunk 3.2 resmi ditandai **DONE**. Tahap berikutnya: Chunk 3.3 (Superadmin approval + audit + 2FA).

### 2026-11-06 — 3.3 Superadmin Approval, Audit Log, & 2FA Enforcement — DONE

- **Implementasi Chunk 3.3: Superadmin Approval, Audit Log, & 2FA Enforcement**:
  - **Skema DB & Model Append-Only (`audit_logs` & `AuditLog.php`)**:
    - Migrasi `2026_11_06_000001_create_audit_logs_table.php` dengan kolom `actor_id` (`constrained('users')->nullOnDelete()`), `action` (indexed), `auditable_type`, `auditable_id`, `payload` (JSON), `ip_address`, `user_agent`, dan `created_at` (indexed, `$timestamps = false`).
    - Model `app/Models/AuditLog.php` dengan cast array & datetime serta relasi `actor(): BelongsTo` dan `auditable(): MorphTo`.
  - **Service Logging Sentral (`AuditService.php`)**:
    - `AuditService::log(string $action, ?Model $auditable = null, array $payload = [], ?User $actor = null): AuditLog` yang secara otomatis mengidentifikasi aktor, IP request, user agent, dan timestamp `now()`.
  - **Integrasi Keamanan 2FA & Fortify (`AuditTwoFactorAuthentication.php`)**:
    - Listener menangani event `TwoFactorAuthenticationConfirmed` (mencatat log `user.2fa_enabled`) dan `TwoFactorAuthenticationDisabled` (mencatat log `user.2fa_disabled`).
    - Didaftarkan di `AppServiceProvider::boot()` via `Event::listen()`.
  - **Middleware Penegakan 2FA Superadmin (`EnsureSuperadminTwoFactorEnforced.php`)**:
    - Membentengi rute `/superadmin/*`: superadmin tanpa 2FA aktif (`two_factor_confirmed_at !== null`) otomatis diblokir dari area sensitif; request JSON mengembalikan 403, dan request web/Inertia dialihkan ke `route('security.edit')` dengan pesan error: *"Akun Superadmin wajib mengaktifkan Autentikasi Dua Faktor (2FA) sebelum dapat mengakses panel admin."*.
    - Didaftarkan alias `'superadmin.2fa'` di `bootstrap/app.php` dan dipasang pada grup rute superadmin di `routes/web.php`.
    - Props flash di `HandleInertiaRequests.php` diperbarui untuk mengekspos session key `'warning'`.
  - **Otorisasi Kebijakan & Validasi Input (`VenuePolicy.php` & Form Requests)**:
    - Method otorisasi `approve`, `reject`, dan `unpublish` di `VenuePolicy` hanya mengizinkan user dengan role `superadmin`.
    - `RejectVenueRequest`: Wajib menyertakan `rejection_reason` (min: 5, max: 1000 karakter).
    - `UnpublishVenueRequest`: Wajib menyertakan `reason` (min: 5, max: 1000 karakter).
  - **Controllers & Routing (`app/Http/Controllers/Superadmin/`)**:
    - `VenueApprovalController`:
      - `index`: Filter tab status (Menunggu, Disetujui, Ditolak, Semua), pencarian teks (nama, kota, bisnis), agregat counter statistik, dan pagination.
      - `show`: Detail komprehensif venue (informasi umum, profil bisnis mitra, spesifikasi unit space, matriks jadwal 7 hari, add-on) beserta riwayat audit log venue terkait.
      - `approve`: Transaksi DB atomik mengubah status ke `approved`, mencatat `approved_at` dan `approved_by`, mengosongkan alasan penolakan, serta mencatat audit log `venue.approved`.
      - `reject`: Transaksi DB atomik mengubah status ke `rejected`, menyimpan `rejection_reason`, menonaktifkan `is_published = false`, serta mencatat audit log `venue.rejected`.
      - `unpublish`: Transaksi DB atomik mencabut penayangan publik `is_published = false` serta mencatat audit log `venue.unpublished` dengan alasan penindakan.
    - `AuditLogController`:
      - `index`: Penelusuran ledger audit platform dengan filter kategori (Semua, Kurasi Venue `venue.*`, Keamanan Akun `user.*`), pencarian keyword (aksi, alasan, IP, nama/email aktor), dan pagination 25 baris per halaman.
  - **Antarmuka Pengguna DOKO (React / Inertia / Tailwind)**:
    - `resources/js/pages/superadmin/venues/index.tsx`: Antrean kurasi venue dengan badge total pending, tab filter status, search input, kartu venue dengan penanda status kontras, dan empty state ramah bertema DOKO.
    - `resources/js/pages/superadmin/venues/show.tsx`: Layout editorial split: sisi kiri memuat detail lengkap data pengajuan (cover photo, info venue & kontak, profil mitra, unit space, matriks 7 hari jadwal operasional, layanan add-on), sisi kanan memuat sticky decision panel (tombol Setujui dengan dialog konfirmasi, tombol Tolak dengan modal alasan wajib, tombol Cabut Publikasi dengan modal alasan wajib), dan bagian bawah menampilkan timeline riwayat audit venue.
    - `resources/js/pages/superadmin/audit/index.tsx`: Tabel audit trail immutable platform dengan badge aksi berkode warna, metadata aktor, target entitas, ringkasan payload/alasan, IP address, user agent, dan filter pencarian instan.
    - `resources/js/components/app-sidebar.tsx`: Menu navigasi khusus "Superadmin" memuat tautan "Antrean Kurasi" dan "Log Audit" untuk akun superadmin.
    - `resources/js/pages/dashboard.tsx`: Link "Lihat Semua" dan tombol "Tinjau" pada widget pengajuan pending langsung mengarah ke `/superadmin/venues` dan `/superadmin/venues/{slug}`.

- **Automated Tests (`tests/Feature/Superadmin/`)**:
  - 18 test Feature Pest baru lulus 100%:
    - `VenueApprovalTest.php` (10 test): index queue, show submission, approve venue & catat audit log, owner dapat mempublikasikan venue approved, reject venue dengan alasan & catat audit log, validasi rejection_reason required min 5 char, penolakan publikasi untuk venue rejected, unpublish venue dengan alasan & catat audit log, proteksi 403 owner, proteksi 403 customer.
    - `SuperadminTwoFactorEnforcementTest.php` (5 test): superadmin tanpa 2FA diredirect ke settings security, superadmin tanpa 2FA menerima 403 pada JSON request, superadmin dengan 2FA terkonfirmasi dapat mengakses panel, konfirmasi 2FA mencatat audit log `user.2fa_enabled`, penonaktifan 2FA mencatat audit log `user.2fa_disabled`.
    - `SuperadminAuditTest.php` (3 test): index audit logs dengan pagination & filter kategori (all/venue/security) serta pencarian teks, pencatatan IP & payload audit log secara akurat, proteksi 403 non-superadmin.

- **Bukti Verifikasi**:
  - Concurrency Check: `php artisan slot:concurrency-check` PASS (rows=1).
  - Full CI Pipeline: `composer ci:check` hijau penuh:
    - Pint: Passed
    - PHPStan Level 5: Passed (0 error)
    - Pest: Passed (201 tests, 1139 assertions, 100% lulus)
    - `npm run check` (vp check): All 113 files correctly formatted, 0 warnings / lint errors
    - `npm run types:check`: TypeScript clean
    - `npm run build`: Build sukses 3.62s.
  - Browser QA (Chromium headless 390, 768, 1440):
    - 9/9 pengujian viewport lulus zero horizontal overflow (`scrollWidth === clientWidth`) pada halaman `/superadmin/venues`, `/superadmin/venues/borneo-futsal-arena`, dan `/superadmin/audit`.
    - Alur penegakan 2FA superadmin terverifikasi di browser nyata: redirect otomatis saat belum mengaktifkan 2FA, dan akses penuh kurasi saat 2FA telah aktif.
  - Gate 3 progress: Chunk 3.3 resmi ditandai **DONE**. Seluruh scope Sprint 3 telah tuntas.

### 2026-11-06 — 3.4 Tenant Isolation Tests & Gate 3 Review — DONE

- **Implementasi Chunk 3.4: Tenant Isolation Tests (IDOR / RBAC Matrix)**:
  - **Matriks Keamanan Komprehensif (`tests/Feature/Security/TenantIsolationMatrixTest.php`)**:
    - 13 test Feature Pest baru dengan 54 assertions memvalidasi isolasi tenant antar-bisnis secara ketat:
      - **Venue Isolation**: Owner B diblokir 403 saat mencoba melihat (`edit`), mengubah (`update`), menghapus (`destroy`), mengubah status publikasi (`publish`), atau mengajukan kurasi (`submit-approval`) pada venue milik Owner A. Customer diblokir 403. Guest dialihkan ke login.
      - **Space Isolation**: Owner B diblokir 403 saat mencoba melihat index, form create, store, edit, update, atau delete space pada venue milik Owner A. Akses silang URL venue-space yang tidak cocok otomatis mengembalikan 404 (*route model binding mismatch guard*).
      - **Schedule & Versioning Isolation**: Owner B diblokir 403 saat mencoba melihat atau memperbarui matriks operasional 7 hari pada space milik Owner A.
      - **Add-On Isolation**: Owner B diblokir 403 saat mencoba mengelola add-on milik Owner A (index, store, update, destroy). Akses silang URL space-addon yang tidak cocok mengembalikan 404.
      - **Superadmin Boundary & 2FA Enforcement**: Rute kurasi dan audit superadmin tertutup rapat (403) untuk Owner dan Customer. Superadmin tanpa 2FA terkonfirmasi diblokir oleh middleware `superadmin.2fa` (redirect ke `/settings/security` untuk web, 403 untuk JSON).
      - **Public Catalog Isolation**: Venue dan space berstatus `pending` atau `rejected` terbukti 100% terisolasi dari marketplace publik; tidak muncul pada hasil pencarian `/spaces`, dan akses langsung ke `/v/{slug}` atau `/spaces/{slug}` mengembalikan 404.
      - **Gate Scanner Cross-Tenant Isolation**: Pemindaian tiket untuk Venue A menggunakan scanner Venue B ditolak secara akurat dengan kode `WRONG_VENUE`.
  - **Penyempurnaan Model `SpaceAddOn`**:
    - Menambahkan `space_id` ke dalam atribut `#[Fillable]` pada `app/Models/SpaceAddOn.php` untuk mendukung mutasi relasional yang aman.

- **Evaluasi & Kelulusan Gate 3 Checklist**:
  - [x] **Owner bisa onboarding venue/space/schedule**: Terbukti pada Chunk 3.1 & 3.2 (profil venue, 3 zona waktu Indonesia, cover privat + signed URL, unit space, cancellation policy, 7-day schedule editor dengan live slot preview, add-on multi-type).
  - [x] **Superadmin bisa approve/reject**: Terbukti pada Chunk 3.3 (antrean kurasi `/superadmin/venues`, review detail submission, persetujuan atomik, penolakan dengan modal alasan wajib min 5 karakter, unpublish venue dengan alasan penindakan).
  - [x] **Venue `pending` tidak muncul di marketplace**: Terbukti pada `TenantIsolationMatrixTest::test_pending_and_rejected_venues_are_completely_hidden_from_public_catalog`.
  - [x] **Audit log dan 2FA aktif**: Terbukti pada Chunk 3.3 (ledger `audit_logs` append-only merekam aksi kurasi & security, listener Fortify 2FA, dan middleware `superadmin.2fa`).
  - **Status Gate 3:** **LULUS RESMI (PASS)**.

- **Bukti Verifikasi Pipeline**:
  - Full CI Pipeline: `composer ci:check` hijau penuh:
    - Pint: Passed
    - PHPStan Level 5: Passed (0 error)
    - Pest: Passed (214 tests, 1193 assertions, 100% lulus)
    - `npm run check` (vp check): All 113 files correctly formatted, 0 warnings / lint errors
    - `npm run types:check`: TypeScript clean
    - `npm run build`: Build sukses dalam 3.62s.
  - Concurrency Check: `php artisan slot:concurrency-check` PASS (rows=1).
---

**Blocker eksternal yang masih terbuka:**

- Kredensial sandbox Xendit (0.2).
- Domain + kredensial provider email (0.4).

