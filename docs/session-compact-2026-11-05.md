# Handoff — Sesi 2026-11-05 (Stage 1 Home → Stage 2 Browse)

Dokumen ringkas untuk melanjutkan kerja setelah sesi/konteks di-reset.
Sumber status resmi tetap `implementation-plan.md` §18; `PROGRESS.md` mirror kronologis.
Dokumen ini ringkasan sesi — jangan dipakai sebagai pengganti §18.

---

## 1. Status saat ini

| Item | Status | Bukti |
| --- | --- | --- |
| 1.3 Public catalog (backend) | IN PROGRESS | Route/controller katalog + filter + sort; 81 test / 415 assertion; `composer ci:check` hijau |
| 1.3 Stage 1 Home (UI) | **DONE** — disetujui user 2026-11-05 | QA browser 390/768/1024/1440 + `npm run check/types:check/build` hijau |
| Stage 2 Browse `/spaces` | **DONE** — disetujui user 2026-11-05 | Toolbar chip + jumlah + sort + Filter collapsible, sheet mobile satu kolom, empty state; self-review `better-interface`; QA browser 320–1440 tanpa overflow, 0 console error |
| Stage 3 Venue + space detail | Belum mulai | `/v/{venue}` dan `/spaces/{space}` merespons 200 tetapi **render kosong**; sesi dihentikan sebelum ada file ditulis (kuota provider) |
| 0.2 Xendit sandbox | BLOCKED | Menunggu kredensial |
| 0.4 Email provider | BLOCKED | Menunggu kredensial/domain |

Stage 2 **disetujui user 2026-11-05**; tidak ada perubahan kode setelah bukti di bagian 4. Stage 3 sedang dibangun.

---

## 2. Yang dikerjakan di sesi ini

### 2.1 Stage 1 Home dibangun ulang dari nol (referensi DOKO)

- UI lama **dipindahkan, tidak dihapus** ke `.backup/ui-1.3-home-v1-2026-11-05/` (rollback path; repo ini bukan git).
- Referensi: `design-reference/gumroad-01.png` … `gumroad-06.png` (satu deck UI kit DOKO meskipun namanya "gumroad").
- 13 komponen publik + `nav.ts` di `resources/js/components/public/`, `public-layout.tsx`, `pages/public/home.tsx`.
- Data hanya dari `HomeController` (4 venue seed). **Tidak ada mock data.**
- Disetujui user 2026-11-05.

### 2.2 Perbaikan cacat hasil QA browser (Stage 1)

| Cacat | Perbaikan |
| --- | --- |
| `outline-none` mengeset `--tw-outline-style: none` → focus ring keyboard tidak pernah tampil (HIGH) | Kelas dihapus dari elemen yang dapat difokus Tab (`Dialog.Content` drawer tetap `focus-visible:outline-none`, kontainer non-tabbable); 27/27 elemen fokus kini `solid 2px` |
| Hero dan kartu venue pertama memakai foto identik dalam satu viewport | Kartu `futsal` → `/images/venues/hero-futsal.jpg`; hero tetap `futsal.jpg`; kartu `object-[center_62%]` |
| Separator `·` trust bar menggantung di ujung baris pada 390 | Glyph dihapus; trust bar `flex-wrap gap-x-6` (sesuai `gumroad-01.png`) |
| `alt` gambar kartu mengulang nama venue di accessible name | `alt=""` (dekoratif) |

### 2.3 Copy pass (skill `clarify`)

Aturan: satu istilah untuk satu konsep, label = verb + objek, jangan klaim hal yang tidak dijamin sistem.

