# Email Spike — Decision Memo (Chunk 0.4)

**Tanggal:** 5 November 2026  
**Status:** Pipeline lokal **DONE** (email diterima di Mailpit, fallback log terbukti); **provider transaksional live BLOCKED** menunggu domain + kredensial.  
**Referensi:** `adatempat-prd-revised.md` §5.3, §6, `implementation-plan.md` chunk 0.4.

---

## 1. Keputusan

**Provider default: Resend via SMTP.** Alasan:

- Fokus transaksional, setup DNS sederhana, dan dashboard verifikasi domain yang jelas.
- Laravel 13 sudah punya transport `resend`, tetapi memakai SMTP membuat integrasi provider-agnostic dan tidak menambah dependency baru.
- Alternatif: **Postmark** (reputasi transaksional kuat, berbayar) dan **Amazon SES** (termurah pada volume besar, setup AWS lebih berat). Keputusan final menunggu domain/kredensial.

**Fallback lokal/CI:** `MAIL_MAILER=log` (default saat ini). Email tidak keluar server, tetapi payload tercatat di `storage/logs/laravel.log`. Untuk pengujian lokal end-to-end, gunakan **Mailpit**.

---

## 2. Bukti Lokal

### 2.1 Mailpit

Terpasang lokal: `mailpit v1.33.0` (Homebrew).

```bash
mailpit --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025
```

Kirim email dengan override env (tanpa mengubah `.env` default):

```bash
MAIL_MAILER=smtp MAIL_HOST=127.0.0.1 MAIL_PORT=1025 MAIL_SCHEME=null \
php artisan mail:spike qa@adatempat.test --subject="AdaTempat 0.4 spike"
```

Hasil pada inbox Mailpit (`http://127.0.0.1:8025`):

```text
total=2
AdaTempat 0.4 spike v2  -> qa@adatempat.test
AdaTempat 0.4 spike     -> qa@adatempat.test
```

### 2.2 Fallback log

Dengan `.env` default (`MAIL_MAILER=log`):

```bash
php artisan mail:spike qa@adatempat.test --subject="AdaTempat log fallback"
grep -c "AdaTempat log fallback" storage/logs/laravel.log
# 1
```

### 2.3 Test

`EmailSpikeCommandTest` (2 test, 5 assertion):

- Email terkirim ke recipient yang valid dan menggunakan `EmailSpike` mailable.
- Recipient tidak valid ditolak tanpa mengirim email.

---

## 3. Konfigurasi

### 3.1 Lokal

`.env` default:

```dotenv
MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@adatempat.id"
```

Untuk Mailpit, override seperti §2.1 atau ubah sementara:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_SCHEME=null
```

### 3.2 Staging/Production (Resend SMTP)

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=resend
MAIL_PASSWORD=re_xxxxxxxxx
MAIL_FROM_ADDRESS="no-reply@notifications.adatempat.id"
MAIL_FROM_NAME="AdaTempat"
```

Gunakan subdomain pengirim (mis. `notifications.adatempat.id`) agar reputasi transaksional terpisah dari domain utama. Jika memakai transport native Resend, install `resend/resend-php` dan set `MAIL_MAILER=resend`; untuk MVP SMTP lebih sederhana.

---

## 4. SPF, DKIM, DMARC

Nilai record **tidak boleh dikarang**; salin persis dari dashboard Resend setelah domain ditambahkan.

1. Tambahkan domain/subdomain di Resend → **Domains → Add Domain**. Pilih region terdekat (Singapore/Jakarta jika tersedia).
2. Buka tab **Records**; tambahkan record yang diberikan:
   - **DKIM**: TXT/CNAME (nama biasanya `resend._domainkey...`) — jangan aktifkan proxy Cloudflare (orange cloud).
   - **SPF**: TXT/MX yang diberikan Resend (biasanya mengarah ke infrastruktur Amazon SES).
   - **Return-Path/MX**: opsional, untuk bounce handling.
3. Tunggu verifikasi (biasanya <15 menit, maksimal 72 jam). Cek dengan `dig` atau `https://dns.email/`.
4. Tambahkan DMARC di `_dmarc.notifications.adatempat.id`:
   ```dns
   v=DMARC1; p=none; rua=mailto:dmarcreports@adatempat.id;
   ```
   Mulai `p=none`, pantau laporan, lalu naikkan ke `p=quarantine` dan `p=reject` setelah yakin semua pengirim sah lolos.
5. Verifikasi hasil autentikasi:
   - Kirim ke mailbox nyata (Gmail/Outlook), buka header, pastikan `spf=pass`, `dkim=pass`, `dmarc=pass`.
   - Uji deliverability dengan mail-tester/MXToolbox; jangan masuk spam.
6. Dokumentasikan nilai final record di runbook operasional (bukan di repo jika mengandung data sensitif).

Referensi: <https://resend.com/docs/add-a-domain>, <https://resend.com/docs/dashboard/domains/dmarc>.

---

## 5. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| Domain belum terverifikasi | Email tidak terkirim | Verifikasi DKIM/SPF sebelum launch; fallback log untuk dev |
| SPF/DKIM salah | Masuk spam / ditolak | Salin record dari dashboard, jangan proxy CNAME, cek header |
| DMARC terlalu ketat di awal | Email sah terblokir | Mulai `p=none`, naikkan bertahap |
| Provider outage | Tiket/refund tidak sampai | Queue + retry 3x (chunk 5.4); notifikasi in-app/status halaman |
| Bounce/complaint tidak ditangani | Reputasi turun | Aktifkan bounce webhook provider; bersihkan alamat invalid |
| Email berisi PII | Risiko UU PDP | Minimal data, akses tiket via signed URL, retensi terbatas |
| Log berisi email penuh | PII di log | Jangan pakai log driver di production; redaksi jika perlu |

---

## 6. Status Gate 0

- Pipeline email lokal: **lulus** (Mailpit + fallback log + test).
- Provider transaksional live: **belum** — butuh domain terverifikasi dan kredensial Resend (atau provider lain).
- Setelah domain aktif: jalankan `php artisan mail:spike <email-nyata>`, cek header SPF/DKIM/DMARC, lalu centang Gate 0 “Email provider siap”.
