# Review Report — AdaTempat PRD

**Status:** Final review report untuk MVP  
**Tanggal:** 4 November 2026  
**Dokumen sumber:** `adatempat-prd-original.md`  
**Dokumen turunan:** `adatempat-prd-revised.md`  
**Metode:** Review dokumen, verifikasi eksternal terbatas (Xendit, MDR QRIS Bank Indonesia, Laravel 13), tanpa inspeksi kode karena repositori belum memiliki implementasi.

---

## 1. Ringkasan Eksekutif

PRD AdaTempat kuat sebagai dokumen visi: persona dan RBAC jelas, katalog route lengkap, skema awal sudah ada, daftar fitur yang ditunda eksplisit, dan konvensi copy Indonesia realistis. Namun PRD ini **belum build-ready** untuk dijadikan baseline implementasi tanpa revisi.

Enam masalah P0 membuat build berisiko:

1. **Model uang belum konsisten dan berisiko regulasi.** PRD menyebut saldo `pending` dan `available`, tetapi schema hanya punya satu kolom `balance`. Fee konsumen Rp1.500 tidak ada di ledger, MDR/biaya Xendit tidak dimodelkan, dan escrow platform-menahan-dana memerlukan izin/pihak berlisensi.
2. **Refund dan pembayaran telat tidak didefinisikan.** Webhook bisa tiba setelah hold 10 menit lepas dan slot sudah terjual lagi; tidak ada refund flow atau aturan compensating action.
3. **Anti-overlap slot belum terbukti.** Unique index exact `(space_id, slot_date, start_time, end_time)` tidak mencegah overlap range saat durasi slot berubah atau ada custom block.
4. **Skema kurang untuk fitur yang dijanjikan.** Tidak ada tabel assignment staff-venue, media, amenities, `space_custom_slots`, refund, audit log, pembayaran subscription, dan timezone venue.
5. **Keamanan dan kepatuhan belum memadai.** Mekanisme webhook Xendit tidak seragam, tiket publik membawa token/PII, belum ada 2FA superadmin, rate limit, upload privat, audit trail, dan consent UU PDP.
6. **NFR tidak terukur.** Klaim “0 double booking”, “100% green test”, dan “<300ms” tidak punya definisi p95/p99, beban, atau SLO.

Review ini juga mencatat empat masalah P1/P2: scope MVP terlalu besar, flow operasional belum lengkap, gateway WhatsApp tidak resmi, dan beberapa kontradiksi dokumen.

**Keputusan yang sudah diadopsi:** MVP dipersempit ke booking online + QRIS + tiket + scanner + owner portal minimal + refund manual + approval venue; pembayaran memakai split settlement Xendit; MySQL 8; checkout wajib login; email sebagai kanal notifikasi MVP; WhatsApp, POS, reschedule, wallet/payout, escrow, dan SaaS subscription masuk Phase 2; fee konsumen Rp1.500 dihapus.

---

## 2. Ruang Lingkup & Metode

### 2.1 Yang direview

Seluruh isi `adatempat-prd-original.md`, dengan penekanan pada:

- Model bisnis dan aliran dana.
- Lifecycle booking, pembayaran, refund, dan tiket.
- Konkurensi slot dan integritas data.
- Kelengkapan schema dan RBAC.
- Keamanan, kepatuhan, dan NFR.
- Kesiapan operasional dan phasing MVP.

### 2.2 Yang tidak direview

- Implementasi kode, karena repositori hanya berisi PRD.
- Kontrak komersial Xendit, bank, atau gateway WhatsApp.
- Opini hukum final, perizinan PJP, dan sertifikasi kepatuhan.
- Desain UI detail dan copy final seluruh halaman.

### 2.3 Verifikasi eksternal

- **Xendit:** webhook memakai `x-callback-token` pada dokumentasi umum; Payment Status Callback memakai `X-Callback-Signature` HMAC SHA256 dan `X-Callback-Timestamp`. Verifikasi harus mengikuti produk yang dipilih.
- **Xendit XenPlatform:** mendukung sub-account, split rule, platform fee, dan settlement otomatis; ketersediaan dan KYC harus dikonfirmasi sebelum dikunci.
- **MDR QRIS Bank Indonesia:** sejak 1 Oktober 2026, 0% untuk transaksi sampai Rp100.000 di semua kategori; UMI 0% sampai Rp500.000; UKE/UME/UBE 0,7% di atas ambang. MDR ditanggung merchant dan tidak boleh dibebankan ke konsumen.
- **Laravel 13:** rilis 17 Maret 2026, PHP 8.3–8.5, bug fix sampai Q3 2027, security fix sampai 17 Maret 2028.

