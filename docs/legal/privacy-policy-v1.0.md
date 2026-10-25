# KEBIJAKAN PRIVASI PLATFORM ADATEMPAT
**Versi:** 1.0 — DRAFT UNTUK REVIEW  
**Status:** Belum disahkan untuk penggunaan produksi  
**Tanggal berlaku:** Diisi setelah dokumen disetujui dan dipublikasikan secara resmi  
**Pengendali Data:** Identitas badan hukum, alamat resmi, dan kontak privasi wajib diverifikasi sebelum publikasi produksi. Konfigurasi awal PRD menyebut “PT Adatempat Digital Indonesia”, alamat “Indonesia (Diperbarui melalui Pengaturan Platform)”, dan email support@adatempat.com; nilai tersebut belum dianggap sebagai identitas final.

> **Pemberitahuan penting:** Dokumen ini disusun berdasarkan PRD Adatempat v1.5 sebagai draft awal dan preview untuk pengujian lokal. Dokumen ini belum merupakan nasihat hukum atau dokumen legal final. Jangan memublikasikannya ke produksi sebelum identitas Pengendali Data, penyedia pihak ketiga, fitur yang benar-benar aktif, jadwal retensi, dan ketentuan hukumnya ditinjau serta disahkan.

## 1. Tujuan dan Prinsip

Kebijakan ini menjelaskan bagaimana Adatempat mengumpulkan, menggunakan, menyimpan, mengungkapkan, melindungi, dan menghapus atau menganonimkan data pribadi ketika menjalankan Platform. Adatempat menerapkan prinsip minimalisasi: data diproses sejauh diperlukan untuk tujuan yang dijelaskan, sesuai dasar pemrosesan yang berlaku, dan tidak boleh digunakan dengan cara yang tidak selaras tanpa dasar yang sah.

Dokumen ini mencakup fungsi yang saat ini tersedia serta fungsi yang direncanakan dalam PRD. Informasi mengenai booking, chat, ulasan, pembayaran, langganan, payout, guest session, lokasi perangkat, dan iklan hanya berlaku apabila fungsi terkait telah diluncurkan dan benar-benar memproses data tersebut. Penyebutan suatu kategori tidak berarti fitur itu sudah aktif.

## 2. Identitas Pengendali Data dan Kontak

Pengendali Data adalah penyelenggara yang menentukan tujuan dan cara pemrosesan data pribadi melalui Adatempat. Identitas badan hukum, alamat, dan kontak privasi resmi harus dilengkapi dan diverifikasi sebelum publikasi produksi. Konfigurasi awal PRD menyebut:
- Nama awal: PT Adatempat Digital Indonesia — **belum dikonfirmasi sebagai nama badan hukum final**.
- Alamat: Indonesia (Diperbarui melalui Pengaturan Platform) — **placeholder, wajib dilengkapi**.
- Kontak awal: support@adatempat.com — **wajib diverifikasi sebelum digunakan publik**.

Permintaan terkait data pribadi dapat diajukan melalui kontak resmi setelah informasi final tersebut tersedia. Jangan mengirim password, kode verifikasi, token reset, atau data keuangan sensitif melalui email dukungan.

## 3. Data yang Dapat Diproses

Jenis data bergantung pada fitur yang digunakan.

### 3.1 Akun dan autentikasi
Untuk registrasi dan akses akun, Platform saat ini memproses nama, alamat email, peran akun (pelanggan atau owner), kredensial autentikasi dalam bentuk yang diamankan sistem, status akun, status verifikasi email, serta informasi teknis yang diperlukan untuk sesi, login, logout, dan pemulihan password. Password tidak dimaksudkan untuk disimpan sebagai teks biasa.

Platform juga dapat mencatat waktu serta percobaan autentikasi dan membatasi permintaan berulang untuk menjaga keamanan. Log yang dihasilkan harus dibatasi pada informasi yang diperlukan untuk keamanan dan diagnosis.

### 3.2 Persetujuan dokumen legal
Ketika Pengguna menyetujui Terms and Conditions dan Kebijakan Privasi, Platform dapat mencatat ID dokumen, versi, waktu persetujuan, konteks persetujuan, serta metadata seperti alamat IP dan user-agent sesuai implementasi. Data ini digunakan untuk membuktikan versi yang diterima dan menangani pertanyaan atau sengketa terkait persetujuan.

### 3.3 Data usaha dan venue
Ketika Owner menggunakan pengelolaan usaha dan venue, Platform memproses nama usaha, slug/identitas listing, alamat yang diberikan, status usaha, nama venue, kategori, deskripsi, status venue, dan waktu perubahan/publikasi sesuai fitur yang tersedia. Data yang dipilih untuk ditampilkan pada listing publik dapat dilihat oleh pengunjung.

