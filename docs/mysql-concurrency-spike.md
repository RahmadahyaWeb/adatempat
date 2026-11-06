# MySQL Concurrency Spike — Decision Memo (Chunk 0.3)

**Tanggal:** 5 November 2026  
**Status:** **DONE** — canonical grid + unique cell + row lock terbukti di MySQL lokal.  
**Referensi:** `adatempat-prd-revised.md` §5.1–5.2, §7.2 (`booking_slots`), `implementation-plan.md` chunk 0.3.

---

## 1. Hasil

Dua proses paralel memperebutkan cell yang sama pada `(space_id, slot_date, start_time)` yang sama:

```text
A exit=0 OK booking=900001 slot=2026-11-05 09:00
B exit=1 CONFLICT Slot sudah dipesan.
rows=1 booking_ids=900001
PASS: exactly one reservation persisted; the other was rejected.
```

Perintah reproduksi:

```bash
php artisan migrate
php artisan slot:concurrency-check
```

Exit code: `0` (PASS). Dijalankan dua kali dengan hasil sama; baris probe dibersihkan otomatis.

---

## 2. Implementasi

### 2.1 Skema

| Tabel | Kolom penting | Constraint |
| --- | --- | --- |
| `slot_locks` | `space_id`, `slot_date` | unique `(space_id, slot_date)` — row lock per space per hari |
| `booking_slots` | `booking_id`, `space_id`, `slot_date`, `start_time`, `end_time`, `price` | unique `(space_id, slot_date, start_time)` — canonical grid cell |

Catatan: FK ke `spaces`/`bookings` sengaja ditunda sampai jalur booking nyata memakai ID asli (chunk 2.1); probe sintetis 0.3 memakai ID di luar `spaces`.

### 2.2 Alur reservasi

`SlotReservationService::reserve()`:

1. Normalisasi cell ke `H:i:s`, urutkan berdasarkan `start_time`.
2. `DB::transaction(..., 3)`:
   - `insertOrIgnore` row `slot_locks` untuk `(space_id, slot_date)`.
   - `SELECT ... FOR UPDATE` row tersebut → serialisasi semua reservasi di space+tanggal itu.
   - Insert setiap cell ke `booking_slots`.
3. Duplicate key (`SQLSTATE 23000`) → rollback seluruh transaksi → `SlotConflictException`.
4. Caller (nanti checkout) melepas Redis lock dan menampilkan conflict UX.

### 2.3 Bukti all-or-nothing

Test `SlotReservationServiceTest` membuktikan:

- Dua cell berurutan berhasil disimpan.
- Booking kedua yang meminta `10:00–11:00` + `09:00–10:00` gagal pada cell `09:00`; cell `10:00` **tidak** tersimpan (rollback total).
- Tanggal berbeda tidak saling bentrok.
- Input kosong ditolak.

---

## 3. Batasan Spike

- **Redis Tier 1 belum diuji** di chunk ini; `Cache::lock` all-or-nothing masuk chunk 2.2.
- **Belum ada FK** ke `spaces`/`bookings`; FKs ditambahkan di 2.1 saat booking nyata memakai ID asli.
- **Belum ada slot engine** (durasi 30/60/90/120, timezone, horizon 30 hari, schedule version); itu chunk 1.4.
- Test PHPUnit memakai SQLite in-memory; row lock MySQL hanya dibuktikan oleh `slot:concurrency-check` di MySQL.
- Probe memakai booking ID sintetis (900001/900002) dan membersihkan barisnya.

---

## 4. Keputusan

**GO.** Canonical grid + unique cell + row lock MySQL layak dipakai untuk MVP. Implementasi ini menjadi basis chunk 1.2 (schema) dan 2.2 (hold + Redis + expiry):

- Pertahankan unique `(space_id, slot_date, start_time)`.
- Pertahankan row lock per `(space_id, slot_date)`.
- Tambahkan Redis lock all-or-nothing dan expiry 600 detik di 2.2.
- Tambahkan validasi alignment cell dan schedule version di 1.4.
- Jangan mengandalkan unique `(start_time, end_time)`; MySQL tidak punya exclusion constraint.

**Gate 0:** item “Concurrency MySQL terbukti” dapat dicentang.