---

## 3. Register Temuan

| ID | Severity | Lokasi PRD | Temuan | Dampak | Target revisi |
| --- | --- | --- | --- | --- | --- |
| F-001 | P0 | 1.1, 2.1, 6.7, 8.12, 8.13 | Saldo `pending` vs `available` tidak ada di schema; fee Rp1.500 dan MDR/biaya Xendit tidak dimodelkan; escrow manual berisiko regulasi. | Desain wallet/payout salah, unit economics negatif, risiko izin PJP. | 6.7, 8.12, 8.13, 9.2 |
| F-002 | P0 | 6.2, 6.5, 8.7, 8.11 | Tidak ada refund/state pembayaran terpisah atau aturan webhook telat setelah hold expired. | Dana masuk tanpa slot, double sale, sengketa. | 6.2, 6.5, 8.7, 8.11 |
| F-003 | P0 | 6.1, 6.2, 8.8 | Unique index exact tidak mencegah overlap range; perubahan durasi/custom block memperparah. | Double booking tetap mungkin. | 6.1, 6.2, 8.5, 8.8 |
| F-004 | P0 | 2.1, 4.x, 6.x, 8.x | Entitas hilang: staff-venue, media, amenities, custom slot, refund, platform revenue, audit log, subscription payment, timezone. | Fitur yang dijanjikan tidak dapat dibangun tanpa tabel baru. | 3, 4, 8 |
| F-005 | P0 | 6.5, 6.6, 9.2 | Webhook tidak seragam; tiket publik membawa token/PII; belum ada 2FA, rate limit, upload privat, audit, consent UU PDP. | Risiko keamanan, kebocoran data, penyalahgunaan payout. | 9.2, 6.5, 6.6 |
| F-006 | P0 | 1.4, 9.1–9.3 | NFR tidak terukur: “0 double booking”, “100% green”, “<300ms” tanpa p95/beban/SLO; tidak ada backup/DR/observability. | Target tidak dapat diuji atau diterima. | 1.4, 9 |
| F-007 | P1 | 2.1, 4.x | Scope MVP terlalu besar: marketplace + SaaS + POS + scanner + escrow + subscription + WA + realtime + SSR. | Waktu rilis panjang, risiko integrasi menumpuk. | 2.1, 4 |
| F-008 | P1 | 6.x | Flow kurang: cancel, refund, no-show, reminder, maintenance block, reactivation, proration, dispute. | Operasi harian tidak tertangani. | 6 |
| F-009 | P1 | 6.9 | WhatsApp gateway tidak resmi; tidak ada fallback/opt-in. | Notifikasi gagal, nomor diblokir. | 6.9 |
| F-010 | P1 | 1.5, 9.4 | SSR + Reverb menambah proses deploy, biaya, dan titik gagal; belum ada fallback polling. | Operasional kompleks, biaya naik. | 1.5, 9.4 |
| F-011 | P2 | 3, 4, 6.8, 8.x | `payment_method` tanpa `qris_table`; SaaS fee via QRIS vs debit wallet; guest checkout vs login; section 5 hilang. | Implementasi ambigu, bug integrasi. | 3, 4, 6.8, 8 |
| F-012 | P2 | 1.3, 1.4, 6.8 | Klaim 0% komisi dan fee Rp1.500 (yang kemudian dihapus) belum diuji terhadap MDR, biaya gateway, pajak, dan payout. | Model bisnis bisa merugi per transaksi. | 1.3, 1.4, 6.8 |

---

## 4. Temuan P0

### F-001 — Model uang tidak konsisten dan berisiko regulasi

**Bukti:**

- PRD 1.1 dan 2.1 menyebut “segregated pending and available balances”.
- Schema 8.12 `venue_wallets` hanya memiliki `balance`.
- PRD 1.3 menetapkan fee konsumen Rp1.500, tetapi tidak ada di schema/ledger.
- PRD 6.7 menjelaskan escrow dan payout manual; 8.13 hanya punya tipe `credit_booking`, `debit_payout`, `debit_saas_fee`, `adjustment`.
- MDR dan biaya Xendit tidak dimodelkan, padahal model bisnisnya 0% komisi.

