---
sessionID: ses_e590be5f5ffeL3DeouO3smCvUb
baseMessageCount: 1
updatedAt: 2026-11-04T15:01:04.492Z
version: 1.0
date_created: 2026-11-04
owner: agent
tags: [spec, diagnostic]
consumedState: 26f95eaa1bb70b2cc95e3643f502833c9ea3d375c48ae69ec614ce7f178be75e
---

# Review PRD AdaTempat — temuan kritis & rencana tindak lanjut

## Current spec

# Introduction

Dokumen ini adalah spesifikasi kerja untuk **review PRD AdaTempat** dan **revisi PRD terarah**. Fokusnya: temuan P0/P1, rekomendasi model dana, state machine, ERD v2, NFR terukur, dan phasing. Tidak ada kode yang ditulis. Repositori saat ini hanya berisi `adatempat-prd-original.md`; tidak ada implementasi untuk diperiksa.

## 1. Purpose & Scope

### Tujuan

- Menilai kesiapan PRD sebagai dasar implementasi, bukan hanya kelengkapan fitur.
- Memprioritaskan temuan P0/P1/P2 dengan bukti, dampak, dan rekomendasi.
- Mencatat keputusan model dana: split settlement Xendit sebagai default, QRIS langsung ke venue sebagai fallback, dengan risiko legal/operasional/finansial yang sudah dinilai.
- Menyusun revisi terarah: state machine, ERD v2, kontrak route/API, acceptance criteria, NFR, dan phasing.

### Audiens

Product owner, tech lead, engineer, QA, finance, legal/compliance.

### In Scope

Review seluruh PRD; verifikasi silang Xendit QRIS/webhook/split settlement, MDR QRIS Bank Indonesia, Laravel 13, dan UU PDP; laporan temuan; revisi terarah; decision record; test strategy; phase plan.

### Out of Scope

Implementasi kode, kontrak komersial, opini hukum final, sertifikasi/izin PJP, desain UI detail, dan pembayaran selain QRIS.

### Asumsi

- PRD asli adalah sumber kebenaran requirement.
- Stack target: Laravel 13/PHP 8.3–8.5, React 19/Inertia, Redis 8, MySQL 8, Xendit, email transaksional (MVP), WhatsApp Phase 2.
- MVP berbahasa Indonesia; data awal venue di Kalimantan Selatan (WITA).
- Model dana diputuskan: split settlement Xendit sebagai default, QRIS langsung ke venue sebagai fallback; escrow platform ditunda sampai ada sign-off legal.
- Wallet/payout manual ditunda ke Phase 2 dan tidak termasuk MVP.
- Scope launch MVP: booking online + QRIS + tiket + scanner; POS, wallet/payout manual, dan SaaS subscription masuk Phase 2.
- Owner portal minimal (CRUD venue, space, jadwal) masuk MVP.
- Refund MVP diproses manual oleh superadmin; refund otomatis Xendit masuk Phase 2.
- Checkout online MVP wajib login; guest checkout tidak termasuk.
- Self-service reschedule ditunda ke Phase 2.
- Notifikasi MVP memakai email; WhatsApp (Fonnte/Waha) ditunda ke Phase 2.
- Venue wajib disetujui superadmin sebelum publish ke marketplace.
- Pembatalan/refund mengikuti policy per space; refund diproses manual oleh superadmin di MVP.
- Fee konsumen Rp1.500 dihapus di MVP; monetisasi dari SaaS subscription Phase 2 (MVP tanpa pendapatan platform dari transaksi).
- Superadmin MVP: approval venue, refund manual, dan audit log; dashboard GMV/reports penuh Phase 2.
- Customer booking history minimal (upcoming/past) masuk MVP; aksi reschedule Phase 2.
- Laporan review ditulis terpisah sebagai `review-report.md`; PRD revisi ditulis sebagai `adatempat-prd-revised.md`.
- Horizon booking MVP 30 hari ke depan.
- Revisi PRD ditulis ke file baru `adatempat-prd-revised.md`; `adatempat-prd-original.md` tidak diubah.

### Deliverables

1. Laporan review `review-report.md` dengan register temuan.
2. File baru `adatempat-prd-revised.md` berisi revisi terarah; PRD asli tetap utuh.
3. Decision record model dana (split settlement), database (MySQL 8), MVP scope, checkout auth, cancellation policy, fee konsumen, refund manual, reschedule, notifikasi email, approval venue, superadmin scope, customer history, dan penundaan wallet/payout.
4. Traceability matrix temuan → PRD → revisi → AC.
5. Test strategy dan phase plan.

## 2. Definitions

| Istilah | Definisi |
| --- | --- |
| P0/P1/P2 | Prioritas: memblokir build/risiko uang-legal; penting; konsistensi dokumen. |
| MDR | Merchant Discount Rate; biaya QRIS yang ditanggung merchant. |
| PJP | Penyelenggara Jasa Pembayaran berizin Bank Indonesia. |
| Escrow | Platform menahan dana customer sebelum disalurkan ke venue. |
| Split settlement | Penyedia pembayaran memecah dana otomatis ke venue dan platform. |
| Hold | Penguncian slot sementara selama checkout/pembayaran. |
| Canonical grid | Pembagian waktu baku per space per hari sebagai satu-satunya representasi slot. |
| Idempotency | Operasi aman diulang tanpa menggandakan efek/ledger. |
| Ledger | Catatan mutasi keuangan append-only. |
| RBAC | Role-Based Access Control. |
| SLO | Service Level Objective yang terukur. |
| UU PDP | UU No. 27/2022 tentang Pelindungan Data Pribadi. |