| Sebelum | Sesudah | Alasan |
| --- | --- | --- |
| Cari tempat (nav, footer, CTA) | **Cari venue** | "tempat" ambigu (alamat vs venue) |
| Konfirmasi instan | **Tiket digital** | `instant_book` boolean **per space**, tidak bisa diklaim platform-wide |
| …konfirmasi langsung setelah pembayaran | …**tiket terbit setelah pembayaran berhasil** | PRD: payment sukses → confirmed → tiket |
| Pilih tempat dan waktu | Pilih venue, tanggal, dan jam | verb + objek konkret |
| Bayar dengan harga yang terlihat | Lihat total sebelum bayar | menyebut hal yang dicek user |
| …dijumlahkan di halaman **checkout** | …di halaman **pembayaran** | jargon Inggris di UI Indonesia |
| Datang sesuai jadwal | Tunjukkan tiket saat tiba | hasil yang bisa dieksekusi |
| Tiga langkah, tanpa telepon bolak-balik | Tiga langkah, tanpa menunggu balasan | tidak mengasumsikan kanal telepon |
| CTA owner: …Setiap venue diperiksa sebelum tayang | dihapus | sudah dikatakan di deskripsi katalog |
| Empty state 2 kalimat | "Belum ada venue terverifikasi. Cek lagi nanti." | kalimat kedua mengulang yang pertama |
| "…tarif per jam" | "…harga per jam" | konsistensi istilah |

Klaim yang **dibatalkan sendiri** sebelum diterapkan: "jam yang terisi tidak muncul" — dokumen tidak pernah menyatakan slot terisi disembunyikan (bisa jadi ditampilkan nonaktif).

### 2.4 Stage 2 Browse `/spaces`

Komposisi mengikuti layar shop DOKO (`gumroad-02/03.png`):

- **Header:** eyebrow "Katalog venue", H1 "Cari venue.", deskripsi di kanan pada `lg`, ditutup hairline tipis. Padding `pt-8 md:pt-12 lg:pt-14`.
- **Toolbar dua tier** (`space-toolbar.tsx`):
  - Tier 1: 10 chip kategori (`Semua` + kategori), `aria-pressed`, chip aktif `bg-ink text-cream`, `gap-2 lg:gap-1.5`, `px-3`.
  - Tier 2 (`justify-between`): jumlah hasil `role="status"` di tepi kiri; `Urutkan` (select) + `Filter (n)` di kanan.
- **Filter:** tombol `Filter (n)` membuka panel Radix Collapsible di `lg` berisi form yang sama (`SpaceFilterForm`); di bawah `lg` membuka sheet bawah (Radix Dialog) dengan field satu kolom dan baris aksi `sticky bottom-0 bg-cream pt-4 pb-6` + `scroll-pb-24`.
- **Kartu** (`space-card.tsx`): gambar `categoryImage()` (`alt=""`), tag kategori, nama, venue + kota, kapasitas, `Price` + "/ jam". Badge "Pesan langsung" **dihapus**.
- **Empty state:** dua varian singkat (dengan filter → "Tidak ada yang cocok dengan filter ini." + `Hapus filter`; tanpa filter → "Belum ada lapangan atau ruang yang tayang.").
- **Pagination:** reuse `Pagination`, backend `withQueryString`.
- **Sort:** `sort=price_asc|price_desc` (default `price_asc`), prop `sort`, select di toolbar. `price_asc` tidak dikirim ke URL agar URL default bersih.
- **A11y:** H1 tunggal, `h2` sr-only "Hasil pencarian" sebelum grid, 20 tab stop dengan ring `solid 2px` cobalt, target ≥44px.

Satu baris toolbar tidak mungkin: 10 chip butuh 879px, klaster kanan ±340px, konten 1312px di 1440. Karena itu dua tier, dengan jumlah hasil dipindah ke kiri (bukan melayang di tengah).

### 2.5 Self-review `better-interface` + 6 domain skill

Dijalankan setelah rekomposisi: `better-accessibility`, `better-layout`, `better-writing`, `better-typography`, `better-colors`, `better-ui`.

Temuan yang diperbaiki:

| Sev | Lokasi | Perbaikan |
| --- | --- | --- |
| MEDIUM | `space-card.tsx` | Badge "Pesan langsung" dihapus (`instant_book` tidak didefinisikan PRD; glossary melarang klaim tak terjamin) |
| MEDIUM | `spaces/index.tsx` | H1 + `<title>` jadi "Cari venue" agar konsisten dengan nav |
| LOW | `spaces/index.tsx` | `h2` sr-only "Hasil pencarian" memperbaiki lompatan h1 → h3 |