Fitur foto, galeri, koordinat, fasilitas, custom fields, harga, jadwal, ketersediaan, dan dokumen verifikasi hanya akan diproses ketika fitur tersebut tersedia dan Owner memasukkan data terkait.

### 3.4 Pencarian dan penggunaan situs
Saat Pengunjung menggunakan pencarian, kata kunci, kategori, dan teks lokasi yang dimasukkan dapat diproses untuk mengembalikan hasil. Jangan mengasumsikan semua input pencarian disimpan permanen. Cookie, session, atau local storage dapat digunakan sejauh diperlukan untuk autentikasi, keamanan, preferensi, dan fitur yang benar-benar aktif.

Informasi teknis seperti alamat IP, user-agent, waktu permintaan, dan catatan error dapat diproses secara terbatas untuk keamanan, pencegahan penyalahgunaan, pemecahan masalah, dan keandalan sistem.

### 3.5 Fitur transaksi dan komunikasi yang direncanakan
Apabila fiturnya telah diluncurkan, kategori berikut dapat diproses sesuai kebutuhan:
- **Booking:** identitas Pelanggan, venue, jadwal, detail harga, status transaksi, pembatalan, dan sengketa.
- **Pembayaran, refund, invoice, dan payout:** pengenal transaksi, nominal, status, bukti yang diperlukan, rekening penerima Owner, dan catatan akuntansi. Data rekening payout harus ditangani dengan kontrol akses dan perlindungan sesuai desain yang telah disahkan. Adatempat tidak bermaksud menyimpan kredensial rahasia instrumen pembayaran Pelanggan.
- **Chat pra-booking dan dukungan:** isi pesan, identitas partisipan, metadata pengiriman, dan laporan moderasi sejauh diperlukan.
- **Ulasan dan moderasi:** isi ulasan, kaitan dengan booking yang selesai, tanggapan Owner, laporan, dan keputusan moderasi.
- **Langganan, promosi, dan iklan:** paket, periode langganan, status pembayaran, konfigurasi promosi, periode iklan, serta metrik impresi/klik yang diterapkan.
- **Sesi tamu/favorit dan lokasi:** nama panggilan, token sesi yang dilindungi, preferensi/favorit lokal, serta koordinat lokasi perangkat hanya jika izin yang sesuai diberikan. Pengguna harus tetap dapat memasukkan lokasi secara manual bila pencarian lokasi tersedia.

Kategori yang belum dipakai oleh implementasi aktif tidak boleh dianggap telah dikumpulkan atau dibagikan.

## 4. Tujuan dan Dasar Pemrosesan

Data hanya dapat diproses untuk tujuan yang sesuai, termasuk:
1. membuat dan mengelola akun, mengautentikasi Pengguna, memverifikasi email, serta memulihkan akses;
2. menyimpan bukti persetujuan Terms dan Kebijakan Privasi;
3. menampilkan serta mengelola informasi usaha dan venue;
4. menanggapi pencarian dan permintaan yang dikirim melalui fitur Platform;
5. melindungi keamanan, mencegah spam/penipuan, membatasi penyalahgunaan, dan menjaga keandalan layanan;
6. menjalankan transaksi, pembayaran, refund, payout, chat, ulasan, iklan, dan langganan jika fitur terkait sudah aktif;
7. memenuhi kewajiban hukum, menangani keluhan, dan menyimpan catatan yang diperlukan untuk mempertahankan atau menegakkan hak yang sah.

Dasar pemrosesan yang relevan ditentukan sesuai konteks dan hukum yang berlaku, misalnya persetujuan yang sah, pelaksanaan perjanjian atau langkah sebelum perjanjian atas permintaan Pengguna, kewajiban hukum, dan kepentingan sah yang telah dinilai secara proporsional. Persetujuan untuk fitur opsional—termasuk akses lokasi perangkat—tidak boleh diasumsikan hanya karena Pengguna menggunakan Platform. Rincian dasar per kategori perlu dikonfirmasi dalam review hukum dan desain final.

## 5. Data Wajib dan Opsional

Data yang ditandai wajib pada formulir diperlukan untuk menyediakan fungsi yang diminta. Jika Pengguna tidak menyediakannya, proses pendaftaran atau fungsi terkait mungkin tidak dapat diselesaikan. Data opsional hanya perlu diberikan jika Pengguna ingin memakai fitur yang memerlukannya.

Jika fitur lokasi tersedia, akses koordinat perangkat hanya dilakukan setelah izin yang relevan diberikan; pengguna harus dapat memilih atau memasukkan lokasi secara manual. Menolak izin lokasi tidak boleh dianggap sebagai persetujuan atau alasan untuk mengumpulkan koordinat secara diam-diam.