## 3. Requirements, Constraints & Guidelines

### Requirements

- **REQ-001**: Laporan review `review-report.md` memuat ID temuan, severity, lokasi PRD, bukti, dampak, rekomendasi, target revisi, owner, dan status.
- **REQ-002**: Review mencakup model dana, lifecycle pembayaran/refund, konkurensi/overlap slot, kelengkapan skema, RBAC/tenant, keamanan/kepatuhan, NFR/observability, dan scope/phasing.
- **REQ-003**: Decision record model dana mencatat keputusan **split settlement Xendit** sebagai default dan **QRIS langsung ke venue** sebagai fallback; perbandingan escrow tetap disertakan beserta risiko legal, kustodi, operasional, settlement, refund, rekonsiliasi, onboarding, biaya, kontrol, dan kesiapan MVP.
- **REQ-004**: PRD revisi mendefinisikan state machine booking, payment, refund, payout, dan subscription: state, event, guard, side effect, timeout, idempotency key, compensating action.
- **REQ-005**: PRD revisi mendefinisikan konkurensi slot: canonical grid, versioning jadwal, granularitas lock, constraint database, cleanup hold, dan perilaku saat schedule berubah.
- **REQ-006**: PRD revisi menambahkan entitas hilang: assignment staff-venue, media, amenities, `space_custom_slots`, refund, `spaces.cancellation_policy`, platform revenue, audit log, pembayaran subscription (Phase 2), timezone venue, template notifikasi (email MVP, WhatsApp Phase 2), reminder.
- **REQ-007**: PRD revisi menyelesaikan kontradiksi: saldo `pending` vs `available`, `payment_method` table QRIS, biaya SaaS (invoice vs debit wallet), guest checkout vs login required (diselesaikan: checkout online wajib login), penomoran section.
- **REQ-008**: PRD revisi menetapkan NFR: p50/p95/p99, profil beban, error rate, uptime, RPO/RTO, queue lag, latency webhook/scan, observability.
- **REQ-009**: MVP dipecah menjadi fase dengan exit criteria. Launch MVP mencakup booking online + QRIS + tiket + scanner + owner portal minimal (CRUD venue/space/jadwal) + refund manual via superadmin + checkout wajib login + notifikasi email + approval venue oleh superadmin + customer booking history minimal + panel superadmin minimal (approval/refund/audit) + horizon booking 30 hari; POS cash/table QRIS, self-service reschedule, wallet/payout manual, SaaS subscription, dashboard GMV penuh, dan WhatsApp masuk Phase 2.
- **REQ-010**: Setiap flow revisi punya acceptance criteria Given-When-Then dan strategi pengujian.
- **REQ-011**: Revisi PRD ditulis ke `adatempat-prd-revised.md`; file asli tidak diubah.

### Security Requirements

- **SEC-001**: Verifikasi webhook mengikuti produk Xendit: `x-callback-token` pada dokumentasi umum, atau `X-Callback-Signature` HMAC SHA256 + `X-Callback-Timestamp` pada Payment Status Callback. Jangan mengasumsikan satu mekanisme untuk semua produk.
- **SEC-002**: Webhook idempotent berdasarkan event/payment ID, diproses asynchronous, merespons 2xx cepat, dan aman saat replay.
- **SEC-003**: Tenant isolation enforced oleh policy dan global scope; akses lintas `business_id`/`venue_id` ditolak dan diuji sebagai IDOR.
- **SEC-004**: Halaman tiket memakai signed URL dengan expiry atau autentikasi pemilik; token 64 karakter dan PII tidak boleh bocor lewat URL yang dapat ditebak.
- **SEC-005**: Superadmin wajib 2FA; approve venue, payout, override subscription, dan perubahan konfigurasi masuk audit log.
- **SEC-006**: Rate limit untuk login, checkout, hold, scan, dan webhook; sertakan lockout/abuse control.
- **SEC-007**: Upload media/bukti transfer privat, validasi MIME/ukuran, disajikan via signed URL.
- **SEC-008**: PII diminimalkan dan dienkripsi bila memungkinkan; consent, retensi, penghapusan, dan kebijakan privasi sesuai UU PDP.
- **SEC-009**: Ledger append-only; invariant `sum(ledger) = saldo`; payout mereservasi saldo atomik dan mencegah saldo negatif.
- **SEC-010**: Secret Xendit/token webhook/kredensial gateway tidak masuk repo; rotasi dan akses minimum didokumentasikan.

### Constraints