Kontras terukur lolos AA: stone/cream 5,71:1, ink/orange 4,95:1, cream/cobalt 5,29:1, ink/cream 12,76:1.

### 2.6 Konfigurasi model dikembalikan

- `~/.config/opencode/oh-my-opencode-slim.json` dipulihkan dari `…bak-before-deepseek-free-20261105-034835`.
- State deepseek-free disimpan ke `…bak-before-restore-20261105-111655` (bolak-balik tetap mungkin).
- Semua ID model diverifikasi ada di katalog provider `opencode-go`; JSON valid.
- **Perlu restart OpenCode** agar rantai model baru terbaca.

| Agent | Rantai model |
| --- | --- |
| orchestrator | `opencode-go/deepseek-v4.1-flash#high` → `mimo-v2.6-flash` → `beam-free#high` |
| oracle | `claude-haiku-5-5#xhigh` → `minimax-m3.1-flash#xhigh` |
| explorer | `glm-5.3-flash#high` → `mimo-v2.6-flash` |
| designer | `claude-haiku-5-5#xhigh` → `gpt-6-luna#high` |
| fixer | `mimo-v2.6-flash` → `deepseek-v4.1-flash#high` |
| observer | `nemotron-3.5-super-vl-free` → `gpt-6-luna#high` |

### 2.7 Skill UI dipasang eksplisit

- Skill `clarify` (dari `ui-skills.com/skills/pbakaus/clarify`, sumber asli `pbakaus/impeccable`) dibuat di `~/.config/opencode/skills/clarify/SKILL.md` dan ditambahkan ke daftar skill `designer` di kedua preset (`openai`, `opencode-go`).
- `AGENTS.md` mendapat bagian **Skill UI & Copywriting**: daftar `skills` di config hanya izin; agent wajib memuat skill relevan lewat tool `skill` sebelum kerja UI, dan `clarify` → `better-writing` untuk copy.
- `~/.config/opencode/oh-my-opencode-slim/designer_append.md` tidak terpakai: plugin tidak punya mekanisme `*_append.md`, schema agent hanya punya `prompt` (mengganti, bukan menambah). Isinya kini tercakup di `AGENTS.md`.

### 2.8 Preset `free` (2026-11-05, setelah kuota Go menipis)

Preset baru `free` ditambahkan di `~/.config/opencode/oh-my-opencode-slim.json` dan dijadikan aktif. Preset `opencode-go` dan `openai` **tidak diubah** (tetap bisa dipilih lewat `/preset`). Backup: `oh-my-opencode-slim.json.bak-before-free-preset-20261105-125637`.

| Agent | Rantai model (semua `opencode/*`, biaya 0) |
| --- | --- |
| orchestrator | `crouton-alpha-free#max` → `pulse-alpha-free#max` → `deepseek-v4.1-flash-free#high` |
| oracle | `crouton-alpha-free#max` → `flint-40b-free#max` |
| explorer | `glm-5.3-flash-free#high` → `deepseek-v4.1-flash-free#high` |
| designer | `nemotron-3.5-super-vl-free#xhigh` → `crouton-alpha-free#max` |
| fixer | `deepseek-v4.1-flash-free#high` → `mimo-v2.6-flash-free` |
| observer | `nemotron-3.5-super-vl-free#xhigh` → `flint-40b-free#max` |

Alasan pemilihan: `opencode-go/*-free` tetap dihitung ke kuota Go, jadi dihindari. `crouton-alpha-free` adalah model gratis terbaru dengan tangga varian terlengkap (minimal–max) → dipakai untuk peran yang butuh penalaran. `nemotron-3.5-super-vl-free` satu-satunya gratis yang jelas multimodal (VL), sehingga designer/observer tetap bisa membaca `design-reference/*.png`. `deepseek-v4.1-flash-free`, `glm-5.3-flash-free`, dan `mimo-v2.6-flash-free` adalah model flash untuk kerja mekanis/cepat.

