# UI Kit Plan — AdaTempat

**Sumber tunggal:** `design-reference/gumroad-01.png` … `gumroad-06.png` (DOKO Specialty Coffee UI Kit).
**Prinsip:** komposisi, grid, hierarki tipografi, spacing, dan detail visual mengikuti DOKO; konten, copy, foto, dan brand diganti ke AdaTempat. Tidak menyalin logo, teks, atau aset berbayar DOKO.

## Token dari referensi

| Token | Nilai |
| --- | --- |
| Background cream | `#F0EAD9` |
| Ink | `#1D1D1F` |
| Cobalt (accent) | `#2154D6` |
| Navy (section gelap) | `#2F3E66` |
| Brown (foto/aksen) | `#876659` |
| Sand / border | `#C9BAA8`, `#DDD3BC` |
| Display font | Bricolage Grotesque |
| Body font | Geist |
| Mono / angka | Geist Mono |

Catatan: warna di atas diambil dari piksel gambar, bukan estimasi. Verifikasi ulang per section saat implementasi.

## Prinsip implementasi

1. Satu halaman per stage; berhenti untuk review sebelum lanjut.
2. Setiap halaman diverifikasi di 390, 768, dan 1440 px.
3. Tidak ada data mock di komponen final; gunakan props backend yang sudah ada. Halaman yang backend-nya belum ada memakai data contoh terisolasi.
4. Foto venue memakai aset lokal (bukan hotlink) dengan fallback art per kategori. Seed `cover_photo_path` masih null.
5. Copy Indonesia; harga `Rp`; tanggal/jam format Indonesia.
6. Aksesibilitas: target sentuh ≥44px, focus ring, kontras AA, `prefers-reduced-motion`.

## Stage

### Stage 1 — Fondasi + Home
- Token warna/font, public layout (header, nav, footer), komponen dasar: button, chip, input, select, card, price, section heading, pagination.
- Home: hero + search, kategori, venue unggulan, cara kerja, CTA owner, footer.
- Data: `HomeController` yang sudah ada.
- Output review: screenshot 390/768/1440.

### Stage 2 — Browse
- `/spaces`: filter bar (kota, kategori, tanggal, harga), grid kartu space, pagination, empty state, mobile filter sheet.

### Stage 3 — Venue + Space
- `/v/{venue}`: hero cover, info venue, daftar space.
- `/spaces/{space}`: detail space, amenities, add-on, jam operasional, sticky summary (slot grid menyusul di 1.5).

### Stage 4 — Booking flow
- `/checkout`, `/payment/{code}`, `/tickets/{code}`: form customer, ringkasan, countdown, QRIS, tiket + QR.

### Stage 5 — Customer
- `/bookings`: upcoming/past, status refund, aksi batal.

### Stage 6 — Owner
- `/owner/venues`, `/owner/venues/{venueId}/spaces`, schedule editor, `/gate/scanner`.

### Stage 7 — Superadmin
- `/superadmin/venues`, `/superadmin/refunds`, `/superadmin/audit`.

### Stage 8 — Auth
- `/login`, `/register`, `/register/business` diselaraskan dengan UI kit tanpa mengubah alur.

## Blueprint DOKO (dari 6 gambar)

### Home (gumroad-01/02)
- Navbar: wordmark kiri; 4 link kanan; kontrol akun/keranjang paling kanan.
- Hero 2 kolom: teks kiri (badge uppercase, headline 2 baris, paragraf, 2 tombol), foto kanan; tanpa overlay gelap.
- Info bar tipis di bawah hero: 3 item dipisah titik.
- Section editorial: label uppercase, heading besar, paragraf, foto.
- Grid kartu 3 kolom; kartu: gambar, nama, meta, harga; border tipis, shadow minimal.
- Mobile: teks stack di atas foto, tombol penuh lebar, menu overlay dari kanan.

### Shop / Browse (gumroad-02/03)
- Header halaman: label + heading + deskripsi + sort.
- Chips filter (terpilih = hitam fill, lainnya outline).
- Grid 3 kolom desktop / 1 kolom mobile.
- Kartu dengan badge opsional, gambar, nama, meta, harga.

### Detail (gumroad-03)
- Gambar utama + thumbnail horizontal.
- Label kategori, heading, harga, deskripsi.
- Sticky bar bawah: pilihan, quantity, subtotal, CTA penuh lebar.

## Stage 1 — komposisi Home

1. Header publik (sticky, cream, border bawah tipis).
2. Hero 2 kolom: badge, headline 2 baris, paragraf, CTA cobalt + outline, foto portrait di kanan.
3. Info bar mono: `Venue terverifikasi · Harga transparan · Tiket digital` (pemisah berupa jarak, bukan glyph — mengikuti `gumroad-01.png`; "Konfirmasi instan" tidak dipakai karena `instant_book` boolean per space).
4. Chips kategori + grid 3 venue unggulan.
5. Section "Cara kerja" split: teks 3 langkah + foto.
6. CTA owner: band cobalt, heading, paragraf, tombol putih/outline.
7. Footer gelap navy.

Token dan font mengikuti tabel di atas. Foto: `public/images/venues/` (lokal), fallback per kategori.

## Mapping referensi ke halaman

| Referensi DOKO | Halaman AdaTempat |
| --- | --- |
| Home (gumroad-01/02 kiri) | `/` |
| Shop / grid (gumroad-02 kanan) | `/spaces` |
| Product Detail (gumroad-03 tengah) | `/spaces/{space}` |
| Our Story (gumroad-06) | `/v/{venue}` |
| Coffee Menu (gumroad-03 kanan) | daftar add-on / jam operasional |
| Visit Us (gumroad-03 kanan bawah) | footer / info venue |
| Cart (gumroad-03/05) | `/checkout` |
| Mobile nav (gumroad-03) | semua halaman publik |

Halaman yang belum punya referensi langsung (checkout, tiket, owner, superadmin) mengikuti token dan pola komponen DOKO yang sama.