- **CON-001**: Laravel 13 rilis 17 Maret 2026, PHP 8.3–8.5, bug fix sampai Q3 2027, security fix sampai 17 Maret 2028. Gunakan `^13.0` dan cek kompatibilitas paket.
- **CON-002**: React 19 + Inertia SSR memerlukan proses Node; Reverb memerlukan WebSocket server dan fallback polling.
- **CON-003**: MySQL 8 final untuk MVP. MySQL tidak mendukung exclusion constraint; overlap dicegah dengan canonical grid immutable + unique index per sel grid + row lock per `(space_id, date)` dan validasi overlap dalam transaksi.
- **CON-004**: Redis 8 untuk lock/cache/queue; database tetap source of truth. Multi-key lock di Redis Cluster memerlukan hash tag.
- **CON-005**: Produk Xendit wajib diverifikasi di sandbox: QR Code vs Payment Request, mekanisme webhook, split settlement/sub-account, biaya, settlement timing.
- **CON-006**: Gateway WhatsApp Fonnte/Waha tidak resmi dan berisiko ban; sediakan fallback email/SMS dan opt-in.
- **CON-007**: Tidak ada kode di repo; jangan mengarang path, signature API, atau perilaku library. Verifikasi dokumentasi saat implementasi.
- **CON-008**: Tidak ada data produksi; gunakan seed/factory dan sandbox.

### Guidelines

- **GUD-001**: P0 = memblokir build/risiko uang-legal; P1 = penting dan dapat dijadwalkan; P2 = konsistensi dokumen.
- **GUD-002**: Setiap temuan menyebut lokasi PRD dan bukti; hindari opini tanpa rujukan.
- **GUD-003**: Utamakan revisi terarah; pertahankan maksud asli dan fitur yang sudah ditunda.
- **GUD-004**: Tandai asumsi/keputusan terbuka; setiap P0 punya owner dan tanggal resolusi sebelum implementasi.

## 4. Interfaces & Data Contracts

### Review Report Contract

Setiap temuan minimal memiliki: `finding_id`, `severity`, `location`, `evidence`, `impact`, `recommendation`, `target_revision`, `owner`, `status`.

### State Machine Minimum

| Domain | State | Event masuk | Side effect |
| --- | --- | --- | --- |
| Booking | `pending_payment` | Checkout valid, hold berhasil | Buat invoice, kirim instruksi bayar. |
| Booking | `confirmed` | Payment sukses dan slot valid | Terbitkan tiket, antre email. |
| Booking | `expired` | Hold/invoice melewati deadline | Lepas slot, tandai invoice expired. |
| Booking | `cancelled` | Cancel sesuai policy | Lepas slot, hitung refund sesuai `cancellation_policy` per space. |
| Booking | `rescheduled` (Phase 2) | Reschedule disetujui | Lepas slot lama, klaim slot baru, catat log. |
| Booking | `checked_in` | Scan valid | Kunci token, catat waktu masuk. |
| Booking | `no_show` | Slot berakhir tanpa check-in | Terapkan policy no-show. |
| Payment | `pending` / `paid` / `expired` / `refund_pending` / `refunded` | Invoice, webhook, expiry, refund | Catat ledger/liability; refund manual via superadmin; jangan confirm jika slot sudah lepas. |
| Payout (Phase 2) | `requested` / `approved` / `processing` / `paid` / `rejected` | Pengajuan dan proses superadmin | Reservasi/debit/release saldo, bukti transfer privat. |
| Subscription (Phase 2) | `trialing` / `active` / `past_due` / `cancelled` | Trial, invoice, grace, pembayaran | Publish/unpublish venue sesuai policy. |

Setiap transisi wajib punya guard, aktor, idempotency key, timestamp, dan audit trail. Transisi tak terdefinisi ditolak. Notifikasi MVP memakai email; WhatsApp Phase 2. Venue harus `approval_status = approved` sebelum `is_published = true`. Refund MVP diproses manual oleh superadmin (bukti privat + ledger); auto refund Phase 2. Payout manual dan subscription adalah Phase 2; jangan dijadikan prasyarat MVP. Dengan split settlement, settlement rental ditangani Xendit.

### Proposed Data Contract Changes

- `venue_users`/`staff_venue_assignments`: `user_id`, `venue_id`, `role`, `assigned_by`; unique `(user_id, venue_id)`.
- `venue_media`/`space_media`: path, type, sort, alt text, cover flag.
- `amenities` + pivot `space_amenities`.
- `space_custom_slots`: space, date, start/end, reason, created_by; validasi overlap.
- `space_schedule_versions`: jadwal immutable; booking lama merujuk versi saat dibuat.
- `refunds` (MVP): payment/booking, amount, reason, status, `customer_bank_account`, `proof_path`, `processed_by`, `xendit_refund_id`, actor, timestamps. Refund manual via superadmin; otomatis Phase 2.
- `spaces.cancellation_policy`: enum `free_cancellation`, `partial_refund`, `non_refundable`; `cancellation_cutoff_hours`, `refund_percentage`; policy per space, dihitung saat cancel.
- Customer booking history: query `bookings` by `user_id`, status, dan tanggal; tampilkan upcoming/past. Tidak ada tabel baru; aksi reschedule Phase 2.
- Horizon booking: validasi `today <= booking_date <= today + 30 hari` di request dan slot generator; tanggal di luar horizon ditolak.
- `platform_revenue`/perluasan `wallet_transactions`: `platform_fee`, `refund`, `payout_hold`, `payout_release` (Phase 2), `subscription_payment`, `adjustment`.
- `audit_logs`: actor, action, subject, before/after, IP, user agent.
- Phase 2: `subscription_payments` atau `payment_transactions.payable_type/payable_id` dengan `booking_id` nullable.
- `venues.timezone`: IANA timezone; simpan UTC, tampilkan waktu lokal.
- `notification_templates`/`booking_reminders`.
- Phase 2: `payout_requests` dengan `approved_at`, `processed_at`, `paid_at`, `idempotency_key`, `ledger_hold_id`; `approved` bukan berarti sudah ditransfer.