## 6. Pengungkapan Data dan Pihak Ketiga

Adatempat tidak menjual data pribadi hanya untuk tujuan menjual data tersebut. Data dapat dibagikan secara terbatas jika diperlukan dan memiliki dasar yang sesuai, antara lain:
- kepada Owner atas informasi Pelanggan yang benar-benar diperlukan untuk melaksanakan booking, apabila fitur booking tersedia;
- kepada penyedia hosting/infrastruktur, layanan email atau notifikasi, payment gateway, pemroses pembayaran, dan penyedia teknis lain sesuai fungsi yang diaktifkan;
- kepada penyedia pengamanan, pencatatan log, atau dukungan teknis sejauh diperlukan untuk operasi dan keamanan;
- kepada penasihat profesional, auditor, atau pihak yang berwenang jika diperlukan secara sah; dan
- jika pengungkapan diwajibkan oleh hukum atau diperlukan untuk menangani penipuan, keamanan, sengketa, atau perlindungan hak.

Penyedia pihak ketiga yang benar-benar digunakan, perannya, dan lokasi pemrosesan datanya harus didokumentasikan sebelum peluncuran. Adatempat tidak akan menyatakan bahwa data tidak pernah dibagikan jika penyedia layanan turut memprosesnya.

## 7. Transfer Data

Jika pemrosesan menggunakan penyedia yang menyimpan atau mengakses data dari luar Indonesia, transfer lintas negara hanya boleh dilakukan setelah dasar, persyaratan perlindungan, dan kewajiban pemberitahuan yang berlaku ditinjau. Daftar penyedia dan negara pemrosesan harus dilengkapi sebelum produksi. Dokumen ini tidak menyatakan bahwa transfer lintas negara sudah terjadi atau bahwa persyaratan tertentu telah terpenuhi.

## 8. Retensi, Penghapusan, dan Anonimisasi

Data tidak disimpan lebih lama dari yang diperlukan untuk tujuan yang sah, kecuali ada kewajiban hukum atau kebutuhan pembuktian yang membenarkan retensi lebih lama. Jadwal berikut berasal dari konfigurasi awal PRD, ditandai **PERLU VALIDASI HUKUM**, dan tidak boleh dianggap sebagai pendapat hukum atau kebijakan produksi yang sudah disahkan:

1. **Transaksi keuangan, invoice, refund, payout, dan buku besar:** PRD mencantumkan retensi minimal 10 tahun sejak tahun buku transaksi berakhir. Periode dan awal penghitungan harus diverifikasi terhadap kewajiban hukum serta kebijakan akuntansi sebelum produksi.
2. **Log audit dan persetujuan dokumen legal:** PRD mencantumkan retensi minimal 5 tahun untuk pembuktian kepatuhan dan sengketa. Konfirmasi akhir tetap diperlukan.
3. **Sesi Pengunjung:** apabila fitur guest session aktif, PRD menetapkan token kedaluwarsa setelah 30 hari tidak aktif dan metadata sesi yang tidak menghasilkan chat/konversi akun dibersihkan setelah 90 hari.
4. **Akun yang dinonaktifkan:** PRD menetapkan bahwa kredensial dicabut segera dan data profil pribadi non-transaksional dianonimkan setelah masa bebas sengketa 90 hari, sedangkan hubungan ke arsip transaksi dipertahankan jika diperlukan.
5. **Log teknis dan data operasional lainnya:** jangka penyimpanan harus ditentukan berdasarkan kebutuhan keamanan, diagnosis, batas akses, dan kewajiban hukum yang relevan.

Penghapusan atau penonaktifan akun tidak berarti seluruh data langsung dihapus. Data yang wajib dipertahankan untuk transaksi, persetujuan legal, audit, keamanan, sengketa, atau kewajiban hukum dapat tetap disimpan dengan akses yang dibatasi. Jika data tidak lagi diperlukan, data akan dihapus atau dianonimkan sesuai prosedur yang diterapkan.

## 9. Keamanan dan Insiden

Adatempat perlu menerapkan kontrol yang sesuai terhadap risiko, termasuk pembatasan hak akses, perlindungan kredensial, pengamanan sesi, pencatatan tindakan sensitif, backup yang terkontrol, pembaruan keamanan, serta prosedur penanganan insiden. Akses internal harus dibatasi berdasarkan kebutuhan tugas.

Tidak ada sistem yang dapat dijamin aman secara mutlak. Adatempat tidak menyatakan data selalu bebas dari risiko. Jika terjadi kegagalan pelindungan data pribadi, penanganan, dokumentasi, dan pemberitahuan akan dilakukan sesuai persyaratan hukum yang berlaku setelah fakta dan dampaknya dinilai.

## 10. Hak Pengguna

