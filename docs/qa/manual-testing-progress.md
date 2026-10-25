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
- Setelah penerapan URL ULID, owner pertama dan owner kedua berhasil mengakses halaman edit milik masing-masing; akses silang ke usaha maupun venue owner lain menghasilkan 404 dari kedua arah.
- Akun demo owner dapat melihat daftar usaha sendiri saja.
- Profil publik `studio-demo-adatempat` dan `studio-demo-owner-dua` tampil tanpa login beserta venue yang dipublikasikan.
- Akun customer menerima 403 saat mencoba membuka `/owner/businesses`.
- Pencarian dengan kata kunci `Ruang Meeting Demo` menampilkan hasil yang sesuai.
- Filter kategori Meeting menampilkan dua venue Meeting demo yang dipublikasikan.
- Filter lokasi `Banjarbaru` menampilkan venue dari kedua usaha demo.
- Kombinasi kata kunci `Ruang Meeting`, lokasi `Banjarbaru`, dan kategori Meeting menampilkan hasil yang sesuai.
- Pencarian dengan kata kunci yang tidak cocok (`VenueTidakAda123`) menampilkan keadaan tanpa hasil dan opsi untuk membersihkan pencarian.
- Pencarian berdasarkan nama usaha `Studio Demo Pemilik Dua` menampilkan venue `Ruang Meeting Pemilik Dua`.
- Pemeriksaan responsivitas halaman utama pada viewport 390 px lulus: layout rapi, kolom pencarian tidak terpotong, kartu venue sesuai lebar layar, dan tidak ada scroll horizontal yang tidak semestinya.
- Pemeriksaan responsivitas khusus halaman Kelola Usaha pada viewport 390 px ditunda atas keputusan pengguna; UI dinilai aman untuk sementara dan audit UI lanjutan dilewati.
- Akses ke URL edit usaha dengan ID numerik (`/owner/businesses/1/edit`) ditolak dengan 404; route management tidak menerima numeric ID.
- Akses ke URL edit venue dengan ID numerik (`/owner/businesses/1/venues/1/edit`) ditolak dengan 404; route management venue tidak menerima numeric ID.
- Registrasi customer baru setelah perubahan URL verifikasi email berhasil dan diarahkan ke halaman verifikasi.
- Tautan verifikasi untuk `uji-verifikasi-ulid@example.com` berhasil digunakan; akun berhasil diverifikasi setelah perubahan URL verifikasi berbasis ULID.
- Tautan verifikasi dengan parameter `signature` yang diubah ditolak oleh aplikasi; uji manipulasi tautan verifikasi lulus.
- Tautan verifikasi milik `uji-verifikasi-ulid@example.com` ditolak saat dibuka dari sesi akun owner demo yang berbeda; pengikatan tautan ke akun lulus.

## Data uji yang sudah dibuat secara manual

- Usaha: `Adatempat Test Venue`
- Alamat terakhir yang disimpan: `Banjarbaru, Kalimantan Selatan (Uji Edit)`
- Venue: `Ruang Meeting Adatempat 2`
- Akun customer dan dua akun owner telah dipakai untuk pengujian akses.

## Retest yang masih diperlukan

Sebelum perubahan URL berbasis ULID, pemeriksaan terakhir untuk membuka kembali URL edit venue milik owner pertama justru menghasilkan 404. Hasil ini belum memenuhi ekspektasi akses normal untuk pemilik yang benar. Setelah rute ULID dipasang, buka kembali halaman lewat navigasi daftar usaha/venue saat login sebagai owner pertama, lalu pastikan halaman edit milik sendiri terbuka. Setelah itu, ulangi pengujian akses antar-owner menggunakan URL ULID yang baru.

## Data demo untuk uji isolasi antar-owner

`DemoDataSeeder` sekarang juga perlu menyiapkan owner kedua (`demo-owner-2@adatempat.test`) dengan usaha `Studio Demo Pemilik Dua` dan venue `Ruang Meeting Pemilik Dua`. Gunakan owner pertama untuk mencoba membuka URL edit milik owner kedua, lalu ulangi sebaliknya. Masing-masing harus menerima 404 ketika mencoba mengakses record yang bukan miliknya.

## Perubahan implementasi yang sedang dikerjakan

- Menambahkan `public_id` ULID untuk usaha dan venue serta mengisi identifier untuk data lama.
- Memakai identifier publik ULID, bukan primary key numerik, pada rute pengelolaan usaha dan venue.
- Memakai `public_id` pada URL verifikasi email; URL tetap harus bertanda tangan dan terikat pada akun terautentikasi serta hash email.
- Menambahkan data demo customer, owner, usaha aktif, dan venue terpublikasi khusus environment lokal.
- Mengingatkan pengembang agar seluruh query pengelolaan tetap dibatasi melalui relasi owner yang sedang login. Identifier yang sulit ditebak bukan pengganti otorisasi.