### Webhook Contract

- Verifikasi header autentikasi sesuai produk Xendit; tolak yang tidak valid.
- Idempotensi berdasarkan `event_id`/`payment_id`; event duplikat tidak mengubah ledger dua kali.
- Respons 2xx cepat setelah validasi dan enqueue; proses berat di queue.
- Perubahan status hanya lewat service domain, bukan controller webhook.

### Route/API Additions

Cancel booking + refund quote sesuai policy per space; preview/confirm reschedule (Phase 2); refund request/approval via superadmin (MVP); auto refund (Phase 2); cancel payout (Phase 2); upgrade/downgrade/proration/reactivation subscription (Phase 2); approval venue oleh superadmin (MVP); panel superadmin minimal + audit log (MVP); dashboard GMV (Phase 2); customer booking history upcoming/past (MVP); resend notifikasi (email MVP, WhatsApp Phase 2); signed URL tiket; health check queue/webhook.

## 5. Acceptance Criteria

- **AC-001**: Given laporan review, When diaudit terhadap PRD, Then setiap P0/P1 punya ID, lokasi, bukti, dampak, rekomendasi, target revisi, dan owner.
- **AC-002**: Given decision record model dana, When direview finance/legal, Then minimal tiga opsi dibandingkan dengan risiko legal, kustodi, operasional, biaya, dan kesiapan MVP, serta ada rekomendasi dan sign-off.
- **AC-003**: Given state machine, When diuji dengan telat bayar, webhook ganda, expiry, refund, cancel, reschedule, dan no-show, Then semua transisi terdefinisi dan tidak ada state menggantung.
- **AC-004**: Given dua request paralel untuk slot sama/overlap, When keduanya submit, Then tepat satu berhasil, yang lain mendapat konflik jelas, dan database konsisten.
- **AC-005**: Given pembayaran sukses setelah hold expired dan slot terjual, When webhook diproses, Then booking tidak `confirmed`; refund/credit dipicu dan ledger seimbang.
- **AC-006**: Given webhook duplikat, When event sama dikirim ulang, Then tidak ada kredit/notifikasi ganda; ledger tidak berubah.
- **AC-007**: Given dua payout request bersamaan (Phase 2), When saldo tidak cukup, Then saldo direservasi atomik; tidak ada saldo negatif; invariant ledger benar.
- **AC-008**: Given user business A mengakses venue business B, When request diproses, Then akses ditolak; matriks RBAC/IDOR lulus.
- **AC-009**: Given URL tiket bocor/kedaluwarsa, When diakses tanpa signature/izin, Then ditolak; token tidak dapat dienumerasi.
- **AC-010**: Given target NFR, When load test pada profil yang disepakati, Then p95/p99, error rate, dan queue lag memenuhi target atau target direvisi dengan alasan.
- **AC-011**: Given scope launch MVP, When direview, Then booking online + QRIS + tiket + scanner, owner portal minimal, refund manual, dan checkout wajib login tercakup; POS, self-service reschedule, wallet/payout manual, dan SaaS subscription ditandai Phase 2 dan tidak menjadi prasyarat launch.
- **AC-012**: Given refund disetujui, When superadmin memproses manual, Then refund tercatat di ledger, status customer terlihat, bukti privat tersimpan, dan invariant saldo benar.
- **AC-013**: Given owner portal minimal, When owner membuat venue/space/jadwal, Then data tervalidasi, tersimpan, dan tampil di marketplace setelah publish/approval.
- **AC-014**: Given online checkout, When user belum login, Then diarahkan ke login dan booking tidak dibuat; walk-in POS tetap dapat membuat booking tanpa akun customer.
- **AC-015**: Given scope MVP, When reschedule diakses, Then fitur ditandai Phase 2 dan tidak tersedia di launch.
- **AC-016**: Given venue baru, When owner menyimpan data, Then venue berstatus `pending` dan tidak tampil di marketplace sampai superadmin menyetujui.
- **AC-017**: Given booking `confirmed`, When notifikasi dikirim, Then email terkirim dan tidak ada dependency WhatsApp di MVP.
- **AC-018**: Given checkout, When total dihitung, Then tidak ada fee konsumen Rp1.500; total = subtotal + add-on; platform tidak mengambil komisi transaksi di MVP.
- **AC-019**: Given customer membatalkan booking, When policy per space `free_cancellation`/`partial_refund`/`non_refundable`, Then refund dihitung sesuai cutoff/percentage dan diproses manual oleh superadmin.
- **AC-020**: Given superadmin login, When membuka panel MVP, Then hanya approval venue, refund manual, dan audit log yang tersedia; dashboard GMV penuh ditandai Phase 2.
- **AC-021**: Given customer login, When membuka booking history, Then melihat upcoming/past; tidak ada aksi reschedule di MVP.
- **AC-022**: Given customer memilih tanggal lebih dari 30 hari ke depan, When checkout, Then tanggal ditolak dengan pesan yang jelas dan slot tidak di-hold.
- **AC-023**: Given akhir fase perencanaan, When diperiksa, Then `review-report.md` dan `adatempat-prd-revised.md` tersedia; PRD asli tidak berubah.

