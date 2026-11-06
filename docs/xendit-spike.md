# Xendit Spike — Decision Memo (Chunk 0.2)

**Tanggal:** 5 November 2026  
**Status:** Implementasi lokal + contract test selesai; **verifikasi sandbox live menunggu kredensial**.  
**Referensi:** `adatempat-prd-revised.md` §5.3, `implementation-plan.md` chunk 0.2, docs Xendit per tautan di bawah.

---

## 1. Keputusan Singkat

**Go bersyarat.** Integrasi QRIS + webhook + refund layak dilanjutkan, dengan tiga koreksi terhadap asumsi PRD:

1. Pakai **Payment Requests API v3** (`POST /v3/payment_requests`), bukan legacy `/qr_codes`.
2. Webhook v3 memakai **`x-callback-token`**, bukan HMAC `X-Callback-Signature`. HMAC hanya relevan untuk produk Payment Status Callback legacy.
3. **Split rule 100% ke venue akan gagal.** Untuk platform fee 0, gunakan header **`for-user-id`** ke sub-account venue tanpa split rule. Split rule hanya dipakai jika platform benar-benar mengambil fee.

Jika salah satu dari tiga hal ini tidak dikonfirmasi Xendit saat uji sandbox, fallback MVP adalah **QRIS langsung ke venue + konfirmasi manual** (pilot-only).

---

## 2. Kontrak yang Sudah Diverifikasi

### 2.1 QRIS

| Item | Nilai |
| --- | --- |
| Endpoint | `POST https://api.xendit.co/v3/payment_requests` |
| Auth | Basic auth: secret key sebagai username, password kosong |
| Header wajib | `api-version: 2024-11-11` |
| Payload inti | `reference_id`, `type: PAY`, `country: ID`, `currency: IDR`, `request_amount`, `capture_method: AUTOMATIC`, `channel_code: QRIS`, `channel_properties.expires_at` |
| Response | `payment_request_id`, `status: REQUIRES_ACTION`, `actions[]` dengan `descriptor: QR_STRING` |
| Batas | Amount Rp1–Rp10.000.000; expiry maksimum 48 jam; settlement T+1 hari kerja |
| Refund | Didukung; partial refund didukung tetapi tergantung issuer; validity 7 hari |

Sumber: <https://docs.xendit.co/apidocs/create-payment-request>, <https://docs.xendit.co/docs/qris>.

### 2.2 Webhook

| Item | Nilai |
| --- | --- |
| Auth | Header `x-callback-token` harus sama dengan token dashboard |
| Event bayar | `payment.capture` (status `SUCCEEDED`, `captures[].capture_id`) |
| Event gagal | `payment.failure` |
| Event expiry | `payment_request.expiry` |
| Event refund | `refund.succeeded` / `refund.failed` |
| Idempotency | Kunci berdasarkan `payment_id` / `capture_id` / `refund_id`; event duplikat diabaikan |
| Response | HTTP 2xx sebagai acknowledgement |

**Koreksi PRD:** langkah 5 di §5.3 menyebut `X-Callback-Signature` HMAC untuk Payment Status Callback. Untuk Payment Requests v3, dokumentasi resmi hanya memakai `x-callback-token`. Jangan mengimplementasikan HMAC untuk jalur ini.

Sumber: <https://docs.xendit.co/apidocs/payment-webhook-notification>, <https://docs.xendit.co/docs/handling-webhooks>, <https://docs.xendit.co/docs/payments-api-webhooks>, <https://docs.xendit.co/apidocs/refund-webhook-notification>.

### 2.3 Split & Sub-account

| Item | Nilai |
| --- | --- |
| Buat sub-account | `POST /v3/accounts`; test mode langsung `LIVE` |
| KYC live | `PENDING_VERIFICATION`, `VERIFICATION_IN_PROGRESS`, `AWAITING_RESUBMISSION`, `PASSED`, `FAILED` |
| Transaksi atas nama venue | Header `for-user-id: {business_id}` |
| Split fee platform | Header `with-split-rule: {split_rule_id}` + `POST /split_rules` |
| Split 100% | **Gagal** — dana harus menyisakan ruang untuk fee |
| Fee/MDR | Dipotong dari dana sebelum settlement; dengan `for-user-id`, venue menanggung MDR |

**Rekomendasi MVP:** pakai `for-user-id` ke sub-account venue dan **tanpa** `with-split-rule`. Ini memberi 100% nilai sewa ke venue dan platform fee 0, sekaligus MDR ditanggung venue. Split rule baru dipakai jika kelak platform mengambil komisi.

Sumber: <https://docs.xendit.co/apidocs/create-account-v3>, <https://docs.xendit.co/docs/split-payments>, <https://docs.xendit.co/docs/accepting-payments-for-sub-accounts>, <https://docs.xendit.co/apidocs/create-split-rule>.

### 2.4 Refund