Sepanjang berlaku dan tunduk pada batasan yang ditetapkan hukum, Pengguna dapat mengajukan permintaan terkait hak atas data pribadinya, termasuk memperoleh informasi mengenai pemrosesan, mengakses atau memperbaiki data, menarik persetujuan untuk pemrosesan yang didasarkan pada persetujuan, meminta penghapusan atau pemusnahan ketika syaratnya terpenuhi, meminta pembatasan atau penghentian pemrosesan, dan menyampaikan keberatan atau pengaduan. Hak tertentu dapat memiliki pengecualian, prasyarat, atau dampak terhadap kemampuan menyediakan layanan.

Permintaan akan diverifikasi secara wajar untuk memastikan identitas pemohon dan melindungi data pihak lain. Adatempat dapat meminta informasi tambahan yang proporsional. Kanal, prosedur, tenggat, dan kontak resmi untuk permintaan subjek data wajib ditetapkan dan ditampilkan sebelum kebijakan digunakan secara publik.

## 11. Anak dan Data Sensitif

Kelayakan usia pengguna, proses persetujuan orang tua/wali apabila diwajibkan, dan perlakuan terhadap data anak harus ditetapkan melalui review hukum sebelum peluncuran. Draft ini tidak menganggap bahwa mekanisme tersebut sudah tersedia. Pengguna tidak boleh memberikan data pribadi anak atau data sensitif orang lain tanpa kewenangan dan dasar yang sah.

## 12. Cookie, Session, dan Penyimpanan Browser

Platform dapat menggunakan cookie dan mekanisme penyimpanan browser untuk menjaga sesi login, keamanan, preferensi, serta fitur lain yang benar-benar aktif. Jika fitur guest favorites, guest chat, analytics, atau iklan menggunakan local storage/cookie tambahan, tujuan dan kontrol pengguna harus dijelaskan sebelum fitur tersebut diluncurkan.

Pengguna dapat mengelola cookie melalui pengaturan browser, namun menonaktifkan cookie yang diperlukan dapat memengaruhi fungsi login atau fitur lain. Detail teknologi, masa hidup, dan kontrol opt-out perlu diisi berdasarkan implementasi serta vendor final.

## 13. Perubahan Kebijakan dan Persetujuan Ulang

Adatempat dapat memperbarui Kebijakan Privasi ketika pemrosesan data atau persyaratan hukum berubah. Setiap versi harus memiliki nomor versi, tanggal berlaku, dan rekaman persetujuan sesuai konteks. Jika perubahan material atau hukum mensyaratkan persetujuan ulang, Pengguna akan diminta menyetujui versi baru sebelum memakai fungsi yang memerlukannya.

Pembaruan kebijakan tidak secara otomatis membatalkan transaksi berbayar yang telah dikonfirmasi atau menghapus hak yang telah timbul. Pemrosesan yang berkaitan dengan transaksi historis dapat tetap dilakukan sejauh memiliki dasar yang sah dan diperlukan untuk kewajiban transaksi, audit, sengketa, atau hukum.

## 14. Keluhan Privasi dan Hukum

Pertanyaan atau keluhan privasi dapat diajukan melalui kontak privasi resmi yang wajib ditetapkan sebelum publikasi. Konfigurasi awal PRD mencantumkan support@adatempat.com, tetapi alamat tersebut harus diverifikasi dan kanal untuk permintaan hak subjek data harus dikonfirmasi.

Kebijakan ini dimaksudkan untuk ditinjau agar sesuai dengan hukum Republik Indonesia, termasuk peraturan mengenai Pelindungan Data Pribadi dan penyelenggaraan sistem elektronik yang berlaku beserta perubahannya. Tidak ada bagian kebijakan ini yang menghilangkan hak yang tidak dapat dikesampingkan oleh hukum.

## 15. Informasi Final yang Harus Dilengkapi Sebelum Produksi

Sebelum dipublikasikan, penanggung jawab wajib:
1. mengonfirmasi Pengendali Data, badan hukum, alamat, dan kontak privasi;
2. mencatat provider hosting, email/WhatsApp, pembayaran, analitik, serta negara/lokasi pemrosesannya yang benar-benar digunakan;
3. mengonfirmasi data aktual per fitur, tujuan, dasar pemrosesan, dan penerima data;
4. memvalidasi jadwal retensi dan proses penghapusan/anonymisasi berdasarkan hukum, akuntansi, dan kebutuhan operasional;
5. menetapkan prosedur permintaan hak subjek data, penanganan insiden, dan keluhan;
6. menetapkan kebijakan anak, cookie/local storage, transfer data lintas negara, dan persetujuan ulang; serta
7. memperoleh review dan pengesahan yang sesuai sebelum status dokumen diubah menjadi Published di production.