## 6. Test Automation Strategy

- Unit: state machine, guard, pricing add-on, overlap logic, ledger math, policy refund, reschedule (Phase 2), timezone.
- Integration: constraint DB, Redis lock paralel, webhook idempotensi, queue retry, upload privat, signed URL, owner portal CRUD, refund manual + ledger invariant.
- Contract: Xendit sandbox untuk QRIS/webhook/split; email provider di-mock; WhatsApp Phase 2; payload disimpan sebagai fixture.
- Concurrency: beberapa worker mencoba slot sama; pastikan hanya satu booking persist dan lock dilepas.
- Boundary: booking tepat 30 hari diterima, 31 hari ditolak; timezone venue diuji.
- E2E: owner CRUD venue/space → approval superadmin → browse → slot → checkout → bayar → tiket → scan → refund manual → customer history; POS cash/table QRIS (Phase 2); payout (Phase 2); subscription (Phase 2).
- Security: RBAC/IDOR, rate limit, replay webhook, enumerasi tiket, validasi upload, dependency scan.
- Load: profil dari forecast; ukur p95/p99, error rate, queue lag, latency scan.
- CI gate: test P0 wajib lulus; coverage bukan target utama.

## 7. Rationale & Context

### Register Temuan

| ID | Sev | Lokasi | Temuan | Target revisi |
| --- | --- | --- | --- | --- |
| F-001 | P0 | 1.1, 2.1, 6.7, 8.12, 8.13 | Saldo `pending` vs `available` tidak ada di schema; MDR/biaya Xendit dan monetisasi SaaS tidak dimodelkan; fee Rp1.500 dihapus; escrow manual berisiko regulasi. | 6.7, 8.12, 8.13, 9.2 |
| F-002 | P0 | 6.2, 6.5, 8.7, 8.11 | Tidak ada refund/state pembayaran terpisah atau aturan webhook telat setelah hold expired. | 6.2, 6.5, 8.7, 8.11 |
| F-003 | P0 | 6.1, 6.2, 8.8 | Unique index exact tidak mencegah overlap range; perubahan durasi/custom block memperparah. | 6.1, 6.2, 8.5, 8.8 |
| F-004 | P0 | 2.1, 4.x, 6.x, 8.x | Entitas hilang: staff-venue, media, amenities, custom slot, refund, platform revenue, audit log, subscription payment, timezone. | 3, 4, 8 |
| F-005 | P0 | 6.5, 6.6, 9.2 | Webhook tidak seragam; tiket publik membawa token/PII; belum ada 2FA, rate limit, upload privat, audit, consent UU PDP. | 9.2, 6.5, 6.6 |
| F-006 | P0 | 1.4, 9.1–9.3 | NFR tidak terukur: “0 double booking”, “100% green”, “<300ms” tanpa p95/beban/SLO; tidak ada backup/DR/observability. | 1.4, 9 |
| F-007 | P1 | 2.1, 4.x | Scope MVP terlalu besar: marketplace + SaaS + POS + scanner + escrow + subscription + WA + realtime + SSR. | 2.1, 4 |
| F-008 | P1 | 6.x | Flow kurang: cancel, refund, no-show, reminder, maintenance block, reactivation, proration, dispute. | 6 |
| F-009 | P1 | 6.9 | WhatsApp gateway tidak resmi; tidak ada fallback/opt-in. | 6.9 |
| F-010 | P1 | 1.5, 9.4 | SSR + Reverb menambah proses deploy, biaya, dan titik gagal; belum ada fallback polling. | 1.5, 9.4 |
| F-011 | P2 | 3, 4, 6.8, 8.x | `payment_method` tanpa `qris_table`; SaaS fee via QRIS vs debit wallet; guest checkout vs login; section 5 hilang. | 3, 4, 6.8, 8 |
| F-012 | P2 | 1.3, 1.4, 6.8 | Klaim 0% komisi dan penghapusan fee konsumen belum diuji terhadap MDR, biaya gateway, pajak, dan monetisasi SaaS Phase 2. | 1.3, 1.4, 6.8 |

### Keputusan Model Dana