**Dampak:** desain wallet/payout bisa salah total, unit economics tidak terukur, dan platform yang menahan dana customer lalu membayar venue secara manual berisiko dianggap menjalankan layanan pembayaran tanpa izin.

**Rekomendasi:** gunakan split settlement Xendit untuk MVP; jangan menahan dana customer. Jika escrow tetap diinginkan, pindahkan ke Phase 2 dengan sign-off legal, partner/izin, trust account, dan prosedur rekonsiliasi. Hapus fee konsumen Rp1.500 di MVP; monetisasi dari SaaS Phase 2.

**Keputusan:** diadopsi. Split settlement Xendit default, fallback QRIS langsung, escrow dan wallet/payout Phase 2, fee konsumen dihapus.

### F-002 — Refund dan pembayaran telat tidak ada

**Bukti:**

- PRD 6.2: hold expire setelah 600 detik; cleanup mengembalikan slot.
- PRD 6.5: webhook mengubah booking menjadi `confirmed`.
- PRD 8.7 punya status `expired`, tetapi tidak ada aturan jika webhook `paid` datang setelah expiry.
- PRD 8.11 punya status `pending`, `paid`, `expired`, `failed`, tetapi tidak ada `refund_pending`/`refunded`.

**Dampak:** customer bisa membayar setelah slot dilepas dan terjual lagi; tidak ada compensating action, ledger refund, atau status yang jelas.

**Rekomendasi:** state machine eksplisit; webhook telat tidak boleh confirm; picu `refund_pending`; refund manual superadmin di MVP; idempotensi berdasarkan event/payment ID.

**Keputusan:** diadopsi. Refund manual superadmin di MVP; auto refund Phase 2.

### F-003 — Anti-overlap slot belum terbukti

**Bukti:**

- PRD 8.8: unique `(space_id, slot_date, start_time, end_time)`.
- PRD 6.1: slot duration 30/60/90/120 menit dan `space_custom_slots`.
- PRD 6.2: Redis lock per `space_id:date:start_time`.

**Dampak:** unique index exact hanya mencegah duplikat persis. Booking 19:00–20:00 dan 19:30–20:30 tidak bentrok di index, tetapi overlap secara waktu. Perubahan durasi slot dan custom block memperparah.

**Rekomendasi:** canonical grid immutable per schedule version; unique index per sel grid `(space_id, slot_date, start_time)`; row lock per `(space_id, date)`; validasi overlap dalam transaksi. MySQL 8 tidak punya exclusion constraint, jadi integritas harus diuji dengan parallel test.

**Keputusan:** diadopsi. MySQL 8 final; canonical grid + unique cell + row lock.

### F-004 — Schema kurang untuk fitur yang dijanjikan

**Bukti:** PRD 2.1, 3, 4.x, 6.1, 6.4, 6.7, dan 8.x menjanjikan fitur yang tidak punya tabel/kolom:

- Assignment staff ke venue.
- Media/gallery (hanya `cover_photo_path`).
- Amenities.
- `space_custom_slots` (dirujuk 6.1, tidak ada di schema).
- Refund.
- Platform revenue/fee.
- Audit log superadmin.
- Pembayaran subscription (`payment_transactions.booking_id` non-null).
- Timezone venue.
- `spaces.cancellation_policy` (hanya ada `reschedule_policy`).
- Template notifikasi dan reminder.

**Dampak:** fitur tidak dapat dibangun tanpa tabel baru; beberapa fitur terpaksa di-hardcode atau ditunda.

**Rekomendasi:** tambahkan entitas di PRD revisi; tandai tabel Phase 2 agar tidak masuk migrasi MVP.

**Keputusan:** diadopsi. Schema revisi mencantumkan tabel MVP dan Phase 2 secara terpisah.

### F-005 — Keamanan dan kepatuhan

**Bukti:**

- PRD 6.5 menyebut “token and signature verification” dan 9.2 “HMAC token validation”; Xendit memakai mekanisme berbeda tergantung produk.
- PRD 6.6: tiket berisi token 64 karakter; route `/tickets/{code}` publik.
- Tidak ada 2FA superadmin, rate limit, upload privat, audit trail, consent/retensi UU PDP.