Belum diverifikasi: kualitas nyata `crouton-alpha-free`/`pulse-alpha-free` (alpha, tanpa metadata benchmark di katalog) dan apakah `crouton-alpha-free` mendukung input gambar — bila tidak, designer jatuh ke rantai kedua. Perubahan butuh restart OpenCode.

---

## 3. Keputusan desain & istilah (jangan diubah tanpa alasan)

**Palet** (dari `gumroad-05.png`, blok `@theme` di `resources/css/app.css` — jangan menyentuh token auth di bawahnya):

| Token | Nilai | Pakai |
| --- | --- | --- |
| cream | `#F0EAD9` | latar halaman |
| sand | `#DDD3BC` | hover tombol light |
| ink | `#252525` | teks, border |
| stone | `#5E5A52` | teks sekunder |
| cobalt | `#2154D6` | CTA, fokus |
| cobalt-deep | `#1A44B5` | hover cobalt |
| navy | `#182136` | footer |
| orange | `#F16839` | tag kategori |
| butter | `#F3C74F` | fokus di navy/cobalt |

**Tipografi**: Bricolage Grotesque (display, `font-stretch` 80–85%), Geist (body), Geist Mono (eyebrow/meta/harga). Radius kontrol 2px, container 90rem.

**Glosarium** — kunci untuk Stage 3:

- **venue** untuk entitas yang disewa; **lapangan** (olahraga) / **ruang** (meeting) hanya saat menyebut unit.
- **tiket** (bukan "konfirmasi") untuk dokumen hasil booking.
- **tambahan** (bukan "add-on").
- **harga** (bukan "tarif").
- Jangan klaim "konfirmasi instan" / "slot terisi disembunyikan" — tidak dijamin sistem.

**Keputusan lain yang sudah diambil:**

- Chip kategori hanya ada di toolbar, tidak diulang di sheet (menghindari dua sumber kebenaran yang saling menimpa).
- Sort tidak ditampilkan sebagai kalimat "diurutkan dari harga termurah" karena kini bisa diubah user.
- `SpaceListItem` memakai `base_price: number` (controller meng-cast `int`) dan `capacity: number | null`.

---

## 4. Bukti verifikasi

Verifikasi terakhir (setelah rekomposisi toolbar + fix `justify-between`):

```
npm run check        → 93 file format, 87 lint, 0 warning
npm run types:check  → tsc --noEmit, exit 0
npm run build        → ✓ built in 1.84s
composer ci:check    → hijau (81 test / 415 assertion)
```

QA browser (Chromium, `:8000`):

- 320 / 390 / 768 / 1024 / 1280 / 1440: `scrollWidth === clientWidth`, 0 console error, semua gambar termuat.
- Toolbar: 320 chip 4 baris lalu Filter pindah baris; 390 chip 3 baris, sort + Filter satu baris; 1024–1440 chip satu baris, jumlah hasil di tepi kiri (x=64), aksi rata kanan.
- Sort: `?sort=price_desc` → kartu pertama Rp 250.000. Terapkan filter mempertahankan sort; reset mempertahankan sort; klik chip mempertahankan sort dan filter lain.
- Panel: trigger → Tab masuk ke field "Kota" dengan ring 2px. Sheet 390: tinggi 698 = klien, aksi terlihat tanpa scroll, Escape menutup dan mengembalikan fokus, chip terakhir tetap bisa diklik.
- Filter kota → `?city=Samarinda` 2 hasil; `Hapus filter` kembali ke 8 hasil.
- Anchor `/#kategori` tidak tertutup header sticky di 390 dan 1024.

Screenshot bukti (yang masih valid):

- `visual-qa/stage1-final-{390,768,1440}.png`, `stage1-final-390-trust.png`
- `visual-qa/stage1-copy2-{390,1440}.png`
- `visual-qa/stage1-verify-{1024,dark-1440,focus-1440,390-menu}.png`
- `visual-qa/stage2-browse-{390,768,1440}.png`, `stage2-toolbar-{1024,1280}.png`, `stage2-toolbar-panel-1024.png`, `stage2-filter-sheet-390.png`, `stage2-empty-390.png`