| Kriteria | Escrow platform | Split settlement Xendit | QRIS langsung ke venue |
| --- | --- | --- | --- |
| Legal/izin | Risiko tinggi; perlu izin PJP/partner. | Lebih rendah; diproses penyedia berlisensi. | Paling rendah. |
| Kustodi | Platform memegang dana; perlu trust account. | Tidak ada kustodi; settlement otomatis. | Tidak ada kustodi. |
| Payout | Manual + bukti + antrean superadmin. | Otomatis untuk rental. | Terpisah per venue. |
| Refund | Platform menanggung/menahan refund. | Mengikuti mekanisme Xendit. | Sulit dipaksa. |
| Rekonsiliasi | Gateway → ledger → bank. | Laporan per sub-account. | Banyak rekening venue. |
| Onboarding | Cepat teknis, KYC tetap perlu. | KYC/sub-account per venue; friksi awal. | Paling cepat. |
| Biaya | Platform menanggung MDR + payout. | Biaya gateway dipotong sebelum split. | Venue menanggung QRIS. |
| Kontrol | Tinggi. | Sedang. | Rendah. |
| Kesiapan MVP | Rendah–sedang. | Sedang–tinggi. | Tinggi, tetapi UX rendah. |

**Keputusan:** **split settlement Xendit** menjadi default MVP, dengan **QRIS langsung ke venue** sebagai fallback jika sub-account/KYC belum siap. **Escrow platform** ditunda sampai ada sign-off legal, partner/izin, trust account, dan prosedur rekonsiliasi. Karena split dipilih, `venue_wallets`/`payout_requests` bukan jalur dana utama; jadikan laporan settlement atau tunda ke Phase 2. **Fee konsumen Rp1.500 dihapus**; split rule mengarahkan 100% nilai rental ke venue setelah biaya gateway, dan platform tidak mengambil komisi transaksi. Monetisasi berasal dari SaaS subscription Phase 2. MDR ditanggung merchant/venue dan tidak boleh dibebankan ke konsumen.

Catatan MDR: sejak 1 Oktober 2026, QRIS 0% untuk transaksi sampai Rp100.000 di semua kategori; UMI 0% sampai Rp500.000; UKE/UME/UBE 0,7% di atas ambang. Karena fee konsumen dihapus, MDR tetap ditanggung venue; validasi perhitungan dan pajak dengan Xendit/konsultan.

### Decision Log

| Keputusan | Nilai | Dampak |
| --- | --- | --- |
| Output revisi | File baru `adatempat-prd-revised.md`; PRD asli tidak diubah. | Review tetap bisa dibandingkan dengan versi asli. |
| Model dana | Split settlement Xendit; fallback QRIS langsung; escrow Phase 2. | Wallet/payout manual tidak di MVP; fee platform via split. |
| Database | MySQL 8. | Tidak ada exclusion constraint; overlap via canonical grid + unique cell + row lock. |
| Wallet/payout | Phase 2, tidak di MVP. | Skema disiapkan, implementasi ditunda. |
| Launch MVP | Booking online + QRIS + tiket + scanner. | POS, wallet/payout, SaaS subscription tidak memblokir launch. |
| SaaS subscription | Phase 2. | MVP fokus transaksi booking; billing ditunda. |
| Owner portal | Minimal CRUD venue/space/jadwal di MVP. | Onboarding venue self-service; fitur lanjutan Phase 2. |
| Refund | Manual via superadmin di MVP; otomatis Xendit Phase 2. | Perlu bukti transfer dan audit; tidak memblokir launch. |
| Checkout auth | Wajib login di MVP; guest checkout tidak termasuk. | Booking online punya `user_id`; walk-in POS tanpa akun customer. |
| Reschedule | Phase 2. | State/log disiapkan, tidak dirilis di MVP. |
| Notifikasi | Email di MVP; WhatsApp Phase 2. | Tidak ada dependency Fonnte/Waha di launch. |
| Publikasi venue | Superadmin approval sebelum publish. | Venue `pending` tidak tampil di marketplace. |
| Fee konsumen | Dihapus di MVP; monetisasi SaaS Phase 2. | Total = subtotal + add-on; platform tidak ambil komisi transaksi. |
| Pembatalan | Policy per space; refund manual superadmin. | Perlu field policy + cutoff/percentage; refund dihitung saat cancel. |
| Superadmin MVP | Approval venue + refund manual + audit. | Dashboard GMV/reports penuh Phase 2. |
| Customer history | Minimal upcoming/past. | Tanpa reschedule; tiket tetap bisa diakses via email. |
| Laporan review | File terpisah `review-report.md`. | PRD revisi tetap fokus; review bisa dibaca sendiri. |
| Horizon booking | 30 hari ke depan. | Slot generator dan UI membatasi tanggal. |

### Keputusan Arsitektur