**Dampak:** webhook spoofing, replay, kebocoran tiket/PII, penyalahgunaan akses superadmin, dan risiko kepatuhan.

**Rekomendasi:** verifikasi webhook sesuai produk; idempotensi; signed URL tiket; 2FA superadmin; rate limit; upload privat; audit log; consent dan retensi UU PDP.

**Keputusan:** diadopsi sebagai SEC-001 sampai SEC-010 di PRD revisi.

### F-006 — NFR tidak terukur

**Bukti:** PRD 1.4 dan 9.1–9.3 memakai klaim absolut tanpa definisi beban, p95/p99, error budget, RPO/RTO, atau observability.

**Dampak:** target tidak dapat diuji; “0 double booking” tidak bisa dijamin hanya dengan Redis/unique index tanpa test konkurensi.

**Rekomendasi:** tetapkan p50/p95/p99, profil beban, error rate, uptime, RPO/RTO, queue lag, latency webhook/scan, dan observability. Ubah klaim absolut menjadi target terukur.

**Keputusan:** diadopsi. PRD revisi memakai target awal yang harus divalidasi load test.

---

## 5. Temuan P1/P2

### F-007 — Scope MVP terlalu besar

PRD 2.1 mencakup marketplace, SaaS subscription, POS, scanner, escrow, WhatsApp, realtime, dan SSR sekaligus. Ini bukan MVP; ini beberapa produk. **Keputusan:** MVP dipersempit ke booking online + QRIS + tiket + scanner + owner portal minimal + refund manual + approval venue + email; POS, reschedule, wallet/payout, escrow, SaaS, WhatsApp, dan dashboard GMV masuk Phase 2.

### F-008 — Flow operasional belum lengkap

PRD 6.x tidak mendefinisikan cancel, refund, no-show, reminder, maintenance block, reactivation, proration, dan dispute. **Keputusan:** cancellation/refund masuk MVP dengan policy per space; no-show dan reminder minimal; maintenance block, reactivation, proration, dan dispute masuk Phase 2.

### F-009 — WhatsApp gateway tidak resmi

Fonnte/Waha berisiko pembatasan nomor dan tidak punya jaminan delivery. **Keputusan:** email menjadi kanal MVP; WhatsApp Phase 2 dengan fallback email/SMS dan opt-in.

### F-010 — SSR + Reverb menambah kompleksitas

SSR diperlukan untuk SEO, Reverb untuk real-time slot. Keduanya menambah proses deploy, biaya, dan titik gagal. **Keputusan:** pertahankan dengan fallback polling; ukur biaya dan latency sebelum load test.

### F-011 — Kontradiksi dokumen

- `bookings.payment_method` hanya `qris|cash`, sedangkan `payment_transactions.payment_channel` punya `qris_table`.
- SaaS fee disebut via QRIS invoice, tetapi `wallet_transactions` punya `debit_saas_fee`.
- Guest checkout didukung schema, tetapi RBAC menyatakan login required.
- Section 5 hilang dari penomoran.

**Keputusan:** checkout online wajib login; walk-in POS Phase 2 boleh tanpa akun customer; SaaS invoice Phase 2; `payment_method` MVP hanya `qris`, nilai `cash`/`qris_table` Phase 2; penomoran diperbaiki.

### F-012 — Model bisnis belum diuji

0% komisi + fee Rp1.500 tidak cukup menutup MDR, biaya gateway, dan operasional. **Keputusan:** fee konsumen dihapus; MVP tidak mengambil komisi; monetisasi SaaS Phase 2. Konsekuensinya MVP tidak punya pendapatan transaksi dan harus didanai sebagai fase akuisisi.

---

## 6. Keputusan yang Diadopsi