| Item | Nilai |
| --- | --- |
| Endpoint | `POST /v3/refunds` |
| Required | `reference_id`, `payment_id`, `reason` |
| Optional | `amount` (omit = full refund) |
| Header | `api-version: 2024-11-11`; `for-user-id` untuk transaksi sub-account |
| Status | `PENDING`, `SUCCEEDED`, `FAILED` |
| Split refund | Fee split **tidak** otomatis kembali; perlu rekonsiliasi/transfer manual |

**Catatan:** refund memakai `payment_id`, bukan `payment_request_id` (contoh di panduan lama memakai payment request ID). Window QRIS 7 hari dan dukungan partial refund berbeda per issuer; Gopay tidak mendukung partial refund. Refund tetap manual di MVP.

Sumber: <https://docs.xendit.co/apidocs/create-a-refund>, <https://docs.xendit.co/docs/refund-payment-request>, <https://docs.xendit.co/docs/qris>.

### 2.5 Sandbox

- Simulasi pembayaran: `POST /v3/payment_requests/{payment_request_id}/simulate` dengan body `{ "amount": ... }`. Response `PENDING`; hasil akhir dikirim lewat webhook.
- Test mode tidak memerlukan scan QR fisik ke issuer.
- Test API key + callback token diambil dari Dashboard Xendit → Settings → Developers.
- Sub-account test langsung `LIVE`; sub-account live tetap harus KYC.

Sumber: <https://docs.xendit.co/apidocs/simulate-payment-test-mode>, <https://docs.xendit.co/apidocs/quick-setup>.

---

## 3. Yang Sudah Diimplementasikan

- `app/Services/Xendit/XenditClient.php` — QRIS create, get status, simulate, refund, create test sub-account.
- `app/Http/Controllers/XenditWebhookController.php` — verifikasi `x-callback-token`, validasi payload, dedupe via cache 7 hari.
- `routes/api.php` — `POST /api/webhooks/xendit`.
- `app/Console/Commands/XenditSpikeCommand.php` — `php artisan xendit:spike`.
- Contract test dengan `Http::fake()` dan webhook replay test (9 test).
- Config `services.xendit` + placeholder `.env.example`.

Yang **belum** diverifikasi karena belum ada kredensial: QRIS sandbox live, pengiriman webhook nyata, split/KYC sub-account live, refund nyata.

---

## 4. Checklist Verifikasi Live

1. Isi `.env`:
   ```dotenv
   XENDIT_SECRET_KEY=xnd_development_...
   XENDIT_CALLBACK_TOKEN=...
   XENDIT_BASE_URL=https://api.xendit.co
   ```
2. Buat tunnel publik (contoh `ngrok http 8000`) dan daftarkan `https://<tunnel>/api/webhooks/xendit` di Dashboard → Webhooks.
3. Jalankan:
   ```bash
   php artisan serve
   php artisan xendit:spike --create-account=venue-test@example.com --amount=150000
   ```
   Catat `payment_request_id`, `QR`, dan `status`.
4. Simulasi pembayaran:
   ```bash
   php artisan xendit:spike --amount=150000 --simulate
   ```
   Pastikan webhook `payment.capture` masuk dan log mencatat `duplicate=false`; kirim ulang payload yang sama untuk membuktikan `duplicate=true`.
5. Refund:
   ```bash
   php artisan xendit:spike --refund=py-... --amount=150000
   ```
   Pastikan `refund_id` dan status; tunggu `refund.succeeded`.
6. Uji sub-account + `for-user-id`: buat QRIS dengan `--sub-account=<business_id>` dan cek settlement mengarah ke sub-account venue, bukan master.
7. Catat hasil di memo ini dan ubah status chunk 0.2 menjadi `DONE` atau `BLOCKED`.

---

## 5. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| Split 100% gagal | Asumsi PRD tidak terpenuhi | Pakai `for-user-id` tanpa split rule; konfirmasi ke Xendit |
| Refund transaksi split tidak otomatis | Dana venue/platform tidak seimbang | Refund manual + rekonsiliasi transfer; catat kebijakan MDR |
| KYC venue live lama | Venue tidak bisa menerima dana | Onboarding KYC di owner portal; fallback pilot QRIS langsung |
| Refund window 7 hari / issuer berbeda | Customer tidak bisa refund setelah window | Tampilkan policy di checkout; refund manual di luar window |
| Webhook telat/duplikat | Double confirm | Idempotency key + state machine; jangan confirm jika booking expired |
| Tidak ada HMAC v3 | Salah implementasi keamanan | Verifikasi `x-callback-token`; jangan pakai asumsi HMAC |

---

## 6. Rekomendasi Gate 0

**Go bersyarat** jika:
- QRIS sandbox berhasil dan QR string tampil.
- Webhook `payment.capture` terverifikasi dan replay tidak menggandakan efek.
- Xendit mengonfirmasi `for-user-id` + 100% settlement ke venue dan perlakuan MDR.
- Refund pada transaksi sub-account/split dijelaskan.

Jika salah satu gagal, pilih fallback **QRIS langsung ke venue + konfirmasi manual** untuk pilot, dan tunda split settlement ke fase berikutnya.