- **Model dana:** split settlement Xendit default; fallback QRIS langsung ke venue; escrow platform dan wallet/payout manual ditunda ke Phase 2.
- **Database:** MySQL 8 final. MySQL tidak mendukung exclusion constraint; gunakan canonical grid immutable + unique index per sel grid + row lock per `(space_id, date)` + validasi overlap dalam transaksi. Ini trade-off yang diterima; jangan mengandalkan unique index exact.
- **Slot:** canonical grid per schedule version; perubahan durasi membuat versi baru; Redis hanya optimasi, DB source of truth.
- **State machine:** eksplisit; webhook memicu service domain yang memvalidasi guard.
- **Uang:** ledger append-only; saldo cache dari ledger; invariant diuji; tidak ada fee konsumen; split 100% nilai rental ke venue; refund adalah liability dan diproses manual oleh superadmin di MVP.
- **Scope:** Phase 1 = auth/RBAC → owner portal minimal → approval venue → katalog+slot (horizon 30 hari) → checkout wajib login+QRIS+webhook+tiket → scanner → cancel/refund per policy (manual) → customer history → notifikasi email → panel superadmin minimal. Phase 2 = POS cash/table QRIS, self-service reschedule, settlement/wallet/payout, SaaS subscription, auto refund, WhatsApp, dashboard GMV, reminder/laporan lanjutan.
- **SSR/Reverb/WhatsApp:** pertahankan dengan fallback polling; WhatsApp Phase 2, email menjadi kanal MVP.

## 8. Dependencies & External Integrations

- **EXT-001**: Xendit QRIS (QR Code/Payment Request): API, expiry, status.
- **EXT-002**: Xendit webhook: `x-callback-token` atau `X-Callback-Signature` sesuai produk; sandbox replay.
- **EXT-003**: Xendit XenPlatform/split settlement: sub-account, KYC, split rule, fee, settlement timing.
- **EXT-004**: Phase 2: gateway WhatsApp Fonnte/Waha; risiko ban; fallback email/SMS.
- **EXT-005**: Redis 8 untuk lock/cache/queue.
- **EXT-006**: MySQL 8 final; strategi overlap canonical grid + unique cell + row lock.
- **EXT-007**: Laravel 13/PHP 8.3–8.5, React 19, Inertia, Reverb, Tailwind v4.
- **EXT-008**: Layanan hukum/kepatuhan PJP, UU PDP, PPN/PPh.
- **EXT-009**: Hosting PHP, Node SSR, queue, scheduler, Reverb, observability.
- **EXT-010**: Google Maps, penyimpanan privat bukti transfer/media, email/SMS fallback.
- **EXT-011**: Refund manual: dashboard Xendit/transfer bank, bukti privat, dan rekonsiliasi ledger.
- **EXT-012**: Email transaksional MVP untuk konfirmasi booking dan tautan tiket; provider dipilih saat implementasi.

## 9. Examples & Edge Cases

- **Overlap:** booking 19:00–20:00 vs 19:30–20:30; jika grid 30 menit, slot 19:30–20:00 bentrok dan harus ditolak. Perubahan durasi 60→30 membuat schedule version baru; booking lama tidak diubah.
- **Webhook telat:** checkout T+0, expiry T+600, webhook `paid` T+650. Booking `expired`; payment `refund_pending`; refund dicatat; slot tidak otomatis diklaim.
- **Webhook duplikat:** event sama dua kali; kredit ledger dan notifikasi hanya sekali; keduanya 2xx.
- **Guest checkout:** user belum login mencoba checkout; sistem mengarahkan ke login; booking guest tidak dibuat. Walk-in POS dikecualikan karena dibuat oleh staff.
- **Approval venue:** owner membuat venue; status `pending`; superadmin approve; baru `is_published = true`. Jika rejected, venue tetap tersembunyi dengan alasan.
- **Customer history:** customer login melihat booking upcoming/past; detail tiket dan status refund; tidak ada tombol reschedule di MVP.
- **Horizon booking:** tanggal di luar 30 hari ditolak sebelum hold; UI menampilkan batas horizon.
- **Refund manual:** customer bayar QRIS, booking cancelled; policy per space menentukan refund; superadmin verifikasi, memproses refund via dashboard Xendit/transfer, upload bukti, ledger mencatat debit refund; status customer `refunded`.
- **Payout race (jika escrow/Phase 2):** saldo Rp1.000.000, dua request Rp700.000; satu reservasi, satu ditolak; saldo tidak negatif.
- **Timezone:** venue Banjarmasin (WITA) booking 19:00 disimpan 11:00 UTC; tampilan/scanner memakai timezone venue.
- **QRIS:** transaksi Rp300.000, MDR UKE/UME/UBE 0,7% = Rp2.100; tidak ada fee konsumen Rp1.500; MDR ditanggung venue dan tidak dibebankan ke konsumen.
- **Tiket:** signed URL dengan expiry; QR berisi token 64 karakter; scanner memvalidasi status, token belum dipakai, venue, dan window waktu.

## 10. Validation Criteria