| Keputusan | Nilai | Dampak |
| --- | --- | --- |
| Output review | `review-report.md` terpisah | PRD revisi tetap fokus; review bisa dibaca sendiri. |
| Output PRD | `adatempat-prd-revised.md` baru | `adatempat-prd-original.md` tidak diubah. |
| Model dana | Split settlement Xendit; fallback QRIS langsung | Tidak ada escrow/kustodi dana di MVP. |
| Database | MySQL 8 | Tidak ada exclusion constraint; overlap via canonical grid + unique cell + row lock. |
| Launch MVP | Booking online + QRIS + tiket + scanner + owner portal minimal + refund manual + approval venue + email + customer history + superadmin minimal | POS, reschedule, wallet/payout, SaaS, WhatsApp, GMV dashboard Phase 2. |
| Checkout | Wajib login | Guest checkout tidak ada; walk-in POS Phase 2 tanpa akun. |
| Reschedule | Phase 2 | State/log disiapkan, tidak dirilis di MVP. |
| Notifikasi | Email MVP; WhatsApp Phase 2 | Tidak ada dependency Fonnte/Waha di launch. |
| Publikasi venue | Superadmin approval sebelum publish | Venue `pending` tidak tampil di marketplace. |
| Pembatalan | Policy per space | Refund dihitung dari `cancellation_policy`, cutoff, dan percentage. |
| Fee konsumen | Dihapus di MVP | Total = subtotal + add-on; platform tidak ambil komisi. |
| Superadmin MVP | Approval venue + refund manual + audit | Dashboard GMV penuh Phase 2. |
| Customer history | Minimal upcoming/past | Tanpa reschedule; tiket tetap bisa diakses via email. |
| Horizon booking | 30 hari | Slot generator/UI membatasi tanggal. |

---

## 7. Perubahan yang Dituangkan di PRD Revisi

1. **Executive summary dan business model** diperbarui: 0% komisi, tanpa fee konsumen, MVP gratis, monetisasi SaaS Phase 2.
2. **Scope** dipecah menjadi Phase 1 MVP dan Phase 2; fitur yang ditunda eksplisit.
3. **RBAC** diperbarui: customer, owner, superadmin di MVP; staff/POS Phase 2; checkout login required.
4. **Route catalog** diperbarui: owner portal minimal, superadmin approval/refund/audit, gate scanner, customer history; route Phase 2 ditandai.
5. **Workflow** mencakup slot/horizon, hold 10 menit, QRIS split settlement, tiket, scanner, cancellation/refund, approval venue, email, dan flow Phase 2.
6. **Schema** ditambah: `spaces.cancellation_policy`, `refunds`, `audit_logs`, `schedule_versions`, customer history query, horizon; tabel Phase 2 dipisahkan.
7. **NFR** dibuat terukur: p95/p99, uptime, RPO/RTO, queue lag, observability.
8. **Security** mencakup webhook auth, idempotency, signed URL, 2FA, rate limit, upload privat, audit, UU PDP.
9. **Testing** mencakup unit, integration, contract, concurrency, E2E, security, load, boundary 30 hari.
10. **Risiko dan open verification** dicatat: Xendit split/KYC, refund mechanics, MDR/pajak, PJP, UU PDP, MySQL overlap, SSR/Reverb, email deliverability.

---

## 8. Risiko Tersisa & Verifikasi Sebelum Build

| Risiko | Mengapa penting | Verifikasi/ mitigasi |
| --- | --- | --- |
| Ketersediaan split settlement Xendit | Tidak semua venue mungkin lolos KYC/sub-account. | Konfirmasi komersial dan sandbox; siapkan fallback QRIS langsung dengan konfirmasi manual. |
| Refund pada split settlement | Platform tidak memegang dana; refund tidak bisa dilakukan dari saldo platform. | Konfirmasi Xendit refund API/ dashboard; jika tidak tersedia, venue transfer ke platform atau platform menalangi lalu menagih venue. |
| MDR, PPN/PPh | Fee konsumen dihapus; MDR ditanggung venue; pajak belum dihitung. | Validasi dengan Xendit dan konsultan pajak; masukkan ke invoice/ledger. |
| Legal PJP/escrow | Escrow ditunda, tetapi tetap perlu kepastian jika Phase 2 dijalankan. | Sign-off legal sebelum Phase 2; partner berlisensi/trust account. |
| UU PDP | Tiket dan booking menyimpan PII. | Consent, retensi, enkripsi, akses minimum, kebijakan privasi. |
| Overlap MySQL | Tidak ada exclusion constraint. | Test paralel, unique cell, row lock, validasi overlap; audit query. |
| SSR/Reverb | Proses deploy dan biaya. | Fallback polling, observability, load test. |
| Email deliverability | Kanal notifikasi MVP. | Provider transaksional, SPF/DKIM/DMARC, retry, log. |
| Adopsi venue | MVP gratis dan approval manual. | Target venue seed, onboarding playbook, feedback loop. |
| Pendapatan MVP | Tidak ada fee/komisi; SaaS Phase 2. | Anggarkan MVP sebagai biaya akuisisi; siapkan metrik konversi Phase 2. |