**Abaikan** `visual-qa/public-*.png`, `ui-kit-*.png`, `stage1-spaces-current.png` — dari UI lama / halaman kosong.

---

## 5. File yang dibuat & diubah

**Dibuat**

- `resources/js/pages/public/home.tsx`
- `resources/js/pages/public/spaces/index.tsx`
- `resources/js/layouts/public-layout.tsx`
- `resources/js/components/public/`: `button`, `chip`, `container`, `input`, `mobile-menu`, `nav.ts`, `pagination`, `price`, `section-heading`, `select`, `site-footer`, `site-header`, `space-card`, `space-filters`, `space-toolbar`, `venue-card`, `wordmark`

**Diubah**

- `resources/css/app.css` — hanya blok token publik
- `resources/js/lib/catalog.ts` — fallback foto per kategori
- `resources/js/app.tsx` — mapping `PublicLayout`
- `resources/js/types/catalog.ts` — `SpaceListItem`, `SpacesIndexPageProps`, `SpaceSort`
- `vite.config.ts` — abaikan `.backup/**` di lint/fmt
- `implementation-plan.md` §18, `PROGRESS.md`, `docs/ui-kit-plan.md`, dokumen ini

**Backend (katalog browse):**

- `SpaceBrowseController`: normalisasi payload (`base_price` → `int`, `category_label`), parameter `sort=price_asc|price_desc`, prop `sort`.
- `tests/Feature/Catalog/PublicCatalogTest.php`: `test_browse_sorts_by_price`.
- Tidak ada perubahan route.

---

## 6. Rollback

Repo ini **bukan git repo**; hanya ada dua jalur rollback:

1. `.backup/ui-1.3-home-v1-2026-11-05/` — UI publik versi lama (`catalog.ts`, komponen, layout, pages).
2. `~/.config/opencode/oh-my-opencode-slim.json.bak-*` — snapshot konfigurasi model (timestamped).

Screenshot QA lama tidak menggantikan rollback kode.

---

## 7. Lanjutan

1. **Stage 2 sudah disetujui user 2026-11-05.** Status `REVIEW` → `DONE` sudah dicatat di §18, `PROGRESS.md`, dan dokumen ini.
2. **Stage 3 belum dimulai** (`/v/{venue}` + `/spaces/{space}`): hero cover, info venue, daftar space, detail space, amenitas, tambahan, jam operasional, panel ringkasan — **pakai ulang** token & komponen publik yang sudah ada, jangan bikin paralel. Tanpa CTA booking (slot grid menyusul di 1.5).
3. Pertahankan glosarium §3 saat menulis string Stage 3.
4. Verifikasi 390/768/1440 di browser + `npm run check`, `npm run types:check`, `npm run build`, `composer ci:check`.
5. Update `implementation-plan.md` §18 + `PROGRESS.md` dengan bukti; **berhenti** untuk konfirmasi user sebelum tahap berikutnya.
6. Terbuka (LOW, tidak memblokir): filter tidak valid (mis. `1e3`) ditolak server lalu redirect balik tanpa pesan; input tanggal menerima tanggal lampau; pagination belum teruji visual karena seed 8 space < 12/halaman; 3 kartu badminton memakai foto identik karena `cover_photo_path` null dan hanya ada satu aset per kategori.
7. Opsional bila user meminta: toolbar satu baris dengan memindahkan jumlah hasil ke dekat H1.

---

## 8. Batasan yang masih berlaku

- Tanpa dependency baru; lane UI tidak mengubah backend/route/test.
- Jangan menyentuh auth, dashboard, settings, `.backup/`.
- Jangan menambah fitur Phase 2.
- `cover_photo_path` masih `null` di seed → semua foto memakai fallback per kategori.
- `app.blade.php` menambahkan `.dark` dari preferensi sistem; public layout memaku `colorScheme: 'light'` agar halaman publik tetap cream.
- Dev server: `php artisan serve` di `:8000`, Vite di `:5173`.