- Traceability: setiap F-xxx terpetakan ke section PRD, revisi, dan AC.
- Konsistensi: tidak ada kontradiksi istilah/saldo/enum/route antar-section.
- State lengkap: semua transisi punya guard, side effect, timeout, idempotency.
- Uang: invariant ledger, saldo tidak negatif, refund (MVP)/payout (Phase 2) punya audit trail.
- Owner portal: CRUD venue/space/jadwal tervalidasi dan tenant-scoped; venue `pending` tidak tampil sampai approval.
- Notifikasi: email MVP terkirim untuk booking `confirmed`; WhatsApp Phase 2.
- Fee konsumen: tidak ada Rp1.500; total = subtotal + add-on; platform revenue hanya SaaS Phase 2.
- Pembatalan: refund dihitung sesuai `cancellation_policy` per space dan diproses manual superadmin.
- Superadmin MVP: hanya approval venue, refund manual, audit log; dashboard GMV penuh Phase 2.
- Customer history: upcoming/past tampil; aksi reschedule tidak ada di MVP.
- Deliverables: `review-report.md` dan `adatempat-prd-revised.md` tersedia; PRD asli tidak berubah.
- Horizon booking: 30 hari diterima, 31 hari ditolak.
- Konkurensi: tes paralel membuktikan hanya satu booking berhasil untuk slot sama/overlap.
- Keamanan: RBAC/IDOR, signed URL, rate limit, replay webhook, upload privat lulus.
- NFR: p95/p99, error rate, uptime, RPO/RTO, queue lag terukur dan disetujui.
- Sign-off: product, engineering, QA, finance, legal/security.
- Scope MVP: booking online + QRIS + tiket + scanner + owner portal minimal + refund manual + checkout wajib login + notifikasi email + approval venue + cancellation policy per space + tanpa fee konsumen + customer history minimal + panel superadmin minimal + horizon booking 30 hari; POS, self-service reschedule, wallet/payout manual, escrow, SaaS subscription, WhatsApp, dan dashboard GMV penuh tidak termasuk; tidak ada AC MVP yang bergantung pada fitur Phase 2.
- Definition of done fase ini: laporan review, `adatempat-prd-revised.md`, decision record, traceability, test strategy; belum ada kode dan PRD asli tetap utuh.

## 11. Related Specifications / Further Reading

- `adatempat-prd-original.md` — PRD sumber.
- Xendit, “Handling webhooks” — https://docs.xendit.co/docs/handling-webhooks
- Xendit, “Route & split payments for sub-accounts” — https://docs.xendit.co/docs/split-payments
- Xendit, “XenPlatform” — https://www.xendit.co/en/products/xenplatform
- Bank Indonesia, “MDR QRIS Bagi Merchant” — https://www.bi.go.id/id/publikasi/ruang-media/cerita-bi/Pages/mdr-qris.aspx
- Laravel, “Release Notes 13.x” — https://laravel.com/framework/docs/releases
- Laravel News, “Laravel 13 Released” — https://laravel-news.com/laravel-13-released
- UU No. 27/2022 tentang Pelindungan Data Pribadi.
- Inertia.js SSR — https://inertiajs.com/server-side-rendering

## Q&A history

Q: Bentuk keluaran apa yang Anda inginkan setelah tanggapan ini?
A: Laporan review + revisi PRD terarah (Recommended)

Q: Untuk MVP, bagaimana dana customer sebaiknya mengalir?
A: Belum diputuskan — beri rekomendasi lengkap dengan risiko

Q: Saat revisi PRD dieksekusi nanti, file mana yang harus diubah?
A: Buat `adatempat-prd-revised.md` baru (Recommended)

Q: Apakah rekomendasi split settlement Xendit dijadikan default di PRD revisi?
A: Ya, split settlement + fallback QRIS langsung (Recommended)

Q: Database final untuk PRD revisi?
A: MySQL 8

Q: Setelah split settlement dipilih, bagaimana status wallet/payout manual di PRD revisi?
A: Phase 2, tidak di MVP (Recommended)

Q: Scope launch MVP mana yang dikunci untuk PRD revisi?
A: Booking online + QRIS + tiket + scanner (Recommended)

Q: Apakah SaaS subscription masuk MVP atau Phase 2?
A: Phase 2, MVP fokus transaksi booking (Recommended)

Q: Apakah owner portal minimal (CRUD venue, space, jadwal) masuk MVP?
A: Ya, minimal CRUD (Recommended)

Q: Bagaimana mekanisme refund untuk MVP?
A: Manual via superadmin (Recommended)

Q: Online checkout MVP: wajib login atau guest checkout?
A: Wajib login (Recommended)

Q: Apakah self-service reschedule masuk MVP atau Phase 2?
A: Phase 2 (Recommended)

Q: Apakah notifikasi WhatsApp masuk MVP?
A: Email saja dulu

Q: Bagaimana venue diterbitkan ke marketplace?
A: Superadmin approval sebelum publish (Recommended)

Q: Kebijakan pembatalan/refund untuk MVP?
A: Ikuti policy per space (Recommended)

Q: Apakah fee konsumen Rp1.500 tetap dipakai di MVP?
A: Hapus fee konsumen, andalkan SaaS

Q: Scope superadmin di MVP?
A: Approval venue + refund manual + audit (Recommended)

Q: Apakah customer booking history masuk MVP?
A: Ya, minimal upcoming/past (Recommended)

Q: Laporan review ditulis di mana?
A: File terpisah `review-report.md` (Recommended)

Q: Berapa horizon booking ke depan untuk MVP?
A: 30 hari (Recommended)