---

## 9. Rencana Eksekusi & Kriteria Lulus

### Phase 1 — MVP

1. **Fondasi:** auth/RBAC, business/venue, owner portal minimal, approval venue, audit log.
2. **Marketplace & slot:** katalog, venue/space profile, schedule, canonical grid, horizon 30 hari.
3. **Checkout & payment:** hold 10 menit, QRIS split settlement, webhook idempotent, tiket, email.
4. **Gate & customer:** scanner, customer booking history, refund manual, cancellation policy.
5. **Hardening:** concurrency test, ledger/refund invariant, RBAC/IDOR, load test, security review.

### Phase 2

POS cash/table QRIS, staff management, self-service reschedule, wallet/payout/escrow, SaaS subscription/trial/invoices, WhatsApp, dashboard GMV, reminders, auto refund, laporan lanjutan.

### Kriteria lulus MVP

- Tidak ada double booking pada test konkurensi (termasuk overlap dan perubahan durasi).
- Webhook replay tidak menggandakan kredit/notifikasi.
- Refund manual tercatat, bukti privat, status customer terlihat.
- Venue `pending` tidak tampil; approval bekerja.
- Checkout wajib login; horizon 30 hari ditegakkan.
- Email konfirmasi/tiket terkirim; tidak ada dependency WhatsApp.
- p95/p99 dan queue lag memenuhi target awal atau target direvisi dengan alasan.
- `review-report.md` dan `adatempat-prd-revised.md` ada; PRD asli tidak berubah.

---

## 10. Traceability

| Finding | PRD asli | PRD revisi | Acceptance criteria |
| --- | --- | --- | --- |
| F-001 | 1.1, 2.1, 6.7, 8.12, 8.13 | 1.4, 5.3, 7.10–7.12 | AC-018 |
| F-002 | 6.2, 6.5, 8.7, 8.11 | 5.3, 5.5, 7.7, 7.8 | AC-005, AC-006, AC-012, AC-019 |
| F-003 | 6.1, 6.2, 8.8 | 5.1, 5.2, 7.6 | AC-004 |
| F-004 | 2.1, 4.x, 6.x, 8.x | 7 | AC-013, AC-016 |
| F-005 | 6.5, 6.6, 9.2 | 5.4, 8.2 | AC-008, AC-009, AC-017 |
| F-006 | 1.4, 9.1–9.3 | 1.4, 8 | AC-010 |
| F-007 | 2.1, 4.x | 2 | AC-011 |
| F-008 | 6.x | 5.5, 5.11 | AC-019 |
| F-009 | 6.9 | 5.10, 5.11 | AC-017 |
| F-010 | 1.5, 9.4 | 6, 8.3 | — |
| F-011 | 3, 4, 6.8, 8.x | 3, 4, 5, 7 | AC-014 |
| F-012 | 1.3, 1.4, 6.8 | 1.4, 5.3 | AC-018 |

---

## 11. Referensi

- `adatempat-prd-original.md` — PRD sumber.
- `adatempat-prd-revised.md` — PRD revisi.
- `implementation-plan.md` — rencana eksekusi Sprint 0–6 dan chunk backlog.
- Xendit, “Handling webhooks” — https://docs.xendit.co/docs/handling-webhooks
- Xendit, “Route & split payments for sub-accounts” — https://docs.xendit.co/docs/split-payments
- Xendit, “XenPlatform” — https://www.xendit.co/en/products/xenplatform
- Bank Indonesia, “MDR QRIS Bagi Merchant” — https://www.bi.go.id/id/publikasi/ruang-media/cerita-bi/Pages/mdr-qris.aspx
- Laravel, “Release Notes 13.x” — https://laravel.com/framework/docs/releases
- Laravel News, “Laravel 13 Released” — https://laravel-news.com/laravel-13-released
- UU No. 27/2022 tentang Pelindungan Data Pribadi.
- Inertia.js SSR — https://inertiajs.com/server-side-rendering

---

**Catatan:** review ini tidak mengubah `adatempat-prd-original.md`. Semua keputusan di atas dituangkan dalam `adatempat-prd-revised.md`.