## Progress utama pengujian manual — 20 poin

Penghitungan ini mencakup pengujian yang dilakukan sejak awal percakapan, bukan hanya pengujian setelah format progress diperkenalkan. Satu poin dapat menggabungkan beberapa pemeriksaan yang membentuk satu skenario fungsional.

1. **Lulus** — Validasi registrasi: field wajib, format email, konfirmasi password, dan persetujuan Terms/Privacy.
2. **Lulus** — Registrasi customer berhasil dan verifikasi email reguler berhasil.
3. **Lulus** — Login/logout: password salah ditolak, kredensial benar diterima.
4. **Lulus** — Registrasi dan verifikasi email owner berhasil.
5. **Lulus** — Halaman Kelola Usaha dapat dibuka setelah perbaikan sintaks Livewire Title.
6. **Lulus** — Pembuatan usaha: validasi field wajib, usaha tersimpan sebagai Draft dan muncul di daftar.
7. **Lulus** — Edit usaha berhasil; perubahan alamat bertahan setelah reload.
8. **Lulus** — Halaman Kelola Venue dan formulir venue terbuka; kategori tersedia.
9. **Lulus** — Validasi nama/kategori venue dan pembuatan venue Draft.
10. **Lulus** — Edit venue berhasil; nama/deskripsi tersimpan setelah reload dan pencarian daftar venue menemukan hasil sekali.
11. **Lulus** — Venue Draft tidak muncul di katalog publik.
12. **Lulus** — Pengunjung tanpa login diarahkan ke login; akun customer ditolak dari area owner dengan 403.
13. **Lulus** — Isolasi usaha: owner hanya melihat usahanya; akses langsung ke usaha owner lain menghasilkan 404 dari kedua arah.
14. **Lulus** — Isolasi venue: akses langsung ke venue owner lain menghasilkan 404 dari kedua arah.
15. **Lulus** — URL pengelolaan usaha/venue milik sendiri menggunakan ULID dan dapat dibuka; URL numerik ditolak dengan 404.
16. **Lulus** — Profil publik kedua usaha demo dan venue terpublikasinya tampil tanpa login.
17. **Lulus** — Pencarian kata kunci venue, pencarian berdasarkan nama usaha, dan keadaan tanpa hasil.
18. **Lulus** — Filter kategori, lokasi, kombinasi beberapa filter, serta reset pencarian.
19. **Lulus** — Responsivitas halaman utama pada viewport 390 px; tidak ada pemotongan elemen atau scroll horizontal yang tidak semestinya. Audit responsivitas halaman owner ditunda atas keputusan pengguna.
20. **Lulus** — Keamanan verifikasi email: URL ULID berhasil, signature yang dimanipulasi ditolak, tautan ditolak dari sesi akun lain, dan owner yang belum memverifikasi email diarahkan kembali ke halaman verifikasi saat membuka area owner.

### Posisi saat ini

**20/20 poin lulus. Rangkaian pengujian manual ini selesai.** Uji terakhir mengonfirmasi owner yang belum memverifikasi email tidak dapat membuka `/owner/businesses` dan diarahkan kembali ke halaman verifikasi email.

Setiap instruksi pengujian berikutnya harus menggunakan penanda `[n/20 testing]`. Saya akan menaikkan nomor setelah hasil langkah tersebut dikonfirmasi pengguna; pengujian yang sudah lulus tidak akan dihitung ulang.


## Catatan setelah rangkaian 20 poin

- Pengujian tambahan halaman pemulihan password (`/forgot-password`) **dilewati atas permintaan pengguna**. Kode halaman memang tersedia di repository, tetapi belum ada hasil pengujian manual; status ini tidak mengubah hasil 20/20 poin sebelumnya.
- Pengujian MFA Super Admin (`/admin/mfa/setup`) juga **dilewati atas permintaan pengguna**. Implementasi halaman tersedia di repository, tetapi hasil pengujian manualnya belum diverifikasi.
- Sebelum memberi instruksi uji manual baru, periksa terlebih dahulu `main` di GitHub untuk memastikan fitur dan kontrolnya benar-benar sudah diimplementasikan. Jangan membuat skenario pengujian untuk fitur yang belum ada.
- Setelah pemeriksaan fitur yang sudah ada selesai atau dilewati berdasarkan keputusan pengguna, lanjutkan pengembangan dengan mencocokkan implementasi di repository terhadap PRD Adatempat v1.5 dan roadmap implementasinya.
