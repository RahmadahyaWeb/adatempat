# Progres Pengujian Manual Adatempat

Tanggal pencatatan: 26 Oktober 2026

Dokumen ini menyimpan hasil uji manual lokal sebelum pengembangan dilanjutkan. Tidak ada kredensial akun uji pribadi yang dicatat di sini.

## Hasil pengujian yang lulus

- Registrasi menampilkan validasi saat formulir wajib kosong.
- Registrasi menolak format email yang tidak valid.
- Registrasi menolak konfirmasi password yang tidak cocok.
- Persetujuan Terms dan Privacy diwajibkan sebelum registrasi.
- Setelah dokumen legal di-seed untuk preview lokal, registrasi customer berhasil dan akun dapat diverifikasi lewat tautan di log lokal.
- Logout berhasil; login dengan password salah ditolak dan login dengan kredensial benar berhasil.
- Registrasi serta verifikasi akun owner berhasil.
- Pengunjung tanpa login diarahkan dari halaman owner ke halaman login.
- Akun customer menerima 403 saat mengakses halaman pengelolaan usaha owner.
- Owner kedua hanya melihat usaha miliknya sendiri; usaha owner pertama tidak muncul.
- Owner kedua mendapat 404 saat mengakses URL langsung untuk mengedit usaha milik owner pertama.
- Owner kedua mendapat 404 saat mengakses URL langsung untuk mengedit venue milik owner pertama.
- Usaha dapat dibuat sebagai Draft, terlihat di daftar owner, dan data alamat tetap tersimpan setelah halaman dimuat ulang.
- Form venue menampilkan validasi untuk nama dan kategori wajib.
- Venue kategori Meeting dapat dibuat sebagai Draft; nama, kategori, dan deskripsi bertahan setelah halaman dimuat ulang.
- Perubahan nama dan deskripsi venue berhasil disimpan.
- Pencarian venue menemukan hasil yang sesuai tepat satu kali.
- Venue Draft tidak muncul di katalog publik.

## Data uji yang sudah dibuat secara manual

- Usaha: `Adatempat Test Venue`
- Alamat terakhir yang disimpan: `Banjarbaru, Kalimantan Selatan (Uji Edit)`
- Venue: `Ruang Meeting Adatempat 2`
- Akun customer dan dua akun owner telah dipakai untuk pengujian akses.

## Retest yang masih diperlukan

Sebelum perubahan URL berbasis ULID, pemeriksaan terakhir untuk membuka kembali URL edit venue milik owner pertama justru menghasilkan 404. Hasil ini belum memenuhi ekspektasi akses normal untuk pemilik yang benar. Setelah rute ULID dipasang, buka kembali halaman lewat navigasi daftar usaha/venue saat login sebagai owner pertama, lalu pastikan halaman edit milik sendiri terbuka. Setelah itu, ulangi pengujian akses antar-owner menggunakan URL ULID yang baru.

## Perubahan implementasi yang sedang dikerjakan

- Menambahkan `public_id` ULID untuk usaha dan venue serta mengisi identifier untuk data lama.
- Memakai identifier publik ULID, bukan primary key numerik, pada rute pengelolaan usaha dan venue.
- Memakai `public_id` pada URL verifikasi email; URL tetap harus bertanda tangan dan terikat pada akun terautentikasi serta hash email.
- Menambahkan data demo customer, owner, usaha aktif, dan venue terpublikasi khusus environment lokal.
- Mengingatkan pengembang agar seluruh query pengelolaan tetap dibatasi melalui relasi owner yang sedang login. Identifier yang sulit ditebak bukan pengganti otorisasi.
