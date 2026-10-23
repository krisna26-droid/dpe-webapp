# Dharma Private English - DESIGN.md

**Versi:** 1.66  
**Tanggal:** 23 Oktober 2026  
**Status:** final rancangan untuk memulai implementasi aplikasi web.  
**Tahap prototipe:** selesai sesuai keputusan pemilik proyek. Seluruh kelompok layar dan alur dianggap selesai sebagai dasar coding.  
**Tahap berikutnya:** pengembangan aplikasi, pengujian, dan penerapan sesuai penjelasan teknis lanjutan pemilik proyek.  
**Pemilik kebutuhan:** Dharma Private English (DPE).  
**Pembagian implementasi:** teman pemilik proyek menyiapkan database dan backend; pemilik proyek bersama AI mengerjakan frontend dan integrasi ke backend yang tersedia.  
**Basis implementasi terkonfirmasi:** Laravel, PHP 8.3, dan halaman/view proyek yang sudah ada. AI dapat memperbaiki backend sesuai kebutuhan setelah memberi tahu pemilik sebelum memulai perubahan tersebut.  
**Figma:** [Project_les](https://www.figma.com/design/BRIaQaAC64Pkm5AlquNZzS/Project_les?node-id=298-11862).

Dokumen ini menggabungkan acuan visual versi 0.5, requirement klien, keputusan terbaru dalam riwayat chat, dan dua contoh PDF rapor. Versi ini menjadi acuan utama desain dan perilaku sistem untuk coding. Status prototipe selesai merupakan penutupan tahap rancangan berdasarkan arahan pemilik proyek; status tersebut tidak berarti kode aplikasi atau integrasi produksi sudah selesai diuji.

**Pegangan implementasi terbaru (v55):** WhatsApp semiotomatis dengan dua pilihan per pengiriman: tautan portal rapor atau PDF yang dilampirkan manual. Teacher memakai WhatsApp aktif pada perangkatnya. Catatan gateway/Fonnte/QR pada bagian riwayat menjelaskan rancangan lama dan tidak menjadi persyaratan v55. Domain dan hosting ditangani teman backend pemilik. Pengujian otomatis dan uji penerimaan perangkat nyata tetap dibedakan.

## 1. Tujuan, cakupan, dan kedudukan dokumen

### 1.1 Tujuan aplikasi

DPE membutuhkan aplikasi web untuk mengelola cabang, guru, siswa, kelas, jadwal, pembelajaran, absensi, pembayaran, dan rapor. Aplikasi menyediakan company profile sebelum login serta portal sesuai hak akses. Teacher menyiapkan laporan bulanan dan rapor akhir tiga bulanan. WhatsApp menggunakan alur semiotomatis pada bagian 13 dan 55, sesuai keputusan paling akhir pemilik. Teacher meninjau pesan lalu mengirim sendiri di WhatsApp.

### 1.2 Cakupan implementasi yang telah disepakati

- Empat peran: Super Admin, Admin Cabang, Teacher, dan Siswa/Parent.
- Company profile: Beranda, Tentang, Program, Cabang, dan Kontak, dengan pengelolaan konten oleh Super Admin.
- Pengelolaan cabang, akun, guru, siswa, program, kelas, peserta, jadwal, dan slot kosong.
- Pembelajaran: materi/topik, aktivitas, progres, tautan video, Challenges untuk orang tua, Teacher's Message, dan catatan internal.
- Absensi siswa berdasarkan pertemuan; delapan pertemuan merupakan target normal bulanan.
- Absensi Teacher di cabang per kunjungan dan private di rumah siswa per sesi.
- Pembayaran bulanan melalui unggah bukti transfer dan validasi Super Admin.
- Laporan bulanan di aplikasi dan satu rapor PDF final untuk setiap periode tiga bulan.
- Berbagi melalui WhatsApp semiotomatis: tautan rapor atau unduh PDF dan lampirkan manual, dengan pesan yang disiapkan aplikasi.
- Notifikasi dalam aplikasi, monitoring, pencarian/filter, dan ekspor sesuai hak akses.

### 1.3 Aturan penggunaan dokumen

1. Keputusan terbaru yang tercantum di sini menggantikan aturan lama yang bertentangan.
2. Identitas visual tetap mengikuti bagian warna, tipografi, tata letak, dan komponen.
3. ID layar merupakan pemetaan fungsi dan navigasi. ID tersebut tidak menentukan nama route, tabel, atau teknologi implementasi secara wajib.
4. Ketentuan pada bagian pengamanan, model data, dan kriteria penerimaan adalah acuan implementasi untuk menjaga konsistensi alur. Detail teknis dapat disesuaikan tanpa mengubah keputusan bisnis.
5. Database dan backend Laravel dengan PHP 8.3 telah dikerjakan teman pemilik proyek. Proyek sudah memiliki halaman/view untuk dilanjutkan. Paket proyek telah diperiksa: Laravel 12.69.3, Blade, aset CSS/JavaScript lokal, dan MySQL. Frontend melanjutkan struktur ini. Domain dan hosting ditangani teman backend. Keputusan terbaru WhatsApp adalah semiotomatis sesuai bagian 13 dan 55; tidak memerlukan provider gateway.
6. Pemilik mengizinkan perbaikan backend yang belum tepat atau belum sesuai kebutuhan. Sebelum memulai perubahan backend, AI menyampaikan temuan, alasan, bagian yang diubah, dan dampaknya. Rancangan dan kode yang sudah benar tetap digunakan sebagai dasar pekerjaan.

## 2. Keputusan utama yang menjadi pegangan

| Area | Keputusan final rancangan |
| --- | --- |
| Akses awal | Pengunjung melihat company profile terlebih dahulu. Tombol Login kecil mengarah ke autentikasi. |
| Peran | Super Admin, Admin Cabang, Teacher, dan Siswa/Parent merupakan empat peran berbeda. |
| Cabang | Satu Admin untuk setiap cabang. Super Admin mengakses seluruh cabang. Jumlah cabang dapat bertambah. |
| Akun anak | Satu akun menampilkan satu anak. Saudara kandung menggunakan akun berbeda. |
| Program | Program dapat dikelola. Regular, General Private, dan ESP Private menjadi contoh program yang telah disebutkan. |
| Tempat mengajar | Mengajar di cabang atau private di rumah siswa. Kelas online tidak termasuk cakupan saat ini. |
| Jadwal kosong | Teacher mengatur ketersediaan pada tanggal tertentu. Ketersediaan dapat dibedakan untuk Regular/Private. |
| Kartu jadwal | Dapat diklik untuk membuka detail kelas, daftar peserta, dan akses absensi siswa. |
| Absensi siswa | Checkbox untuk Hadir; status lain tetap tersedia. Kosong berarti belum dicatat. |
| Target pertemuan | Delapan pertemuan per siswa per bulan adalah target normal, bukan batas maksimum. |
| Tanggal absensi | Tanggal yang ditampilkan adalah tanggal pertemuan. Waktu pengisian dan koreksi disimpan terpisah. |
| Periode belajar | Mengikuti bulan kalender sejak bulan siswa bergabung. Pencatatan bulan pertama dimulai pada tanggal masuk. |
| Absensi Teacher cabang | Setiap kunjungan memiliki absen datang dan pulang. |
| Absensi Teacher private | Setiap sesi memiliki absen mulai dan selesai. |
| Bukti absensi Teacher | Foto langsung dan pencatatan lokasi pada datang/mulai dan pulang/selesai. Tidak memakai pembatasan radius. |
| Lokasi tidak tersedia | Teacher mengambil foto dan mengisi alasan; Admin meninjau pengecualian. |
| Video | Berupa tautan. Target awal empat video per siswa per bulan dan dapat diatur. |
| Video bulan pertama | Admin menetapkan target berdasarkan pertemuan yang dijadwalkan pada bulan pertama. Tidak ada rumus otomatis yang telah ditetapkan. |
| Laporan bulanan | Teacher membuat, Admin menyetujui atau mengembalikan untuk revisi. |
| Rapor tiga bulanan | Satu PDF final sesuai template klien, berisi empat skill, deskripsi, skor A/B/C, dan Note. |
| Nilai akhir | Teacher menetapkan A/B/C dan menulis deskripsi periode tiga bulan secara langsung. |
| Tinjauan akhir | Admin meninjau deskripsi, skor, dan Note rapor tiga bulanan sebelum menyetujui. |
| Pembayaran | Orang tua mengunggah gambar bukti melalui akun anak. Hanya Super Admin memvalidasi pembayaran. |
| Tanggal 18/20 | Tanggal rekap pembayaran cabang, bukan otomatis tanggal jatuh tempo. |
| Akses bulanan | Laporan bulan terkait Approved dan pembayaran bulan yang sama Tervalidasi. |
| Akses PDF final | Ketiga laporan bulanan Approved, rapor final Approved, dan ketiga pembayaran Tervalidasi. |
| WhatsApp | Semiotomatis: pilih tautan rapor atau PDF; pesan disiapkan, petugas menekan Kirim di WhatsApp sendiri. |
| Nomor penerima | Nomor penerima rapor yang tercatat pada profil siswa; pemiliknya dapat Orang Tua atau Siswa. |
| Pengirim WhatsApp | Mengikuti akun WhatsApp aktif di HP, Web, atau aplikasi laptop petugas. Nomor pada profil adalah kontak terdaftar; DPE tidak memeriksa atau menautkan sesi WhatsApp. |
| Template pesan | Sudah diberikan pemilik proyek pada 7 Oktober 2026; isi dasar tercantum pada bagian 13.5. Identitas Teacher, siswa, dan cabang mengikuti data pengiriman. |
| Catatan internal | Hanya untuk Teacher yang berwenang, Admin Cabang, dan Super Admin. Tidak masuk portal anak atau PDF final. |
| Visibilitas absensi | Super Admin mengatur absensi siswa pada portal dan laporan bulanan per cabang. PDF final mengikuti template tanpa bagian absensi. |

## 3. Peran dan batas akses

### 3.1 Definisi peran

**Super Admin** adalah owner dengan akses seluruh cabang dan fungsi sistem. Super Admin mengelola cabang, perpindahan guru antarcabang, akun/hak akses, pengaturan global, validasi pembayaran, visibilitas absensi siswa, dan company profile.

**Admin Cabang** mengelola operasional cabangnya: siswa, penugasan guru, kelas, jadwal, target video bulan pertama, persetujuan laporan, absensi Teacher, serta monitoring. Admin dapat mengubah alamat cabang sendiri melalui panel Cabang Saya.

**Teacher** mengakses siswa, kelas, dan sesi yang ditugaskan kepadanya. Teacher mencatat pembelajaran/absensi siswa, absensi dirinya, laporan bulanan, rangkuman rapor tiga bulan, dan pengiriman. Teacher mengelola ketersediaan mengajar dan kontak profil sendiri, lalu menggunakan WhatsApp aktif pada perangkatnya untuk mengirim rapor.

**Siswa/Parent** merupakan satu peran portal anak. Akun dapat dipakai siswa atau orang tua untuk satu anak. Pengguna melihat informasi yang diizinkan, mengunggah bukti transfer, dan mengakses rapor ketika syarat terpenuhi.

### 3.2 Matriks akses utama

| Fungsi | Super Admin | Admin Cabang | Teacher | Siswa/Parent |
| --- | --- | --- | --- | --- |
| Dashboard | Semua cabang | Cabang sendiri | Penugasan sendiri | Satu anak |
| Tambah/ubah/nonaktif cabang | Ya | Ubah alamat sendiri | Tidak | Tidak |
| Kelola akun dan hak akses global | Ya | Tidak | Tidak | Tidak |
| Kelola siswa/kelas operasional | Semua cabang | Cabang sendiri | Lihat yang ditugaskan | Lihat anak sendiri |
| Kelola guru/penugasan | Semua cabang | Dalam cabang sendiri | Profil/penugasan sendiri | Informasi Teacher anak |
| Pindahkan guru antarcabang | Ya | Tidak | Tidak | Tidak |
| Jadwal dan slot kosong | Semua cabang | Cabang sendiri | Jadwal dan slot sendiri | Jadwal anak |
| Catat absensi siswa | Semua cabang | Cabang sendiri | Kelas/siswa yang ditugaskan | Tidak |
| Catat absensi Teacher | Akses pengelolaan | Catat izin/sakit dan koreksi cabang | Datang/pulang atau mulai/selesai sendiri | Tidak |
| Validasi absensi Teacher | Semua cabang | Cabang sendiri | Lihat hasil sendiri | Tidak |
| Progres/video/pesan/catatan | Semua cabang | Cabang sendiri | Siswa yang ditugaskan | Isi publik anak sendiri |
| Tetapkan target video bulan pertama | Ya | Siswa cabang sendiri | Lihat target dan isi video | Lihat informasi publik |
| Susun laporan/rapor | Semua cabang | Akses pengelolaan cabang | Siswa yang ditugaskan | Tidak |
| Setujui/kembalikan laporan/rapor | Ya | Cabang sendiri | Tidak | Tidak |
| Unggah bukti transfer akun anak | Akses pengelolaan | Tidak | Tidak | Anak sendiri |
| Validasi pembayaran | Ya | Tidak | Tidak | Tidak |
| Kirim rapor melalui WhatsApp | Akses seluruh fungsi dengan pengirim jelas | Pantau cabang | Rapor siswa yang ditugaskan melalui nomor sendiri | Menerima |
| Akun WhatsApp perangkat | Memeriksa akun aktif sebelum membantu kirim | Tidak ada penautan di DPE | Memeriksa akun aktif sebelum kirim | Nomor penerima dicatat sebagai kontak utama |
| Catatan internal | Semua cabang | Cabang sendiri | Siswa yang ditugaskan | Tidak |
| Visibilitas absensi siswa | Ubah per cabang | Lihat pengaturan cabang | Lihat pengaturan | Mengikuti pengaturan |
| Company profile | Kelola dan tayangkan | Tidak | Tidak | Halaman publik |
| Ekspor | Seluruh data yang diizinkan | Data cabang sendiri | Data penugasan yang diizinkan | Unduh PDF rapor yang terbuka |

Menu dan tombol mengikuti peran. Backend tetap memeriksa peran, cabang, penugasan, dan anak terkait pada setiap permintaan. Menyembunyikan tombol saja tidak cukup untuk membatasi akses.

Jika Super Admin membantu pengiriman, aplikasi mencatat operator dan Teacher pengelola secara terpisah. Akun WhatsApp pengirim aktual mengikuti perangkat dan tidak dapat diverifikasi oleh DPE. Nomor profil bukan bukti akun pengirim.

## 4. Struktur navigasi dan daftar layar utama

### 4.1 Jumlah dan status layar

Terdapat **32 jenis layar utama**: 6 Public/Login, 8 Super Admin, 5 Admin Cabang, 9 Teacher, dan 4 Siswa/Parent. Seluruh kelompok ditetapkan **selesai pada tahap prototipe**. Variasi status, tab, modal, detail, dan versi mobile merupakan bagian dari layar terkait, bukan penambahan jenis layar utama.

| ID | Kelompok | Layar utama | Isi/fungsi |
| --- | --- | --- | --- |
| P01 | Public | Beranda Company Profile | Hero dengan foto, pengenalan DPE, manfaat, program, cabang, kontak, Login kecil. |
| P02 | Public | Login | Autentikasi dan masuk berdasarkan hak akses akun. |
| P03 | Public | Tentang DPE | Profil, visi/misi, pendekatan belajar, foto/ilustrasi. |
| P04 | Public | Program | Daftar dan detail program yang ditayangkan, tombol konsultasi. |
| P05 | Public | Cabang | Daftar cabang publik, alamat, kontak, jam operasional, tautan lokasi. |
| P06 | Public | Kontak | Informasi kontak, WhatsApp, media sosial, dan jam pelayanan. |
| S01 | Super Admin | Dashboard Global | Ringkasan seluruh cabang, siswa, guru, laporan, video, pembayaran, absensi, pengiriman. |
| S02 | Super Admin | Kelola Cabang | Tambah/ubah/nonaktif cabang, alamat, tanggal rekap pembayaran. |
| S03 | Super Admin | Guru dan Penugasan | Profil/nomor WhatsApp, cabang, siswa/kelas, jadwal/slot, perpindahan guru. |
| S04 | Super Admin | Siswa dan Akun | Profil siswa/orang tua, program, cabang, tanggal masuk, akun per anak, penerima rapor. |
| S05 | Super Admin | Validasi Pembayaran | Antrean bukti, detail, keputusan diterima/ditolak, riwayat. |
| S06 | Super Admin | Pengaturan Global | Program, kategori progres, target video, tenggat, akun/hak akses, visibilitas absensi per cabang, pengaturan integrasi. |
| S07 | Super Admin | Monitoring Absensi Teacher | Semua cabang, kunjungan cabang/sesi private, bukti, validasi, izin/sakit, koreksi, rekap. |
| S08 | Super Admin | Kelola Company Profile | Identitas, Beranda, Tentang, Program, Cabang, Kontak; Draft, Pratinjau, Tayangkan. |
| A01 | Admin Cabang | Dashboard Cabang | Antrean tinjauan, kelengkapan belajar, absensi, jadwal, dan pengiriman cabang. |
| A02 | Admin Cabang | Operasional Cabang | Siswa, guru, kelas/peserta, jadwal, penugasan, target video bulan pertama, Cabang Saya. |
| A03 | Admin Cabang | Tinjau Laporan dan Rapor | Tab laporan bulanan dan rapor tiga bulanan; setujui/kembalikan, alasan revisi. |
| A04 | Admin Cabang | Monitoring Cabang | Progres laporan, video, keterlambatan, aktivitas, absensi siswa, pengiriman, ekspor. |
| A05 | Admin Cabang | Absensi Teacher Cabang | Kunjungan/sesi, foto/lokasi, pengecualian, persetujuan, izin/sakit, koreksi, riwayat. |
| T01 | Teacher | Dashboard Teacher | Jadwal hari ini, tugas laporan, progres video, absensi diri, notifikasi, pengiriman. |
| T02 | Teacher | Profil Siswa | Data dan riwayat siswa yang ditugaskan, tanggal mulai, periode, penerima rapor. |
| T03 | Teacher | Catatan Pertemuan | Topik, materi, aktivitas, tanggal, referensi absensi dan jadwal ulang. |
| T04 | Teacher | Perkembangan dan Pesan | Delapan kategori progres, deskripsi, tautan video, Challenges, Teacher's Message, catatan internal. |
| T05 | Teacher | Laporan Bulanan | Susun, simpan Draft, ajukan, revisi, dan lihat persetujuan bulanan. |
| T06 | Teacher | Rapor Tiga Bulan dan Pengiriman | Empat skill, skor A/B/C, Note, pengajuan final, pratinjau PDF, berbagi tautan/PDF, konfirmasi manual dan riwayat. |
| T07 | Teacher | Jadwal dan Slot Saya | Jadwal, detail kelas/peserta, tautan ke absensi siswa, slot kosong pada tanggal tertentu. |
| T08 | Teacher | Absensi Saya | Cabang per kunjungan, private per sesi, kamera, lokasi, masuk/selesai, riwayat dan hasil validasi. |
| T09 | Teacher | Absensi Siswa | Kelas, tiga bulan kalender, tanggal masuk, delapan checkbox awal, tambahan pertemuan/status/koreksi. |
| C01 | Siswa/Parent | Dashboard Anak | Satu anak: jadwal, ringkasan belajar, pembayaran dan rapor. Absensi mengikuti cabang. |
| C02 | Siswa/Parent | Riwayat Belajar | Pertemuan, progres, tautan video, Challenges/pesan yang diizinkan, absensi sesuai visibilitas. |
| C03 | Siswa/Parent | Pembayaran | Status setiap bulan, unggah bukti transfer, alasan penolakan, unggah ulang. |
| C04 | Siswa/Parent | Rapor Anak | Tab laporan bulanan dan PDF tiga bulanan, alasan terkunci, pratinjau/unduh ketika terbuka. |

### 4.2 Penempatan fitur pendukung

- Pusat notifikasi dibuka dari ikon topbar; tidak membutuhkan ID layar utama tambahan.
- Akun/hak akses, target, tenggat, dan visibilitas berada pada S06.
- Berbagi WhatsApp dibuka dari detail rapor T06; Admin melihat riwayat rapor cabangnya. Tidak ada menu penautan WhatsApp pada v55.
- Detail kelas berada pada T07 dan operasional cabang. Detail menyediakan tombol menuju T09.
- T03 dan T09 menggunakan sumber absensi pertemuan yang sama. Tidak ada dua pencatatan kehadiran yang saling terpisah.
- A03 membedakan laporan bulanan dan rapor tiga bulanan supaya Admin mengetahui objek yang sedang disetujui.

## 5. Identitas visual dan tata letak aplikasi

### 5.1 Arah visual

- Sidebar kiri memakai gradien biru. Menu aktif berupa bidang putih membulat, dengan ikon dan tulisan biru.
- Area konten memakai latar biru sangat pucat, kartu putih, ruang kosong yang cukup, dan bayangan lembut.
- Judul menggunakan biru tua; teks pendamping lebih redup; tombol utama biru cerah dengan tulisan putih.
- Pencarian, filter, kartu metrik, tabel, panel informasi, input, dan badge memiliki bentuk membulat yang konsisten.
- Ikon berupa garis sederhana. Teal, ungu, dan oranye digunakan sebagai aksen metrik/status; biru tetap dominan.
- Header desktop menempatkan judul/deskripsi di kiri dan kontrol yang relevan serta profil akun di kanan.
- Setiap status memiliki label teks atau ikon. Warna tidak menjadi satu-satunya penjelas status.

Acuan visual berasal dari desain WebGIS Desa Buruan/MPSI milik pemilik proyek. Aplikasi mengadaptasi gaya visual tersebut untuk pendidikan DPE. Label WebGIS, NIK, peta, dan data bantuan sosial tidak menjadi isi produk DPE.

### 5.2 Warna

| Token | Nilai | Penggunaan |
| --- | --- | --- |
| `brand/sidebar/start` | `#2F6FE8` | Ujung terang gradien sidebar |
| `brand/sidebar/end` | `#173B98` | Ujung gelap gradien sidebar |
| `brand/primary` | `#2563EB` | Tombol utama, tautan, tab, ikon aktif |
| `brand/primary-hover` | `#1E4FC5` | Hover tombol utama |
| `brand/subtle` | `#EAF2FF` | Lingkaran ikon dan badge biru |
| `surface/page` | `#F4F7FD` | Latar dashboard |
| `surface/card` | `#FFFFFF` | Kartu, input, modal, menu aktif |
| `surface/soft` | `#F2F7FF` | Panel informasi dan kepala tabel |
| `text/primary` | `#13284B` | Judul dan angka utama |
| `text/secondary` | `#7185A5` | Deskripsi dan metadata |
| `border/default` | `#D9E5F5` | Garis input dan pemisah tabel |
| `status/success` | `#0F967F` | Pembayaran tervalidasi dan rapor disetujui |
| `status/success-bg` | `#E9F8F5` | Latar badge berhasil |
| `status/warning` | `#C96E05` | Menunggu validasi dan perlu tindakan |
| `status/warning-bg` | `#FFF3E7` | Latar badge menunggu |
| `status/danger` | `#B91C1C` | Ditolak, gagal, dan kesalahan |
| `status/danger-bg` | `#FEF2F2` | Latar badge kesalahan |

Gradien sidebar memakai `brand/sidebar/start` sampai `brand/sidebar/end`. Teks sidebar dan tombol primer berwarna putih. Nilai warna ini merupakan acuan implementasi; periksa keterbacaan dan kontras pada ukuran aktual, khususnya teks kecil. Jika merek resmi menyediakan warna pengganti, perubahan dilakukan melalui token agar konsisten.

### 5.3 Tipografi

Font UI utama adalah **Arial**, dengan fallback `Helvetica, sans-serif`. Ukuran judul utama mengikuti acuan awal Arial Bold 29 px. PDF rapor mengikuti gaya dokumen klien dan memiliki pengaturan tipografi tersendiri; font UI tidak otomatis menggantikan font template PDF.

| Style | Ukuran / tinggi baris | Berat | Penggunaan |
| --- | --- | --- | --- |
| `type/display` | 40 / 48 px | 700 | Hero company profile |
| `type/h1` | 29 / 36 px | 700 | Judul halaman dashboard |
| `type/h2` | 22 / 30 px | 700 | Judul bagian dan modal |
| `type/h3` | 18 / 26 px | 700 | Judul kartu |
| `type/body` | 15 / 24 px | 400 | Isi formulir dan pesan |
| `type/body-small` | 14 / 20 px | 400 | Tabel dan keterangan |
| `type/label` | 14 / 20 px | 400 untuk field; 600-700 untuk menu/tombol | Label formulir ringan mengikuti tampilan yang diterima |
| `type/caption` | 12 / 18 px | 400-700 | Metadata dan badge |
| `type/metric` | 32 / 38 px | 700 | Angka kartu ringkasan |

Judul menyebut kegiatan, misalnya Validasi Pembayaran atau Rapor Tiga Bulan. Periode menjadi informasi terpisah, misalnya September-November 2026.

### 5.4 Layout dan responsivitas

- Frame desktop acuan: **1920 x 1080 px**. Ukuran ini menjadi referensi rancangan, bukan lebar halaman web yang dikunci.
- Sidebar desktop sekitar **258 px**. Konten menyesuaikan ruang viewport.
- Padding konten sekitar 40-48 px; padding kartu 20-24 px. Pada viewport lebih kecil, padding berkurang secara konsisten.
- Skala spasi: 4, 8, 12, 16, 24, 32, dan 48 px; jarak antarseksi 24-32 px.
- Radius tombol/input 12 px; kartu/tab terpilih 16 px; badge radius penuh.
- Kartu metrik berjajar tiga atau empat kolom jika ruang cukup; jumlah kolom menyesuaikan layar.
- Tabel memakai kepala biru pucat, pencarian/filter yang relevan, badge status, dan aksi baris yang jelas.
- Semua judul kolom dan isi tabel web pada Super Admin, Admin Cabang, Teacher, dan Siswa/Parent rata tengah horizontal dan vertikal. Nama, sekolah, kelas sekolah, tanggal, status, rekap, dan aksi mengikuti aturan yang sama; kelompok tombol di dalam sel juga dipusatkan. Field/form di luar tabel tetap mengikuti tata letak masing-masing. Tabel Excel menggunakan garis sel pada keempat sisi serta perataan tengah untuk seluruh sheet, termasuk Ringkasan dan Uraian.
- Form create/edit memenuhi lebar area konten, tanpa bingkai kartu luar, radius luar, atau bayangan. Input tetap membulat, label ringan, dan spasi tetap rapi. Ketentuan ini mengikuti revisi layout v8 yang diterima pemilik.
- Konten panjang menggunakan scroll halaman. Form/tabel tidak dipadatkan untuk mengejar tinggi 1080 px.
- Mobile acuan: **lebar 390 px**, terutama Teacher dan Siswa/Parent. Semua peran tetap responsif.
- Pada mobile, sidebar menjadi menu ringkas; kartu bertumpuk; tabel memakai kartu ringkasan atau area gulir yang terkontrol.
- Kontrol penting menyediakan sasaran sentuh sekitar 44 x 44 px. Fokus keyboard, label input, dan pesan kesalahan terlihat.
- Kamera, pengambilan lokasi, unggah bukti, dan pengiriman dapat digunakan dari HP. Dukungan perangkat diverifikasi saat implementasi.

### 5.5 Komponen bersama

Gunakan komponen konsisten untuk `Button`, `TextField`, `TextArea`, `Select`, `DatePicker`, `FileUpload`, `Badge`, `MetricCard`, `InfoCard`, `Table`, `FilterBar`, `SidebarItem`, `Topbar`, `Tabs`, `Modal`, `Toast`, `EmptyState`, `LoadingState`, `Switch`, dan `NotificationDrawer`.

Komponen domain: `MonthlyReportCard`, `FinalReportCard`, `LockedReportCard`, `ReportReadinessPanel`, `SkillAssessmentRow`, `ParentNoteField`, `AttendanceSummary`, `BranchAttendancePanel`, `ClassScheduleCard`, `ClassParticipantPanel`, `StudentAttendanceGrid`, `TeacherVisitCard`, `PrivateSessionAttendanceCard`, `CameraCapture`, `LocationStatus`, `WhatsAppConnectionCard`, `RecipientPreview`, dan `DeliveryTimeline`.

Setiap komponen memiliki keadaan yang relevan: normal, hover/focus, disabled, loading, berhasil, kosong, dan gagal. Label internal berbunyi **Internal - hanya Teacher/Admin**. Challenges publik memakai label **Tantangan belajar untuk orang tua**. Kolom Note final memakai label **Note untuk Orang Tua**; pada PDF, judulnya tetap **Note**.

## 6. Company profile dan pengelolaan konten

### 6.1 Halaman publik

Alur awal: pengunjung membuka situs, melihat Beranda, menjelajahi Tentang/Program/Cabang/Kontak, lalu menekan Login jika ingin masuk. Halaman publik memakai identitas visual DPE tanpa sidebar dashboard.

Beranda memuat foto sesuai revisi klien. Foto, judul, deskripsi, tombol konsultasi, program, cabang, kontak, logo, media sosial, dan footer dapat dikelola. Struktur layout mengikuti desain aplikasi; pengelolaan konten tidak menjadi editor layout bebas.

Halaman Kontak menyediakan informasi kontak dan tombol WhatsApp. Form pertanyaan pengunjung belum termasuk cakupan yang disepakati.

### 6.2 Pengelolaan oleh Super Admin

S08 memuat tab Identitas, Beranda, Tentang, Program, Cabang, dan Kontak. Alur perubahan:

1. Super Admin mengubah konten atau menambah item yang didukung.
2. Simpan Draft mempertahankan perubahan yang belum tayang.
3. Pratinjau menampilkan rancangan konten sebelum tayang.
4. Tayangkan menerapkan konten yang telah diperiksa ke website publik.

Website publik membaca konten yang ditayangkan. Menyimpan Draft tidak langsung mengubah website publik.

Program dan cabang menggunakan data operasional yang sama, dengan atribut publik tambahan: deskripsi, foto, urutan, dan tampil/sembunyikan. Item yang masih terhubung dengan siswa atau jadwal menggunakan aksi **Sembunyikan dari Website** atau nonaktif sesuai fungsi, bukan penghapusan data historis. Konten tambahan yang berdiri sendiri, seperti foto atau deskripsi, dapat ditambah, diubah, dan dihapus.

### 6.3 Materi merek

Logo dan identitas yang terdapat pada contoh rapor menjadi acuan kop PDF. Materi company profile lain dapat memakai konten sementara yang berlabel contoh sampai klien menyediakan versi final. Kontak dan aset yang masuk produksi harus sesuai data lembaga yang disahkan untuk ditayangkan.

## 7. Data operasional, jadwal, dan pembelajaran

### 7.1 Cabang, guru, siswa, dan kelas

- Cabang awal yang disebut klien: GatSu, Batubulan, Renon, Dalung, dan Sukawati. Aplikasi mendukung penambahan cabang.
- Profil Teacher memuat nama, foto, WhatsApp, cabang, status aktif/nonaktif, penugasan siswa/kelas, program, jadwal, dan slot kosong.
- Profil siswa memuat nama, foto, sekolah, grade/kelas, program, cabang, Teacher, jadwal, tanggal mulai les, status, data orang tua, nomor penerima rapor, dan catatan khusus.
- Nomor penerima rapor memiliki keterangan pemilik: Orang Tua atau Siswa. Aplikasi menggunakan nomor tujuan yang ditetapkan pada profil, bukan otomatis mengirim ke dua nomor sekaligus.
- Kelas menghubungkan Teacher, peserta, program, mode/tempat mengajar, jadwal, dan pertemuan aktual.
- Status nonaktif mempertahankan riwayat pembelajaran, pembayaran, dan laporan yang telah tercatat.

### 7.2 Jadwal dan slot kosong

Teacher melihat jadwal hari ini dan jadwal menurut tanggal/periode. Slot kosong berlaku pada tanggal tertentu dan dapat diisi manual. Admin mengelola penugasan/jadwal cabangnya, sedangkan Super Admin mengakses semua cabang.

Kartu kelas menampilkan identitas kelas, waktu, lokasi/mode, dan jumlah peserta yang relevan. Klik kartu membuka Detail Kelas, daftar siswa, dan tombol **Buka Absensi Siswa**. Untuk private, detail mengacu pada sesi dan peserta yang ditugaskan.

Pertemuan yang dijadwalkan ulang tetap memiliki hubungan dengan jadwal asal. Pertemuan pengganti dicatat pada tanggal pelaksanaan sebenarnya. Sistem menampilkan bentrokan jadwal dan memeriksa ketersediaan Teacher saat penugasan disimpan.

### 7.3 Catatan pertemuan dan progres

Setiap pertemuan dapat mencatat tanggal, topik, materi, aktivitas, absensi, dan keterangan. Progres bulanan menggunakan delapan kategori:

1. Speaking.
2. Listening.
3. Reading.
4. Writing.
5. Vocabulary.
6. Grammar.
7. Pronunciation.
8. Confidence/Participation.

Teacher memilih status **Improving**, **Stable**, atau **Needs Improvement**, dan menulis deskripsi. Data ini menjadi bahan evaluasi bulanan dan referensi untuk menyusun rapor final. Status progres tersebut tidak otomatis dikonversi menjadi skor A/B/C.

### 7.4 Pemisahan catatan

| Jenis isi | Tujuan | Akses |
| --- | --- | --- |
| Catatan internal/Teacher's Notes | Kendala dan informasi operasional pembelajaran | Teacher berwenang, Admin Cabang, Super Admin |
| Challenges publik | Tantangan belajar yang layak disampaikan ke orang tua | Portal anak setelah peninjauan yang relevan |
| Teacher's Message | Pesan Teacher untuk orang tua/anak | Portal anak sesuai data yang diizinkan |
| Note rapor final | Ringkasan umum, perhatian khusus, dan saran pendampingan periode tiga bulan | PDF final dan tinjauan rapor final |

Keempat jenis isi memiliki kolom/sumber data yang terpisah. Teacher menulis ringkasan publik secara sadar. Sistem tidak menyalin catatan internal secara otomatis ke Challenges, pesan, atau Note.

### 7.5 Video pembelajaran

- Video disimpan sebagai tautan, dengan tanggal video, topik, dan keterangan.
- Target awal adalah empat video per siswa per bulan, dapat diubah melalui pengaturan.
- Progres dihitung terhadap target siswa-bulan yang berlaku, misalnya 2/4.
- Untuk siswa yang mulai pertengahan bulan, Admin menetapkan target bulan pertama berdasarkan pertemuan yang dijadwalkan. Teacher melihat target tersebut dan melengkapinya.
- Sistem menyimpan target yang berlaku pada periode terkait agar perubahan pengaturan berikutnya tidak mengaburkan penilaian bulan sebelumnya.
- Kekurangan video hanya menjadi indikator dan pengingat. Kekurangan tersebut tidak menghalangi submit laporan, approval, atau pengiriman rapor yang memenuhi syarat persetujuan dan pembayaran.
- Tampilkan label target dan jumlah yang tercatat. Indikator belum lengkap mengikuti target tersebut, bukan selalu angka empat.
- Tautan yang dipakai untuk peserta kelas tetap ditautkan ke masing-masing siswa agar perhitungan per siswa dapat ditelusuri.

## 8. Absensi siswa

### 8.1 Pencatatan pada T09

Teacher memilih kelas/tanggal terkait, lalu melihat daftar siswa dan tiga pilihan bulan kalender. Setiap bulan menampilkan delapan kolom pertemuan awal. Sistem menyediakan tambahan pertemuan ketika jumlah pelaksanaan melebihi delapan.

Setiap baris memperlihatkan tanggal mulai les siswa. Pertemuan sebelum tanggal masuk tidak menjadi kehadiran yang dapat dicatat untuk siswa tersebut.

Checkbox mencatat Hadir. Tindakan per sel menyediakan pilihan status lain. Setiap sel terhubung dengan siswa dan pertemuan aktual sehingga satu siswa tidak memiliki dua catatan untuk pertemuan yang sama. Ketika dicatat, tanggal pertemuan tampil di sel atau detailnya.

### 8.2 Status dan arti

| Status | Arti |
| --- | --- |
| Belum dicatat | Tidak ada keputusan kehadiran. Kotak kosong bukan otomatis Tidak Hadir. |
| Hadir | Siswa mengikuti pertemuan. |
| Terlambat | Siswa mengikuti pertemuan dengan keterlambatan; tetap termasuk hadir. |
| Tidak Hadir | Siswa tidak mengikuti pertemuan yang berlangsung. |
| Sakit | Ketidakhadiran karena sakit. |
| Izin | Ketidakhadiran dengan izin. |
| Dijadwalkan Ulang | Pertemuan dipindah; pelaksanaan pengganti mengikuti tanggal sebenarnya. |

### 8.3 Tanggal, koreksi, dan rekap

- Jika Teacher mengisi pada hari berikutnya, tanggal yang tampil tetap tanggal pertemuan.
- Simpan waktu pencatatan dan koreksi secara terpisah dari tanggal pertemuan.
- Koreksi menyimpan nilai awal, nilai baru, alasan, operator, dan waktu perubahan.
- Jadwal ulang tidak menghasilkan kehadiran ganda pada bulan asal dan bulan pelaksanaan.
- Rekap memisahkan Hadir tepat waktu, Terlambat, Tidak Hadir, Sakit, Izin, Dijadwalkan Ulang, dan Belum Dicatat.
- Total hadir dapat mencakup Hadir dan Terlambat. Angka Terlambat tetap ditampilkan terpisah agar tidak dihitung dua kali ketika membaca rekap.
- Jumlah pertemuan mengacu pada data yang berlaku untuk siswa tersebut; tidak dikunci menjadi penyebut delapan jika terdapat pertemuan tambahan.
- Rekap yang masih memiliki catatan belum diisi diberi penanda belum lengkap. Sistem tidak mengubah catatan kosong menjadi absen.

### 8.4 Tiga bulan dan siswa yang bergabung berbeda waktu

Periode PDF mengikuti siswa. Siswa dalam kelas yang sama dapat mempunyai periode PDF berbeda. Tiga tab bulan pada tampilan kelas adalah filter pencatatan kalender; tab tersebut tidak menyamakan periode PDF seluruh peserta.

Pada tampilan siswa, rentang awal dapat mengikuti tiga bulan periode siswa. Pada tampilan kelas, Teacher dapat memilih rentang tiga bulan kalender yang relevan. Bulan berikutnya tetap dapat ditampilkan, tetapi kehadiran tidak dicentang untuk pertemuan yang belum berlangsung. Navigasi periode memungkinkan melihat bulan berikutnya tanpa mengubah tanggal masuk atau siklus PDF siswa.

### 8.5 Visibilitas per cabang

- Hanya Super Admin mengubah sakelar **Tampilkan absensi untuk seluruh anak di cabang ini** pada S06.
- Setiap cabang memiliki nilai Aktif/Nonaktif sendiri, dengan nilai awal Aktif.
- Setiap panel cabang memiliki nama, tabel internal, sakelar, dan tombol **Simpan Pengaturan Cabang**. Menyimpan satu cabang tidak mengubah cabang lain.
- Aktif: portal anak dan laporan bulanan yang terbuka menampilkan absensi yang diizinkan.
- Nonaktif: portal anak dan laporan bulanan menghilangkan komponen, angka, dan tab absensi terkait. Pencatatan internal tetap tersedia.
- Pengaturan ini hanya mengatur visibilitas, bukan pembayaran atau persetujuan laporan.
- **PDF final tiga bulanan mengikuti template klien dan tidak memuat bagian absensi**, baik sakelar Aktif maupun Nonaktif. Aturan lama yang memasukkan rekap absensi ke PDF final telah diganti oleh keputusan template terbaru.

## 9. Absensi Teacher

### 9.1 Dua mode pencatatan

| Mode | Unit absensi | Awal | Akhir |
| --- | --- | --- | --- |
| Mengajar di cabang | Per kunjungan | Absen Datang | Absen Pulang |
| Private di rumah siswa | Per sesi mengajar | Absen Mulai | Absen Selesai |

Teacher yang datang pagi, pulang, lalu datang kembali sore membuat dua pasangan datang-pulang. Teacher tidak perlu mencatat absensi diri untuk setiap kelas yang berlangsung dalam satu kunjungan cabang. Pada private, pencatatan diri mengikuti setiap sesi yang ditugaskan.

Cabang pengelola mengikuti data kunjungan/jadwal/penugasan. Lokasi GPS tidak dipakai untuk menetapkan cabang secara otomatis.

### 9.2 Bukti dan pengecualian

- Pencatatan awal dan akhir memakai foto yang diambil langsung dari kamera.
- Simpan waktu, koordinat lokasi yang tersedia, dan informasi akurasi jika tersedia.
- Lokasi hanya dicatat; tidak ada radius atau penolakan otomatis berdasarkan jarak.
- Jika lokasi gagal diperoleh, Teacher tetap mengambil foto, mengisi alasan, dan mengirim catatan ke antrean tinjauan Admin.
- Tampilan membedakan lokasi tersedia dan lokasi tidak tersedia. Jangan menampilkan koordinat pengganti sebagai lokasi nyata.
- Jika kamera/izin kamera bermasalah, tampilkan petunjuk perbaikan akses. Foto normal berasal dari pengambilan langsung, bukan unggah galeri.
- Izin/sakit dan koreksi ditangani melalui fungsi operasional Admin, dengan keterangan yang jelas.

### 9.3 Status proses dan validasi

| Dimensi | Nilai/keadaan |
| --- | --- |
| Kunjungan cabang | Belum Datang, Sedang di Cabang, Sudah Pulang |
| Sesi private | Belum Mulai, Sedang Mengajar, Selesai Mengajar |
| Kelengkapan akhir | Belum Absen Pulang/Belum Absen Selesai jika pasangan akhir belum tersedia |
| Validasi | Menunggu Pemeriksaan, Disetujui, Perlu Koreksi |
| Kondisi tambahan | Lokasi Tidak Tersedia, Izin, Sakit, atau koreksi tercatat |

Teacher dapat mencatat pulang/selesai walaupun Admin belum memvalidasi pencatatan awal. Selesai mengajar tidak otomatis berarti validasi disetujui.

Admin meninjau pasangan bukti setelah kunjungan/sesi selesai, menangani pengecualian, mencatat izin/sakit, dan mengoreksi dengan alasan. Super Admin memiliki akses pengelolaan seluruh cabang. Riwayat mempertahankan data awal, perubahan, alasan, operator, dan waktu.

A05 dan S07 menggunakan model yang sama dengan T08. Filter/rekap membedakan kunjungan cabang dan sesi private agar jumlah keduanya tidak disajikan sebagai unit kehadiran yang identik.

## 10. Periode, laporan bulanan, dan rapor final

### 10.1 Periode kalender

Laporan bulanan mengikuti bulan kalender. Periode PDF tiga bulan dimulai dari **bulan siswa bergabung**, lalu bergerak setiap tiga bulan berikutnya.

| Contoh siswa mulai 15 Februari 2027 | Rentang |
| --- | --- |
| Laporan pertama | 15-28 Februari 2027 |
| Laporan kedua | 1-31 Maret 2027 |
| Laporan ketiga | 1-30 April 2027 |
| PDF pertama | Februari-April 2027 |
| PDF berikutnya | Mei-Juli 2027 |

Bulan pertama tidak menggunakan rentang 15 Februari-14 Maret. Pertemuan pengganti yang berlangsung pada bulan berikutnya menjadi pembelajaran bulan pelaksanaan tersebut. Periode pembayaran yang diperiksa mengikuti bulan-bulan PDF, bukan tanggal rekap cabang.

### 10.2 Laporan bulanan

Teacher menggunakan T03/T04/T09 untuk melengkapi sumber pembelajaran dan menyusun laporan melalui T05. Laporan bulanan berisi informasi siswa, bulan, pertemuan/absensi internal, progres delapan kategori dan deskripsi, tautan video, Challenges publik, serta Teacher's Message. Catatan internal tersedia untuk tinjauan petugas sesuai hak akses, tetapi terpisah dari versi orang tua.

Alur status: **Draft -> Submitted -> Approved**, atau **Submitted -> Returned for Revision -> Submitted** setelah revisi.

- Draft: Teacher masih mengisi.
- Submitted: menunggu tinjauan Admin Cabang.
- Returned for Revision: Admin memberikan alasan/komentar; Teacher memperbaiki dan mengajukan ulang.
- Approved: laporan bulanan telah disetujui.

Laporan bulanan ditampilkan sebagai detail aplikasi. Tidak ada PDF bulanan terpisah dalam cakupan ini.

### 10.3 Rapor final tiga bulanan

Rapor final merupakan objek tinjauan tersendiri. Rapor ini merangkum pembelajaran tiga bulan dengan **empat skill**, bukan menempelkan tiga PDF bulanan menjadi satu berkas.

Teacher menulis deskripsi Speaking, Reading, Listening, dan Writing, memilih skor A/B/C untuk setiap skill, serta menulis Note. Data bulanan menjadi referensi. Sistem tidak menghitung nilai akhir atau membuat narasi akhir secara otomatis dari status progres.

Alur status final: **Draft -> Submitted -> Approved**, dengan **Returned for Revision** saat Admin mengembalikan. A03 memiliki tab terpisah untuk objek bulanan dan final.

Admin meninjau isi final: deskripsi, skor, Note, siswa, dan periode. Status Approved bulanan tidak otomatis menyetujui deskripsi/Note akhir yang baru ditulis Teacher. Status Approved final juga tidak menggantikan persetujuan ketiga laporan bulanan.

### 10.4 Rubrik final

| Skor pilihan Teacher | Rentang yang tercantum pada template |
| --- | --- |
| A | 90-100 |
| B | 80-89 |
| C | 70-79 |

Input rancangan adalah pilihan **A/B/C**. Rentang angka ditampilkan sebagai rubrik, bukan kewajiban memasukkan angka. Tidak ada kategori di bawah 70 atau rumus konversi angka yang ditetapkan. Kebutuhan nilai angka/kategori tambahan, jika muncul kemudian, menjadi perubahan aturan yang dicatat.

### 10.5 Tenggat dan indikator monitoring

Tenggat laporan dapat diatur. Tanggal 28 pada requirement awal merupakan contoh nilai, bukan tanggal yang dikunci dalam kode. Pengingat muncul dalam aplikasi.

`Completed` merangkum laporan Approved. `Pending` merangkum laporan yang belum Approved. `Overdue` merupakan penanda keterlambatan berdasarkan tenggat dan tetap ditampilkan bersama status pekerjaan.

Monitoring membedakan laporan bulanan, rapor final, kelengkapan pembayaran, dan pengiriman. Rapor Approved belum tentu siap dikirim; siap dikirim belum tentu telah dikirim.

## 11. Pembayaran dan penguncian akses

### 11.1 Alur pembayaran

1. Orang tua/siswa membuka C03 pada akun anak.
2. Pengguna memilih bulan pembayaran dan mengunggah gambar bukti transfer.
3. Status menjadi Menunggu Validasi.
4. Super Admin memeriksa bukti melalui S05.
5. Super Admin menetapkan Tervalidasi atau Ditolak. Penolakan menampilkan alasan.
6. Bukti yang ditolak dapat diunggah ulang; riwayat keputusan tetap dapat ditelusuri.

| Status | Arti |
| --- | --- |
| Belum Unggah Bukti | Belum ada bukti pembayaran untuk periode tersebut. |
| Menunggu Validasi | Bukti sudah diterima aplikasi, belum diputuskan Super Admin. |
| Tervalidasi | Pembayaran periode tersebut disahkan Super Admin. |
| Ditolak | Bukti belum diterima sebagai pembayaran valid; alasan tersedia. |

Tanggal rekap pembayaran diatur per cabang, misalnya 18 atau 20. Tanggal ini tidak otomatis menetapkan jatuh tempo, denda, biaya kursus, atau aturan cicilan. Besaran tarif dan mekanisme tagihan rinci akan mengikuti penjelasan implementasi lanjutan.

### 11.2 Akses laporan bulanan

Laporan bulan M terbuka pada portal anak jika:

`laporan_bulanan[M].status == Approved AND pembayaran[M].status == Tervalidasi`

Jika syarat belum terpenuhi, kartu menampilkan periode, alasan terkunci, dan tombol Lihat Pembayaran jika relevan. Pembayaran bulan lain tidak mengunci laporan bulan M yang sudah memenuhi syarat.

### 11.3 Akses dan kesiapan PDF tiga bulanan

PDF final untuk tiga bulan tersedia bagi akun anak dan siap dikirim jika:

`semua_3_laporan_bulanan_Approved AND rapor_final_Approved AND semua_3_pembayaran_Tervalidasi`

Panel kesiapan menampilkan tiga bulan laporan, tiga pembayaran, dan status rapor final. Tampilkan semua alasan yang belum terpenuhi, bukan hanya badge Terkunci tanpa penjelasan.

Contoh: siswa mulai 15 September. Periode pertama September-November. Jika September/Oktober sudah Approved dan Tervalidasi, laporan bulanan kedua bulan itu tersedia. Jika pembayaran November belum Tervalidasi, laporan November dan seluruh PDF September-November tetap terkunci. Persetujuan final saja tidak membuka PDF tersebut.

Teacher/Admin tetap dapat menyusun dan meninjau draft internal sebagai bagian proses persetujuan. Hak tinjauan internal dipisahkan dari hak unduh portal anak dan kesiapan mengirim dokumen final.

### 11.4 Pemeriksaan pada server

- Penguncian berlaku pada detail, pratinjau, unduh berkas, dan aksi pengiriman yang dilindungi.
- Backend memeriksa ulang persetujuan/pembayaran ketika permintaan dilakukan, bukan hanya ketika halaman pertama dimuat.
- Berkas final, bukti transfer, dan bukti absensi tidak menggunakan tautan publik permanen yang melewati pemeriksaan akses.
- URL yang pernah diketahui pengguna tidak boleh menjadi jalan pintas untuk membuka rapor anak lain atau rapor yang terkunci.
- Pembayaran tidak otomatis menyetujui rapor. Visibilitas absensi tidak mengubah status pembayaran.

## 12. Template dan pembuatan PDF final

### 12.1 Sumber dan format dasar

Contoh **Student's Evaluation Dina_3.pdf** menjadi acuan susunan final yang memuat Note. **template rapor.pdf** menjadi acuan tambahan format lembaga/rubrik. PDF menggunakan ukuran **A4 portrait** dengan kop lembaga, tabel empat skill, dan penutup sesuai contoh klien.

### 12.2 Urutan isi

1. Kop: logo, nama Dharma Private English, SK, slogan, alamat, dan telepon.
2. Judul: Dharma Private English Student's Report.
3. Student's Name dan identitas periode yang diperlukan.
4. Scoring Rubric: A (90-100), B (80-89), C (70-79).
5. Learning Period, menyebut tiga bulan dan tahun.
6. Tabel `No | Skills | Descriptions | Score` dengan urutan Speaking, Reading, Listening, Writing.
7. **Note** tepat di bawah tabel skill, berisi catatan umum Teacher untuk orang tua.
8. Informasi dokumentasi pembelajaran dan akun Instagram resmi.
9. Narasi penutup lembaga.
10. Nama dan tanda tangan Teacher serta Manager of Dharma Private English.

PDF final tidak menambahkan tabel absensi, daftar delapan kategori progres, Challenges terpisah, Teacher's Message terpisah, atau daftar tautan video. Informasi tersebut tetap tersedia pada portal sesuai hak akses. Teacher dapat merumuskan perhatian/saran yang relevan di dalam Note tanpa menyalin catatan internal.

### 12.3 Data dinamis dan identitas

- Nama siswa, periode, deskripsi empat skill, skor, Note, nama Teacher, dan identitas penanggung jawab mengikuti data rapor.
- Nama Krisna/Dina, periode contoh, dan nama Teacher contoh bukan nilai tetap untuk seluruh PDF.
- Identitas Manager, narasi penutup, daftar Instagram, dan aset kop menjadi konfigurasi lembaga.
- Aset tanda tangan Teacher dikaitkan dengan identitas Teacher terkait; aset Manager dikelola melalui pengaturan lembaga. Identitas penandatangan harus sesuai data yang disetujui untuk rapor tersebut.
- Jika aset belum tersedia pada tahap coding awal, gunakan placeholder yang jelas. Jangan menganggap tanda tangan pada contoh berlaku untuk semua Teacher.

Identitas yang terdapat pada contoh klien:

| Elemen | Nilai acuan contoh |
| --- | --- |
| Nama | Dharma Private English |
| SK | 421.8/5262/DIKPORA |
| Alamat | Jl. Jaya Giri No. 29, Denpasar 80233 Bali-Indonesia |
| Telepon | 0817560387 / 081547653590 |
| Slogan | We give you the best teaching of English |
| Instagram | @dharmaprivatenglish |
| Instagram cabang pada contoh | @dharmaprivatenglishcabang1, @dharmaprivatenglishcabang2, @dharmaprivatenglishcabang3 |
| Manager pada contoh | Ketut Setia Dewi, S.S. |

Nilai tersebut merupakan acuan materi yang diberikan klien, bukan hasil pemeriksaan akun publik atau penetapan nomor pengirim gateway. Akun WhatsApp pengirim tetap mengikuti koneksi Teacher.

### 12.4 Tata letak dan versi PDF

- Pertahankan gaya kop, tabel, warna kepala tabel, dan pemisahan penutup dari contoh klien.
- Jumlah halaman mengikuti panjang deskripsi dan Note. Contoh dua/tiga halaman tidak menjadi batas jumlah halaman.
- Teks panjang membungkus dengan benar; judul kolom diulang ketika tabel berlanjut; kop dan isi tidak saling menimpa.
- Note ditempatkan setelah seluruh tabel skill, meskipun harus berlanjut ke halaman berikutnya.
- Simpan versi rapor/template yang digunakan pada dokumen terkirim agar riwayat menunjukkan isi yang benar-benar dikirim saat itu.
- Perubahan isi setelah persetujuan memerlukan tinjauan ulang sebelum mengirim versi baru. Dokumen terkirim sebelumnya dipertahankan sebagai riwayat, bukan diam-diam diganti dengan isi baru.

## 13. WhatsApp semiotomatis

### 13.1 Dua pilihan berbagi

Teacher pemilik rapor dapat memilih ulang untuk setiap siswa/percobaan:

| Pilihan | Perilaku |
| --- | --- |
| Kirim tautan rapor | Pesan memuat URL detail rapor pada portal anak. Penerima login dengan akun anak; kepemilikan, persetujuan, pembayaran dan PDF diperiksa kembali. |
| Kirim file PDF | Unduh PDF final yang disetujui, buka chat tujuan dengan pesan disiapkan, lalu petugas melampirkan PDF dan menekan Kirim sendiri. |

WhatsApp aktif pada perangkat menentukan akun pengirim. Aplikasi DPE tidak memasukkan nomor Teacher sebagai perintah mengganti pengirim, tidak menautkan akun melalui QR, dan tidak mengetahui status diterima/dibaca. Laptop dapat memakai WhatsApp Web atau aplikasi desktop; browser dapat meminta konfirmasi pembukaan aplikasi.

### 13.2 Penerima dan pratinjau

Tujuan berasal dari tepat satu kontak utama milik anak pada data wali/penerima rapor. Hubungan kontak dapat menyebut orang tua/wali atau siswa sendiri. Nomor 08 dinormalisasi ke 628; format internasional, +, 00, spasi, kurung dan tanda hubung ditangani sebagai satu nomor. Daftar nomor, URL, atau nomor tidak sah ditolak. Jika kontak utama belum jelas, petugas memperbaiki data melalui modul kontak yang berwenang; sistem tidak memilih kontak sembarang.

Pratinjau memuat Teacher pengelola/kontak terdaftar, siswa, periode, penerima dan nomor tujuan. Sapaan dipilih Netral/Anda (default), Ibu, Bapak atau Nama penerima; tidak menebak jenis kelamin dari nama atau nomor. Salam mengikuti waktu cabang saat pesan disiapkan. Template dasar klien tetap menjadi sumber, dengan identitas Teacher, siswa dan cabang sesuai data. Petugas memperbarui pratinjau sesudah mengganti metode/sapaan.

### 13.3 Pengamanan dan riwayat

Server memeriksa hak akses, tiga laporan bulanan Approved dengan salinan yang sesuai, final Approved, tiga pembayaran tervalidasi, portal anak aktif, kontak utama dan PDF terkini yang valid. Hash sumber bulanan pada final harus sesuai dengan persetujuan bulanan saat ini. Pemeriksaan diulang sebelum membuka WhatsApp, mengunduh PDF untuk berbagi dan mengonfirmasi pengiriman. Form lama tidak dapat mengganti nomor, pesan, metode atau identitas dengan payload browser. Klik ganda pada persiapan yang sama mengembalikan catatan yang sama.

Teacher pemilik mengirim; Admin Cabang hanya memantau rapor cabangnya. Super Admin dapat membantu, dengan identitas operator disimpan terpisah dari Teacher pengelola. Akun WhatsApp pengirim aktual tetap tidak dapat diverifikasi oleh DPE. Konfirmasi pengiriman hanya dapat dilakukan operator yang menyiapkan pesan tersebut.

Riwayat baru memakai report_whatsapp_shares, terpisah dari report_share_attempts lama. Tersimpan metode, sapaan, versi PDF/persetujuan, identitas petugas/Teacher/kontak, nomor tujuan, pesan, URL portal, waktu persiapan, permintaan buka, unduh dan konfirmasi. Data kontak/pesan historis tidak berubah mengikuti edit profil berikutnya. Berkas, catatan internal dan token login tidak dijadikan tautan publik.

### 13.4 Arti status dan penggunaan

- Pesan disiapkan: pratinjau disimpan; belum ada permintaan membuka WhatsApp.
- Permintaan buka WhatsApp dicatat: aplikasi mengarahkan browser ke click-to-chat. Ini belum membuktikan aplikasi WhatsApp benar-benar terbuka atau pesan terkirim.
- Dikonfirmasi terkirim oleh petugas: operator menyatakan sudah mengirim di WhatsApp. Bukan status otomatis diterima/dibaca.

Opsi PDF mengharuskan unduhan melalui alur berbagi sebelum konfirmasi, serta pernyataan bahwa PDF sudah dilampirkan. Sistem tidak dapat membuktikan isi lampiran yang akhirnya dipilih di WhatsApp. Pesan tersimpan lebih dari 30 menit perlu disiapkan ulang sebelum buka/unduh; pengiriman yang sudah dilakukan masih dapat dikonfirmasi selama sumber dan hak akses tetap sah.

Tautan rapor mengarah ke portal yang meminta login. Setelah login Siswa/Parent dari tautan tersebut, pengguna kembali ke rapor yang dituju; URL tujuan luar aplikasi tidak diikuti. Penerima tetap tidak dapat membuka anak lain. Untuk uji lintas perangkat gunakan alamat DPE yang dapat dijangkau penerima; localhost/127.0.0.1 hanya menunjuk perangkat yang membuka tautan. PDF yang sudah diterima melalui WhatsApp merupakan salinan; pencabutan pembayaran mengunci akses portal/berbagi berikutnya, bukan menarik kembali berkas yang sudah dikirim.

### 13.5 Template pesan WhatsApp dari klien

Template dasar berikut diberikan pemilik proyek. Nama Ema, Eka, dan Cabang 2 merupakan isi contoh; identitas tersebut diganti dengan data Teacher, siswa, dan cabang yang benar ketika pengiriman dipersonalisasi. Teks dasar dipertahankan sebagai acuan pesan klien.

```text
Selamat malam Ibu, mohon maaf mengganggu waktunya. Saya Ema, tutor les Bahasa Inggris Eka di Dharma Private English Cabang 2.

Saya ingin menyampaikan raport hasil pembelajaran siswa selama 3 bulan terakhir. Terima kasih atas dukungan Bapak/Ibu selama ini dalam mendampingi proses belajar anak.

Saya sangat menghargai usaha dan perkembangan yang telah ditunjukkan oleh siswa selama periode ini, dan saya berharap Ibu juga dapat terus memberikan apresiasi serta dukungan di rumah agar motivasi belajar siswa tetap terjaga.

Perlu saya sampaikan bahwa penilaian ini dilakukan berdasarkan performa siswa selama 3 bulan terakhir, baik dari segi pemahaman materi, keaktifan, maupun konsistensi dalam belajar.

Sebagai penutup, saya mohon bantuan Ibu untuk terus mengingatkan siswa agar lebih rutin dan disiplin dalam mengikuti les, karena kehadiran dan keterlibatan selama proses belajar sangat berpengaruh terhadap perkembangan kemampuan dan hasil belajar siswa.

Apabila terdapat kendala atau pertanyaan terkait raport maupun perkembangan siswa, Ibu dapat menghubungi saya kapan saja.

Terima kasih atas perhatian dan kerja samanya.

Salam hangat,

Ms Ema
```

| Isi contoh | Sumber personalisasi |
| --- | --- |
| Ema pada perkenalan | Nama Teacher pengirim |
| Eka | Nama siswa terkait rapor |
| Cabang 2 | Nama/label cabang yang sesuai penugasan siswa |
| Ms Ema pada penutup | Nama tampilan/tanda salam Teacher; gelar tidak dipaksakan sama untuk semua Teacher |

Sapaan **Selamat malam** dan **Ibu** merupakan isi template dasar. Sesuai keputusan terbaru, salam mengikuti waktu persiapan dan sapaan dipilih petugas: netral, Ibu, Bapak, atau nama penerima. Sistem tidak menebak sapaan dari nomor telepon. Pratinjau memperlihatkan teks yang disiapkan untuk WhatsApp; pengirim aktual mengikuti akun WhatsApp perangkat.

Backend mengisi variabel pesan dari data yang berwenang dan menjaga akses serta pencatatan. Frontend menampilkan pratinjau dan membuka WhatsApp atas tindakan petugas. Pengiriman akhir dilakukan petugas di WhatsApp.

## 14. Notifikasi, monitoring, pencarian, dan ekspor

### 14.1 Notifikasi dalam aplikasi

Pusat notifikasi menampilkan pemberitahuan sesuai peran/cabang/penugasan. Contoh pemicu: laporan Submitted, revisi diminta, rapor Approved, tenggat mendekat/terlewati, target video belum lengkap, pembayaran menunggu/ditolak/tervalidasi, absensi Teacher perlu pemeriksaan, dan koneksi/pengiriman WhatsApp bermasalah.

Pengingat laporan cukup berada dalam aplikasi. Pengiriman rapor melalui WhatsApp adalah proses tersendiri; reminder tidak otomatis dikirim melalui WhatsApp.

### 14.2 Monitoring

- Super Admin melihat ringkasan seluruh cabang.
- Admin melihat ringkasan cabang sendiri.
- Mulai v44, kartu rinci Monitoring laporan tidak ditampilkan lagi pada dashboard Admin Cabang; v44.1 menerapkan revisi yang sama pada dashboard Super Admin. v44.2 menambahkan dua panel baru pada Super Admin sesuai gambar pemilik: Monitoring rapor bulanan dengan Completed/Pending/Overdue serta prioritas Teacher, dan Ringkasan cabang berupa batang jumlah siswa aktif. Bentuk empat kartu monitoring lama tetap tidak ditampilkan pada kedua dashboard tersebut. Ringkasan lengkap periode, persetujuan, tenggat, video, dan pembayaran tersedia melalui menu Monitoring Laporan. Kartu Review laporan dan aktivitas cabang pada dashboard Admin, ringkasan utama serta Jadwal hari ini pada Super Admin, dan monitoring Teacher tetap berfungsi.
- Teacher melihat siswa/tugasnya sendiri.
- Dashboard menampilkan total siswa/guru aktif, siswa per cabang/program, laporan selesai/belum selesai, target video, aktivitas terbaru, pembayaran, serta absensi/pengiriman yang relevan.
- Monitoring per Teacher memperlihatkan tanggung jawab laporan, jumlah Approved/Pending, dan kekurangan video.
- Bedakan status persetujuan, overdue, pembayaran, dan hasil pengiriman. Satu badge tidak menggabungkan seluruh dimensi tersebut.
- Angka total pada kartu dan daftar memakai filter periode/cabang yang sama agar hasilnya dapat ditelusuri.

### 14.3 Pencarian, filter, dan ekspor

Pencarian/filter mendukung kebutuhan modul, termasuk nama siswa, Teacher, cabang, program, grade, status, tanggal/periode, mode mengajar, dan status laporan/pengiriman. Filter akses tetap berlaku walaupun pengguna mengubah parameter permintaan.

Ekspor mendukung data siswa, Teacher, absensi, laporan/progres, video, jadwal, dan slot kosong sesuai hak akses. Format yang diminta klien adalah Excel/PDF. Ekspor internal berbeda dari PDF rapor resmi untuk orang tua; template dan izin akses tidak disamakan.

## 15. Acuan model data dan konsistensi implementasi

Daftar berikut merupakan hubungan data konseptual, bukan DDL atau keputusan teknologi database final.

| Entitas konseptual | Hubungan/informasi penting |
| --- | --- |
| Akun dan peran | Peran, status, cabang/Teacher/siswa terkait; akun anak menunjuk satu siswa. |
| Cabang | Identitas, alamat, status, tanggal rekap, visibilitas absensi, data publik. |
| Teacher | Akun, profil, cabang/penugasan, nomor WhatsApp, aset tanda tangan. |
| Siswa | Akun anak, orang tua, tanggal masuk, program, cabang, penerima rapor. |
| Program | Nama, status, konfigurasi dan deskripsi publik. |
| Kelas dan peserta | Teacher, program, tempat/mode, peserta serta tanggal mulai keterlibatan. |
| Jadwal/pertemuan | Tanggal aktual, waktu, kelas/Teacher, hubungan jadwal ulang. |
| Slot kosong | Teacher, tanggal, waktu, jenis ketersediaan. |
| Absensi siswa | Siswa dan pertemuan unik, status, pencatatan/koreksi. |
| Absensi Teacher | Jenis kunjungan/sesi, cabang/jadwal, pasangan awal-akhir, bukti, validasi. |
| Catatan pembelajaran | Topik, materi, aktivitas, progres, catatan internal dan publik yang terpisah. |
| Video dan target periode | Siswa, bulan, tautan, metadata, target yang ditetapkan. |
| Laporan bulanan | Siswa-bulan, status, isi publik/internal, versi, keputusan Admin. |
| Rapor final | Siswa, tiga bulan, empat deskripsi/skor, Note, status dan versi. |
| Pembayaran/bukti | Siswa-bulan, bukti, status, keputusan Super Admin dan riwayat. |
| Kontak WhatsApp | Nomor profil Teacher dan tepat satu wali/penerima utama siswa; tidak menyimpan sesi WhatsApp. |
| Berbagi WhatsApp | Rapor/versi PDF, Teacher, operator, snapshot penerima/pesan, metode, waktu persiapan/buka/unduh/konfirmasi manual. |
| Dokumen PDF | Rapor/versi/template, waktu pembuatan, berkas, referensi pengiriman. |
| Konten website | Draft dan versi ditayangkan, halaman, aset, urutan/visibilitas. |
| Notifikasi dan audit | Sasaran sesuai akses, peristiwa, operator, waktu, perubahan dan alasan. |

Aturan konsistensi yang harus dijaga:

1. Satu catatan absensi siswa untuk satu siswa pada satu pertemuan.
2. Laporan bulanan dan pembayaran terhubung ke siswa dan bulan kalender yang benar.
3. Rapor final memiliki tepat tiga bulan dalam siklus siswa dan mempertahankan hubungan ketiga laporan.
4. Persetujuan mengacu pada versi isi. Perubahan setelah persetujuan memerlukan tinjauan ulang sebelum versi baru dipakai untuk pengiriman.
5. Foto, waktu, lokasi, dan koreksi absensi Teacher dapat ditelusuri tanpa menghapus nilai awal.
6. Catatan pengiriman menyimpan nomor tujuan/pengirim serta versi dokumen saat pengiriman. Perubahan profil berikutnya tidak mengubah riwayat yang sudah terjadi.
7. Identitas pengirim berasal dari koneksi Teacher yang benar, bukan hanya field nomor telepon pada profil.
8. Menyembunyikan cabang/program publik tidak menghapus hubungan data operasional.
9. Status pembayaran, persetujuan, visibilitas, validasi absensi, dan pengiriman disimpan sebagai dimensi terpisah.

## 16. Pengamanan dan perilaku aplikasi

- Autentikasi menentukan peran dari akun, bukan dari pilihan role pada halaman login.
- Server membatasi objek berdasarkan peran, cabang, penugasan, dan hubungan akun anak.
- Simpan kata sandi menggunakan mekanisme hashing yang sesuai teknologi implementasi. Kredensial tidak disimpan sebagai teks biasa.
- Batasi/validasi unggahan bukti transfer, foto, dan aset yang dibutuhkan sesuai format/ukuran yang ditetapkan saat coding.
- Lindungi foto absensi, lokasi, bukti transfer, PDF final, dan sesi WhatsApp dari akses pengguna yang tidak berwenang.
- Form dan aksi penting mempunyai validasi server, penanganan error, serta pengaman permintaan berulang.
- Audit mencatat validasi pembayaran, persetujuan/revisi, koreksi absensi, perubahan akses/pengaturan cabang, publikasi konten, dan percobaan pengiriman.
- Kamera dan lokasi digunakan pada saat tindakan absensi Teacher, bukan untuk pelacakan terus-menerus.
- Waktu pencatatan memiliki timezone operasional yang konsisten. Nilai timezone aplikasi/cabang ditetapkan pada konfigurasi implementasi, tidak mengikuti timezone browser secara diam-diam.
- Data dummy digunakan selama pengembangan. Skenario pengujian pengiriman memakai lingkungan/nomor uji yang ditentukan sebelum integrasi nyata dijalankan.

## 17. Kriteria penerimaan sebelum aplikasi dinyatakan siap

Daftar ini merupakan pemeriksaan untuk coding/pengujian berikutnya. Status prototipe selesai tidak menandai daftar ini telah lulus.

| Area | Hasil yang harus dapat dibuktikan |
| --- | --- |
| Autentikasi dan akses | Admin tidak membuka cabang lain, Teacher tidak membuka penugasan lain, akun anak tidak membuka siswa lain, termasuk melalui URL/permintaan langsung. |
| Company profile | Halaman publik muncul sebelum login. Draft belum tampil publik; Tayangkan mengubah konten yang dipilih. |
| Cabang/program | Tambah cabang dapat dilakukan tanpa perubahan kode; sembunyikan publik mempertahankan data operasional. |
| Jadwal | Klik kartu memperlihatkan peserta yang benar dan membuka absensi kelas yang tepat. |
| Siswa baru | Siswa masuk 15 Februari hanya memiliki pencatatan Februari sejak tanggal masuk; PDF pertama Februari-April. |
| Pertemuan tambahan | Pertemuan kesembilan dapat dicatat tanpa mengganti/menimpa delapan catatan sebelumnya. |
| Absensi siswa | Kosong tetap Belum Dicatat, Late termasuk hadir, tanggal pertemuan berbeda dari waktu input jika dicatat terlambat. |
| Jadwal ulang | Pertemuan pengganti masuk bulan pelaksanaan dan tidak menghasilkan kehadiran ganda. |
| Visibilitas | GatSu Aktif dan Renon Nonaktif dapat berjalan bersamaan. Menyimpan satu cabang tidak mengubah cabang lain. |
| Absensi Teacher cabang | Dua kunjungan dalam sehari menghasilkan dua pasangan datang-pulang dan riwayat yang terpisah. |
| Absensi Teacher private | Setiap sesi memiliki awal/akhir, foto langsung, dan lokasi/alasan pengecualian yang tepat. |
| Validasi Teacher | Teacher dapat menyelesaikan sesi sebelum Admin memvalidasi. Koreksi mempertahankan riwayat nilai awal dan alasan. |
| Target video | Target bulan pertama dari Admin mengubah indikator siswa-bulan yang tepat; target bulan sebelumnya dapat ditelusuri. |
| Laporan bulanan | Submitted dapat dikembalikan dengan alasan, diajukan ulang, dan disetujui tanpa bercampur dengan status final. |
| Rapor final | Teacher memilih A/B/C dan menulis empat deskripsi/Note; Admin meninjau versi final yang sama. |
| Penguncian | Satu pembayaran atau laporan bulanan belum valid mengunci PDF seluruh periode; laporan bulan lain yang memenuhi syarat tetap terbuka. |
| Persetujuan final | Tiga laporan bulanan Approved belum membuka PDF jika rapor final belum Approved. |
| Akses berkas | Akun yang tidak berwenang atau belum memenuhi syarat tidak dapat mengunduh lewat tautan berkas langsung. |
| PDF | Isi memuat empat skill dan Note; identitas/periode dinamis; catatan internal dan bagian di luar template tidak bocor. |
| Layout PDF | Narasi/Note panjang tidak terpotong atau bertabrakan dengan kop/tanda tangan. |
| WhatsApp | Penerima diambil dari kontak utama; petugas memeriksa akun WhatsApp aktif sebelum mengirim. Tautan rapor tetap memerlukan login akun anak. |
| Status berbagi | Buka WhatsApp tidak dianggap terkirim; konfirmasi manual dibedakan dari status diterima/dibaca yang tidak tersedia. |
| Klik/coba ulang | Klik ganda persiapan yang sama menghasilkan satu catatan. Data yang berubah mengharuskan pratinjau baru; sumber diperiksa ulang pada setiap tindakan. |
| Ekspor | Data hasil ekspor mengikuti peran/cabang/filter dan tidak membuka data internal pada portal anak. |
| Responsif | Tindakan utama, kamera, lokasi, unggah bukti, tabel, dan PDF dapat digunakan pada ukuran desktop/mobile yang ditargetkan. |

## 18. Penutupan prototipe dan hal yang menyusul saat coding

### 18.1 Status kelompok

| Kelompok | ID | Status tahap prototipe |
| --- | --- | --- |
| Public dan Login | P01-P06 | Selesai |
| Super Admin | S01-S08 | Selesai |
| Admin Cabang | A01-A05 | Selesai |
| Teacher | T01-T09 | Selesai |
| Siswa/Parent | C01-C04 | Selesai |
| Variasi, detail, modal, dan alur | Bagian layar terkait | Dianggap selesai sebagai acuan implementasi |

Pembuatan paket SVG atau desain prototipe baru tidak menjadi pekerjaan tahap berikutnya. Penyempurnaan tampilan dapat dilakukan langsung pada aplikasi selama coding dengan mempertahankan aturan dan arah visual dalam dokumen ini.

### 18.2 Hal yang belum ditetapkan secara teknis

| Hal | Perlakuan sekarang | Waktu penyelesaian |
| --- | --- | --- |
| Stack dan struktur proyek | Source v26: Laravel 12.69.3, Blade, CSS/JavaScript lokal, MySQL; PHP pengguna 8.3 | Melanjutkan struktur yang tersedia |
| Database/hosting/deployment | Skema database mengikuti backend teman; konfigurasi menjalankan/deployment perlu dilengkapi | Saat pemeriksaan paket dan perencanaan teknis |
| WhatsApp | Semiotomatis, dua pilihan per pengiriman sesuai keputusan terbaru | Implementasi v55; uji WhatsApp Web/aplikasi dan penerima nyata dilakukan pemilik |
| Template pesan WhatsApp | Sudah tersedia pada bagian 13.5; personalisasi identitas mengikuti data aktual | Saat integrasi pratinjau/pengiriman; variasi sapaan disepakati bila dibutuhkan |
| Aset logo/foto/tanda tangan final | Gunakan materi contoh yang tersedia atau placeholder yang jelas | Saat penyiapan aset produksi |
| Pembayaran | Bukti transfer dan validasi bulanan; nominal tagihan bukan isian wajib yang disepakati. Tanggal rekap tetap per cabang | Sesuaikan modul pembayaran pada tahapnya |
| Timezone dan batas file | Konfigurasi implementasi, bukan keputusan tersembunyi | Saat implementasi modul terkait |
| Instalasi HP/PWA dan toko aplikasi | Kebutuhan lanjutan yang pernah disebutkan; belum menjadi paket implementasi terperinci | Jika pemilik memasukkannya dalam scope teknis berikutnya |

Daftar tersebut tidak membuka kembali pembuatan prototipe. Alur bisnis yang diperlukan sudah tersedia; rincian teknis akan dilengkapi saat pemilik menjelaskan tahap coding.

### 18.3 Catatan revisi versi 1.0

- Menutup tahap prototipe sesuai arahan pemilik dan mengganti catatan progres lama yang masih menyebut S01 saja.
- Memperbarui daftar dari 23 menjadi 32 jenis layar utama.
- Melengkapi company profile P03-P06 dan pengelolaannya pada S08.
- Memasukkan T08/A05/S07 untuk absensi Teacher serta T09 untuk absensi siswa.
- Menetapkan absensi cabang per kunjungan dan private di rumah siswa per sesi, dengan pencatatan awal/akhir.
- Menetapkan bulan kalender, tanggal masuk siswa, delapan pertemuan sebagai target normal, dan tanggal pertemuan sebagai tanggal absensi.
- Menetapkan target video bulan pertama oleh Admin berdasarkan pertemuan terjadwal.
- Mengganti berbagi WhatsApp manual dengan pengiriman otomatis melalui gateway dari nomor Teacher.
- Menambahkan pengelolaan koneksi Teacher, identitas penerima, status provider, dan riwayat pengiriman.
- Memisahkan laporan bulanan dari rapor final tiga bulanan beserta persetujuannya.
- Menetapkan PDF final empat skill, skor A/B/C, Note, identitas/tanda tangan, dan format template klien.
- Memperbarui aturan visibilitas absensi: portal/laporan bulanan mengikuti cabang; PDF final mengikuti template tanpa absensi.
- Melengkapi matriks akses, penguncian berkas, hubungan data, dan kriteria penerimaan implementasi.

**Acuan versi ini:** requirement PDF klien, riwayat `project siswa(1).txt`, `dpe-design-v0.5(1).md`, `template rapor.pdf`, `Student's Evaluation Dina_3.pdf`, serta keputusan percakapan sampai 7 Oktober 2026. Referensi produk lama yang tidak dilampirkan tidak diperlukan untuk memahami aturan utama dalam dokumen ini.

### 18.4 Catatan revisi versi 1.1

- Memasukkan teks WhatsApp yang diberikan klien dan mengakhiri status menunggu template pesan.
- Memetakan nama Teacher, siswa, cabang, dan penutup sebagai isi yang dipersonalisasi.
- Mencatat kebutuhan penyesuaian sapaan tanpa mengubah teks dasar secara diam-diam.
- Menetapkan pembagian kerja: teman pemilik mengerjakan database/backend; pemilik bersama AI mengerjakan frontend.
- Menambahkan paket serah terima teknis dan urutan pekerjaan frontend per kelompok.

### 18.5 Catatan revisi versi 1.2

- Menetapkan Laravel dan PHP 8.3 sebagai basis proyek, dengan halaman/view yang sudah tersedia untuk dilanjutkan.
- Mencatat PHP 8.3.30, Composer 2.4.1, Node.js 24.12.0, dan npm 11.6.2 dari terminal pemilik proyek.
- Menetapkan pemeriksaan versi Laravel dan alat frontend dari source/lockfile sebelum menentukan pola implementasi.
- Memperbarui otorisasi: AI dapat memperbaiki backend yang kurang sesuai setelah memberi tahu pemilik sebelum perubahan dimulai.

## 19. Serah terima backend dan pekerjaan frontend

### 19.1 Tujuan tahap berikutnya

AI membangun frontend sesuai dokumen ini, mengikuti struktur proyek yang diberikan, dan mengintegrasikan fungsi ke backend teman pemilik proyek. Status database/backend selesai berasal dari keterangan pemilik; kesesuaian seluruh endpoint dengan keputusan terbaru akan diperiksa pada awal integrasi.

Basis proyek adalah Laravel dengan PHP 8.3 dan halaman/view yang sudah tersedia. Frontend melanjutkan view dan pola integrasi dalam proyek tersebut. Teknologi UI seperti Blade, komponen interaktif, bundler, dan pustaka CSS akan dipastikan dari source; keberadaan view tidak dipakai untuk menebak semua dependensi proyek.

`DESIGN.md` menjadi acuan fitur, tampilan, alur, hak akses, dan kriteria penerimaan. PRD ringkas dapat menjadi ringkasan scope jika dibutuhkan tim. Dokumen API dan petunjuk menjalankan proyek menjadi penghubung teknis utama antara frontend dan backend.

### 19.2 Paket yang perlu diberikan teman backend

| Berkas/informasi | Isi yang diperlukan | Prioritas |
| --- | --- | --- |
| ZIP source project | Kode backend, struktur folder, konfigurasi proyek yang aman, route/controller/service/middleware, serta frontend/view yang sudah ada jika ada | Wajib |
| Ekspor database atau migration + seeder | Struktur yang sesuai versi source, relasi, dan data dummy yang cukup untuk mencoba alur | Wajib |
| `README_SETUP.md` atau petunjuk setara | Bahasa/framework dan versi, dependensi, perintah instalasi, konfigurasi, impor/migrasi, cara menjalankan, URL/port aplikasi | Wajib |
| `.env.example` atau contoh konfigurasi | Nama variabel, nilai contoh lokal, base URL, kebutuhan storage/queue/gateway, tanpa kredensial produksi | Wajib |
| Dokumen API | Endpoint aktual, method, autentikasi/izin, parameter, body, format response, error, pagination/filter, upload/download | Wajib bila backend memakai API; sesuaikan dengan bentuk backend |
| Contoh request/response atau koleksi pengujian API | Contoh sukses dan gagal untuk modul penting, termasuk login, laporan, pembayaran, absensi, upload, PDF, dan pengiriman | Sangat membantu |
| Akun/data uji empat peran | Kredensial lokal/demo, cabang/penugasan/anak terkait, serta skenario draft, revisi, terkunci, tervalidasi, dan gagal kirim | Wajib untuk integrasi dan pemeriksaan akses |
| Manifest dependensi dan lockfile | Misalnya file dependensi PHP atau Node sesuai stack aktual; versi konsisten dengan kode backend | Wajib bila dipakai proyek |
| Aset/storage contoh | Logo, foto contoh, bukti dummy, dan file yang dirujuk database; atau cara membuat ulang aset uji | Diperlukan untuk modul terkait |
| Catatan status fitur backend | Daftar selesai/belum tersedia, endpoint stub, perubahan kebutuhan yang belum diterapkan, dan pekerjaan proses latar | Wajib untuk menentukan batas integrasi |
| Petunjuk integrasi frontend | View server atau frontend terpisah, base URL, cookie/token, CSRF/CORS jika relevan, peran/status yang dikembalikan, dan format tanggal/waktu | Wajib |

ZIP source dan ekspor database harus berasal dari revisi yang sama. Kode, skema, dan contoh response tidak boleh mendeskripsikan tiga versi backend yang berbeda. Data uji memakai identitas dummy dan nomor uji yang ditetapkan.

### 19.3 Informasi minimum setiap endpoint/modul

Untuk tiap endpoint yang digunakan frontend, catat URL/method aktual, peran yang diizinkan, parameter/filter, contoh request, contoh response, status/error, serta tipe/ukuran file jika memakai upload. Jelaskan autentikasi, sesi/login/logout, pemetaan role, aturan cabang/penugasan, pagination, format enum/status, dan akses file terlindungi.

Untuk backend dengan view server, jelaskan route, template/view, data yang dikirim controller, aksi form, validasi, serta mekanisme sesi. Frontend tidak diasumsikan harus berupa aplikasi terpisah sebelum arsitektur aktual diketahui.

Perubahan terbaru yang perlu dicocokkan khusus:

- Absensi Teacher cabang per kunjungan dan private per sesi.
- Absensi siswa dengan pertemuan tambahan dan siswa masuk pertengahan bulan.
- Target video bulan pertama dari Admin.
- Persetujuan laporan bulanan dan rapor final sebagai objek terpisah.
- Rapor empat skill, A/B/C, Note, dan periode tiga bulan kalender.
- Penguncian berdasarkan tiga laporan bulanan, final Approved, dan tiga pembayaran.
- WhatsApp banyak nomor Teacher, template pesan, dan status pengiriman sebenarnya.
- Visibilitas absensi portal/laporan bulanan dan PDF final tanpa absensi.

### 19.4 Batas tanggung jawab

| Pekerjaan | Pemilik utama |
| --- | --- |
| Layout, navigasi, komponen, form, tabel, filter, responsivitas, loading/error/empty states | Pemilik proyek bersama AI frontend |
| Pemetaan data backend ke layar dan pengiriman aksi sesuai endpoint | Pemilik proyek bersama AI frontend |
| Skema database, query, validasi server, izin objek, penyimpanan file, proses PDF, gateway, dan status proses latar | Disiapkan teman backend; AI dapat memperbaiki bagian yang belum sesuai setelah memberi tahu pemilik |
| Pemeriksaan kesesuaian alur dan penemuan gap integrasi | Bersama |
| Keputusan perubahan kebutuhan dan kewenangan perbaikan | Pemilik proyek; perubahan teknis dan dampaknya dicatat agar dapat dibagikan ke teman backend |

Frontend menampilkan validasi untuk membantu pengguna; backend tetap menjaga aturan final. Frontend memicu pembuatan/pengiriman PDF melalui fungsi backend yang tersedia. Kredensial database/gateway tidak menjadi bagian kode browser.

Jika fungsi backend belum tersedia, keliru, atau belum sesuai alur, AI mencatat gap beserta kebutuhan data dan dampak layarnya. Pemilik telah mengizinkan perbaikan langsung sesuai kebutuhan sistem. Sebelum memulai perubahan backend, AI memberi tahu temuan, alasan perbaikan, file/modul yang terdampak, serta pengaruhnya pada route, response, database, atau fungsi lain. Setelah itu, perbaikan dilakukan dalam scope yang diizinkan dan diperiksa bersama frontend terkait. Ringkasan perubahan disiapkan agar pemilik dapat menyelaraskan kode dengan teman backend.

### 19.5 Urutan pekerjaan frontend per kelompok

| Tahap | Kelompok | Cakupan | Hasil untuk ditinjau |
| --- | --- | --- | --- |
| 0 | Pemeriksaan proyek dan fondasi | Jalankan backend, pahami kontrak, autentikasi, pemetaan role, layout dan komponen bersama | Aplikasi lokal berjalan; struktur integrasi dan gap terpetakan |
| 1 | Public dan Login | P01-P06 | Company profile, halaman publik, dan login terintegrasi |
| 2 | Super Admin | S01-S08 | Dashboard, cabang, guru, siswa, pembayaran, pengaturan, absensi, dan pengelolaan konten |
| 3 | Admin Cabang | A01-A05 | Operasional, tinjauan bulanan/final, monitoring, absensi Teacher cabang |
| 4 | Teacher | T01-T09 | Pembelajaran, laporan, rapor/Note, pengiriman, jadwal, serta absensi diri/siswa |
| 5 | Siswa/Parent | C01-C04 | Portal satu anak, riwayat belajar, unggah bukti, laporan/PDF terkunci dan tersedia |
| 6 | Pemeriksaan alur lintas peran | Skenario pada bagian 17 | Alur dari pencatatan, tinjauan, pembayaran, akses, sampai pengiriman konsisten |

Tahap 0 dan 6 bukan tambahan layar utama. Urutan dapat menyesuaikan dependensi endpoint yang ditemukan. Pengerjaan tetap satu kelompok per tahap, dilengkapi integrasi dan pemeriksaan yang relevan, lalu berhenti untuk peninjauan pemilik sebelum kelompok berikutnya.

### 19.6 Kriteria selesai setiap kelompok frontend

Kelompok selesai ketika tampilan mengikuti arah visual, aksi utama terhubung ke fungsi backend yang tersedia, data asli lingkungan uji tampil, akses/validasi yang relevan diperiksa, dan keadaan loading/kosong/gagal dapat dipahami. Bagian yang masih memakai data dummy atau endpoint stub dicatat secara jelas. Tampilan statis saja tidak disebut integrasi selesai.

### 19.7 Lingkungan kerja yang diberikan pemilik

| Komponen | Versi/keterangan dari terminal |
| --- | --- |
| PHP | 8.3.30 CLI, ZTS, Visual C++ 2019 x64 |
| Framework backend | Laravel; nomor versi belum diberikan |
| Composer | 2.4.1 |
| Node.js | 24.12.0 |
| npm | 11.6.2 |
| Sistem kerja contoh | Windows PowerShell di VS Code, direktori `D:\Kerja\DPE` |
| Frontend yang tersedia | Proyek sudah memiliki halaman/view untuk dilanjutkan |

Catatan tersebut merupakan lingkungan yang dilaporkan pemilik, bukan hasil pemeriksaan source atau uji kompatibilitas dependensi. Versi Laravel dan paket aktual diperiksa pada `composer.json`, `composer.lock`, `package.json`, serta lockfile frontend yang tersedia. Perintah build/run mengikuti konfigurasi proyek. Penyesuaian dependensi yang diperlukan dijelaskan berdasarkan hasil pemeriksaan, bukan dilakukan karena asumsi versi framework.


## 22. Pemeriksaan source dan pembaruan v27

### 22.1 Status implementasi

Paket yang diberikan menjadi baseline v26. Halaman public/login, banyak CRUD Super Admin, pengelolaan siswa/guru Admin, absensi per sesi, monitoring foto/GPS, pemeriksaan, dan pencatatan ketidakhadiran sudah tersedia. Dashboard Teacher/Student dan beberapa menu masih placeholder. Status rancangan selesai tidak berarti seluruh fitur aplikasi sudah selesai.

Pembaruan v27 menyelaraskan unit absensi:
- Cabang: datang–pulang per kunjungan, dapat beberapa kunjungan sehari.
- Rumah siswa: mulai–selesai per sesi dengan lokasi mengajar yang dipilih pada jadwal.
- Ketidakhadiran cabang: satu catatan per guru/cabang/tanggal yang memiliki jadwal; jumlah kelas tidak menggandakan ketidakhadiran.
- Belum tercatat tetap memerlukan konfirmasi Admin; sistem tidak otomatis menganggap guru tidak hadir.
- Catatan v25/v26 dipertahankan sebagai riwayat sesi lama. Catatan lama tidak digabung atau ditebak menjadi kunjungan.
- Lokasi jadwal lama default di cabang. Jadwal private mendatang yang berlangsung di rumah siswa perlu ditandai melalui Edit Sesi.
- Bukti diambil dari kamera melalui halaman web. HTTPS atau localhost diperlukan untuk kamera/GPS. Pengambilan dari kamera pada UI tidak menjadi bukti antimanipulasi terhadap permintaan HTTP buatan.
- Pelaksanaan mengikuti pasangan waktu; pemeriksaan memiliki status tersendiri. Kunjungan/sesi dapat ditutup sebelum Admin memeriksa bukti.
- Riwayat tindakan baru menyimpan snapshot, operator, waktu, dan alasan; riwayat operator sebelum v27 tidak direkonstruksi.

### 22.2 Pekerjaan berikutnya

Tahap berikutnya mengikuti evaluasi pemilik setelah v27 dipasang. Kesenjangan yang masih memerlukan implementasi adalah absensi siswa, progres Teacher, laporan bulanan dan periode siswa yang bergabung pertengahan bulan, rapor final beserta persetujuan/PDF sesuai template, pembayaran dan portal anak yang terlindungi, Fonnte, serta Draft–Preview–Publish konten publik.

Form koreksi lengkap terhadap bukti/waktu absensi, pencabutan status manual, pengaturan visibilitas absensi, dan ekspor merupakan pekerjaan lanjutan. Label Perlu Koreksi pada monitoring saat ini mencatat permintaan dan alasan; bukan klaim bahwa seluruh alur pengeditan sudah tersedia.

Pengembangan tetap satu tahap, diuji, dikirim sebagai ZIP pembaruan dengan instruksi VS Code/Laragon, lalu dievaluasi pemilik sebelum modul berikutnya.

### 22.3 Perbaikan tabel v28

Tombol Edit pada daftar Sesi Belajar Super Admin dipindahkan ke kolom Aksi. Susunan sel mengikuti header: Waktu, Cabang, Guru, Program/Kelas, Status, Aksi. Pemilik telah memasang dan mengonfirmasi perbaikan ini sebelum tahap v29.

## 23. Absensi siswa — pembaruan v29

### 23.1 Cakupan yang tersedia

Pembaruan ini menambahkan halaman Kehadiran Siswa untuk Super Admin/Admin dan Absensi Siswa untuk Teacher. Teacher mengakses sesi yang ditugaskan kepadanya, Admin mengakses cabang penugasannya, dan Super Admin mengakses seluruh cabang. Portal anak serta pengaturan visibilitas absensi pada laporan belum menjadi bagian pembaruan ini.

- Rekap kelas per bulan menyediakan tiga tab bulan kalender, minimal delapan kolom pertemuan, dan kolom tambahan ketika jumlah sesi melebihi delapan. Delapan merupakan target normal, bukan batas pencatatan.
- Sel sebelum siswa mulai les, di luar keanggotaan/pendaftaran pada tanggal sesi, serta pertemuan mendatang tidak menerima pencatatan kehadiran biasa. Kotak kosong tetap berarti belum dicatat.
- Status tersedia: Hadir, Terlambat, Tidak Hadir, Sakit, Izin, dan Dijadwalkan Ulang. Terlambat ikut dihitung hadir dan tetap memiliki jumlah tersendiri.
- Peserta kelas mengikuti keanggotaan dan pendaftaran yang berlaku pada tanggal pertemuan. Peserta private dipilih secara eksplisit oleh Admin/Super Admin pada halaman sesi; sistem tidak memasukkan semua siswa private secara otomatis.
- Centang pada sel baru mencatat Hadir. Perubahan terhadap catatan yang sudah tersimpan membuka halaman detail dan memerlukan alasan koreksi.
- Tanggal pertemuan mengikuti waktu pelaksanaan aktual jika tersedia, atau jadwal semula. Waktu pengisian dan perubahan dicatat oleh server secara terpisah; koreksi mempertahankan waktu pencatatan pertama.
- Jadwal pengganti yang ditautkan harus memiliki cabang, program, dan kelas yang sama. Sumber tidak dihitung sebagai pertemuan hadir tambahan setelah memiliki pengganti yang berlaku. Catatan hadir pada sumber harus dikoreksi sebelum membuat hubungan pengganti.
- Riwayat baru menyimpan nilai sebelum/sesudah, operator, alasan, dan waktu. Data lama, termasuk status lama yang belum dikenali, dipertahankan; operator dan riwayat lama tidak direka ulang.
- Versi catatan dan tanda daftar peserta menjaga agar penyimpanan dari halaman lama tidak menimpa perubahan operator lain.

### 23.2 Integrasi dan database

Catatan per siswa/sesi tetap menggunakan tabel `session_students`, juga sebagai sumber catatan pembelajaran per siswa. Migration `2026_10_10_050000_add_student_attendance_history.php` menambahkan `attendance_updated_at`, `attendance_recorded_by_user_id`, dan `attendance_version`, serta tabel baru `student_attendance_audits`. Tidak ada penghapusan atau penggantian nilai absensi lama.

Route internal tersedia di `/superadmin/student-attendance`, `/admin/student-attendance`, dan `/teacher/student-attendance`. Form menggunakan sesi login Laravel dan validasi server. Endpoint `session-students` lama juga diarahkan melalui aturan pencatatan/koreksi yang sama. Catatan yang sudah memiliki status, waktu pencatatan, atau catatan pembelajaran tidak dapat dihapus melalui penghapusan peserta biasa.

Di Super Admin, tombol Absensi ditambahkan bersama Edit dalam kolom Aksi pada Sesi Belajar. Halaman detail sesi juga menyediakan akses ke peserta/absensi. Tampilan baru menggunakan layout dashboard yang ada dan stylesheet khusus modul; form detail tetap selebar area konten tanpa kartu pembungkus.

### 23.3 Pemeriksaan dan penerapan

Pemeriksaan meliputi hak akses, tanggal mulai, keanggotaan/pendaftaran, pertemuan tambahan, perhitungan status, koreksi/audit, benturan versi, peserta private, jadwal ulang, rendering halaman, dan perilaku JavaScript centang/filter. Migration diuji pada database MySQL terpisah menggunakan fixture skema sebelum v29 untuk memastikan nilai lama tetap tersimpan. Pemeriksaan visual di browser Windows/Laragon tetap dilakukan pemilik setelah memasang pembaruan.

Lingkungan source yang diperiksa: Laravel 12.69.3 dan PHP 8.3; direktori pemasangan pemilik saat ini `C:\laragon\projectles\dpe-webapp`. Pembaruan disampaikan sebagai ZIP patch terhadap v28 dengan instruksi PowerShell langsung di chat. Database lokal perlu dicadangkan sebelum menjalankan migration tambahan; pemasangan tidak memerlukan reset database atau seed ulang.

### 23.4 Kelanjutan

Setelah peninjauan v29, pekerjaan berikutnya mencakup progres Teacher, laporan bulanan, periode dan rapor final sesuai template, pembayaran/portal anak, Fonnte, dan pengelolaan konten publik. Ekspor serta visibilitas absensi pada portal/laporan juga masih perlu diimplementasikan. Setiap tahap tetap dikerjakan dan dievaluasi terpisah.

### 23.5 Penjelasan peserta dan checkbox per sesi — v30

Menindaklanjuti peninjauan pemilik, halaman Buka absensi kini menampilkan kolom Hadir dengan checkbox tepat di sebelah nama setiap peserta. Teacher dapat mencentang dari halaman satu pertemuan atau rekap bulanan. Checkbox baru menyimpan Hadir; checkbox catatan yang sudah tersimpan membuka detail untuk koreksi dengan alasan. Terlambat tetap dicentang sebagai hadir tanpa mengubah status Terlambat menjadi Hadir. Jadwal mendatang menampilkan nama dan checkbox yang belum aktif.

Nama peserta berasal dari penempatan siswa yang sebenarnya, bukan data contoh yang dimasukkan saat instalasi. Alur pemasangan daftar peserta pada versi ini:

1. Super Admin membuka Siswa & Pendaftaran, membuat atau memilih profil siswa. Profil baru membutuhkan satu akun Siswa / Parent; tombol Buat akun siswa tersedia pada form profil.
2. Pada Detail Siswa, pilih Tambah pendaftaran untuk menghubungkan siswa ke cabang/program. Tanggal mulai pendaftaran dan tanggal mulai belajar harus berlaku pada tanggal sesi.
3. Untuk kelas, buka Kelompok Kelas → Detail → Tambah anggota, pilih siswa dan tanggal mulai keanggotaan. Pada Sesi Belajar, kelompok, cabang, program, dan Teacher harus sesuai. Halaman absensi Super Admin menyediakan pintasan Tambah anggota kelas.
4. Untuk private/tanpa kelompok, buka Sesi Belajar → Absensi → Kelola peserta private. Admin/Super Admin memilih nama siswa dan menyimpan peserta. Peserta yang telah memiliki catatan tetap dipertahankan.
5. Teacher membuka Absensi Siswa, memilih kelas/private dan bulan yang sesuai, lalu membuka sesi atau mencentang pada rekap. Nama mengikuti peserta sesi; pencatatan Hadir aktif ketika sesi sudah dimulai.

Halaman kosong menampilkan petunjuk sesuai peran dan tautan pengelolaan untuk pengguna yang berwenang. Pengelolaan anggota kelas pada versi ini tersedia melalui Super Admin; menu Kelompok Kelas Admin masih merupakan pekerjaan lanjutan. Checkbox kehadiran dan checkbox pemilihan peserta private memiliki fungsi yang terpisah.

Pembaruan v30 merupakan penyempurnaan tampilan dan petunjuk terhadap v29. Struktur data serta aturan validasi absensi tetap menggunakan integrasi v29. Pemasangan v30 mengikuti v29 yang telah terpasang dan tidak menambahkan migration baru.

### 23.6 Tombol kembali dan konfirmasi absensi tanpa refresh — v31

Halaman Detail Pendaftaran Siswa kini memiliki tombol Kembali ke Siswa & Pendaftaran yang menuju daftar siswa, sama dengan tujuan menu sidebar. Tombol tersedia setelah pendaftaran berhasil dibuat serta ketika membuka detail pendaftaran yang sudah ada.

Perilaku checkbox v29/v30 diganti dengan konfirmasi pada rekap bulanan dan halaman satu pertemuan. Mengklik checkbox menampilkan popup berisi nama siswa, tanggal pertemuan, status saat ini, dan pilihan status yang akan disimpan. Checkbox tetap mengikuti data tersimpan selama popup terbuka. Batal, Escape, dan klik latar popup menutup konfirmasi tanpa mengirim perubahan. Ya, simpan mengirim permintaan JSON melalui sesi login Laravel; status, checkbox, waktu pencatatan pertama, dan rekap yang tampil diperbarui setelah server mengonfirmasi hasil, tanpa memuat ulang halaman.

Status tersedia tetap Hadir, Terlambat, Tidak Hadir, Sakit, Izin, dan Dijadwalkan Ulang. Pilihan Belum dicatat / kosongkan mengembalikan status ke kosong, bukan otomatis Tidak Hadir. Pengubahan catatan tersimpan membutuhkan alasan koreksi; catatan pembelajaran tidak dihapus oleh pengisian checkbox. Waktu pencatatan pertama dipertahankan dan riwayat koreksi tetap dicatat oleh server.

Tombol terkunci selama penyimpanan berlangsung untuk mencegah pengiriman ganda. Kesalahan validasi atau koneksi ditampilkan di popup dan tidak diartikan sebagai keberhasilan. Jika versi data berubah, server mengembalikan nilai terbaru; tampilan mengikuti nilai tersebut dan pengguna perlu menutup lalu membuka konfirmasi kembali sebelum mengubahnya. Tidak ada percobaan ulang otomatis yang menimpa data operator lain.

Controller mendukung respons JSON untuk pengisian popup dan tetap menggunakan redirect pada form detail biasa. Rekap halaman dan kontribusi tiap sel dihitung melalui service yang sama supaya pembaruan tampilan mengikuti aturan tanggal mulai les, sesi mendatang/dibatalkan, serta jadwal pengganti. Hak akses Teacher, Admin cabang, dan Super Admin tetap diperiksa oleh server.

Pembaruan v31 diterapkan di atas v30. Tidak ada migration baru, reset database, seed ulang, atau perubahan akun yang diperlukan. Pengujian mencakup kontrak JSON, preservasi catatan/waktu, benturan versi, akses, rendering tombol kembali/popup, serta pembatalan, koreksi, klik ganda, dan pembaruan rekap melalui JavaScript. Pemeriksaan visual akhir pada browser Windows/Laragon dilakukan setelah pemasangan.

### 23.7 Penyelarasan tampilan filter dan pemberitahuan — v32

Setelah pemilik memasang dan mengonfirmasi seluruh pengujian v31, tampilan rekap Absensi Siswa disempurnakan sesuai dua gambar peninjauan. Pemberitahuan Rekap belum lengkap diposisikan di antara kartu metrik dan kartu rekap dengan jarak 12 px pada kedua sisi vertikal. Saat pemberitahuan tersembunyi setelah penyimpanan, jarak antarkartu kembali 24 px.

Tombol Tampilkan dan Reset pada filter memakai ukuran yang sama, 140 × 50 px pada desktop, dengan tinggi mengikuti input filter. Keduanya sejajar dengan bagian bawah input dan digeser 12 px ke kanan dari awal kolom aksinya. Filter memakai dua kolom pada layar menengah dan satu kolom pada layar kecil agar tombol tidak melampaui area form. Pada susunan satu kolom, pergeseran dihilangkan dan kedua tombol membagi lebar baris secara merata. Pembaruan hanya mengubah stylesheet modul, tanpa perubahan backend, JavaScript, atau database. Pemasangan dilakukan di atas v31 tanpa migration baru.

## 24. Catatan Belajar per pertemuan — pembaruan v33

### 24.1 Cakupan tahap ini

Setelah pemilik memasang dan mengonfirmasi v32, tahap berikutnya mengaktifkan Catatan Belajar sebagai implementasi T03. Daftar pertemuan memiliki filter bulan, cabang, kelas/private, kelengkapan ringkasan, dan pencarian. Setiap pertemuan menyediakan topik, materi, aktivitas, peserta, referensi kehadiran, dan catatan individual internal. Tombol pada daftar berada di kolom Aksi. Halaman detail memiliki Kembali ke Catatan Belajar dan Buka absensi siswa; halaman absensi per pertemuan juga menyediakan tautan Catatan belajar.

Teacher mengakses sesi yang ditugaskan kepadanya. Admin Cabang dapat melihat dan memperbaiki sesi cabang penugasannya yang masih aktif; Super Admin mengakses seluruh cabang. Menu Catatan Belajar tersedia pada ketiga peran tersebut. Portal anak tidak mendapatkan route atau akses catatan internal pada tahap ini.

### 24.2 Penyimpanan dan kaitan dengan absensi

Topik, materi, dan aktivitas memakai kolom `lesson_sessions` yang sudah tersedia. Catatan individual memakai `session_students.individual_learning_note`, dengan batas 255 karakter sesuai skema sumber. Isian dapat disimpan bertahap; ringkasan pertemuan dianggap lengkap setelah ketiga kolom terisi. Indikator ini merupakan kelengkapan catatan pertemuan, bukan status laporan bulanan atau penilaian skill.

Peserta kelas mengikuti keanggotaan, pendaftaran program/cabang, dan tanggal mulai pada pertemuan. Peserta private mengikuti daftar yang dipilih Admin/Super Admin, bukan seluruh siswa yang terdaftar. Membuka halaman atau menyimpan ringkasan tanpa catatan individual tidak membuat kehadiran maupun peserta dummy. Sesi mendatang, dibatalkan, atau sudah memiliki jadwal pengganti ditampilkan untuk dilihat tanpa menerima perubahan hasil belajar. Catatan sebelum siswa mulai les dan status lama yang belum dikenali tetap dapat dibaca, tetapi tidak diedit dari form ini.

Catatan individual disimpan melalui layanan absensi yang sudah tersedia agar audit, versi, dan hak akses tetap berlaku. Perubahan catatan mempertahankan status, waktu absensi pertama, dan operator pencatatan kehadiran pertama. Catatan awal tanpa status kehadiran tetap memiliki kehadiran kosong, bukan Hadir/Tidak Hadir. Versi catatan bersama diperbarui agar popup absensi dari halaman lama tidak menimpa catatan baru. Catatan internal tidak otomatis disalin menjadi Challenges publik, Teacher's Message, atau isi rapor.

### 24.3 Koreksi, riwayat, dan benturan data

Penyimpanan ringkasan serta seluruh catatan individual berada dalam satu transaksi. Form memeriksa snapshot sesi, peserta, catatan, dan versi absensi. Bila data berubah sejak form dibuka, penyimpanan ditolak dan halaman menyediakan Muat data terbaru; isian pengguna tetap ditampilkan sampai dimuat ulang. Pengiriman berulang tidak membuat dua perubahan yang sama. Mengubah data yang telah tersimpan memerlukan alasan perubahan. Isian yang tidak berubah tidak membuat riwayat baru.

Tabel baru `lesson_learning_audits` menyimpan operator, waktu server, alasan, dan isi sebelum/sesudah penyimpanan melalui halaman Catatan Belajar. Riwayat tersebut dapat dibuka pada detail pertemuan dan memiliki pagination. Catatan/riwayat lama dari sebelum fitur ini tersedia tidak direkonstruksi. Identitas dan tanggal pertemuan yang sudah memiliki riwayat pembelajaran dilindungi dari pemindahan melalui Edit Sesi.

### 24.4 Penerapan dan kelanjutan

Pembaruan v33 merupakan ZIP patch di atas v32. Migration tambahan `2026_10_10_060000_create_lesson_learning_audits.php` hanya membuat tabel riwayat; tidak mengganti kolom pembelajaran atau absensi yang sudah ada. Database lokal dicadangkan sebelum migration. Pemasangan tidak memerlukan reset database, seed ulang, penggantian `.env`, atau pembuatan ulang kunci aplikasi. Rollback tabel riwayat yang sudah terisi ditolak untuk menjaga catatan perubahan.

Pengujian mencakup penyimpanan awal/koreksi, keenam status kehadiran, waktu/operator absensi, perubahan bersamaan, peserta private/kelas, tanggal mulai, akses antarperan/cabang, sesi mendatang/batal/pengganti, validasi, HTML, pagination, dan preservasi data lama pada migration. Pemeriksaan akhir tampilan dilakukan pemilik pada browser Windows/Laragon setelah pemasangan.

Tahap ini menyelesaikan Catatan Pertemuan (T03). Perkembangan bulanan delapan kategori, video dan target per siswa-bulan, Challenges/pesan publik (T04), serta penyusunan dan tinjauan laporan bulanan (T05/A03) menjadi pekerjaan berikutnya setelah peninjauan v33. PDF final, pembayaran/portal anak, Fonnte, ekspor, dan pengelolaan konten tetap mengikuti urutan tahap berikutnya.


## 25. Implementasi Perkembangan dan Pesan — v34 (11 Oktober 2026)

### 25.1 Cakupan halaman

Menu **Perkembangan & Pesan** tersedia untuk Teacher, Admin, dan Super Admin. Daftar memakai siswa sebenarnya dan bulan kalender; tersedia filter bulan/cabang/nama, pagination, dan kolom Aksi terakhir. Halaman siswa memuat delapan kategori progres, ringkasan perkembangan, Challenges publik, Teacher’s Message, catatan internal bulanan, daftar video, target, dan riwayat aktivitas. Tampilkan dan Reset memiliki ukuran yang sama; card/form memakai jarak dan tata letak responsif yang mengikuti dashboard.

Teacher mengakses isi periode yang menetapkan dirinya sebagai pemilik laporan. Daftar siswa juga mengikuti penugasan mengajar, anggota kelas, dan peserta sesi kelas/private sebagaimana diperbaiki pada v35 (bagian 26), termasuk siswa yang periodenya belum disiapkan. Profil Teacher harus aktif. Admin hanya mengakses cabang dengan penugasan aktif hari ini; Super Admin mengakses semua cabang. Akun anak tidak dapat membaca atau mengubah draf melalui rute petugas.

### 25.2 Menyiapkan periode dan target video

Admin/Super Admin membuka daftar, memilih **Siapkan periode**, lalu menentukan Teacher pemilik laporan dari Teacher yang mengajar siswa atau penugasan Teacher aktif di cabang selama masa pendaftaran siswa pada bulan tersebut (aturan v36, bagian 27). Penetapan tersebut berlaku untuk laporan siswa-bulan; penugasan pertemuan dan daftar peserta absensi tetap diatur melalui alurnya sendiri.

Periode memakai tabel `monthly_reports` dan `report_cycles` yang sudah tersedia. Jika belum ada siklus yang mencakup bulan, aplikasi menyiapkan rentang tiga bulan kalender sejak bulan siswa masuk, misalnya Oktober–Desember atau Januari–Maret. Siklus lama yang mencakup bulan dipertahankan; irisan yang ambigu ditolak untuk diperiksa petugas. Menyiapkan periode yang sama berulang kali mengembalikan laporan yang sudah ada tanpa mengubah pemilik, target, atau isinya.

Siswa yang masuk setelah tanggal satu wajib memiliki target video bulan pertama yang ditentukan Admin berdasarkan pertemuan terjadwal. Nilai 0 diperbolehkan. Bulan penuh berikutnya menggunakan nilai pengaturan saat periode dibuat (awal empat video bila pengaturan belum tersedia), lalu menyimpan target itu pada laporan. Mengubah pengaturan sesudahnya mempertahankan target periode yang sudah tersedia. Admin dapat mengubah/menegaskan target melalui halaman periode selama status Draft/Revisi, termasuk perencanaan bulan yang belum dimulai. Periode lama pada bulan pertama yang tidak penuh ditandai menunggu konfirmasi Admin; nilai lamanya tetap dipertahankan sampai dikonfirmasi.

Generator laporan lama juga diperbaiki: jika enrollment belum dimulai pada tanggal satu, pemilihan enrollment dan Teacher pemilik pada bulan tersebut memakai hari awal enrollment dalam bulan itu. Tidak ada penghitungan prorata otomatis untuk target video.

### 25.3 Draf progres dan pemisahan isi

Delapan kategori tetap mengikuti bagian 7.3. Teacher memilih Improving, Stable, atau Needs Improvement dan menulis deskripsi; status A/B/C tidak digunakan di halaman ini. Draf boleh menyimpan sebagian kategori, dengan status dan deskripsi diisi bersama atau keduanya kosong. Maksimal 4.000 karakter per deskripsi dan 8.000 karakter per ringkasan/pesan/catatan bulanan. Kategori di luar delapan kategori ditolak; kategori lama tambahan di database dipertahankan. Kategori master yang nonaktif perlu diperiksa Super Admin sebelum draf disimpan.

Isian publik memakai `development_summary`, `parent_challenges_summary`, dan `parent_message`; isian internal memakai `internal_teacher_note`. Catatan profil/sesi, `student_challenges.internal_note`, dan pesan lama tidak disalin otomatis ke isian publik. Penyimpanan draf tidak mencatat kehadiran dan tidak mengirim pesan/notifikasi. Isian publik pada tahap ini tetap merupakan draf untuk tinjauan berikutnya.

Isi hanya dapat diubah dari Draft/Revisi pada bulan yang sudah berlangsung dan sesuai tanggal mulai siswa. Submitted/Approved serta status tidak dikenal ditampilkan untuk pemeriksaan dengan kontrol simpan dinonaktifkan. Rute menyimpan juga memeriksa batas tersebut di server. Setiap formulir memuat penanda data; perubahan pada progres, video, target, kategori, atau tanggal mulai membuat formulir lama ditolak. Masukan tetap ditampilkan bersama tombol Muat data terbaru; aplikasi tidak mengulang penyimpanan otomatis. Menyimpan isian yang identik tidak menambah riwayat baru.

### 25.4 Tautan video dan riwayat

Video memakai tabel `learning_videos` yang sudah tersedia: tanggal, topik, deskripsi, tautan HTTP/HTTPS, dan pertemuan terkait opsional. Video dicatat per siswa, sehingga tautan kelas yang sama dapat dicatat pada masing-masing siswa. Tautan yang sama untuk siswa dan tanggal yang sama ditolak agar tidak menambah jumlah secara tidak sengaja. Tanggal harus berada pada bulan yang dipilih, sejak siswa mulai les, dan tidak melewati hari ini menurut zona cabang.

Pertemuan terkait harus tersedia bagi petugas, berada di cabang periode, memiliki siswa sebagai peserta yang memenuhi enrollment/keanggotaan, tidak dibatalkan atau digantikan, serta memiliki tanggal sama dengan video. Teacher hanya mengubah tautan yang dicatat atas identitasnya sendiri; Admin/Super Admin berwenang menangani tautan di dalam cakupannya. Tombol Edit/Hapus ada di kolom Aksi. Hapus membuka halaman konfirmasi; tautan baru dihapus setelah tombol Ya, hapus tautan. Salinan sebelumnya tetap tersimpan pada riwayat petugas.

Tautan lama dengan skema tidak aman tidak menjadi link yang dapat diklik. Video sebelum tanggal masuk, setelah hari ini, atau dengan tautan yang belum valid ditampilkan untuk diperiksa dan tidak dihitung dalam indikator. Kekurangan target menjadi informasi; tidak ada blok pengajuan/persetujuan karena jumlah video pada tahap ini. Menu lama Super Admin untuk video dan skill laporan juga menolak perubahan terhadap periode yang sudah dikunci.

### 25.5 Migrasi dan batas pengujian

Migration tambahan `2026_10_11_070000_add_monthly_progress_support.php` menambah `video_target_confirmed_at`, `video_target_confirmed_by_user_id`, dan tabel `monthly_progress_audits`. Riwayat menyimpan aktor, waktu server, jenis aktivitas, serta salinan sebelum/sesudah. Delapan kategori master yang belum tersedia ditambahkan; ID, kode, label, status aktif/nonaktif, skill tambahan, laporan, video, dan catatan lama dipertahankan. Charset/collation tabel riwayat mengikuti tabel backend sebelumnya agar foreign key sesuai pada MySQL. Rollback otomatis ditolak setelah fitur menyimpan data/riwayat.

Pembaruan tidak menyertakan `.env`, kredensial, database pengguna, foto, vendor, atau reset/reseed. Cadangkan proyek dan database, salin berkas patch, jalankan `php artisan migrate --force`, bersihkan route/view cache, lalu uji di Laragon. Petunjuk pemasangan diberikan langsung dalam chat di bawah ZIP.

Pengujian meliputi akses per role/cabang, persiapan bulan pertama, target historis, delapan kategori, pemisahan isi publik/internal, input berbahaya, penyimpanan atomik, penanda formulir lama, penguncian status, video/peserta pertemuan, konfirmasi hapus, pagination, penempatan tombol, dan pelestarian data saat migrasi. Tes otomatis menggunakan SQLite dan HTTP/DOM Laravel; koneksi database MySQL pengguna dan pemeriksaan visual browser dilakukan di Laragon pengguna.

### 25.6 Tahap berikutnya

T04 Perkembangan dan Pesan selesai sebagai sumber draf pembelajaran bulanan. Berikutnya adalah T05/A03: penyusunan laporan bulanan, pemeriksaan kelengkapan delapan kategori, pengajuan Teacher, tinjauan Admin/Super Admin, revisi, dan persetujuan. Alur antarmuka pengajuan/tinjauan, tampilan orang tua setelah persetujuan, PDF final, pembayaran, Fonnte, ekspor, dan konten mengikuti tahap selanjutnya.

## 26. Perbaikan daftar siswa Teacher — v35 (11 Oktober 2026)

### 26.1 Hubungan penugasan dengan daftar perkembangan

Setelah pemasangan v34, pemilik melaporkan siswa yang sudah ditugaskan belum muncul di Perkembangan & Pesan. Filter v34 hanya mengambil laporan milik Teacher atau penugasan siswa dengan `is_report_owner = true`; penugasan mengajar biasa, keanggotaan kelas, dan peserta private belum tercakup.

Daftar v35 mencakup siswa dengan laporan bulanan milik Teacher, penugasan siswa–Teacher yang berlaku pada bulan pilihan tanpa mensyaratkan flag pemilik laporan, anggota kelas aktif dengan Teacher utama tersebut, dan peserta sesi yang ditugaskan kepada Teacher. Nama tetap muncul meskipun belum ada laporan bulanan. Setiap siswa ditampilkan sekali walaupun terhubung melalui beberapa jalur.

Rentang penugasan harus beririsan dengan bulan pilihan dan tanggal mulai siswa. Anggota kelas harus memiliki keanggotaan serta enrollment cabang/program yang beririsan pada tanggal yang sama. Peserta sesi mengikuti layanan roster absensi yang sudah tersedia, dengan pemeriksaan enrollment, keanggotaan kelas bila relevan, serta tanggal mulai pada pertemuan. Peserta sesi mendatang dalam bulan pilihan dapat ditampilkan untuk perencanaan. Sesi dibatalkan, sudah memiliki jadwal pengganti, atau di luar bulan efektif pertemuan tidak digunakan sebagai dasar penugasan ini. Tanggal aktual digunakan bila tersedia, selain itu tanggal rencana. Peserta kelas tetap mengikuti Teacher sesi ketika Teacher utama kelas berbeda.

Filter cabang memasukkan enrollment siswa yang sudah berada dalam cakupan Teacher pada bulan tersebut, serta cabang laporan miliknya. Daftar tidak mengambil seluruh pendaftar hanya karena berada di cabang atau program yang sama. Teacher lain dan profil Teacher nonaktif tidak mendapat akses daftar melalui penugasan tersebut.

### 26.2 Pemilik laporan dan langkah berikutnya

Terlihatnya nama siswa tidak membuat laporan atau mengubah pemiliknya. Bila periode belum tersedia, daftar menjelaskan bahwa Teacher ditugaskan mengajar dan menunggu Admin menyiapkan periode. Admin/Super Admin membuka Perkembangan & Pesan pada bulan yang sama, memilih Siapkan periode, menetapkan Teacher pemilik, dan menentukan target video bila bulan pertama tidak penuh sesuai bagian 25.2.

Bila laporan bulan pilihan sudah dikelola Teacher lain, siswa tetap tampil dengan status Dikelola Teacher lain serta petunjuk menghubungi Admin untuk penetapan pemilik. Isi progres, video, pesan, catatan internal, dan tautan membuka laporan tersebut tidak ditampilkan kepada Teacher yang bukan pemilik. Rute membaca/menyimpan tetap menolak Teacher yang tidak berwenang. Membuka daftar tidak membuat periode, peserta, kehadiran, atau audit baru.

### 26.3 Penerapan dan pemeriksaan

v35 merupakan ZIP patch di atas v34. Perubahan berada pada layanan akses perkembangan, controller daftar, view daftar, tes fitur tambahan, dan dokumen ini. Tidak ada migration baru; pemasangan cukup menyalin patch serta membersihkan cache route/view. Penugasan, enrollment, laporan, pemilik, target video, dan data absensi yang sudah tersimpan dipertahankan.

Pengujian mencakup tiga jalur penugasan, kelas tanpa sesi, Teacher sesi berbeda dari Teacher utama, filter cabang/nama/bulan, batas tanggal, enrollment salah cabang/program, keanggotaan tidak beririsan, sesi batal/pengganti, peserta mendatang, penghapusan duplikasi, Teacher lain/nonaktif, serta laporan milik Teacher lain. Skenario penugasan langsung, kelas, dan private direproduksi gagal pada v34 lalu lulus pada v35. Tes seluruh proyek tetap dijalankan dengan SQLite; pemeriksaan tampilan akhir dan database lokal dilakukan pemilik di Windows/Laragon.

Tahap berikutnya tetap T05/A03 setelah pemilik meninjau perbaikan daftar ini.

## 27. Penyelarasan pilihan Teacher saat persiapan periode — v36 (11 Oktober 2026)

### 27.1 Temuan dari peninjauan pemilik

Pemilik menguji Ketut Regeg, Tes 1, dan Wayan King melalui Admin Cabang. Ketut tidak mendapatkan form, Tes 1 mendapatkan form walaupun belum ditugaskan kepada Teacher, dan Wayan tidak mendapatkan pilihan Dipta yang sudah mengajarnya. Gambar peninjauan Ketut menunjukkan tanggal mulai 9 Oktober serta pemberitahuan tidak ada Teacher yang dapat dipilih.

Pilihan v34/v35 hanya membaca penugasan Teacher ke cabang pada tanggal awal belajar/enrollment, sehingga Teacher yang mulai di pertengahan bulan atau sudah mengajar melalui penugasan siswa/kelas/private bisa terlewat. Form juga disembunyikan sepenuhnya ketika daftar pilihan kosong. Tes 1 yang belum ditugaskan tetap boleh memiliki form karena penetapan pemilik laporan merupakan kewenangan Admin.

### 27.2 Pilihan Teacher dan saran

Persiapan v36 mengutamakan hubungan mengajar siswa yang berlaku selama bulan pilihan dan masa pendaftarannya pada cabang laporan. Sumber hubungan mencakup penugasan siswa–Teacher (tanpa mensyaratkan `is_report_owner`), Teacher utama kelas aktif dengan keanggotaan/enrollment cabang dan program yang beririsan, serta Teacher sesi kelas/private yang mempunyai peserta valid sesuai layanan absensi. Sesi batal, digantikan, di luar bulan efektif, sebelum siswa mulai, atau tanpa enrollment/keanggotaan yang sesuai tidak menjadi sumber hubungan. Pertemuan mendatang dalam bulan pilihan dapat dipakai untuk perencanaan.

Hubungan mengajar yang valid cukup untuk memasukkan Teacher siswa ke pilihan walaupun belum ada catatan penugasan cabang yang terpisah. Aplikasi tidak membuat catatan cabang tersebut secara otomatis. Teacher cabang lainnya juga dapat dipilih apabila penugasan cabangnya beririsan dengan bulan serta masa enrollment siswa; pemeriksaan tidak lagi terbatas pada satu tanggal awal. Teacher tanpa hubungan siswa maupun penugasan cabang yang sesuai tidak tersedia.

Profil dan akun Teacher harus aktif, dan akun harus memiliki peran Teacher. Teacher siswa dikelompokkan sebagai Teacher siswa ini, disertai sumber penugasannya; pilihan cabang lainnya tampil terpisah. Satu penugasan pemilik laporan yang valid menjadi saran utama. Jika tidak ada pemilik unik dan hanya satu Teacher siswa yang aktif, Teacher tersebut disarankan. Jika terdapat beberapa Teacher tanpa satu pemilik yang jelas, Admin harus memilih sendiri. Teacher cabang yang tidak memiliki hubungan mengajar siswa tidak dipilih otomatis hanya karena merupakan satu-satunya pilihan.

Pilihan diperiksa kembali saat form disimpan. Pemilik laporan ditetapkan pada laporan siswa-bulan, tanpa memindahkan keanggotaan, peserta sesi, atau penugasan mengajar. Persiapan periode yang sudah tersedia tetap mempertahankan pemilik, target, siklus, dan isi sebelumnya.

### 27.3 Form kosong dan penerapan

Form tetap terlihat ketika belum ada pilihan Teacher; pilihan Teacher dan tombol simpan dinonaktifkan dengan penjelasan singkat serta pintasan Data Guru/Guru dan Penugasan sesuai peran. Teacher siswa yang terhubung tetapi profil/akun/perannya tidak memenuhi syarat disebutkan untuk diperiksa petugas. Target video bulan pertama tetap ditentukan manual oleh Admin, termasuk nilai 0; bulan penuh dan aturan draf tetap mengikuti bagian 25.

Pembaruan menambah `StudentProgressTeacherService`, menyesuaikan layanan persiapan serta view form, menambah tes fitur, dan memperbarui dokumen ini. Route dan struktur database tidak berubah; tidak ada migration, reseed, perubahan akun/penugasan, atau koreksi tanggal otomatis saat pemasangan. v36 diterapkan sebagai ZIP patch di atas v35 dengan instruksi PowerShell langsung di chat.

Skenario Ketut, Tes 1, dan Wayan direproduksi gagal pada logika lama, kemudian lulus pada logika baru melalui fixture tes. Pemeriksaan juga mencakup Teacher kelas/private tanpa catatan cabang, Teacher sesi tanpa peserta eksplisit untuk kelas, saran pemilik laporan, pilihan ganda, batas tanggal/enrollment, cabang/program lain, profil/akun nonaktif, sesi batal/pengganti, form tanpa pilihan, serta perubahan penugasan antara membuka dan menyimpan form. Tes seluruh proyek menggunakan SQLite; pemeriksaan akhir tampilan dan database pengguna dilakukan di Windows/Laragon. Tahap berikutnya tetap T05/A03 setelah peninjauan v36.


## 28. Audit konsistensi aplikasi dan perlindungan riwayat — v37 (11 Oktober 2026)

### 28.1 Cakupan dan persiapan laporan

Setelah pemilik mengonfirmasi v36, kode aplikasi aktif diperiksa melalui peninjauan aturan antarhalaman, pemeriksaan route/view/model, dan pengujian seluruh proyek. Cakupan mencakup akun dan hak akses, cabang, guru, penugasan, siswa, enrollment, kelas, jadwal/peserta, absensi, pembelajaran/progres, video, tagihan/bukti pembayaran, siklus laporan, kontak wali, serta backend konten publik dan konfigurasi yang sudah tersedia. Audit ini tidak mengaktifkan layar atau integrasi yang masih direncanakan.

Persiapan ulang periode yang sudah ada mengembalikan laporan yang sama setelah memeriksa hak akses, tanpa menghitung ulang pilihan Teacher berdasarkan enrollment baru. GET form persiapan juga mengarah ke laporan yang sudah tersedia. Pemilik, target, siklus, dan isi laporan tersimpan tidak diganti oleh persiapan ulang. Hak akses tetap mengikuti cabang laporan dan Teacher pemilik.

Batas tanggal enrollment, anggota kelas, dan penugasan cabang bersifat inklusif sepanjang hari terakhir. Periode enrollment satu hari dan hari terakhir bulan dapat dipakai saat persiapan. Video pada hari terakhir bulan masuk ke daftar dan jumlah video. Generator siklus backend memakai irisan bulan, tanggal mulai siswa, enrollment, dan penugasan pemilik laporan aktif; penugasan yang mulai di tengah bulan tidak lagi dicari hanya pada tanggal awal enrollment. Beberapa catatan pemilik untuk Teacher yang sama tidak menghasilkan pilihan ambigu. Generator otomatis tetap membutuhkan satu Teacher pemilik eksplisit; pilihan manual Admin tetap mengikuti bagian 27.

### 28.2 Penugasan, sesi, absensi, dan halaman manajemen

Sesi baru atau perubahan referensi sesi menolak kelompok kelas yang berbeda cabang/program, serta Teacher, akun Teacher, cabang, program, atau kelompok nonaktif. Referensi historis yang tidak diubah tetap dapat dipertahankan saat mengedit sesi. Anggota kelas membutuhkan enrollment siswa pada cabang/program yang sama dengan periode yang beririsan; periode keanggotaan kelas yang sama tidak boleh bertumpang tindih. Penugasan siswa baru membutuhkan Teacher dengan profil dan akun aktif. Duplikasi pemilik laporan tanpa tanggal akhir ditolak dengan pesan validasi.

Sesi asal yang telah mempunyai pengganti valid tidak lagi ditawarkan untuk check-in Teacher dan tidak menjadi dasar pencatatan Tidak Hadir, baik private maupun rekap kunjungan cabang. Pengganti yang dibatalkan tidak menonaktifkan sesi asal. Riwayat dan absensi yang sudah terbuka tetap bisa ditutup sesuai aturan sebelumnya. Profil Teacher nonaktif ditolak ketika check-in baru atau mengajukan laporan melalui endpoint backend lama.

Filter Teacher pada monitoring Admin memasukkan penugasan yang beririsan dengan rentang pilihan serta riwayat sesi/absensi pada cabang tersebut. Teacher historis tidak menghilang hanya karena tidak sedang memiliki penugasan cabang hari ini; Teacher dari cabang lain tetap di luar cakupan Admin.

Halaman Detail/Edit Pengguna dan empat halaman Penugasan Teacher ke Siswa yang sebelumnya belum tersedia kini memakai layout dashboard dan tombol kembali. Penugasan langsung dapat dibuka dari Siswa & Pendaftaran oleh Super Admin. Tindakan tabel ditempatkan di kolom Aksi. Edit akun Super Admin dapat mempertahankan peran yang sudah dimiliki; pengguna biasa tidak dapat dipromosikan melalui form ini, dan Super Admin tidak dapat menonaktifkan akun sendiri. Input username yang berupa array ditolak sebagai kesalahan validasi login.

### 28.3 Riwayat file, pembayaran, kategori, dan siklus

Penghapusan file memeriksa constraint referensi sebelum menghapus file fisik. Foto yang masih dipakai profil lain tetap tersedia bila metadata tidak dapat dihapus. Tautan video baru/perubahan menerima URL HTTP/HTTPS; URL lama yang tidak aman tidak dirender menjadi tautan aktif. Delapan kategori perkembangan wajib tidak dapat dihapus atau diganti identitasnya, dan kategori tambahan yang sudah dipakai laporan tidak dapat dihapus.

Bulan tagihan baru dinormalkan ke awal bulan, tanggal tenggat tetap opsional, dan siswa tidak boleh memiliki dua tagihan dalam bulan kalender yang sama, termasuk ketika catatan lama memakai hari terakhir bulan. Tagihan yang sudah memiliki bukti pembayaran tidak dapat dihapus atau dipindahkan ke siswa/cabang/bulan lain. Peninjau bukti harus merupakan akun Super Admin aktif yang sedang login. Status peninjauan dibatasi ke Disetujui atau Ditolak, penolakan membutuhkan alasan, dan persetujuan kedua pada tagihan yang sama ditolak dengan validasi tanpa menimpa bukti sebelumnya.

Wali dengan riwayat pembagian rapor tidak dapat dihapus atau dipindahkan ke siswa lain; perubahan kontak tetap tersedia. Perubahan rentang siklus tetap boleh dilakukan bila seluruh laporan bulanan tersimpan masih tercakup. Perubahan yang mengecualikan laporan lama ditolak. Tiga deklarasi route GET duplikat dihapus tanpa mengganti URL atau nama route yang digunakan.

### 28.4 Penerapan dan bukti pemeriksaan

v37 adalah ZIP patch di atas v36. Perubahan meliputi 27 file backend, 17 view, satu file route, lima file tes, dan dokumen ini. Delapan file kode/view adalah tambahan; file lain memperbarui kode yang sudah tersedia. Tidak ada migration, dependency baru, perubahan skema database, reseed, atau koreksi otomatis terhadap data pengguna saat pemasangan. Arsip menyertakan salinan source v36 untuk file lama yang berubah, manifest checksum, dan AUDIT_RESULTS.md. Instruksi pemasangan diberikan langsung di chat.

Hasil pengujian akhir: 1.513 tes PHP dengan 4.951 assertions dan 14 tes JavaScript lulus, tanpa kegagalan atau tes yang dilewati. Sebanyak 62 kasus regresi/smoke ditambahkan, termasuk pemeriksaan 59 route GET tanpa parameter dari empat peran beserta kesesuaian kolom dan posisi Edit/Hapus. Pemeriksaan struktur mencakup 275 route, 272 aksi controller, 153 parameter binding model, 36 model, dan 138 hubungan model. Lint mencakup 350 file PHP sumber; 144 view Blade dapat dikompilasi, dan 164 file PHP hasil kompilasi aplikasi/vendor lolos lint. Tujuh file JavaScript dan tujuh stylesheet dashboard/publik juga diperiksa. Cache route serta cache view dapat dibangun dan dibersihkan.

Tes database memakai SQLite di lingkungan PHP 8.3, bukan database MySQL/MariaDB milik pemilik proyek. Perubahan ini tidak mengubah sumber Vite atau asset CSS/JS; build Vite tidak dijalankan karena dependency Node tidak tersedia. Pemeriksaan browser visual di Windows/Laragon dan data produksi pengguna tetap perlu dilakukan setelah pemasangan. Audit ini mengurangi risiko regresi pada kasus yang diuji, bukan jaminan aplikasi bebas semua kemungkinan bug. Tahap berikutnya tetap T05/A03 setelah peninjauan v37.


## 29. Pengajuan dan tinjauan laporan bulanan T05/A03 — v38 (11 Oktober 2026)

### 29.1 Alur Teacher, Admin, dan Super Admin

Teacher membuka Laporan Bulanan untuk melihat laporan yang menetapkannya sebagai pemilik. Daftar awal mengikuti bulan berjalan; bulan dapat dikosongkan untuk melihat semua bulan, dengan filter status, cabang, dan nama siswa. Daftar menampilkan 15 laporan per halaman, kelengkapan, target/video, status, tenggat, serta tombol pada kolom Aksi. Siswa yang baru ditugaskan tetapi belum mempunyai periode tetap diperiksa melalui Perkembangan & Pesan; aplikasi tidak membuat laporan hanya karena daftar dibuka. Admin menyiapkan periode serta pemilik sesuai bagian 25–28.

Form Perkembangan & Pesan menambah Simpan & periksa laporan. Penyimpanan dan validasi tetap dilakukan sebelum beralih ke detail laporan; isian gagal atau formulir kedaluwarsa tidak dianggap tersimpan. Tombol Simpan draf mempertahankan alur sebelumnya. Detail memuat delapan kategori, ringkasan perkembangan, Challenges untuk orang tua, Teacher’s Message, tautan video, catatan internal bulanan, rekap absensi internal, serta riwayat status.

Teacher mengajukan dari Draf atau Perlu revisi. Admin cabang dan Super Admin memeriksa laporan Diajukan, lalu memilih Setujui laporan atau Minta revisi. Antrean awal Admin memuat Diajukan dari semua bulan dalam cakupannya. Tindakan membuka halaman konfirmasi lebih dahulu; GET, Batal, serta tombol tutup/kembali tidak menulis data. POST konfirmasi yang berhasil mengubah status, mencatat petugas, dan kembali ke detail. Permintaan revisi membutuhkan alasan maksimal 255 karakter; Teacher melihat alasan tersebut dan dapat memperbaiki draf sebelum mengajukan ulang.

### 29.2 Kelengkapan, hak akses, dan formulir kedaluwarsa

Pengajuan dan persetujuan memeriksa ulang delapan kategori master yang berbeda dan aktif. Setiap kategori membutuhkan status Improving, Stable, atau Needs Improvement serta deskripsi nonkosong maksimal 4.000 karakter. Ringkasan perkembangan, Challenges, dan Teacher’s Message wajib diisi, masing-masing maksimal 8.000 karakter; catatan internal opsional maksimal 8.000 karakter. Draf tetap boleh disimpan sebagian menurut bagian 25. Periode sebelum siswa mulai les, bulan mendatang, atau tanggal mulai siswa yang belum berlangsung tidak dapat diajukan. Target bulan pertama parsial harus dikonfirmasi Admin; target 0 tetap valid. Kekurangan video, absensi yang belum lengkap, dan tenggat yang terlewat tidak menghalangi pengajuan/persetujuan. Tenggat dihitung inklusif sepanjang tanggal tersebut berdasarkan zona waktu cabang.

Teacher hanya membaca/mengajukan laporan miliknya dengan akun dan profil aktif. Admin hanya membaca atau memproses laporan dari cabang dengan penugasan Admin yang berlaku hari ini; Super Admin aktif dapat meninjau seluruh cabang. Teacher lain, Admin cabang lain, siswa, dan akun nonaktif tidak mendapat isi laporan atau catatan internal. Penugasan mengajar tetap terpisah dari kepemilikan laporan. Setelah Diajukan atau Disetujui, editor perkembangan, video, target, dan endpoint perubahan skill/video lama tetap terkunci. Revisi membuka kembali isian menurut aturan draf yang sudah tersedia.

Seluruh POST pengajuan/persetujuan/revisi melalui UI maupun endpoint HTTP lama wajib menyertakan expected_snapshot dari isi yang ditinjau. Versi mencakup isi, kategori, target, video, identitas yang ditampilkan, tenggat, metadata status, dan riwayat tindakan. Perubahan di antara membuka dan mengonfirmasi ditolak, termasuk urutan Diajukan → Perlu revisi → Diajukan dengan isi yang sama. Halaman menampilkan petunjuk memuat data terbaru, mempertahankan versi lama, dan menonaktifkan tindakan ulang sampai data dimuat kembali. Pemanggilan service internal yang tepercaya boleh tidak mengirim versi formulir; validasi kelengkapan, hak akses, serta transisi status tetap berlaku.

Status, timestamp, salinan persetujuan, dan riwayat status disimpan dalam satu transaksi dengan penguncian baris laporan. Permintaan ganda atau transisi yang tidak sesuai status tidak menambah keputusan. Aktor diambil dari akun yang sedang login, bukan field input. Riwayat baru memakai UUID v7 untuk menjaga urutan tindakan dalam detik yang sama; riwayat lama tetap dipertahankan.

### 29.3 Salinan persetujuan dan pemisahan isi publik/internal

Persetujuan baru menyimpan approved_snapshot versi 2: identitas siswa/cabang/Teacher yang ditinjau, bulan dan target, delapan penilaian, tiga isian publik, serta tanggal, topik, keterangan, URL, dan nama Teacher pada video valid. Detail Disetujui membaca salinan ini sehingga perubahan data induk atau video setelah persetujuan tidak mengganti isi yang telah disetujui. public_content tidak memuat catatan internal; catatan internal hanya berada pada bagian khusus petugas di root snapshot. Pemisahan ini menjadi dasar tahap rapor/portal berikutnya, bukan aktivasi akses orang tua pada v38.

Laporan Disetujui lama tetap hanya baca. Bila salinan lama belum menyimpan identitas/video, halaman memberi keterangan dan memakai identitas saat ini sebagai informasi petugas; jumlah video yang tidak tersimpan tidak ditampilkan sebagai angka nol yang pasti. Bila snapshot belum tersedia, isian tersimpan ditampilkan dengan pemberitahuan bahwa salinan lama belum tersedia. Tautan HTTP/HTTPS valid saja yang aktif; isi dan alasan revisi dirender sebagai teks ter-escape.

Rekap absensi pada detail adalah informasi internal terkini dari cabang dan bulan laporan, memakai tanggal aktual bila tersedia serta aturan roster/kelayakan yang sudah diuji. Sesi mendatang, dibatalkan, digantikan, atau sebelum mulai les tidak masuk pertemuan berlangsung. Catatan pembelajaran per sesi tidak disalin ke isi publik. Rekap ini tidak menjadi syarat persetujuan dan tidak dibekukan di dalam public_content.

### 29.4 Penerapan, pengujian, dan tahap berikutnya

v38 merupakan ZIP patch di atas v37. Perubahan meliputi layanan konten/workflow laporan, controller UI baru, integrasi endpoint lama, route, navigasi, tiga view laporan bulanan, pintasan editor/daftar perkembangan, stylesheet, fixture/tes, dan dokumen ini. Paket menyertakan salinan source v37 untuk file lama yang berubah, manifest checksum, serta TEST_RESULTS.md. Tidak ada migration, skema baru, reseed, dependency baru, atau koreksi otomatis data pengguna. Instruksi PowerShell disampaikan langsung di chat setelah tautan ZIP; tidak ada README instalasi.

Pengujian akhir: 1.591 tes PHP dengan 5.564 assertions dan 14 tes JavaScript lulus, tanpa kegagalan/error/skipped. Sebanyak 78 kasus lifecycle baru mencakup alur lengkap, delapan kategori, nilai/ukuran isian, master bermasalah, bulan/tanggal mulai/target, kekurangan video, akses dan peran, konfirmasi/pembatalan, formulir kedaluwarsa, permintaan ganda, penguncian editor, snapshot lama/baru, pemisahan catatan internal, escaping, pagination/kolom Aksi, filter, tenggat inklusif, rekap absensi, dan rollback bila pencatatan riwayat gagal. Tes lama dilengkapi fixture delapan kategori serta versi formulir; skenario izin/transisi tetap dipertahankan.

Pemeriksaan struktur mencakup 289 route, 286 aksi controller, 164 parameter binding model, 36 model, dan 138 hubungan tanpa kesalahan. Sebanyak 354 file PHP sumber lolos lint; 147 view Blade berhasil dikompilasi dan 167 file PHP kompilasi aplikasi/vendor lolos lint. Tujuh file JavaScript dan delapan stylesheet diperiksa, serta referensi route/view/component literal tidak menunjukkan target hilang. Cache route/view dapat dibangun dan dibersihkan. Asset baru dimuat langsung dari public/assets/dpe; sumber Vite tidak berubah, sehingga build Vite tidak diperlukan untuk patch ini.

Tes database menggunakan SQLite dan PHP 8.3. Pemeriksaan browser visual serta database MySQL/MariaDB pengguna di Windows/Laragon belum dijalankan di lingkungan ini dan dilakukan setelah pemasangan. Pengujian membuktikan kasus yang diperiksa, tanpa jaminan tidak mungkin ada bug lain. Tahap selanjutnya setelah peninjauan v38 adalah laporan akhir tiga bulan dan PDF, dilanjutkan gerbang persetujuan/pembayaran untuk portal siswa/orang tua serta pengiriman WhatsApp sesuai bagian 10. Fitur tersebut belum diaktifkan oleh v38.


## 30. Rapor akhir tiga bulan dan PDF T06/A03 — v39 (11 Oktober 2026)

### 30.1 Periode, daftar siswa, dan Teacher pemilik

Menu Rapor Tiga Bulan tersedia untuk Teacher, Admin cabang, dan Super Admin. Daftar memuat siklus siswa dalam cakupan pengguna, dengan filter bulan awal, status, dan nama siswa; filter awal Semua status agar siklus yang belum disiapkan tetap terlihat. Tombol selalu berada pada kolom Aksi. Semua halaman detail/form menyediakan tombol kembali. Daftar, form persiapan, konfirmasi, pembatalan, dan preview PDF tidak membuat rapor/file/riwayat melalui GET.

Siklus harus tiga bulan kalender berurutan sejak bulan siswa bergabung, termasuk periode lintas tahun. Admin perlu menyiapkan ketiga periode bulanan dalam siklus tersebut melalui Perkembangan & Pesan. Isian bulanan boleh masih Draf saat rapor final mulai disusun. Form persiapan memperlihatkan status ketiga periode serta alasan jika belum dapat dilanjutkan. Tidak ada pembuatan rapor otomatis saat halaman dibuka.

Teacher pemilik final dipilih secara eksplisit dari profil Teacher dan akun Teacher aktif yang memiliki laporan bulanan dalam siklus itu. Tidak diperlukan penugasan cabang tambahan hanya untuk memunculkan Teacher yang sudah memiliki periode bulanan. Bila hanya satu Teacher memenuhi syarat, pilihan Admin diisi awal. Teacher dapat menyiapkan rapor dengan dirinya sendiri sebagai pemilik; ia tidak dapat memilih pemilik lain. Admin/Super Admin dapat memilih di antara pemilik bulanan yang memenuhi syarat. Bila tidak ada pilihan aktif, form tetap tampil dengan penjelasan.

Cabang rapor final mengikuti cabang periode bulanan terakhir. Admin cabang lama tidak dapat menyiapkan atau mengelola final dari cabang terakhir yang berada di luar penugasannya. Siklus yang sudah memiliki final hanya dapat dibuka sebagai final oleh Teacher pemiliknya, Admin cabang terkait dengan penugasan berlaku hari ini, dan Super Admin aktif. Teacher pemilik bulanan lain dapat melihat keterangan bahwa final dikelola pemilik lain, tanpa mendapat isian final.

### 30.2 Empat skill manual, Note, pengajuan, dan tinjauan

Teacher mengisi Speaking, Reading, Listening, Writing dalam urutan tersebut. Setiap skill berisi deskripsi maksimal 4.000 karakter dan skor A, B, atau C. Angka pada rubrik PDF hanya menjelaskan rentang A 90–100 (Very Good), B 80–89 (Good), C 70–79 (Fair); tidak ada input angka atau konversi otomatis dari Improving/Stable/Needs Improvement bulanan. Note untuk orang tua berada setelah seluruh tabel skill dan maksimal 8.000 karakter.

Draf boleh belum lengkap: pasangan deskripsi/skor boleh sama-sama kosong, tetapi pasangan sebagian, skill tambahan, tipe isian salah, atau skor selain A/B/C ditolak secara atomik. Note boleh kosong saat Draf. Simpan & periksa rapor menyimpan dan memvalidasi dahulu sebelum menuju detail; gagal validasi tidak dianggap tersimpan. Admin/Super Admin hanya dapat mengganti Teacher pemilik saat Draf/Perlu revisi, dengan pilihan dari pemilik bulanan aktif; isian skill dan Note dikerjakan Teacher.

Teacher mengajukan Draf/Perlu revisi setelah empat skill dan Note lengkap, periode sesuai siklus, pemilik aktif, dan bulan ketiga sudah berlangsung. Draf dan preview isi lengkap dapat disiapkan lebih dahulu. Status Diajukan mengunci editor. Admin cabang/Super Admin memeriksa final Diajukan lalu menyetujui atau meminta revisi dengan alasan maksimal 255 karakter. Teacher memperbaiki rapor Perlu revisi dan mengajukan ulang. Persetujuan final terpisah dari persetujuan tiga laporan bulanan.

Setelah Disetujui, Teacher dapat membuka revisi baru dengan alasan melalui konfirmasi. Nomor revisi bertambah, persetujuan saat ini dibuka menjadi Draf, dan perubahan membutuhkan pengajuan serta tinjauan kembali. Salinan persetujuan sebelumnya tercatat pada riwayat dan file PDF lama dipertahankan. Identitas siklus (siswa/periode/nomor siklus) tidak boleh diganti setelah final disiapkan; tenggat berbagi tetap mengikuti aturan yang sudah ada.

Setiap mutasi HTTP final/template memerlukan versi formulir. Versi mencakup isi rapor, identitas siswa/Teacher/cabang, sumber bulanan, template untuk draf, dan riwayat tindakan. Perubahan yang kembali ke isi semula tetap membatalkan formulir lama. Halaman kedaluwarsa meminta pengguna memuat data terbaru tanpa menimpa perubahan. Pengajuan, tinjauan, pembuatan file, dan riwayat petugas dilakukan dalam transaksi dengan kunci baris; aktor berasal dari akun masuk. Klik ganda tidak membuat transisi/file ganda.

### 30.3 Template, preview, dan file PDF terlindungi

PDF A4 portrait mengikuti Student's Evaluation Dina_3.pdf: kop/logo lembaga berulang, nomor SK, slogan, alamat/telepon, judul, nama siswa, rubrik, Learning Period, empat skill, Note, informasi dokumentasi/Instagram, narasi penutup, Teacher dan Manager. Tidak ada tabel absensi, delapan kategori bulanan, Challenges, Teacher’s Message, daftar video, atau catatan internal dalam PDF. Deskripsi dan Note tetap berupa input Teacher; referensi persetujuan bulanan disimpan sebagai ID/status/hash, tanpa menyalin isi privat ke PDF.

Preview draf memakai penanda DRAF - BELUM DISETUJUI dan tidak menyimpan metadata/file/riwayat. Preview persetujuan dan PDF final memakai salinan isi, identitas, Teacher, template, Manager, serta bytes logo saat persetujuan. Perubahan nama, template, atau logo setelah persetujuan tidak mengganti PDF persetujuan lama. Logo berasal dari asset lembaga yang sesuai atau logo bawaan, tanpa URL remote. Tanda tangan yang belum tersedia menampilkan Area tanda tangan - belum diunggah; autograph dari contoh tidak digunakan.

Super Admin mengatur nama lembaga, SK, slogan, alamat, telepon, Instagram (satu per baris), nama Manager, informasi dokumentasi, dan narasi penutup melalui Template PDF. Perubahan berlaku pada persetujuan berikutnya. Nilai awal mengikuti panduan lembaga dan dapat diperbaiki sebelum tinjauan. Kop panjang dibungkus dan tingginya dihitung dari metrik font untuk menjaga jarak dari isi. Teks panjang/Unicode/kata tanpa spasi dibungkus; deskripsi dipisahkan menjadi bagian baris yang dapat berpindah halaman tanpa pemotongan. Kop dan header tabel berulang saat tabel berlanjut. Note dimulai setelah Writing; penutup dan area tanda tangan normal diletakkan bersama.

Buat PDF final membutuhkan final Disetujui dan ketiga laporan bulanan Disetujui. Pembayaran tidak memblokir preview atau pembuatan internal oleh petugas. File disimpan di disk privat, disertai hash bytes dan hash salinan sumber, petugas pembuat, waktu, nomor versi per siklus, serta revisi persetujuan. Permintaan pembuatan yang sudah mempunyai file valid mengembalikan file tersebut. Buat ulang PDF memerlukan alasan dan membuat versi baru, tanpa menimpa file lama. File hilang/berubah tidak dilayani; pengguna diarahkan membuat ulang. Kegagalan transaksi metadata/riwayat membersihkan file baru dan mempertahankan persetujuan.

Buka/Unduh memeriksa kembali hak akses, hubungan file-rapor-siklus, hash sumber, ukuran, MIME, dan hash bytes. URL bersifat rute terproteksi autentikasi, bukan lokasi file publik; respons memakai private/no-store dan nosniff. Teacher lain, Admin cabang lain, siswa, atau akun/profil tidak aktif tidak dapat mengakses file. Metadata/file rapor lama tidak ditautkan atau diakui sebagai persetujuan baru secara otomatis. Nomor versi baru melanjutkan nomor terbesar yang sudah ada.

### 30.4 Kesiapan akses orang tua dan pengiriman

Detail final menampilkan status ketiga laporan bulanan dan pembayaran kalender bulan yang sama. Kesiapan mensyaratkan tiga laporan bulanan Disetujui, final Disetujui, dan satu tagihan siswa yang jelas per bulan dengan bukti pembayaran Approved. Tagihan bulan lain atau beberapa tagihan dalam bulan kalender yang sama tidak memenuhi syarat. Bukti bulan terakhir bertanggal selain tanggal 1 tetap dicocokkan berdasarkan bulan kalender untuk kompatibilitas data lama.

Indikator kesiapan bersifat internal pada v39. Portal siswa/orang tua serta pengiriman WhatsApp belum diaktifkan. Implementasi berikutnya wajib menerapkan kembali gerbang bagian 10–11 pada rute akses dan tindakan pengiriman; indikator ini bukan pengganti pemeriksaan server saat akses/pengiriman. Tidak ada pesan WhatsApp nyata yang dikirim oleh v39.

### 30.5 Backend, pemasangan, pengujian, dan batas verifikasi

Backend menambah quarterly_reports, quarterly_report_events, report_template_settings, serta tiga kolom nullable pada quarterly_report_files. Migration additive tidak mereset tabel atau data lama, memakai charset/collation yang sama dengan skema backend awal, dan foreign key restrict untuk sejarah final. Rollback yang menghapus tabel baru ditolak bila final/template/file terkait sudah berisi data; metadata PDF lama tetap dipertahankan saat tabel baru masih kosong. Source v38 disertakan sebagai backup file yang diubah.

Dependency PHP menambah Dompdf 3.1.6 beserta empat library pendukung dan persyaratan ekstensi GD untuk gambar. Versi dependency yang sudah ada tidak diperbarui. Pemasangan membutuhkan composer install berdasarkan composer.lock, ekstensi DOM/mbstring/GD, dan php artisan migrate. Backup source serta database dilakukan sebelum pemasangan; tidak memakai migrate:fresh, reseed, SQL reset, atau perubahan .env. CSS langsung dari public/assets/dpe; build Vite tidak diperlukan. Instruksi diberikan di chat tepat setelah ZIP, tanpa README instalasi.

Pengujian mencakup seluruh 1.674 tes PHP (5.946 assertions), termasuk 83 kasus lifecycle final baru, serta 14 tes JavaScript absensi. Tidak ada failure/error/skipped. Kasus baru memeriksa periode, empat skill dan batas isian, Teacher aktual tanpa penugasan cabang tambahan, pilihan pemilik, cabang terakhir, akun/peran/penugasan aktif, pengajuan/revisi/persetujuan, konfirmasi/preview tanpa penulisan, formulir wajib-versi/kedaluwarsa, siklus isi yang kembali semula, transaksi dan rollback riwayat/file, gerbang pembayaran, pembekuan identitas/logo/template, file rusak/hilang, versi lama, dan rollback migration aman. Perhitungan akhir ruang kop juga diuji ulang melalui tiga kasus PDF nyata.

Struktur diperiksa pada 330 route, 327 aksi controller, 203 parameter binding, 38 model, dan 149 relasi tanpa kesalahan. PHP/Blade/JavaScript/CSS dan referensi halaman diperiksa; route/view cache dapat dibangun/dibersihkan. Empat PDF fixture A4 dirender untuk pemeriksaan visual setiap halaman: contoh biasa, teks panjang/Unicode/kata tanpa spasi, periode lintas tahun, dan kop maksimum. PDF fixture menggunakan siswa contoh dan tidak memakai identitas/tanda tangan siswa contoh klien.

Pengujian backend menggunakan PHP 8.3.6 dan SQLite :memory:. Database MySQL pengguna di Laragon belum dijalankan di sini. Layout browser diperiksa melalui respons HTTP/DOM dan parser CSS; inspeksi Chrome terhalang batas lingkungan. Pemeriksaan visual PDF memakai hasil renderer aplikasi. Hasil pengujian berlaku untuk kasus yang diperiksa, tanpa menjanjikan tidak mungkin ada bug di luar kasus tersebut. Tahap berikutnya adalah portal siswa/orang tua dengan gerbang persetujuan/pembayaran, kemudian pengiriman WhatsApp sesuai panduan.

## 31. Perapian tabel Rapor Tiga Bulan — v39.1 (12 Oktober 2026)

Pemilik telah memasang dan menguji v39. Perbaikan ini mengikuti dua gambar daftar Rapor Tiga Bulan: nama siswa menempel dengan Oktober, label status belum rata tengah, dan tombol Periksa periode bulanan belum terpusat di kolom Aksi.

Nama siswa ditampilkan pada baris tersendiri; periode berada di bawahnya dengan jarak 0,3 rem. Keterangan cabang juga berada di bawah nama Teacher. Header dan isi kolom Status serta Aksi rata tengah. Label status dan teks tombol, termasuk Periksa periode bulanan yang membungkus ke dua baris, dipusatkan di dalam komponennya. Tabel tetap dapat digulir horizontal pada layar sempit.

Aturan CSS dibatasi pada tabel daftar rapor final, digunakan bersama oleh Teacher, Admin Cabang, dan Super Admin. Perubahan source aplikasi hanya pada public/assets/dpe/quarterly-reports.css; controller, route, view, database, alur persetujuan, dan PDF tidak berubah. Versi URL stylesheet mengikuti waktu perubahan file yang sudah tersedia.

v39.1 merupakan ZIP patch di atas v39, berisi stylesheet, dokumen ini, dan salinan dua file sebelum perubahan. Tidak ada migration, dependency, build Vite, atau reset data. Instruksi PowerShell disampaikan langsung di chat di bawah ZIP, tanpa README instalasi.

Tiga tes halaman yang sudah ada lulus dengan 33 assertions. Parser CSS dan pencocokan aturan pada susunan tabel memeriksa pemisahan nama/periode, pemusatan status/tombol pada tiga keadaan, pembatasan terhadap tabel lain, serta versi URL aset. Pemeriksaan browser visual langsung belum dijalankan di lingkungan ini; pemilik memeriksa hasil setelah pemasangan.

## 32. Portal Siswa/Parent dan review pembayaran — v40

Pemilik telah memasang dan menguji v39.1, kemudian meminta progres berikutnya. v40 mengaktifkan C01–C04 pada aplikasi Laravel yang sama. Pernyataan bahwa portal belum aktif di bagian riwayat v38/v39 menjelaskan keadaan versi tersebut; keadaan implementasi terbaru mengikuti bagian ini. Perapian tabel rapor final v39.1 tetap berlaku.

### 32.1 Akun, dashboard, profil, jadwal, dan riwayat

Satu akun berperan student membaca satu profil melalui students.portal_user_id. Akun saudara tetap terpisah. Portal tidak menerima pilihan student_id dari formulir atau query untuk mengganti anak. Akun yang belum terhubung mendapat keterangan pada dashboard dan arahan menghubungi Admin; halaman data lain ditolak. Akun/peran yang tidak aktif serta akun Teacher/Admin/Super Admin tidak menggunakan rute portal orang tua. Petugas tetap memakai halaman tinjauan internal sesuai perannya.

Dashboard menampilkan nama anak, tanggal mulai les, pendaftaran yang berlaku, jumlah laporan terbuka di antara enam laporan terakhir, status pembayaran terakhir, maksimal empat jadwal terdekat dalam bulan berjalan dan berikutnya, serta pesan Teacher dari laporan bulanan yang sudah terbuka. Angka dan pesan berasal dari data siswa tersebut. Profil memuat identitas publik, sekolah, grade, tanggal bergabung, status siswa, program/cabang yang berlaku, dan nama Teacher dari penugasan aktif. Catatan internal profil tidak ditampilkan. Profil siswa nonaktif mempertahankan akses riwayat bagi akun portal yang masih aktif, tetapi tidak menerima unggah bukti baru.

Jadwal dan Riwayat Belajar dapat difilter per bulan. Pertemuan mengikuti tanggal aktual jika tersedia, pendaftaran program/cabang, keanggotaan kelas, dan tanggal mulai les. Sesi privat harus menghubungkan siswa sebagai peserta secara eksplisit; penugasan seorang Teacher tidak memasukkan semua siswa privatnya ke setiap sesi. Sesi kelas hanya tampil selama pendaftaran dan keanggotaan siswa berlaku. Pertemuan sebelum began_on tidak tampil. Pembatalan dan penggantian diberi keterangan. Pertemuan mendatang tidak diberi status kehadiran siswa.

Riwayat menampilkan materi dan aktivitas umum pada pertemuan yang berlangsung. Progres delapan kategori, ringkasan perkembangan, video, Challenges, dan Teacher's Message berasal dari salinan laporan bulanan yang telah disetujui dan pembayarannya tervalidasi. Catatan individual sesi maupun catatan internal laporan tidak masuk portal. Link video tetap melalui penyaringan URL yang sudah diterapkan pada v38 dan isi teks di-escape.

### 32.2 Akses laporan bulanan dan PDF final

Setiap laporan bulanan dibuka hanya bila statusnya Disetujui dan tepat satu tagihan siswa pada bulan kalender yang sama mempunyai bukti Approved. Tagihan bulan lain tidak membuka bulan tersebut. Data lama yang memakai tanggal selain tanggal 1 tetap dicocokkan berdasarkan bulan kalender. Beberapa tagihan dalam bulan kalender yang sama dikunci dengan arahan pemeriksaan periode; aplikasi tidak memilih salah satunya secara diam-diam.

Isi bulanan dibaca dari approved_snapshot versi 2, dengan pemeriksaan identitas report/student/cycle/branch/bulan. Salinan yang belum tersedia atau salah identitas tetap terkunci dan memerlukan pemeriksaan Admin. Portal tidak menggantinya dengan isi draf terkini. Perubahan sumber, video, atau nama pada data induk tidak mengganti isi yang sudah disetujui. Hanya field publik yang dipilih untuk dikirim ke tampilan.

Rapor final membutuhkan ketiga laporan bulanan Disetujui, final Disetujui, ketiga pembayaran tervalidasi, dan PDF valid pada revisi persetujuan serta hash sumber yang sesuai. Portal memakai file versi terbaru untuk persetujuan tersebut. File dibuat melalui alur internal v39; portal tidak membuat PDF saat membaca halaman. Detail final menampilkan empat skill Speaking/Reading/Listening/Writing, skor A/B/C, deskripsi, Note, serta Pratinjau/Unduh PDF.

Server mengulangi pemeriksaan kepemilikan dan gerbang pada halaman detail maupun rute PDF, termasuk ketika URL disimpan atau dibuka langsung. Pembatalan validasi pembayaran, revisi laporan bulanan, pembukaan ulang final, salinan sumber yang tidak sesuai, serta file hilang/rusak menutup akses portal kembali. URL versi lama tidak dipakai setelah pembuatan ulang; arsip petugas tetap tersimpan sesuai alur v39. Respons data portal, bukti, dan PDF memakai private/no-store, nosniff, dan Referrer-Policy same-origin.

### 32.3 Tagihan, unggah bukti, validasi, dan riwayat

Super Admin menyiapkan Tagihan Bulanan untuk siswa, cabang, dan bulan yang benar. Nominal tagihan menjadi opsional sesuai keputusan sebelumnya pada bagian 10; nilai yang belum ditetapkan disimpan NULL dan ditampilkan Belum ditetapkan. Nilai nol yang benar-benar diisi tetap merupakan Rp 0,00. Nominal lama tidak diganti, tidak ada tarif otomatis, dan portal tidak menebak biaya program. Jatuh tempo tetap opsional. Tagihan baru dinormalisasi ke tanggal 1 dan duplikat bulan kalender ditolak oleh layanan yang sudah ada.

Portal Pembayaran menampilkan periode, status, nominal/jatuh tempo jika tersedia, detail, serta riwayat bukti dan keputusan. Siswa aktif dapat mengunggah bukti bila belum ada bukti atau seluruh bukti ditolak. Bukti berstatus menunggu atau sudah tervalidasi tidak menerima unggah tambahan. Konfirmasi tampil sebelum pengiriman formulir. Akun pengunggah dan waktu berasal dari server; status awal selalu pending. Kiriman status/reviewer/akun lain tidak dipercaya.

Format bukti adalah JPG, PNG, atau WEBP, maksimal 5 MB dan 20 megapiksel. Server memeriksa isi gambar dan MIME. File disimpan pada disk privat dengan nama acak, ukuran, hash, dan metadata, lalu diakses melalui rute yang memeriksa pemilik atau Super Admin. Path yang tidak sesuai, file hilang, ukuran/MIME/hash yang berubah tidak dilayani. Gambar lama tanpa hash dapat dibaca bila path, ukuran, MIME, dan isi valid; riwayat/hash lama tidak dibuat-buat.

Menu Review Pembayaran Super Admin mempunyai filter status/nama siswa, detail bukti, tampilan gambar terlindungi, Validasi Pembayaran, serta Tolak Bukti dengan alasan wajib. Pembatalan validasi yang telah disetujui memakai tindakan Batalkan Validasi, konfirmasi, dan alasan wajib. Gambar yang tidak tersedia/rusak tidak dapat divalidasi melalui halaman baru. Penolakan tidak menghapus bukti lama; siswa dapat mengunggah bukti baru dan melihat alasannya. Satu tagihan tetap dibatasi satu bukti Approved oleh layanan yang sudah ada.

Formulir unggah, keputusan, dan pengaturan cabang membawa signature data yang sedang ditinjau. Perubahan data, klik ulang, serta perubahan status yang kembali ke nilai semula membuat formulir lama ditolak; pengguna diminta memuat halaman terbaru. Penyimpanan bukti/keputusan dan riwayat bersifat transaksional. Bila metadata atau riwayat gagal disimpan, transaksi dibatalkan dan file baru dibersihkan. Keputusan pembayaran, termasuk jalur layanan review yang sudah ada, mencatat aktor, waktu, before/after, alasan, siswa, cabang, tagihan, dan bukti. Riwayat sebelum v40 tidak direkonstruksi sebagai peristiwa baru.

### 32.4 Visibilitas absensi per cabang

Super Admin mendapat menu Pengaturan Portal. Absensi portal default tampil untuk setiap cabang. Formulir terpisah per cabang mengubah hanya cabang tersebut dan mencatat perubahan serta petugasnya. Akun siswa, Teacher, dan Admin Cabang tidak mengubah pengaturan ini.

Saat disembunyikan, label kehadiran pada Jadwal/Riwayat dan komponen rekap pada laporan bulanan cabang itu tidak dikirim ke tampilan portal. Materi publik dan isi laporan lain tetap mengikuti gerbang masing-masing. Rekap yang tampil merupakan rekap terkini dari bulan/cabang laporan dan tidak menganggap kotak yang belum dicatat sebagai tidak hadir. Data absensi internal tidak dihapus atau diganti. PDF final tetap mengikuti template v39 tanpa tabel absensi.

### 32.5 Backend, paket, pengujian, dan tahap berikutnya

Migration baru menambahkan branches.portal_attendance_visible, mengizinkan monthly_charges.amount_idr bernilai NULL dengan presisi lama dipertahankan, dan membuat student_portal_events. Foreign key memakai charset/collation serta jenis ID yang sama dengan skema awal dan restrict untuk mempertahankan riwayat. Tidak ada reset tabel, reseed, atau perubahan .env. Rollback ditolak jika sudah ada peristiwa portal, absensi suatu cabang disembunyikan, atau tagihan mempunyai nominal yang belum ditetapkan; data baru tidak dibuang saat rollback.

ZIP v40 merupakan patch di atas v39.1, berisi source yang berubah/baru, migration, tes, dokumen ini, dan salinan source v39.1 untuk file yang diganti. Backup source/database dilakukan sebelum pemasangan, lalu jalankan migration biasa dan bersihkan cache route/view. Tidak ada dependency baru, composer update, build Vite, migrate:fresh, atau SQL reset. Dependency PDF dari v39 tetap digunakan. Instruksi PowerShell diberikan langsung di chat setelah ZIP, tanpa README instalasi.

Seluruh 1.739 tes PHP lulus dengan 6.277 assertions; 14 tes JavaScript absensi juga lulus, tanpa failure/error/skipped. Tes portal baru mencakup 65 skenario dengan 313 assertions: akun satu anak/peran/nonaktif, URL data siswa lain, penetapan ulang akun, upload isi gambar/tiruan/ukuran/path, signature hilang/kedaluwarsa/klik ulang, penolakan/unggah ulang/riwayat, keputusan Super Admin, pembatalan validasi, rollback riwayat/file/pengaturan, persetujuan dan pembayaran per bulan, salinan publik yang dibekukan/di-escape, tagihan ambigu, PDF nyata/versi lama/revisi/file rusak, roster aktual/mulai les/keanggotaan/sesi privat, visibilitas per cabang, pembacaan tanpa penulisan, nominal opsional, dan migration yang mempertahankan data.

Pemeriksaan struktur mencakup 347 route, 344 aksi controller, 214 parameter binding, 39 model, dan 154 relasi tanpa kesalahan. Sebanyak 372 file PHP dan 189 view hasil kompilasi lolos pemeriksaan sintaks. Semua referensi statis route/view/component tersedia; cache route/view dapat dibangun dan dibersihkan. Parser memeriksa empat stylesheet dengan 617 selector dan 69 pemeriksaan properti layout pada lebar 1280/768/480: kolom Status/Aksi terpusat, nama/periode berjarak, filter memakai dua tombol sama besar 140×50 px sejajar form dan berubah proporsional pada mobile, serta gambar bukti tidak dipotong.

Pengujian memakai PHP 8.3.6 dan SQLite :memory:. MySQL di Windows/Laragon serta pemeriksaan visual browser langsung belum dijalankan di lingkungan ini; pemilik memeriksa setelah pemasangan. Layout diverifikasi melalui halaman hasil HTTP, kompilasi Blade, dan aturan CSS. Hasil ini membuktikan kasus yang diperiksa, tanpa menjanjikan bahwa seluruh kemungkinan bug telah tertutup.

Tahap berikutnya adalah integrasi Fonnte sesuai bagian 12: verifikasi dokumentasi resmi, koneksi Teacher, pengiriman PDF yang memeriksa ulang syarat persetujuan/pembayaran, serta pencatatan hasil pengiriman. v40 tidak mengirim pesan WhatsApp nyata.

## 33. Jarak formulir dan konfirmasi unggah pembayaran — v40.1

Pemilik melaporkan jarak nominal/petunjuk/tombol kirim terlalu rapat pada Detail Pembayaran, serta halaman 419 Page Expired setelah konfirmasi unggah bukti. Kedua gambar diperiksa sebagai acuan. Screenshot 419 menunjukkan penolakan token sesi; penyebab konfigurasi sesi di perangkat pemilik tidak disimpulkan dari gambar saja.

Nominal mempunyai jarak bawah 1,25 rem. Formulir unggah memakai grid dengan jarak 1,25 rem antara bagian input dan tombol; petunjuk format gambar berjarak 0,5 rem dari input. CSS dibatasi pada formulir/detail pembayaran dan tetap mengikuti ukuran tombol serta tata letak responsif v40. Aset menggunakan versi filemtime agar pembaruan terbaca setelah pemasangan.

Konfirmasi bersama menyediakan event pembatalan dpe:confirmed-submit untuk modul yang mengirim formulir secara terkelola. Modul pembayaran menangani event tersebut, mengambil konteks sesi terbaru dari GET /student/payments/{monthlyCharge}/upload-context, lalu mengirim FormData dengan token CSRF terbaru. Endpoint konteks memakai autentikasi/peran student aktif, kepemilikan profil/tagihan, dan respons private/no-store yang sama dengan portal. Endpoint ini tidak membuat bukti atau mengubah data.

Konteks terbaru tidak mengganti expected_snapshot yang telah ditinjau pengguna. Bila versi pembayaran berubah, bukti sedang menunggu/disetujui, atau siswa nonaktif, pengiriman berhenti dan pengguna diminta memuat halaman terbaru. FormData menangkap gambar sebelum input dinonaktifkan sementara. Token dikirim sebagai field _token serta header X-CSRF-TOKEN dengan credentials same-origin. CSRF tetap aktif; token lama atau hilang tetap ditolak server.

Pengiriman baru memakai respons JSON 201 setelah bukti dan riwayat berhasil disimpan. Halaman kembali ke detail pembayaran siswa dengan pesan berhasil dan status Menunggu Validasi. Formulir native tetap menggunakan redirect yang sudah ada. Pembatalan modal tidak mengambil konteks atau mengirim file. Tombol/gambar dinonaktifkan selama permintaan untuk mencegah klik ganda. Form konfirmasi modul lain tetap mengirim formulirnya sendiri.

Kesalahan 401/403/404/413/419/422/500, respons yang tidak sesuai, dan koneksi terputus ditampilkan sebagai teks pada halaman pembayaran; browser tidak diarahkan ke halaman galat unggah. Gambar pilihan tetap tersedia dan kontrol diaktifkan kembali saat gagal. Respons dengan hasil yang belum pasti meminta pengguna memeriksa riwayat sebelum mencoba ulang, tanpa mengirim otomatis. Gambar lebih dari 5 MB atau tipe yang tidak didukung dicegah di browser sebelum konfirmasi; pemeriksaan isi, ukuran, kepemilikan, signature, dan transaksi server v40 tetap berlaku. Alamat permintaan/tujuan dibatasi ke origin aplikasi.

Seluruh 1.748 tes PHP lulus dengan 6.322 assertions dan 33 tes JavaScript lulus, tanpa failure/error/skipped. Suite portal mencakup 74 kasus dengan 358 assertions, termasuk sembilan kasus tambahan untuk konteks sesi dan autentikasi. Tiga kasus memaksa middleware CSRF aktif: token berubah sejak halaman dibuka, token lama/hilang ditolak tanpa penulisan, serta token valid tidak melewati validasi signature/gambar. Sembilan belas tes JavaScript baru menjalankan interaksi konfirmasi bersama dan modul upload: batal, token terbaru, file/snapshot yang benar, klik ganda, status berubah, akun/sesi gagal, ukuran/tipe, kegagalan unggah, penyimpanan pilihan gambar, retry eksplisit, origin lain, dan formulir native modul lain. Empat belas tes absensi yang sudah ada tetap lulus.

ZIP merupakan patch terhadap v40, berisi file source/tes yang berubah, dua file JavaScript/test baru, dokumen ini, dan backup source sebelum revisi. Tidak ada migration, perubahan dependency, konfigurasi .env/sesi, atau reset data. Instruksi pemasangan disampaikan langsung di chat setelah ZIP. Pengujian memakai PHP/SQLite dan Node di lingkungan ini; browser Windows/Laragon serta konfigurasi sesi pengguna diperiksa setelah pemasangan. Tahap Fonnte tetap mengikuti rencana berikutnya.

## 34. Formulir hilang saat popup konfirmasi dibuka — v40.2

Pemilik melaporkan The image field is required setelah memasang v40.1. Screenshot memperlihatkan area unggah diganti kalimat konfirmasi, status masih Belum Unggah Bukti, dan riwayat kosong. Bukti belum tersimpan pada keadaan tersebut.

Penyebab ditemukan pada dashboard.js: formulir mempunyai data-confirm-title dan data-confirm-message sebagai opsi popup, sedangkan judul/pesan popup memakai nama atribut yang sama. document.querySelector mencari pada seluruh dokumen dan memilih formulir yang muncul lebih dahulu. Ketika openConfirm mengisi textContent, seluruh anak formulir termasuk file, token, signature, dan tombol terhapus. Pada jalur native hal ini dapat menghilangkan token; pada jalur v40.1 token terbaru dikirim lewat header tetapi gambar tetap hilang dari FormData. Perbaikan sesi v40.1 belum memperbaiki penyebab penghapusan DOM ini.

Pemilihan backdrop, tombol Batal/Konfirmasi, judul, dan pesan sekarang dibatasi di dalam elemen data-dashboard-confirm. Opsi dari formulir tetap dibaca melalui dataset; hanya isi judul/pesan popup yang diganti. Formulir, gambar pilihan, token, expected_snapshot, method spoofing, dan tombol tetap berada pada tempatnya ketika popup dibuka, dibatalkan, dibuka lagi, atau digunakan oleh formulir lain. Endpoint, validasi, penyimpanan, batas gambar, dan pemeriksaan CSRF v40/v40.1 tetap digunakan.

Pengujian frontend sebelumnya memakai pemetaan selector langsung ke elemen popup sehingga tidak merepresentasikan benturan atribut maupun penghapusan anak oleh textContent. Harness sekarang memakai struktur dari HTML Blade yang benar-benar dirender melalui tes fitur, memilih elemen pertama sesuai urutan dokumen, menjaga hubungan induk/anak, dan menghapus anak ketika textContent diganti. FormData menggunakan implementasi native Node dan mengambil kontrol yang masih berada dalam formulir. Fixture berisi data uji fiktif; bukan screenshot atau data pengguna.

Source v40.1 gagal pada dua tes gambar setelah harness diperbaiki: field image tidak ditemukan pada payload. Source v40.2 lulus seluruh 36 tes JavaScript, terdiri atas 22 kasus pembayaran/konfirmasi dan 14 absensi. Kasus tambahan memeriksa benturan atribut hasil Blade, keberadaan ketiga input setelah membuka popup, batal/buka ulang dengan gambar yang sama, formulir native lain beserta token/_method, serta serialisasi multipart native yang memuat nama file, bytes PNG, token terbaru, dan signature yang ditinjau.

Alur HTTP nyata juga diperiksa pada server PHP dengan database/storage fixture SQLite terpisah: login menggunakan cookie dan CSRF, membuka pembayaran sendiri, menjalankan konfirmasi dan pengiriman multipart, respons berhasil, status Menunggu Validasi, serta membuka ulang gambar terlindungi dengan hash bytes yang sama. Middleware CSRF tetap aktif pada server. Seluruh 1.748 tes PHP lulus dengan 6.322 assertions, tanpa failure/error/skipped. DOM untuk interaksi di lingkungan pengujian tetap terkontrol; pemeriksaan browser Windows/Laragon dilakukan pemilik setelah pemasangan.

ZIP v40.2 merupakan patch di atas v40.1. Perubahan aplikasi hanya pada dashboard.js; tes dan dokumen diperbarui serta source lama disertakan. Tidak ada migration, dependency, perubahan konfigurasi sesi/.env, atau reset data. Instruksi PowerShell diberikan langsung setelah ZIP, tanpa README instalasi. Setelah memasang, buka kembali halaman pembayaran dan muat ulang sebelum memilih/mengirim bukti.


## 35. Jarak form Super Admin dan pengiriman rapor Fonnte — v41

Paket v41 sudah dibuat dan diuji, tetapi belum dipasang oleh pemilik. Bagian ini mencatat hasil pengerjaan paket tersebut. Pemasangan/integrasi WhatsApp ditunda dan progres dilanjutkan melalui v42 langsung dari v40.2 sesuai bagian 36.

Pemilik mengonfirmasi v40.2 sudah dipasang dan berhasil diuji, lalu meminta jarak tombol Tolak Bukti dengan form pada Review Pembayaran serta jarak checkbox, keterangan, dan tombol di Pengaturan Portal diperbaiki sambil melanjutkan progres. Kedua form menggunakan grid dengan gap 1,25rem (20px). Gap diterapkan pada anak form, sehingga textarea/checkbox, keterangan, dan tombol tidak berhimpitan. Form WhatsApp menggunakan jarak antarkomponen dan grup tombol yang konsisten serta susunan responsif.

Tahap berikutnya pada bagian 13 diimplementasikan melalui backend Laravel dan halaman yang sudah digunakan aplikasi. Perubahan backend dan lima tabel tambahan diinformasikan sebelum pengerjaan. Menu Koneksi WhatsApp dan Pengiriman Rapor tersedia untuk Teacher, Admin Cabang, dan Super Admin; profil siswa Admin/Super Admin mempunyai tombol Penerima Rapor. Halaman rapor final mempunyai tautan Pengiriman WhatsApp.

### 35.1 Koneksi dan penerima

- Setiap Teacher menggunakan tepat satu perangkat Fonnte, sesuai nomor WhatsApp profilnya. Token diperiksa melalui device profile sebelum disimpan, dengan nomor yang harus sesuai. Token beberapa perangkat/rotator tidak diterima. Teacher mengelola koneksinya sendiri; Super Admin dapat membantu; Admin Cabang hanya memantau Teacher pada cabang yang berhak diakses. Penugasan siswa/laporan yang nyata ikut menentukan cakupan monitoring, tanpa mensyaratkan TeacherBranchAssignment tambahan.
- Tersedia penyimpanan token perangkat, QR pairing, pemeriksaan status, dan pemutusan koneksi dengan konfirmasi. QR harus berupa PNG base64 yang valid dengan batas ukuran/dimensi; URL gambar eksternal tidak dipakai. Token dan secret callback dienkripsi menggunakan APP_KEY. Token tidak diisi kembali pada halaman, tidak dimasukkan audit, dan tidak di-flash ketika validasi gagal. Perangkat/nomor yang sama tidak boleh dipakai Teacher berbeda. Perubahan pengaturan memakai signature dan revision agar form lama tidak menimpa perubahan berikutnya.
- Penerima rapor mempunyai jenis pemilik Orang Tua/Siswa, nama, satu nomor WhatsApp, dan sapaan Netral/Ibu/Bapak/Nama. Admin yang berhak atas profil siswa atau Super Admin mengaturnya. Nomor 0… dinormalisasi ke 62…; format internasional dibatasi satu nomor tanpa daftar, grup, atau variabel rotator. Bila belum ada pengaturan baru, hanya wali utama yang telah ditetapkan secara eksplisit dengan kontak valid yang dipakai. Kontak lain tidak ditebak ketika kontak utama tidak tersedia.
- Sapaan pesan mengikuti pilihan penerima; pilihan netral menggunakan Anda. Salam waktu mengikuti timezone cabang. Identitas siswa/cabang memakai salinan persetujuan; Teacher pengirim berasal dari akun perangkat yang bersangkutan. Template dasar bagian 13.5 dipertahankan, dengan personalisasi sapaan, identitas, dan penutup tanpa memaksakan Ms untuk semua Teacher.

### 35.2 Pengiriman dan jejak percobaan

Pratinjau memperlihatkan siswa/periode, Teacher dan nomor pengirim, jenis/nama/nomor penerima, operator, versi PDF, pesan, serta alasan syarat belum lengkap. PDF dapat dibuka lewat endpoint internal berizin. Teacher pemilik atau Super Admin menekan Kirim Rapor dan mengonfirmasi popup bersama; Admin Cabang memantau saja. Backend membangun payload dari data server dan mengabaikan target, token, pesan, atau file yang disisipkan dari client.

Pengiriman memakai pemeriksaan portal yang sama: final Approved, tepat tiga laporan bulanan Approved, ketiga pembayaran bulan kalender yang sesuai tervalidasi, salinan persetujuan dan PDF terbaru yang cocok, serta hash/ukuran/bytes PDF valid. Nomor Teacher, penerima, koneksi, versi file, status, dan signature diperiksa kembali setelah preflight perangkat dan tepat sebelum claim pengiriman. PDF dikirim sebagai multipart file langsung; file rapor tidak perlu dibuat publik. Aplikasi membatasi PDF kurang dari 4 MiB secara konservatif karena contoh penolakan pada dokumen send menyebut 4 MB, sedangkan halaman file limitation menyebut 10 MB. Batas paket/perangkat nyata tetap perlu diperiksa saat aktivasi.

Percobaan Dalam Antrean disimpan sebelum API send dihubungi, kemudian diklaim menjadi Mengirim. Idempotency key dan lock mencegah permintaan form yang sama menghasilkan kiriman kedua. Versi persetujuan dengan hasil aktif atau belum pasti tidak boleh dikirim ulang. Kegagalan yang diketahui dapat dicoba ulang dengan alasan wajib, sebagai percobaan baru; riwayat sebelumnya tetap tersimpan. Hasil timeout/HTTP atau identitas respons yang tidak dapat diverifikasi menjadi Menunggu Kepastian. Tidak ada retry POST otomatis.

Setiap percobaan menyimpan operator, Teacher/koneksi/revision, pengirim/penerima, pesan, versi persetujuan dan PDF, hash dokumen/source, nomor percobaan, waktu, ID pesan/permintaan/state gateway jika ada, status, dan alasan. Snapshot nomor dan pesan tidak berubah saat profil kemudian diperbarui. Audit mencatat permintaan, claim, respons, callback, penahanan, dan pemeriksaan operator. Riwayat manual report_share_attempts sebelumnya tetap dipertahankan dan tidak diubah menjadi bukti gateway otomatis.

### 35.3 Pembaruan status dan hasil belum pasti

Respons status true dengan satu ID pesan dan target yang sesuai dicatat Diterima Gateway; HTTP 200 bukan bukti Terkirim. Callback pesan yang sesuai perangkat dan identitas pesan memperbarui hasil. Callback duplikat tidak menambah audit kedua; callback yang datang sebelum respons send disimpan sementara lalu dicocokkan setelah ID tersedia. Pembaruan pending/gagal yang terlambat tidak menurunkan hasil sent/delivered/read. Kegagalan callback setelah queue acceptance dicatat Gagal.

Endpoint callback khusus menggunakan secret acak per koneksi, pemeriksaan nomor perangkat dan ID pesan/state, serta rate limit. Hanya jalur callback ini dikecualikan dari CSRF; perubahan dari akun staf tetap memerlukan sesi/CSRF. Callback status perangkat juga dibatasi nomor dan urutan timestamp. Secret URL tidak ditampilkan kepada Admin Cabang. Tidak ada signature HMAC dari provider yang diasumsikan tanpa kontrak resmi.

Fonnte status sent dipisahkan dari konfirmasi diterima/dibaca. State 0 ditampilkan sebagai belum diterima penerima. Mapping state angka lain ke delivered/read tidak ditebak; hanya status/state tekstual yang jelas dan terverifikasi dipakai untuk label tersebut. Callback mentah yang relevan tetap dicatat untuk penelusuran. Endpoint /status provider yang deprecated tidak dipakai. Halaman hasil membaca endpoint privat aplikasi sendiri secara otomatis, terbatas beberapa menit, tanpa memanggil polling provider atau mengirim ulang pesan. Respons salah/HTML, sesi gagal, origin lain, atau koneksi terputus tidak diubah menjadi keberhasilan.

Super Admin dapat mencatat bahwa pesan tidak terkirim pada hasil Menunggu Kepastian atau claim yang tertahan lebih dari dua menit, setelah memeriksa riwayat perangkat gateway. Alasan/bukti referensi dan checkbox konfirmasi wajib. Tindakan dicatat sebagai pemeriksaan Super Admin, bukan event Terkirim dari gateway, dan memungkinkan percobaan baru melalui pratinjau. Hasil accepted/sent/delivered/read tidak dapat diubah menjadi Terkirim melalui pengakuan manual.

### 35.4 Aktivasi dan batas pengujian

Tambahan database: fonnte_connections, report_recipients, whatsapp_dispatches, whatsapp_audits, dan fonnte_webhooks. Migration menambahkan tabel tanpa reset database. Rollback ditolak bila pengaturan atau riwayat sudah ada. Tidak ada dependency Composer/npm baru. APP_KEY lama harus dipertahankan agar token terenkripsi dapat dibaca.

Aktivasi dilakukan pada aplikasi: siapkan perangkat di akun Fonnte untuk nomor Teacher, gunakan paket yang mendukung file, masukkan token satu perangkat pada Koneksi WhatsApp, tautkan QR bila diperlukan, dan periksa status. Admin/Super Admin melengkapi Penerima Rapor. Setelah seluruh persetujuan/pembayaran dan PDF siap, Teacher meninjau lalu mengirim. Untuk callback, pasang Webhook URL yang ditampilkan pada pengaturan perangkat Fonnte dan aktifkan Auto Read; alamat aplikasi harus dapat diakses provider melalui HTTPS. Pengembangan Laragon dapat memakai upload file langsung, tetapi callback tidak dapat menjangkau localhost.

Kontrak diverifikasi dari dokumentasi resmi Fonnte: [send](https://docs.fonnte.com/api-send-message/), [device profile](https://docs.fonnte.com/api-device-profile/), [QR](https://docs.fonnte.com/api-get-qr/), [message webhook](https://docs.fonnte.com/webhook-update-message-status/), [device webhook](https://docs.fonnte.com/webhook-device-status/), [webhook configuration](https://docs.fonnte.com/webhook-url/), [message status](https://docs.fonnte.com/message-status/), dan [state 0](https://docs.fonnte.com/about-state-0/). Pengujian gateway menggunakan Http::fake, bukan token/perangkat atau penerima nyata. Keberhasilan pengiriman, paket akun, pairing, dan callback Fonnte nyata belum diklaim teruji. Pemilik perlu menguji tahap aktivasi menggunakan perangkat dan nomor penerima yang disepakati.

Seluruh 1.799 tes PHP lulus dengan 6.607 assertions, termasuk 51 kasus WhatsApp dengan 285 assertions, tanpa failure/error/skipped. Seluruh 42 tes JavaScript lulus. Hasil validasi akhir dicatat pada TEST_RESULTS.md di dalam paket. HTML Blade nyata digunakan untuk fixture konfirmasi dan status; kasus pembayaran/absensi lama tetap diuji. Cascade CSS form diperiksa pada 390px, 768px, dan 1440px. Alur HTTP memakai database/storage fixture terpisah dan gateway tiruan, tanpa mengirim WhatsApp nyata. Pemeriksaan tampilan browser Windows/Laragon dilakukan pemilik setelah pemasangan.

ZIP v41 merupakan patch di atas v40.2, memuat source/tes yang berubah, dokumen ini, dan backup source sebelum perubahan. Instruksi PowerShell disampaikan langsung setelah ZIP di chat; tidak dibuat README instalasi. Setelah tahap ini dipasang dan diuji, progres berikutnya mengikuti bagian 14: notifikasi dalam aplikasi serta monitoring kesiapan/hasil sesuai peran dan cabang.

## 36. Penundaan WhatsApp, notifikasi, dan monitoring — v42

### 36.1 Keputusan dan dasar paket

Pemilik meminta melewati tahap pengiriman WhatsApp sementara, lalu melanjutkan progres berikutnya sambil menunggu keputusan klien. v40.2 sudah dipasang dan diuji pemilik; v41 belum dipasang. Pembahasan Fonnte, Meta Cloud API, dan alur berbagi manual belum menghasilkan keputusan baru. Pilihan gateway, nomor pengirim, dan alur pengiriman tidak diganti secara sepihak. Web click-to-chat dapat menyiapkan nomor tujuan dan teks; lampiran PDF pada chat yang sudah dipilih tidak dijanjikan otomatis. Keputusan akhir dicatat saat pemilik menyampaikannya.

v42 dibuat langsung di atas source v40.2 dan tidak memerlukan v41. Paket memuat perbaikan jarak Review Pembayaran/Pengaturan Portal yang diminta sebelumnya, serta tahap 14.1–14.2 yang dapat berjalan sendiri. Tidak ada koneksi, kredensial, tabel, route, atau pengiriman Fonnte/Cloud API baru dalam paket v42. Kontak WhatsApp publik pada company profile tetap mengikuti modul publik yang sudah ada.

### 36.2 Pusat notifikasi

Menu Notifikasi dan panel lonceng tersedia untuk Super Admin, Admin Cabang, Teacher, dan Siswa/Parent. Panel menampilkan jumlah belum dibaca serta paling banyak empat pratinjau; halaman lengkap menyediakan filter jenis/status baca dan pagination 15 item. Pemberitahuan riwayat mencakup 90 hari terakhir. Pengingat/tugas yang masih aktif tetap muncul meskipun lebih lama, lalu hilang ketika kondisi terkait selesai. Halaman GET tidak mengubah status baca atau menciptakan peristiwa workflow.

Sumber pemberitahuan berasal dari data operasional dan riwayat yang sudah disimpan: laporan bulanan/final menunggu tinjauan, revisi untuk Teacher, persetujuan, tenggat bulanan/final dalam tiga hari atau terlewati, target video yang perlu dilengkapi, bukti pembayaran pending untuk Super Admin, hasil pembayaran, serta kehadiran Teacher yang menunggu pemeriksaan atau memerlukan koreksi. Teacher mendapat hasil tinjauan pada laporan miliknya; siswa mendapat metadata persetujuan/pembayaran miliknya. Pusat notifikasi tidak menyalin catatan internal, snapshot mentah, foto pembayaran/kehadiran, atau koordinat lokasi. Reminder berada dalam aplikasi.

Status baca disimpan per pengguna dengan key SHA-256 pada in_app_notification_reads. Membaca tidak menyelesaikan laporan/tugas. Pembacaan berulang mempertahankan satu receipt dan waktu baca pertama. Tindakan Tandai halaman ini dibaca hanya mengirim key item yang ditampilkan, maksimum 15; notifikasi yang baru tiba tidak ikut ditandai. Batch dengan key tidak berhak dibatalkan seluruhnya. Kondisi pengingat yang berubah menghasilkan key baru. Receipt lama tetap dipertahankan.

Tombol Buka memakai POST ber-CSRF, memeriksa ulang cakupan terkini, menandai dibaca, dan menuju route yang dibangun server. Field tujuan dari browser tidak digunakan. Perubahan Teacher pemilik, akun/profil nonaktif, atau akhir penugasan Admin tidak memberi akses melalui key lama. Pemberitahuan pembayaran Teacher mengikuti siswa/bulan laporan atau siklus final miliknya, termasuk tanggal tagihan yang bukan tanggal pertama. Notifikasi kehadiran Teacher dapat membuka catatan tepat yang dirujuk meskipun tidak termasuk 20 baris riwayat terbaru; halaman tersebut dibatasi ke Teacher pemilik aktif. API CRUD notifikasi Super Admin yang lama tetap ada. Notifikasi khusus milik pengguna ditampilkan sebagai teks yang di-escape; action_path lama tidak otomatis diikuti karena tidak mempunyai referensi objek yang dapat diverifikasi.

### 36.3 Monitoring dan dashboard

Menu Monitoring Laporan tersedia untuk Super Admin, Admin Cabang, dan Teacher. Filter mencakup bulan kalender, cabang, Teacher, nama siswa, status bulanan, serta status final. Cakupan memakai service akses laporan yang sudah ada: Super Admin seluruh cabang, Admin penugasan cabang aktif, dan Teacher pemilik laporan aktif. Nama Teacher/opsi filter mengikuti laporan pada periode/cabang yang berhak diakses, tanpa mewajibkan TeacherBranchAssignment tambahan. Parameter cabang/Teacher di luar cakupan ditolak server.

Ringkasan bulanan memperlihatkan total, Approved, belum Approved dengan rincian Draf/Diajukan/Revisi, serta overdue. Jumlah daftar, ringkasan, dan agregasi per Teacher berasal dari kumpulan hasil filter yang sama, sebelum pagination. Kekurangan video dan pembayaran belum tervalidasi merupakan indikator terpisah. Tenggat mengikuti timezone cabang, dengan fallback timezone aplikasi. Video draf dihitung menggunakan validasi periode, tanggal masuk, waktu pelaksanaan, serta URL yang dipakai modul perkembangan. Laporan Approved memakai target/daftar video dari salinan persetujuan; perubahan video sesudah persetujuan tidak mengubah hitungan tersebut. Persetujuan lama yang belum mempunyai salinan video/target diberi penanda perlu pemeriksaan.

Daftar final memuat siklus yang mencakup bulan terpilih. Status final/tenggat, ketiga status bulanan, ketiga pembayaran kalender, dan kesiapan PDF untuk portal dipisahkan. Pemeriksaan memakai StudentPortalService.quarterlyState yang sama dengan portal, termasuk revisi/hash salinan persetujuan dan bytes PDF. Tidak ada label PDF tersedia hanya karena final Approved, ketika pembayaran belum lengkap atau file berubah. Klik dari notifikasi maupun monitoring tetap memakai endpoint modul yang memeriksa izin dan penguncian konten.

Dashboard Teacher dan Super Admin memakai data operasional nyata: siswa binaan pada bulan terkait, periode laporan, sesi hari ini, serta laporan menunggu tinjauan. Sesi dibatalkan/diganti tidak dihitung pada daftar baru. Dashboard Admin mempertahankan aktivitas cabang, mendapat ringkasan monitoring bulan berjalan, dan menghitung Teacher aktif yang terkait melalui penugasan cabang, siswa, kelas, atau tanggung jawab laporan bulan berjalan. Guru dengan tanggung jawab nyata tidak hilang hanya karena belum mempunyai penugasan cabang tambahan. Monitoring laporan memuat periode yang sudah disiapkan Admin; siswa tanpa periode dapat dilihat melalui Perkembangan & Pesan, bukan dianggap tidak terdaftar.

Form Review Pembayaran/Pengaturan Portal memakai grid gap 1,25rem (20px). Filter notifikasi/monitoring memakai jarak 20px dan tombol Tampilkan/Reset dengan tinggi sama 50px, sejajar bagian bawah field. Nama/periode dipisah menjadi baris dengan jarak 8px; status dan aksi tabel monitoring berada di tengah kolom. Layout filter dan item notifikasi menyesuaikan layar kecil. CSS memakai nama kelas khusus, sehingga tidak mengubah susunan modul lain.

### 36.4 Migration, pengujian, dan pemasangan

Migration 2026_10_12_140000_create_in_app_notification_reads hanya membuat tabel receipt dengan primary key gabungan user_id/notification_key, foreign key restrict dan charset/collation sesuai skema proyek. Tabel workflow, notifikasi lama, bukti, serta dokumen tidak direset. Rollback ditolak ketika sudah ada receipt. Panel header tetap dapat dirender pada halaman operasional lama sebelum modul notifikasi diinisialisasi; lonceng dinonaktifkan jika tabel receipt belum tersedia.

Pengujian selesai: 1.800 tes PHP, 6.580 assertion, tanpa failure/error/skipped; termasuk 52 kasus modul ini dengan 258 assertion. Seluruh 36 tes JavaScript pembayaran/konfirmasi dan absensi yang sudah ada lulus. Uji HTTP nyata menggunakan akun/database fiktif terisolasi membuktikan login/cookie, CSRF aktif, receipt per pengguna, izin cabang/Teacher, key milik pengguna lain ditolak, route aksi dari server, unggah multipart setelah konfirmasi, bytes gambar tersimpan sama, notifikasi bukti pembayaran untuk Super Admin, dan pembatasan monitoring staf. Tidak ada pengiriman WhatsApp atau penggunaan akun penerima nyata.

Pembacaan target mengikuti StudentProgressService.targetReady: target otomatis yang sah pada bulan penuh/lanjutan tidak dinyatakan belum disiapkan hanya karena timestamp konfirmasi kosong. Bulan pertama untuk siswa yang mulai pertengahan bulan tetap memerlukan konfirmasi Admin. Pengingat video tidak ditampilkan sebelum tanggal siswa mulai les. Kelima kombinasi ini dicakup tes tambahan.

HTML Blade aktual dipakai untuk memeriksa CSS pada lebar 390, 768, dan 1.440px: 15 kombinasi halaman/lebar memverifikasi jarak form, ukuran/alignment tombol, pembagian nama/periode, dan alignment aksi. Blade dan route cache berhasil dikompilasi. Pengujian runtime memakai PHP 8.3.6/SQLite dan Node; tampilan browser langsung serta MySQL Windows/Laragon belum diuji di lingkungan ini. Hasil mengacu pada kasus yang diperiksa dan tidak menjanjikan seluruh kemungkinan bug telah tertutup.

ZIP v42 merupakan patch langsung di atas v40.2, memuat source/tes/dokumen yang berubah dan salinan source v40.2 untuk file yang diganti. Instruksi PowerShell diberikan di chat tepat setelah ZIP, tanpa README instalasi. Backup source/database dilakukan sebelum copy, lalu migration biasa dan pembersihan cache. Tidak ada dependency baru, composer update, build Vite, penggantian APP_KEY, migrate:fresh, atau reseed data pengguna.

Setelah pemilik memasang dan menguji v42, progres yang dapat dilanjutkan sementara WhatsApp ditunda adalah pencarian/filter dan ekspor Excel/PDF sesuai bagian 14.3. Pengiriman rapor dan monitoring hasil provider dilanjutkan setelah pilihan klien diketahui.

## 37. Pembaruan 12 Oktober 2026 — v43: pencarian, filter, dan ekspor operasional

### 37.1 Konfirmasi baseline dan keputusan yang masih ditunda

Pemilik telah memasang v42 serta menyatakan pengujian Laragon/MySQL yang dijelaskan di chat berhasil. V43 merupakan patch langsung di atas v42. V41 WhatsApp tidak dijadikan prasyarat dan tidak dimasukkan ke paket ini. Pilihan gateway maupun alur pengiriman rapor tetap menunggu keputusan klien.

### 37.2 Menu dan cakupan rekap

Menu **Data & Ekspor** tersedia bagi Super Admin, Admin Cabang, dan Teacher. Terdapat sepuluh rekap: siswa dan pendaftaran, Teacher, absensi siswa, absensi Teacher, laporan bulanan, perkembangan siswa, rapor tiga bulan, video belajar, jadwal/sesi belajar, serta slot Teacher. Monitoring Laporan memiliki tautan menuju rekap bulanan dengan filter bulan, cabang, Teacher, nama, dan status yang sesuai.

Super Admin melihat seluruh cakupan. Admin memakai penugasan cabang yang masih berlaku saat permintaan dilakukan. Teacher memakai profil aktif, penugasan siswa, kelas/sesi, serta kepemilikan laporan yang sebenarnya. Penugasan Teacher ke cabang secara formal tidak menjadi syarat tambahan untuk melihat siswa atau laporan miliknya. Pilihan Teacher Admin juga mempertimbangkan tanggung jawab nyata pada rentang waktu yang dipilih. Cabang di luar cakupan tidak disisipkan ke daftar nama cabang Teacher.

Akun siswa tidak mendapat akses ekspor staf. Akses diperiksa ulang dari akun, profil, penugasan, dan sumber data saat setiap unduhan. Mengubah parameter cabang/Teacher tidak dapat memperluas cakupan. Pusat notifikasi, konfirmasi absensi, pembayaran, serta pembatasan PDF portal memakai alur v42 yang sudah ada.

### 37.3 Filter, satuan baris, dan sumber isi

Rentang tanggal awal/akhir maksimal 366 hari. Filter cabang, Teacher, pencarian, dan status tersedia pada setiap jenis rekap; program, jenis kelas, kelas sekolah, tempat mengajar, serta mode absensi ditampilkan pada rekap yang memiliki konteks tersebut. Pergantian tab mempertahankan tanggal dan mengosongkan filter khusus agar status dari modul lain tidak terbawa.

Siswa dan pendaftaran memakai satu baris per pendaftaran yang beririsan dengan rentang tanggal dan tanggal mulai les. Super Admin juga dapat melihat profil tanpa pendaftaran pada rentang tersebut; Admin hanya melihat pendaftaran cabangnya. Teacher dapat memperoleh akses melalui penugasan siswa langsung maupun konteks kelas/sesi/laporan yang sah. Penugasan dan enrollment yang sudah berakhir diperiksa berdasarkan tanggalnya.

Absensi siswa memakai roster dan perhitungan StudentAttendanceService yang sama dengan layar absensi. Status null tetap Belum dicatat. Sesi mendatang, sebelum mulai les, dibatalkan, dan sudah diganti tetap ditandai menurut aturan yang ada. Kolom masuk perhitungan pertemuan mengacu pada cakupan sesi, bukan pengganti status Hadir. Absensi Teacher hanya mengekspor catatan tersimpan, bukan menyimpulkan ketidakhadiran dari tidak adanya catatan.

Laporan/progres memakai bulan kalender. Video berasal dari konteks laporan bulanan yang dapat diakses, kemudian dibatasi tanggal video; satu video dapat muncul dalam lebih dari satu konteks laporan. Laporan Approved memakai isi dan tautan pada salinan persetujuan. Progres memuat penilaian kategori yang tersimpan dan dapat dibaca petugas. Target video memakai perhitungan otomatis yang sah serta konfirmasi bulan pertama, sama dengan monitoring v42.

Rapor tiga bulan mencakup siklus yang beririsan dengan bulan kalender terpilih. Status persetujuan final berbeda dari kesiapan PDF portal; kesiapan tetap memeriksa tiga laporan, tiga pembayaran, salinan persetujuan, dan PDF final yang sah. PDF rekap operasional tidak mengubah template maupun izin unduh rapor resmi orang tua.

### 37.4 Pratinjau dan format unduhan

Pratinjau menampilkan 15 baris per halaman dengan jumlah seluruh hasil filter. Unduhan memuat seluruh hasil, bukan hanya halaman aktif. Pemeriksaan HMAC mengikat akun/peran, filter, susunan kolom, serta isi dan identitas baris yang dipratinjau. Jika data atau filter berubah, unduhan ditolak dan petugas diarahkan memeriksa pratinjau terbaru. Tampilan tidak mengubah data sumber atau tanda baca notifikasi.

Excel berupa file XLSX asli dengan Rincian dan Ringkasan, header beku, filter kolom, serta tanggal/waktu dan jumlah sebagai nilai bertipe. Nomor WhatsApp dan teks pengguna disimpan literal sebagai teks; teks berawalan rumus tidak menjadi formula. Teks panjang tetap utuh di Rincian dan dibagi berurutan pada sheet Uraian ketika diperlukan. Mulai v43.2 seluruh sel rata tengah horizontal/vertikal tanpa indentasi, memiliki border tipis di keempat sisi, dan menampilkan gridlines worksheet. PDF landscape A4/A3 memakai lebar kolom yang sesuai, header tabel berulang, nomor halaman, serta baris lanjutan untuk teks panjang.

Excel maksimal 3.000 baris sumber; PDF maksimal 300 baris sumber. Rekap dengan isi lebih dari 6 MiB atau terlalu banyak baris PDF lanjutan meminta filter dipersempit. Batas tersebut ditampilkan atau dijelaskan melalui pesan validasi; hasil tidak dipotong diam-diam. Foto/lokasi absensi, bukti transfer, kata sandi/token, catatan internal profil/Teacher, dan catatan slot pribadi tidak menjadi kolom ekspor. Unduhan memakai respons private/no-store, nosniff, nama file terkendali, dan pembatasan frekuensi.

### 37.5 Validasi dan penerapan

Seluruh 1.873 tes PHP lulus dengan 7.297 assertions, tanpa failure/error/skipped; 73 kasus ekspor memuat 717 assertions. Seluruh 36 tes JavaScript lama tetap lulus. Setelah perbaikan format teks Excel, 22 kasus unduhan/tipe sel/teks panjang diuji kembali dan lulus. Pengujian mencakup sepuluh rekap untuk tiga peran, hak akses yang berubah, filter/isi yang berubah, snapshot akun lain, tanggal penugasan, batas jumlah, pagination, target otomatis, isi Approved yang dibekukan, serta status absensi mendatang/belum dicatat/jadwal ulang.

Pengujian HTTP login/cookie dan unduhan menggunakan database/storage fixture terpisah memverifikasi XLSX/PDF nyata, jumlah sumber, respons privat, cakupan Teacher/Admin/Super Admin, larangan siswa, penolakan cabang asing, dan pemulihan dari pratinjau usang. Tidak ada WhatsApp atau akun penerima nyata yang digunakan. Struktur ZIP/XML, nilai dan format sel XLSX, impor/render spreadsheet, serta PDF hasil aplikasi diperiksa. Sepuluh contoh PDF dan contoh teks panjang dirender; contoh teks panjang memiliki empat halaman dan penanda akhirnya tercetak. CSS dari HTML Blade aktual diperiksa pada 390, 768, dan 1.440px: 30 kombinasi rekap/lebar untuk gap form/tombol, kesamaan tinggi tombol, status center, serta scroll tabel.

Runtime pengujian memakai PHP 8.3.6/SQLite dan Node. MySQL Laragon, Excel desktop, dan tampilan browser Windows v43 diuji pemilik setelah pemasangan: buka Data & Ekspor, pilih data siswa yang diketahui, terapkan filter, unduh kedua format, lalu cocokkan jumlah/isi dan coba akun cabang/Teacher lain. Hasil tes mengacu pada kasus yang diperiksa, bukan janji bahwa semua kemungkinan bug telah tertutup.

Paket tidak memerlukan dependency, migration, build Vite, atau perubahan .env/APP_KEY baru. Instruksi PowerShell diberikan langsung di chat setelah ZIP, tanpa README instalasi. Backup source dibuat di luar folder proyek sebelum file patch disalin; cache konfigurasi/rute/view dibersihkan, kemudian aplikasi dibuka kembali. Progres setelah UAT ekspor adalah pemeriksaan penerimaan lintas peran dan persiapan penerapan berdasarkan bagian 17–18. Pengiriman rapor dilanjutkan setelah keputusan klien tentang WhatsApp tersedia.

## 38. Pembaruan 13 Oktober 2026 — v43.1: perbaikan paket unduhan Excel

Pemilik telah memasang v43 dan melaporkan bahwa PDF berhasil dibuka, tetapi Excel menampilkan peringatan masalah isi serta gagal memperbaiki XLSX. Screenshot dibaca langsung sebagai bukti gejala. Perbaikan berfokus pada generator XLSX; perubahan progres berikutnya dan WhatsApp tetap ditunda.

Pemeriksaan berkas v43 menemukan header ZIP dengan nilai versi pembuat dan versi minimum ekstraksi 0 pada seluruh bagian XLSX. Pembaca ZIP/XML yang toleran, termasuk pemeriksaan sebelumnya, dapat menerima paket ini sehingga kelulusannya belum cukup membuktikan kompatibilitas Microsoft Excel. Temuan header tersebut diperbaiki dengan pembentukan paket ZIP 2.0 yang konsisten: header lokal dan direktori pusat, ukuran data, CRC32, serta offset setiap bagian. Kompresi Deflate dipakai jika tersedia; penyimpanan tanpa kompresi juga menghasilkan header sah. Generator tidak memerlukan Phar, ZipArchive, dependency baru, atau berkas sementara.

Isi XML workbook, susunan sheet, filter, header beku, tipe tanggal/jumlah/teks, nomor WhatsApp dengan nol awal, dan teks panjang memakai penyajian v43. Aturan hak akses, snapshot pratinjau, controller unduhan, database, frontend, serta generator PDF tidak diubah. Byte source bagian tersebut dibandingkan dengan baseline v43.

Tes ZIP sekarang memeriksa record biner dan keutuhan isi, termasuk header lokal/pusat yang sama, versi minimum, metode kompresi, ukuran, CRC, offset, jumlah bagian, serta akhir arsip. Tes ini diterapkan pada unduhan XLSX sepuluh rekap, ditambah kasus 0/1/3.000 baris dan teks Unicode panjang. Seluruh 77 tes ekspor/package lulus dengan 4.520 assertions. Cabang tanpa fungsi kompresi juga diuji: 4 tes dan 949 assertions lulus. Seluruh suite aplikasi berisi 1.877 tes PHP dengan 11.100 assertions lulus, tanpa failure/error/skipped.

Sembilan belas contoh XLSX hasil generator baru dibaca dengan ZIP/XML dan openpyxl, kemudian dibuka serta disimpan ulang melalui LibreOffice Calc. Nama sheet, dimensi, dan seluruh nilai sel dibandingkan sebelum/sesudah dan tetap sama, termasuk nol awal nomor telepon. Uji HTTP login/cookie memeriksa berkas XLSX unduhan yang benar-benar dihasilkan, header privat, jumlah sumber, cakupan akun, serta penolakan akses dan pratinjau usang. PDF masih diuji pada kasus unduhan yang sama. Microsoft Excel Windows tidak tersedia di lingkungan ini; pemilik perlu mengunduh ulang setelah memasang patch dan memeriksa file baru pada Excel yang digunakan.

Paket **DPE_Fix_v43_1_Excel.zip** merupakan patch di atas v43. Hanya generator XLSX, tes terkait, dan panduan proyek yang disertakan sebagai perubahan. Paket menyertakan backup source v43 untuk file yang diganti, tanpa .env, database, vendor, atau README instalasi. Instruksi pemasangan diberikan langsung di chat di bawah tautan ZIP. Tidak diperlukan migration atau pembaruan dependency. File baru harus diunduh dari aplikasi setelah pemasangan.

Acuan format kontainer: [PKWARE ZIP File Format Specification, bagian 4.3–4.4](https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT). Hasil pengujian dibatasi pada pemeriksaan yang dijalankan; compatibility check melalui Calc tidak dinyatakan sebagai pengujian Microsoft Excel Windows.


## 39. Pembaruan 13 Oktober 2026 — v43.2: garis Excel dan pemusatan tabel lintas peran

Pemilik mengonfirmasi bahwa XLSX v43.1 sudah berhasil dibuka pada Microsoft Excel Windows. Dua screenshot berikutnya memperlihatkan sel Excel tanpa garis pembatas serta isi pratinjau Data & Ekspor yang belum rata tengah, termasuk angka kelas sekolah. Pemilik meminta perbaikan garis Excel dan pemusatan isi tabel pada setiap role.

### 39.1 Excel

Generator sebelumnya menyembunyikan gridlines dan tidak memberi border pada sel isi. v43.2 menampilkan kembali gridlines worksheet serta menambahkan border tipis di kiri, kanan, atas, dan bawah seluruh sel tabel. Header memakai garis putih pada latar biru; sel isi memakai garis biru abu-abu yang terlihat pada baris putih maupun baris bergantian. Semua isi dan judul kolom rata tengah horizontal/vertikal tanpa indentasi, termasuk nama, sekolah, kelas sekolah, nomor, tanggal, waktu, jumlah, dan keterangan. Aturan berlaku pada Rincian, Ringkasan, serta Uraian bila teks panjang memerlukannya. Sel kosong di dalam rentang tabel juga diberi border.

Tipe sel, nilai, number format, warna zebra, pembungkusan teks, tinggi/lebar, header beku, filter kolom, dan batas ekspor dipertahankan. Nomor WhatsApp dengan nol awal dan teks berawalan karakter rumus tetap disimpan literal. Pembentukan ZIP standar v43.1 tidak diubah; tidak ada dependency baru.

### 39.2 Tabel web

Aturan komponen tabel dashboard dipusatkan pada semua peran: Super Admin, Admin Cabang, Teacher, serta Siswa/Parent. Header dan isi berada di tengah horizontal dan vertikal, termasuk pratinjau sepuluh jenis rekap, pengelolaan siswa/Teacher, penugasan, absensi, progres, laporan, monitoring, pembayaran, serta tabel portal siswa. Pengecualian lama pada nama siswa sticky dan rekap absensi diubah menjadi center. Kelompok tombol aksi dan daftar alasan di dalam sel ikut dipusatkan. Aturan hanya mengenai tabel; susunan form dan tombol filter tetap memakai desain yang telah diuji. Gulir horizontal, ukuran kolom, jarak antarelemen, serta posisi sticky absensi dipertahankan.

Perubahan runtime terdiri atas OperationalExportXlsxWriter.php, dashboard.css, student-attendance.css, dan operations.css. View/controller, layanan akses/filter/snapshot, route, database, JavaScript, dan generator PDF sama byte dengan v43.1. Format rapor resmi tidak berubah. Pengiriman WhatsApp tetap menunggu keputusan klien.

### 39.3 Validasi dan penerapan

Seluruh 1.877 tes PHP lulus dengan 11.100 assertions, tanpa failure/error/skipped. Modul ekspor/package memuat 77 tes dengan 4.520 assertions yang lulus. Lima belas contoh XLSX (sepuluh rekap, teks panjang, dan kasus package 0/1/3.000 baris serta Unicode panjang) memuat 18.831 sel tabel; setiap sel diperiksa memiliki border empat sisi dan alignment tengah. Nilai, tipe, number format, dimensi, susunan sheet, dan warna isi dibandingkan dengan hasil v43.1. Paket ZIP/XML valid dan seluruh 15 file dibuka serta disimpan ulang lewat LibreOffice Calc; semua nilai, perataan, dan border tetap tersimpan. Render Calc dari unduhan HTTP nyata memperlihatkan border serta angka kelas sekolah 6 yang berada di tengah. Microsoft Excel Windows tidak tersedia untuk pengujian v43.2 di lingkungan ini; keberhasilan membuka versi v43.1 dikonfirmasi oleh pemilik.

Audit mencakup 47 view dengan 56 tabel dashboard. HTML hasil aplikasi untuk 78 halaman memuat 76 tabel dan 1.599 sel, berasal dari Super Admin (37 halaman), Admin (20), Teacher (18), serta Siswa (3). Aturan CSS aktual, termasuk urutan stylesheet, prioritas selector dan pewarisan, diperiksa pada 390, 768, dan 1.440px: 375 kombinasi view/halaman dan ukuran lulus untuk centering header, isi, serta kelompok aksi yang ada. Pemeriksaan ini adalah pemeriksaan HTML/CSS, bukan screenshot browser Windows. Endpoint yang memang mengembalikan JSON tidak dihitung sebagai halaman tabel.

Uji HTTP login/cookie dan unduhan pada database/storage fixture terpisah memverifikasi Excel/PDF nyata, header privat, jumlah sumber, pembatasan cabang/Teacher, larangan ekspor siswa, serta penolakan pratinjau yang berubah. Tidak ada WhatsApp atau data produksi yang dipakai. Seluruh source di luar empat perubahan runtime dan panduan dibandingkan dengan baseline v43.1.

Paket **DPE_Fix_v43_2_Tabel.zip** adalah patch di atas v43.1, berisi empat file runtime dan DESIGN.md beserta backup source versi sebelumnya. Tidak diperlukan migration, update Composer/npm, build Vite, atau perubahan .env/APP_KEY. Langkah PowerShell diberikan langsung di chat di bawah ZIP, tanpa README instalasi. Setelah pemasangan, pemilik melakukan Ctrl+F5 pada browser dan mengunduh ulang Excel; file lama tidak berubah saat aplikasi diperbarui. UAT memeriksa border di semua sheet, angka kelas sekolah dan isi tabel yang berada di tengah, serta tabel pada akun empat peran yang digunakan.


## 40. Pembaruan 13 Oktober 2026 — v44: Konten Publik S08 dan revisi dashboard Admin

Pemilik mengonfirmasi v43.2 telah dipasang dan berhasil diuji, lalu meminta progres berikutnya sekaligus penghapusan kartu Monitoring laporan dari dashboard Admin Cabang. Screenshot menunjukkan kartu bulan/periode yang mengulang informasi pada menu Monitoring Laporan. Kartu tersebut dan query khususnya di dashboard Admin dihapus. Menu monitoring, cakupan data cabang, kartu Review laporan, serta dashboard Super Admin/Teacher tetap berfungsi.

Pemeriksaan progres menemukan pengelolaan konten publik sebelumnya masih memakai endpoint JSON. v44 melengkapi S08 melalui menu **Konten Publik** pada Super Admin, mengikuti alur bagian 6.2. Perubahan backend dan tambahan penyimpanan draft disampaikan kepada pemilik sebelum coding. Pengiriman WhatsApp tetap menunggu keputusan klien.

### 40.1 Alur pengelolaan website

Tab Identitas, Beranda, Tentang, Program, Cabang, dan Kontak menampilkan bagian template beserta status website dan draft yang terpisah. Pencarian/pagination tersedia. Bagian tetap memiliki tombol Edit konten; keunggulan layanan dan cara belajar dapat ditambah sebagai bagian mandiri. Nama serta status aktif Program/Cabang berasal dari data operasional yang sama dan tidak diubah oleh editor website. Metadata yatim dari penghapusan sumber melalui API lama tidak merusak daftar.

**Simpan Draft** menyimpan form dan foto secara terpisah dari konten website. **Pratinjau Draft** memakai template website yang sama dan mengganti hanya bagian draft yang dipilih; bagian lainnya memakai konten yang sedang tayang. Pratinjau program dapat memperlihatkan halaman rincian program agar fokus belajar dan durasi dapat diperiksa. Banner pratinjau memberi keterangan versi tersimpan dan tautan kembali ke editor. Navigasi utama pratinjau mempertahankan konteks privat. Field yang belum disimpan tidak ikut ditayangkan oleh tombol publikasi.

**Tayangkan** memakai draft tersimpan setelah konfirmasi dan menerapkan hanya bagian tersebut. Draft bagian lain tetap tersimpan. Operator dapat membuang draft tanpa mengganti versi website. Program, cabang, keunggulan, dan cara belajar dapat disembunyikan; perubahan visibilitas pada form juga tetap menjadi draft sampai ditayangkan. Bagian tambahan keunggulan/cara belajar dapat dihapus beserta draftnya dengan konfirmasi; riwayat tetap disimpan. Data program/cabang yang terhubung dengan kegiatan belajar tidak dihapus melalui editor website.

Identitas/footer, foto utama, pengantar beranda, profil/visi/misi/cara belajar, metadata program, metadata cabang, kontak dan media sosial terhubung ke pembaca konten publik. Label/tujuan tombol utama dapat diubah melalui pilihan halaman atau URL http/https yang valid. Tautan media sosial menggunakan HTTPS. Teks pengguna di-escape oleh Blade dan tidak menjadi HTML aktif. Konten/foto contoh tetap ditandai ketika mode contoh berlaku; publikasi nyata tidak diberi label alamat/kontak contoh. Konten contoh tidak muncul kembali sesudah konten terakhir yang pernah ditayangkan disembunyikan/dihapus. Menyimpan draft pertama tidak mengubah website contoh yang belum pernah dipublikasikan.

### 40.2 Penyimpanan, foto, dan pemeriksaan versi

Migration baru **2026_10_13_010000_create_website_content_drafts.php** membuat dua tabel: website_content_drafts dan website_content_changes. Tabel operasional yang sudah tersedia tidak diubah. Draft menyimpan payload, revision, hash versi website asal, operator, dan waktu. Riwayat menyimpan sebelum/sesudah untuk simpan draft, publikasi, penyembunyian, pembuangan draft, dan penghapusan bagian tambahan. UI menampilkan 15 aktivitas terakhir pada bagian yang dipilih; data riwayat tidak dipotong. Rollback yang dapat menghapus draft/riwayat terisi ditolak.

Tabel baru memakai charset utf8mb4/collation utf8mb4_unicode_ci tanpa menambahkan foreign key lintas tabel lama. ID operator dan payload historis tetap dapat ditelusuri jika sumber lama dihapus melalui API. ID file publikasi tetap memenuhi hubungan FileAsset pada tabel public_content_sections yang sudah ada.

Foto baru disimpan dengan nama UUID pada penyimpanan lokal privat website. Format unggah JPG/PNG/WebP, dimensi maksimal 4.096 × 4.096, dan batas aplikasi paling tinggi 4 MiB. Batas form/validasi memperhitungkan upload_max_filesize serta ruang post_max_size yang tersedia pada PHP; runtime pengujian dengan batas PHP 2 MiB menampilkan 2 MB pada form. Foto SVG, berkas yang menyamar sebagai gambar, ukuran/dimensi berlebih, serta unggah dan hapus foto secara bersamaan ditolak. Foto pembayaran/absensi tidak menjadi pilihan unggah website. Foto draft hanya dapat dibuka Super Admin melalui endpoint pratinjau; akses publik baru mengikuti referensi konten yang ditayangkan. Foto lama tidak dihapus fisik karena dapat diperlukan oleh riwayat atau bagian lain. Referensi publik yang dicabut tidak dapat diunduh lagi jika tidak ada bagian lain yang menayangkannya.

Pemeriksaan HMAC mengikat operator, bagian, versi draft, konten website, data master relevan, dan isi/metadata foto. Form usang, penggantian bagian/operator, perubahan melalui API lama, perubahan foto, serta klik ulang tidak menimpa versi yang lebih baru. Jika versi website berubah setelah draft disimpan, editor memperlihatkan konflik dan meminta perbandingan serta penyimpanan ulang sebelum publikasi. Foto yang hilang juga diperiksa kembali sebelum publikasi. Transaksi menjaga kesatuan perubahan metadata dan audit; berkas unggah baru dibersihkan bila penyimpanan gagal. Publikasi, penyembunyian, pembuangan, dan penghapusan memiliki konfirmasi; endpoint privat memeriksa autentikasi, role aktif, CSRF, dan pembatasan frekuensi.

Endpoint JSON public-content-sections lama dipertahankan untuk kompatibilitas backend. API tersebut masih dapat mengubah konten sesuai otorisasinya; editor draft mendeteksi perubahan tersebut dan tidak menimpanya secara diam-diam. Semua tindakan melalui UI S08 memakai alur draft/pratinjau/publikasi di atas.

### 40.3 Pengujian dan penerapan

Pengujian mencakup semua tab/jenis konten, draft baru dan edit versi tayang, isolasi draft lain, hak akses guest/Admin/Teacher/Siswa/akun nonaktif, operator berbeda, versi usang, klik ulang, perubahan API lama, foto privat dan pencabutan referensi, upload/URL/input tidak valid, pemulihan setelah audit gagal, penyembunyian data master tanpa menghapusnya, metadata yatim, pagination/pencarian, teks berawalan HTML, dan penghapusan bagian tambahan. Seluruh 1.939 tes PHP lulus dengan 11.880 assertions, tanpa failure/error/skipped; 77 tes modul konten/public memuat 889 assertions yang lulus. Seluruh 36 tes JavaScript tetap lulus. Audit HTML/CSS mencakup 49 view/58 tabel serta 86 halaman aktual/84 tabel/1.695 sel pada tiga ukuran layar (405 kombinasi), ditambah 51 pemeriksaan gap/tombol/susunan editor. Detail hasil disertakan dalam TEST_RESULTS.md pada paket v44.

HTTP memakai login/cookie dan database/storage fixture terpisah: keenam tab HTML, multipart upload, penolakan CSRF yang hilang, gambar/pratinjau privat, publikasi nyata, serta penolakan publikasi ulang. Dashboard Admin tidak memuat kartu pada screenshot dan menu monitoring tetap tersedia. Cakupan ekspor serta XLSX/PDF nyata tetap diperiksa. Tidak ada konten produksi atau pesan WhatsApp yang dikirim.

HTML/CSS diperiksa pada 390, 768, dan 1.440px untuk centering tabel empat role serta gap, tinggi tombol filter, dan susunan editor S08. Pemeriksaan MySQL berupa kompilasi DDL dari grammar Laravel, bukan eksekusi pada MySQL Laragon. Pengujian aplikasi memakai PHP 8.3.6/SQLite dan Node; Microsoft Excel dan browser Windows tidak tersedia di lingkungan ini. UAT pemilik memverifikasi tampilan serta migration pada Laragon yang digunakan.

Paket **DPE_Update_v44.zip** merupakan patch di atas v43.2. Tidak diperlukan perubahan Composer/npm, build Vite, .env, atau APP_KEY. Jalankan hanya migration baru di atas melalui --path agar tabel backend lama yang berasal dari impor SQL tidak dibuat ulang. Tidak menggunakan migrate:fresh, refresh, rollback terisi, atau seed ulang. Backup source disediakan dan dibuat lagi sebelum pemasangan; ZIP tidak memuat .env, database, vendor, unggahan pengguna, cache, atau README instalasi. Instruksi diberikan langsung di chat di bawah tautan ZIP.

UAT S08: masuk Super Admin → Konten Publik → Beranda → Foto & bagian utama; ubah judul/foto, Simpan Draft, lalu pastikan website publik belum berubah. Buka Pratinjau, periksa isinya, klik Tayangkan dan konfirmasi, kemudian cocokkan website publik. Periksa metadata Program/Cabang, penyembunyian tanpa penghapusan data operasional, akses role lain, serta dashboard Admin setelah kartu monitoring dihapus. Setelah peninjauan kelompok ini, progres selanjutnya ialah pemeriksaan penerimaan lintas peran dan persiapan penerapan bagian 17–18, sambil menunggu keputusan WhatsApp.


## 41. Revisi 13 Oktober 2026 — v44.1: dashboard Super Admin dan susunan halaman publik

Pemilik telah memasang dan menguji v44 dengan berhasil, lalu meminta empat revisi berdasarkan screenshot. Paket ini menyelesaikan revisi tampilan sebelum melanjutkan progres berikutnya.

### 41.1 Dashboard Super Admin

Bagian **Monitoring laporan** beserta empat kartu Laporan bulanan, Disetujui, Belum disetujui, dan Tenggat terlewati dihapus dari dashboard Super Admin. Partial dashboard bersama menampilkan bagian tersebut hanya untuk Teacher. Dashboard Admin tetap mengikuti revisi v44. Ringkasan utama Super Admin, jadwal hari ini, menu Monitoring Laporan, dan notifikasi tetap tersedia. Tidak ada penghapusan data laporan atau perubahan layanan akses dan perhitungan monitoring.

### 41.2 Jarak pengantar dan penyelarasan judul

Kartu keunggulan beranda sebelumnya selalu memakai margin negatif sehingga menutupi paragraf ketika pengantar beranda ditayangkan. Margin negatif sekarang dibatasi pada kartu keunggulan yang menjadi anak pertama di bawah foto utama. Ketika pengantar berada di atasnya, kartu mendapat margin atas positif 24px, di samping padding bawah pengantar 16px. Aturan berlaku pada desktop dan layar kecil, termasuk pengantar dengan paragraf panjang atau foto. Susunan tanpa pengantar tetap mempertahankan pertemuan kartu dengan foto utama.

Reset margin heading publik/login dilengkapi sampai h4–h6. Judul visi/misi, cara belajar, serta langkah memilih program tidak lagi memakai margin atas/bawah bawaan browser. Tinggi minimum baris judul mengikuti ukuran ikon, dengan teks disejajarkan secara vertikal pada baris itu. Deskripsi tetap memiliki jarak 6–8px; judul panjang dapat membungkus dan menambah tinggi. Kolom teks dapat menyusut tanpa mendorong ikon keluar kartu. Tidak memakai offset posisi atau margin negatif untuk menaikkan judul.

### 41.3 Pemeriksaan dan penerapan

Seluruh 129 tes yang relevan dengan monitoring/dashboard, halaman publik, serta alur draft/pratinjau/publikasi lulus dengan 1.148 assertions. Tes dashboard yang telah ada disesuaikan dengan keputusan terbaru: bagian Monitoring laporan tidak tampil pada dashboard Super Admin/Admin, dan tetap tampil pada Teacher. Blade seluruh aplikasi berhasil dikompilasi dan file tes yang berubah lolos pemeriksaan sintaks PHP.

Pemeriksaan CSS memakai HTML hasil aplikasi untuk 10 respons: beranda dengan pengantar pendek, panjang, foto, tanpa pengantar, dan judul panjang, serta Tentang/Program/Cabang/Kontak/Login. Cascade diperiksa pada 360, 390, 540, 768, 800, 1.024, dan 1.440px; 385 pemeriksaan lulus untuk jarak positif pengantar, pembatasan overlap pada foto utama, margin heading, ukuran baris terhadap ikon, pembungkusan teks, dan grid responsif. Ini pemeriksaan HTML/CSS, bukan screenshot browser Windows. UAT pemilik memeriksa hasil tampilan pada Laragon setelah Ctrl+F5.

Paket **DPE_Fix_v44_1_Tampilan.zip** adalah patch di atas v44. Dua file runtime berubah: public/assets/dpe/site.css dan resources/views/operations/_staff-dashboard.blade.php. DESIGN.md dan tes monitoring yang disesuaikan ikut disertakan, bersama backup keempat file v44. Tidak memerlukan migration, perubahan database, update Composer/npm, build Vite, atau perubahan .env/APP_KEY. Pengelolaan konten beserta draft yang telah disimpan tetap memakai alur v44. Instruksi pemasangan diberikan langsung di chat; ZIP tidak berisi README instalasi. Pengiriman WhatsApp tetap menunggu keputusan klien.


## 42. Pembaruan 14 Oktober 2026 — v44.2: Monitoring rapor dan Ringkasan cabang

Pemilik meminta dashboard yang lebih terisi melalui dua panel seperti gambar image(20261013-155844).png. Gambar bertuliskan Dashboard Super Admin dan mengikuti diskusi dashboard Super Admin sebelumnya. Penerapan pada Super Admin disampaikan sebelum coding. Dashboard Admin Cabang tetap mengikuti revisi yang telah disepakati. Pengambilan data backend dashboard disesuaikan setelah pemberitahuan, tanpa perubahan penyimpanan atau skema database.

### 42.1 Susunan dan perilaku dua panel

Di bawah empat kartu ringkasan utama, panel kiri **Monitoring rapor bulanan** menampilkan tiga bidang kecil: Completed berwarna hijau, Pending oranye, dan Overdue merah. Completed memakai status Approved; Pending mencakup semua status belum Approved; Overdue merupakan bagian Pending dengan tanggal tenggat terlewati menurut timezone cabang. Data berasal dari OperationsMonitoringService yang sama dengan menu Monitoring Laporan. Panel ini merangkum laporan bulanan, bukan status kesiapan PDF rapor final atau validasi pembayaran.

Daftar **Teacher perlu perhatian** memakai pemilik dan cabang yang tercatat pada laporan bulanan. Laporan pending dikelompokkan per pasangan Teacher/cabang sehingga satu Teacher yang menangani beberapa cabang tidak dimasukkan ke cabang yang keliru. Tiga baris prioritas ditampilkan, diurutkan berdasarkan jumlah overdue, pending, lalu nama. Keterangan tersedia jika ada lebih dari tiga kelompok. Tombol **Lihat monitoring** membuka menu yang sudah ada dengan bulan terpilih. Catatan internal dan isi laporan tidak ditampilkan pada kedua panel.

Pemilih periode menggunakan bulan kalender dan validasi yang sama dengan monitoring, dari Januari 2000 sampai Desember 2100. Input tidak valid ditolak. Periode terpilih hanya mengganti panel rapor; empat kartu utama yang berlabel bulan ini dan Jadwal hari ini tetap memakai cakupan waktunya semula. Bulan tanpa laporan menampilkan nol dan keterangan bahwa periode belum tersedia; tidak menambahkan data contoh atau membuat periode otomatis.

Panel kanan **Ringkasan cabang** menampilkan seluruh cabang aktif, nama cabang, batang biru, dan jumlah siswa aktif. Tanggal pada keterangannya menunjukkan keadaan saat ini, terpisah dari pemilih periode rapor. Siswa dihitung satu kali per cabang dari pendaftaran yang berlaku pada tanggal aplikasi, dengan status siswa aktif dan tanggal mulai belajar sudah berlaku. Pendaftaran mendatang, berakhir sebelum hari ini, dan siswa nonaktif tidak dihitung. Batas awal/akhir hari ini bersifat inklusif. Beberapa baris pendaftaran yang merujuk siswa yang sama tidak menggandakan hitungannya. Cabang tanpa siswa tetap tampil dengan nol; cabang nonaktif tidak masuk batang, sementara laporan historisnya tetap mengikuti cakupan monitoring yang berlaku.

Cabang diurutkan menurut jumlah siswa lalu nama. Batang dibandingkan dengan jumlah terbesar pada daftar; ketika semuanya nol, batas progress tetap positif dan isi batang nol. Label jumlah tetap terlihat dan progress memiliki nama aksesibel. Jika tidak ada cabang aktif, panel menampilkan keadaan kosong yang jelas.

### 42.2 Tampilan dan batas perubahan

Panel kiri lebih lebar daripada panel kanan pada desktop. Keduanya menjadi satu kolom pada lebar 1.100px atau lebih kecil; bidang status menjadi satu kolom pada 540px atau lebih kecil. Filter bulan dan tombol Tampilkan memiliki tinggi 42px dengan jarak 12px. Judul/paragraf dapat membungkus, dan kolom tabel Teacher tetap center mengikuti revisi tabel empat role sebelumnya. Stylesheet khusus dashboard-overview.css serta kedua panel hanya dimuat oleh view dashboard Super Admin. Ketika akun Super Admin membuka dashboard Admin/Teacher yang juga diizinkan oleh aplikasi, susunan halaman tersebut tetap memakai tampilan sebelumnya tanpa kedua panel baru.

Empat kartu Cabang aktif, Pengguna aktif, Sesi hari ini, dan Laporan diajukan bulan ini, tombol monitoring/notifikasi, serta Jadwal hari ini dipertahankan. Panel monitoring lama tidak ditambahkan kembali. Dashboard Admin/Teacher/Siswa, revisi halaman publik v44.1, pengelolaan konten/draft, pembayaran, XLSX/PDF, serta pengiriman WhatsApp tidak diubah oleh pembaruan ini. WhatsApp tetap menunggu keputusan klien.

### 42.3 Pengujian dan pemasangan

Seluruh 1.957 tes PHP lulus dengan 11.991 assertions, tanpa error/failure/skipped. Sebanyak 71 tes dashboard/monitoring/Admin lulus dengan 376 assertions, termasuk 18 kasus tambahan untuk kesamaan angka dengan monitoring, Approved dan overdue, pemilik/cabang laporan, urutan/batas prioritas, siswa distinct, tanggal pendaftaran, status nonaktif, nol, pemilih bulan, rentang/input tidak valid, cakupan empat role/akses Super Admin ke dashboard role lain, dan pembacaan tanpa mengubah laporan/pendaftaran.

HTTP dengan database/storage fixture terpisah memakai login/cookie nyata untuk empat peran. Tujuh halaman dashboard diperiksa, termasuk periode kosong. Dua panel, statistik lama/jadwal, CSS yang tersaji, pengamanan route, validasi bulan, dan tautan monitoring lulus pemeriksaan. Cascade tabel mencakup 50 view/59 tabel serta tujuh halaman aktual pada tiga lebar, dengan 171 kombinasi lulus. Kedua panel diperiksa pada 360, 390, 540, 768, 1.100, 1.440, dan 1.920px: 14 kombinasi lulus untuk posisi dua/satu kolom, bidang status, filter, tabel, dan batang cabang. Seluruh Blade berhasil dikompilasi; tiga file PHP yang berubah lolos pemeriksaan sintaks.

Pengujian menggunakan PHP 8.3.6/SQLite serta HTML/CSS hasil HTTP, bukan screenshot browser Windows atau eksekusi MySQL Laragon. Pemeriksaan pemilik setelah pemasangan mencocokkan tampilan dan angka dengan data lokal. Tidak ada data produksi, unggahan pengguna, atau pesan WhatsApp yang disentuh.

Paket **DPE_Update_v44_2_Dashboard.zip** adalah patch di atas v44.1, berisi delapan file project dan backup enam file sebelumnya. Enam file runtime terdiri atas controller Super Admin, StaffDashboardService, view dashboard/partial bersama, partial panel baru, serta CSS baru. DESIGN.md dan tes monitoring ikut disertakan. Tidak diperlukan migration, update Composer/npm, build Vite, atau perubahan .env/APP_KEY. ZIP tidak memuat database, dependensi, data/unggahan pengguna, cache, ataupun README instalasi; langkah PowerShell diberikan langsung di chat.

UAT: pasang patch, Ctrl+F5 dashboard Super Admin, periksa panel kiri/kanan sesuai gambar, cocokkan Completed/Pending/Overdue dengan Monitoring Laporan pada bulan yang sama, pilih bulan lain, lalu cocokkan siswa aktif per cabang dengan pendaftaran yang berlaku saat ini. Periksa empat kartu utama/Jadwal hari ini, tampilan HP, dan dashboard role lain.


## 43. Pembaruan 14 Oktober 2026 — v45: menu operasional Admin Cabang

Pemilik telah memasang dan menerima v44.2. Setelah meminta tabel sisa progres, pemilik menunjukkan bahwa masih ada menu sidebar yang belum terbuka. Daftar sebelumnya belum lengkap. Pemeriksaan source menemukan sepuluh menu dinonaktifkan: Pengaturan Sistem Super Admin; Kelompok Kelas, Jadwal & Sesi, Pembayaran, dan Pengaturan Cabang pada Admin; Jadwal Mengajar, Siswa Binaan, Profil Saya, dan Pengaturan pada Teacher; serta Pengaturan pada Siswa. Seluruhnya tetap merupakan pekerjaan implementasi. Pemilik meminta melanjutkan progres. Pengerjaan Kelompok Kelas/Jadwal & Sesi Admin, pembatasan backend, dan pemeriksaan penugasan disampaikan sebelum perubahan. Audit sidebar juga menemukan Siklus Laporan masih membuka JSON; penambahan halaman tabel disampaikan sebelum dikerjakan.

### 43.1 Kelompok Kelas dan peserta

Menu Kelompok Kelas Admin sekarang membuka daftar dengan pencarian, filter cabang/program/Teacher/status, dan pagination. Admin dapat menambah kelompok, membuka detail, mengedit, mengaktifkan/nonaktifkan, serta menambah/mengedit periode peserta. Cabang mengikuti penugasan Admin yang berlaku pada tanggal aplikasi. Cabang yang belum mulai/berakhir tidak memberikan akses. Super Admin tetap dapat menggunakan halaman Admin dengan cakupan seluruh cabang. Teacher/Siswa dan akun nonaktif ditolak server. Pembatasan berlaku pada daftar, dropdown, URL langsung, serta penyimpanan dan referensi yang dikirim melalui request.

Peserta harus mempunyai enrollment pada cabang/program kelas untuk periode yang sesuai; overlap keanggotaan pada kelas yang sama ditolak. Dropdown siswa menyesuaikan kelas. Siswa, kelompok, Teacher, dan sesi dari cabang lain tidak ditampilkan pada pilihan Admin. Tidak ada penghapusan riwayat keanggotaan. Tombol kembali/tutup/batal kembali ke daftar atau detail modul yang benar, sesuai halaman Admin/Super Admin yang dibuka.

Layanan kelas bersama mengunci record ketika mengedit/mengubah status. Cabang/program kelas dengan peserta atau sesi tidak dapat dipindahkan; kelompok baru digunakan untuk cabang/program lain. Referensi nonaktif yang sudah ada dapat dipertahankan ketika mengoreksi identitas kelas. Teacher baru harus mempunyai profil dan akun Teacher aktif. Layanan keanggotaan menjaga kelas/siswa dan cakupan tanggal absensi/catatan yang sudah tersimpan. Mengakhiri keanggotaan setelah tanggal riwayat tetap dapat dilakukan. Pengamanan ini dipakai pula melalui pengelolaan Super Admin.

### 43.2 Jadwal & Sesi serta pemilihan Teacher

Menu Jadwal & Sesi Admin menyediakan daftar, pencarian Teacher/kelas/topik, filter cabang/program/Teacher/status/tanggal, tambah, detail, dan edit sesi. Sesi cabang/private menggunakan model, layanan, serta roster/absensi yang sudah ada. Detail menyediakan akses Peserta / absensi untuk memeriksa peserta kelas atau menetapkan peserta private. Sesi yang dibuat Admin muncul pada absensi Teacher dan jadwal siswa sesuai penugasan/enrollment. Jadwal pengganti mempertahankan hubungan sesi asal; peserta private yang memenuhi syarat dapat diteruskan tanpa menyalin kehadiran.

Teacher tidak diwajibkan memiliki TeacherBranchAssignment tambahan ketika sudah memiliki tanggung jawab siswa/kelas/sesi/laporan/slot pada cabang tersebut. Pilihan mencakup sumber tanggung jawab yang benar dan nama dari tabel users. Penugasan siswa diperiksa bersama enrollment dan tanggal mulai siswa. Penugasan cabang/siswa yang mendatang dapat tampil untuk menyiapkan jadwal mendatang; server memeriksa keterkaitan Teacher pada tanggal sesi yang disimpan. Pilihan Teacher baru memerlukan profil dan akun aktif. Referensi Teacher nonaktif pada jadwal historis dapat dipertahankan untuk koreksi metadata, sementara pemindahan waktu/jadwal baru memerlukan Teacher aktif.

Dropdown Teacher mengikuti cabang; kelompok mengikuti cabang/program; sesi asal mengikuti cabang/program/kelompok. Memilih kelompok menyesuaikan program dan dapat mengisi Teacher utamanya ketika pilihan Teacher masih kosong. Pemeriksaan server tetap menjadi penentu. Referensi kelompok/sesi asal di luar cakupan ditolak. Status sesi baru dibatasi pada Scheduled/Ongoing/Completed/Cancelled; status lama dapat dipertahankan saat mengedit. Waktu selesai harus sesudah mulai; akhir aktual memerlukan awal aktual. Filter sampai tanggal dapat digunakan tanpa mengisi tanggal awal. Pencarian angka 0 tetap menjadi pencarian.

### 43.3 Bentrok, ketersediaan, dan formulir yang sudah berubah

LessonSchedulingService dipakai oleh penyimpanan sesi bersama untuk Admin dan Super Admin. Interval yang bertumpang tindih dengan sesi Teacher lain ditolak; sesi yang berakhir tepat pada waktu sesi berikutnya dimulai diperbolehkan. Sesi dibatalkan dan sesi yang sudah digantikan tidak menghalangi slot baru. Sesi asal penjadwalan ulang dikecualikan dari pemeriksaan bentrok untuk penggantinya. Pemeriksaan mengunci Teacher dalam transaksi penyimpanan. Pesan bentrok tidak membuka identitas siswa atau cabang lain.

Ketersediaan manual tetap opsional. Jika tersedia data slot pada tanggal/cabang yang dipilih, jadwal harus tercakup oleh Available untuk jenis program terkait. Slot Available yang bersebelahan dapat mencakup satu sesi; gap, jenis program berbeda, serta Booked/Blocked yang bertumpang tindih ditolak. Form menampilkan paling banyak 12 slot terdekat mulai hari ini pada cabang yang boleh dilihat. Jadwal dengan identitas/waktu yang tidak berubah dapat diperbaiki metadatanya tanpa menghapus riwayat. Perlindungan jadwal dengan absensi/catatan yang sudah ada tetap berlaku, termasuk lokasi mengajar.

Form edit kelas, keanggotaan, dan sesi Admin memakai fingerprint record ber-HMAC. Record diperiksa ulang setelah dikunci; halaman lama tidak menimpa perubahan yang lebih baru. Fingerprint lama dipertahankan pada redirect validasi agar input lama tidak langsung mendapat versi baru. Pengguna diminta memuat ulang dan meninjau data terkini. Perubahan status kelas juga memeriksa fingerprint. Tidak ada perubahan APP_KEY pengguna.

### 43.4 Siklus Laporan dan tampilan

Siklus Laporan pada Admin/Super Admin sekarang membuka tabel HTML: siswa, nomor siklus, periode, laporan bulanan yang boleh dilihat, tenggat final, dan aksi Periksa periode bulanan. Siapkan periode mengarah ke Perkembangan & Pesan yang sudah menangani Teacher, target video, dan penyiapan laporan. Tidak membuat siklus/periode melalui GET. Request Accept JSON tetap mendapat kontrak API data/meta lama. Pencarian/pagination dan pembatasan cabang tetap tersedia; tautan laporan bulanan diperiksa terhadap cakupan actor.

View kelas/keanggotaan/sesi dipakai bersama dengan prefix route yang diberikan controller. Tampilan Super Admin tetap memakai route Super Admin. Sidebar Admin menandai kelas/peserta dan sesi pada menu yang tepat. CSS/JavaScript branch-operations hanya dimuat pada ketiga kelompok route Admin terkait. Filter memakai grid responsif, gap 20px, gap tombol 12px, serta tombol Tampilkan/Reset dengan tinggi sama 50px. Semua tabel memakai perataan tengah horizontal/vertikal dari revisi v43.2. Filter Siklus Laporan memakai komponen/jarak operasi bersama. Dashboard v44.2 dan susunan publik v44.1 dipertahankan.

### 43.5 Pemeriksaan dan paket

Seluruh 2.026 tes PHP lulus dengan 12.290 assertions, tanpa error/failure/skipped. Pengujian kelas/sesi/siklus terkait memuat 168 tes/499 assertions yang lulus; 69 kasus baru mencakup akses/role/cabang, pilihan Teacher dari penugasan siswa, jadwal mendatang, create/edit/status/peserta, stale form, riwayat, jadwal ulang, bentrok, slot ketersediaan, alur Teacher/Siswa, serta HTML dan API Siklus Laporan. Seluruh 44 tes JavaScript lulus, termasuk delapan kasus baru untuk dropdown yang saling mengikuti pilihan dan pemulihan opsi ketika cabang/program/kelas diganti.

HTTP memakai login/cookie serta database/storage fixture terpisah, dengan CSRF aktif. Keempat role, 61 tautan sidebar yang aktif, 31 halaman tersimpan, create/edit kelas/peserta/sesi, form lama, konflik jadwal, penolakan cabang/role lain, roster Teacher, jadwal siswa, dan halaman Super Admin lama berhasil diperiksa. Seluruh Blade dikompilasi; 14 file PHP baru/berubah lolos pemeriksaan sintaks. HTML/CSS mencakup 52 view/61 tabel serta 31 halaman aktual/14 tabel/161 sel pada tiga lebar (249 kombinasi). Layout filter/siklus diperiksa pada 360, 390, 540, 768, 1.100, 1.440, dan 1.920px: 35 kombinasi lulus untuk gap, tinggi tombol, serta grid responsif.

Pemeriksaan memakai PHP 8.3.6/SQLite dan Node, serta HTML/CSS hasil HTTP. Pengujian penuh menggunakan batas memori 512M, dengan pemakaian maksimum 144,50 MB. MySQL Laragon dan screenshot browser Windows tidak tersedia di lingkungan pengerjaan. Pemilik memeriksa pemasangan, tampilan, serta alur pada Laragon yang dipakai. Data/unggahan produksi dan pesan WhatsApp tidak disentuh.

Paket **DPE_Update_v45_Operasional_Admin.zip** adalah patch di atas v44.2, berisi 37 file project dan backup 22 file sebelumnya. Tidak memerlukan migration, update Composer/npm, build Vite, atau perubahan .env/APP_KEY. Tidak memuat database, dependency, unggahan pengguna, cache, atau README instalasi. Instruksi pemasangan diberikan langsung di chat di bawah tautan ZIP.

### 43.6 Sisa pekerjaan yang dicatat

Dua dari sepuluh menu yang dinonaktifkan sudah diselesaikan pada v45. Delapan sisanya:

| Role | Menu yang masih perlu diselesaikan |
| --- | --- |
| Super Admin | Pengaturan Sistem |
| Admin Cabang | Pembayaran, Pengaturan Cabang |
| Teacher | Jadwal Mengajar beserta slot sendiri, Siswa Binaan, Profil Saya, Pengaturan |
| Siswa / Parent | Pengaturan |

Penyelesaian sidebar masih berjalan dan dilanjutkan sebelum menutup pemeriksaan lintas peran. Daftar sisa pekerjaan dikelompokkan menjadi tujuh tahap utama: penyelesaian sidebar; pemeriksaan alur lengkap antarrole; pemeriksaan akhir tampilan/penggunaan; penyiapan data/aset final klien; keputusan dan implementasi WhatsApp; pemasangan hosting; serta uji penerimaan/serah terima. Pengelompokan tersebut merupakan tahapan kerja yang dapat memerlukan beberapa pembaruan.

WhatsApp tetap menunggu keputusan klien antara alur otomatis/provider atau semiotomatis. v45 tidak memilih atau memasang Fonnte/Meta maupun mengganti alur secara sepihak. Tahap yang dapat berjalan sendiri diteruskan sesuai arahan pemilik. Tidak ada klaim bahwa seluruh implementasi atau penerimaan akhir telah selesai hanya karena prototipe atau sebagian menu sudah tersedia.

UAT v45: pasang di atas v44.2, Ctrl+F5, lalu masuk Admin Cabang. Buka Kelompok Kelas, tambah kelompok, dan tambahkan siswa dengan enrollment cabang/program yang sesuai. Buka Jadwal & Sesi, pilih Teacher/kelas, simpan sesi, lalu buka Peserta / absensi. Cocokkan daftar siswa pada akun Teacher dan jadwal siswa. Uji filter, tombol kembali, penjadwalan ulang, penolakan bentrok/slot diblokir, dan pemeliharaan absensi ketika mengedit. Buka Siklus Laporan pada Admin/Super Admin dan periksa tautan penyiapan periode. Cek halaman pengelolaan Super Admin dan tampilan HP/desktop. Delapan menu dan keputusan WhatsApp pada tabel di atas tetap merupakan pekerjaan berikutnya.


## 44. Progres v46 — Jadwal Mengajar, slot sendiri, dan Siswa Binaan

### 44.1 Dasar pekerjaan dan cakupan

Pemilik mengonfirmasi pemasangan serta pengujian v45 berhasil, lalu meminta melanjutkan progres. Dua menu Teacher yang belum terbuka diselesaikan: **Jadwal Mengajar** dan **Siswa Binaan**. Pengelolaan slot ketersediaan sendiri berada dalam menu Jadwal Mengajar sesuai T07. Pembatasan backend, pemakaian sumber penugasan/roster yang sama, perlindungan form lama, dan penggunaan tabel yang ada disampaikan sebelum perubahan. Source v45 dipulihkan dari paket yang tersimpan setelah lingkungan membersihkan file sementara; paket v41 yang belum dipasang karena keputusan WhatsApp tidak dijadikan basis.

### 44.2 Jadwal Mengajar dan detail kelas

Teacher melihat kartu sesi miliknya pada rentang tanggal dengan filter cabang, program, status, serta kelas/topik. Tersedia pintasan Hari ini dan 7 hari ke depan. Kartu berisi tanggal/waktu, nama kelas/program, cabang/lokasi mengajar, status, dan jumlah peserta unik. Tanggal memakai actual_start_at ketika tersedia, kemudian planned_start_at, sejalan dengan modul absensi/catatan/progres yang sudah ada.

Klik kartu membuka detail pertemuan dan daftar peserta, dengan tombol Buka Absensi Siswa, Catatan Belajar, serta kembali ke jadwal. Daftar peserta memakai StudentAttendanceService: anggota kelas diperiksa terhadap tanggal keanggotaan, enrollment cabang/program, dan tanggal mulai siswa; peserta sesi explicit tetap mengikuti riwayat yang tersedia. Tidak ada pencatatan absensi, penambahan peserta, atau penyiapan periode melalui pembukaan halaman. Halaman absensi serta konfirmasi checkbox yang sudah diterima tetap digunakan.

Akses jadwal mengikuti teacher_id pada sesi, termasuk Teacher pengganti, bukan otomatis diberikan kepada Teacher default kelas. Jadwal dibatalkan dan hubungan penjadwalan ulang tetap tampil sebagai riwayat. Link jadwal asal/pengganti hanya disediakan ketika sesi tersebut juga milik Teacher yang sedang login. Tombol Kehadiran saya hari ini tampil untuk sesi hari ini yang berlaku; halaman kehadiran tetap memakai alur yang ada. Teacher tidak mengubah penugasan atau membuat sesi kelas melalui menu ini; Admin/Super Admin tetap mengelola jadwal.

### 44.3 Siswa Binaan

Daftar dan detail memakai StudentProgressAccessService::students untuk bulan pilihan. Sumber mencakup penugasan siswa langsung, anggota kelas yang berlaku, peserta sesi sendiri yang memenuhi enrollment, serta laporan yang dimiliki Teacher. Siswa tidak disembunyikan hanya karena Admin belum menyiapkan periode rapor atau belum ada TeacherBranchAssignment tambahan. Penugasan yang mendatang/berakhir serta tanggal mulai siswa mengikuti bulan yang dipilih; siswa nonaktif tetap dapat dilihat melalui hubungan/riwayat yang berwenang.

Filter bulan, nama, dan status siswa tersedia. Detail menampilkan identitas belajar dasar, sekolah, kelas sekolah, tanggal mulai, pendaftaran bulan pilihan, kelas aktif bersama Teacher tersebut, dan sesi yang benar-benar diikuti bersama Teacher tersebut. Sesi dari Teacher lain tidak ditampilkan hanya karena siswa yang sama ikut di sana. Semua isi tabel, termasuk angka kelas sekolah, status, dan aksi, berada di tengah.

Periode milik Teacher yang sedang login memiliki link perkembangan dan laporan bulanan yang sudah ada. Jika periode belum disiapkan, ditampilkan penjelasan untuk Admin; tidak ada tombol penyiapan periode oleh Teacher. Jika pemilik periode Teacher lain, hanya indikator tersebut yang tampil, tanpa isi rapor, catatan, identitas Teacher lain, atau ID/link laporan tersebut. Catatan profil internal, akun/kredensial portal, kontak wali, dan foto privat tidak ditambahkan ke tampilan baru.

### 44.4 Slot ketersediaan sendiri dan pembatasan backend

Teacher dapat menambah dan mengubah tanggal, waktu mulai/selesai, cabang, jenis Regular/Private, status Tersedia/Available, Sudah terisi/Booked, Tidak tersedia/Blocked, serta catatan maksimal 255 karakter. Identitas Teacher berasal dari profil aktif akun login pada server; input teacher_id tidak diperbolehkan. Pilihan cabang mengikuti tanggung jawab nyata: penugasan cabang, penugasan siswa dengan enrollment/tanggal yang bertumpang tindih, kelas aktif, sesi yang berlaku, laporan, dan slot yang sudah ada. Saat menyimpan tanggal baru/cabang baru, hubungan diperiksa pada tanggal slot tersebut dan cabang harus aktif. Cabang historis nonaktif dapat dipertahankan pada tanggal slot yang sama.

Akun dan profil dibaca ulang agar halaman yang sudah terbuka tidak dapat dipakai setelah akses dicabut. URL detail/edit/penyimpanan slot Teacher lain ditolak. Pembaruan memeriksa signature record terbaru di dalam transaksi; signature lama dipertahankan pada redirect validasi agar form lama tidak memperoleh otorisasi versi terbaru. Waktu HH:mm dan HH:mm:ss didukung, nilai lama lowercase dinormalisasi, serta input array yang tidak valid menghasilkan validasi tanpa error server. Respons halaman baru memakai private/no-store dan nosniff; isi pengguna di-escape oleh Blade.

TeacherAvailabilityService bersama mengunci Teacher dan record slot ketika menyimpan, konsisten dengan kunci pada pemeriksaan jadwal. Slot Teacher memakai tabel teacher_availability yang sama, tampil pada Ketersediaan Guru Super Admin serta referensi slot Admin, dan langsung dipakai guard penjadwalan v45. Perubahan slot tidak memindahkan, membatalkan, atau menghapus absensi sesi yang sudah dibuat. Detail/edit menampilkan sesi sendiri yang bertumpang tindih pada cabang slot agar perubahan dapat dikoordinasikan dengan Admin. Tidak ada penghapusan slot/riwayat otomatis dan tidak ada migration baru.

### 44.5 Tampilan, paket, dan penerimaan

CSS teacher-workspace hanya dimuat pada halaman baru Teacher. Kartu jadwal dua kolom menjadi satu pada layar kecil. Filter memakai gap 20px, tombol dengan gap 12px dan tinggi sama 50px, serta grid tiga/dua/satu kolom menurut lebar layar. Card/form diberi jarak 24px; tabel dan aksi tetap center. Tombol kembali tersedia pada detail siswa, detail jadwal, tambah/edit/detail slot. Sidebar menandai Jadwal Mengajar aktif ketika slot dibuka.

Pengujian PHP 8.3.6/SQLite: 2.087 tes dengan 12.580 assertions lulus, tanpa error/failure/skipped. Sebanyak 61 tes fitur baru/281 assertions memeriksa akses empat role, penugasan/roster, tanggal aktual, jadwal ulang, periode rapor yang belum disiapkan, pemilik rapor lain, slot, input tidak valid, form lama, akun/profil dicabut, riwayat, integrasi penjadwalan, escaping, serta pagination. Seluruh 44 tes JavaScript yang ada juga lulus.

Pengujian HTTP memakai login/cookie nyata empat role: 63 tautan sidebar aktif membuka HTML; 21 halaman disimpan untuk pemeriksaan. CSRF, tambah/edit slot, signature lama, identitas/cabang lain, link absensi/catatan/progres, konsistensi roster, dan penolakan jadwal pada slot diblokir diperiksa. HTML/CSS pada tujuh lebar 360–1920 px memuat 70 pemeriksaan tabel/868 sel center, 21 pemeriksaan filter, tujuh pemeriksaan kartu jadwal, dan 21 pemeriksaan spacing form. Seluruh Blade dikompilasi dan delapan file PHP baru/berubah lolos pemeriksaan sintaks.

MySQL Laragon dan screenshot browser HP/Windows belum diuji di lingkungan pengerjaan. SQLite tidak menyimulasikan seluruh perilaku row lock MySQL; perlindungan transaksi diperiksa pada source dan melalui simulasi form lama. Tes menggunakan data/storage terpisah. Pemulihan dependency PDF hanya untuk melengkapi lingkungan tes setelah file sementara dibersihkan; composer.json/composer.lock untuk pengguna tidak berubah. Hasil pengujian bukan janji bahwa semua kemungkinan bug sudah hilang; UAT tetap dilakukan pemilik pada instalasi yang digunakan.

Paket DPE_Update_v46_Teacher.zip merupakan patch di atas v45, berisi 21 file project dan backup empat file sebelumnya. Tidak ada migration, pembaruan dependensi Composer/npm, build Vite, atau perubahan .env/APP_KEY untuk pengguna. Paket tidak memuat database, unggahan, dependency, cache, atau README instalasi. Cara pemasangan diberikan langsung di chat di bawah ZIP.

UAT v46: pasang di atas v45, tekan Ctrl+F5, lalu login Teacher. Buka Jadwal Mengajar, pilih tanggal sesi yang telah ditugaskan, buka kartu, dan cocokkan peserta dengan Absensi Siswa. Buka Siswa Binaan dan pastikan siswa yang ditugaskan tetapi belum memiliki periode rapor tetap muncul; cocokkan kelas sekolah, tautan perkembangan, filter bulan, dan tombol kembali. Buka Slot Ketersediaan, tambah slot pada cabang/tanggal penugasan yang valid, edit statusnya, lalu cocokkan slot pada akun Super Admin serta form penjadwalan Admin. Uji penolakan sesi yang berada pada waktu diblokir, dan pastikan perubahan slot tidak menghapus sesi/absensi yang sudah ada. Periksa tampilan HP/desktop pada Laragon yang digunakan.

### 44.6 Sisa pekerjaan

Enam menu sidebar masih perlu diselesaikan:

| Role | Menu |
| --- | --- |
| Super Admin | Pengaturan Sistem |
| Admin Cabang | Pembayaran, Pengaturan Cabang |
| Teacher | Profil Saya, Pengaturan |
| Siswa / Parent | Pengaturan |

Penyelesaian sidebar masih merupakan bagian tahap implementasi. Setelahnya tetap ada pemeriksaan alur lengkap antarrole, pemeriksaan akhir tampilan/penggunaan, penyiapan data/aset klien, keputusan dan implementasi WhatsApp, pemasangan hosting, serta uji penerimaan/serah terima. Pengelompokan tahap bukan jaminan jumlah ZIP. WhatsApp tetap menunggu keputusan klien dan tidak diaktifkan pada v46. Penerimaan final aplikasi belum dinyatakan selesai.


## 45. Progres v47 — Profil/pengaturan akun dan revisi dashboard Teacher

### 45.1 Arahan pemilik dan empat card dashboard

Pemilik mengonfirmasi v46 sudah dipasang, meminta progres berikutnya, dan melampirkan gambar blok Monitoring laporan pada dashboard Teacher. Empat card yang dimaksud adalah Laporan bulanan, Disetujui, Belum disetujui, dan Tenggat terlewati. Gambar berhasil dilihat pada file lampiran lokal. Blok tersebut beserta judul, tombol Buka monitoring, dan kalimat pengantarnya dihapus dari dashboard Teacher, mengikuti pola penghapusan blok lama pada Admin/Super Admin. Ringkasan aktivitas di bagian atas, sambutan, tombol Monitoring Laporan/Notifikasi, dan Jadwal hari ini tetap tampil. Menu Monitoring Laporan beserta data/rekapnya tetap tersedia; panel ringkas Monitoring rapor/Ringkasan cabang pada dashboard Super Admin tidak dihapus.

Progres berikutnya melengkapi Profil Saya Teacher, Pengaturan Teacher, dan Pengaturan Siswa/Parent. Cakupan serta perubahan backend untuk pemilik akun, verifikasi kata sandi lama, unggahan foto, dan login ulang disampaikan sebelum dikerjakan. Tidak ada migration baru.

### 45.2 Profil Saya Teacher

Halaman menampilkan nama, username, email, nomor WhatsApp, foto, status profil, cabang dengan tanggung jawab yang berlaku hari ini, serta kelas/program aktif yang menetapkan Teacher tersebut sebagai pengajar utama. Cabang memakai sumber penugasan v46 sehingga Teacher dengan siswa/kelas yang ditugaskan tidak harus memiliki TeacherBranchAssignment tambahan. Tersedia link Jadwal Mengajar, Siswa Binaan, Slot Ketersediaan, Pengaturan akun, Edit profil, dan kembali ke dashboard.

Teacher aktif dapat mengubah nama akun, email, nomor WhatsApp, dan foto dirinya sendiri. Identitas akun/Teacher berasal dari login pada server. Username, role, status, penugasan, dan referensi photo_file_id tidak dapat dikirim untuk diubah oleh Teacher. Profil yang belum disiapkan atau nonaktif memiliki keadaan baca dengan penjelasan untuk menghubungi Admin; edit ditolak. Pengaturan kata sandi tetap tersedia untuk akun yang aktif, termasuk akun yang profil Teacher/anaknya belum disiapkan.

Profil dibaca ulang, dikunci di dalam transaksi, dan dibandingkan dengan signature dari form. Perubahan Admin yang sudah tersimpan menolak form lama; signature lama dipertahankan ketika redirect validasi. Email bersifat opsional dan mengikuti keunikan akun; nama/email/kontak dipangkas dengan aman tanpa error ketika input bukan string. Nomor WhatsApp memuat 8–15 angka dan boleh memakai awalan +, spasi, tanda kurung, atau tanda hubung. Perubahan kontak tidak menghubungkan atau mengirim WhatsApp.

Unggahan baru dibatasi JPG/PNG/WebP, 2 MB, dan 4096 × 4096 piksel. Foto disimpan pada storage privat melalui FileAssetService. Endpoint foto tidak menerima ID Teacher/asset dari pengguna: hanya foto milik profil login yang dipilih. Path, format raster aktual, keberadaan file, dan SHA-256 diperiksa; respons memakai private/no-store dan nosniff. Foto lama GIF/BMP dari form Admin tetap dapat dilihat. File yang hilang/korup atau jenis yang tidak didukung menghasilkan 404 dan pratinjau kembali ke inisial.

Pembaruan User/Teacher/metadata foto berada dalam satu transaksi. Foto baru dibersihkan bila penyimpanan profil gagal. Foto lama dibersihkan setelah transaksi luar berhasil; referensi berkas yang masih dipakai dilindungi pemeriksaan foreign key. Perubahan profil tidak mengubah penugasan, absensi, periode rapor, pembayaran, atau snapshot PDF yang sudah ada. Pratinjau foto tidak menyimpan data sebelum tombol Simpan profil ditekan.

### 45.3 Pengaturan Teacher dan Siswa/Parent

Kedua role memiliki informasi akun serta form Ubah kata sandi, berisi kata sandi lama, kata sandi baru, dan konfirmasi. Nama pengguna, peran, status, serta hubungan anak/Teacher tidak dapat diubah lewat form ini. Akun Student menampilkan anak yang terhubung ke akun itu saja. Tersedia tombol tampilkan/sembunyikan kata sandi, konfirmasi sebelum menyimpan, Batal, dan kembali ke dashboard/profil.

Server mengunci dan memeriksa ulang akun aktif/perannya, memverifikasi kata sandi lama terhadap password_hash terkini, memeriksa konfirmasi, dan menolak kata sandi yang sama. Kata sandi baru minimal 8 karakter, memuat huruf dan angka, serta dibatasi 72 byte untuk mencegah pemotongan diam-diam pada bcrypt. Kata sandi disimpan melalui Hash::make. Permintaan dibatasi frekuensinya, CSRF berlaku, dan nilai kata sandi tidak dikembalikan dalam HTML/flash input saat validasi gagal.

Setelah berhasil, akun saat ini logout, session diinvalidasi, token CSRF diregenerasi, dan login menampilkan pesan bahwa kata sandi berhasil berubah. Kata sandi lama tidak dapat dipakai untuk login baru. Middleware AccountSession menggunakan pemeriksaan password marker Laravel pada route autentikasi: sesi dengan hash kata sandi lama meminta login ulang pada permintaan berikutnya. Marker tidak dipakai silang ketika identitas yang telah diautentikasi berubah ke akun lain. Sesi lama dari sebelum pemeriksaan ini dipasang baru memperoleh marker pada penggunaan pertama; tidak ada klaim penghapusan seluruh session historis secara fisik.

### 45.4 Tampilan dan hasil pengujian

CSS/JavaScript own-account hanya dipakai pada halaman akun baru; login menambahkan pesan sukses yang diperlukan untuk alur pergantian kata sandi. Form dan panel memakai jarak 24px, tombol/gabungan input memiliki gap 12px, kontrol kata sandi setinggi 50px, dan profil/upload menyesuaikan layar kecil. Data tabel dan aksi tetap center. Seluruh label menunjuk input yang benar. Password input tidak diisi ulang oleh server. Foto privat tetap melalui endpoint autentikasi.

Seluruh 2.154 tes PHP 8.3.6/SQLite lulus dengan 12.886 assertions, tanpa error/failure/skipped. Sebanyak 67 tes akun baru/296 assertions memeriksa penghapusan blok dashboard yang dimaksud, tiga menu aktif, empat role, profil sendiri, siswa sebagai sumber cabang, pemalsuan identitas/role, invalid input, email duplikat, signature lama, foto privat/korup/legacy, batas file, rollback file/data, akun dicabut, kata sandi lama/konfirmasi/aturan kata sandi, flash input, login baru, password marker, serta escaping. Seluruh 49 tes JavaScript lulus, termasuk lima kasus baru untuk toggle kata sandi, penggantian/cancel/validasi foto, pembersihan object URL, dan fallback avatar.

HTTP dengan login/cookie nyata empat role memeriksa 66 tautan sidebar aktif dan 28 halaman. Profil serta unggahan/unduhan foto, signature lama, CSRF, pergantian kata sandi Teacher/Student, login lama ditolak/login baru diterima, sesi kedua diminta login ulang, serta penghapusan blok monitoring Teacher diperiksa. Alur jadwal, peserta, perkembangan, slot, dan guard Admin v46 tetap lulus. HTML/CSS pada tujuh lebar 360–1920px memuat 98 pemeriksaan tabel/1.036 sel center, 28 pemeriksaan jarak form akun, 42 pemeriksaan kontrol kata sandi, serta pemeriksaan filter/kartu jadwal yang sudah ada. Seluruh Blade dikompilasi dan 11 file PHP baru/berubah lolos pemeriksaan sintaks.

Data/storage tes terpisah dari data pengguna. Screenshot browser HP/Windows, MySQL Laragon, serta semua pola transaksi bersamaan MySQL belum diuji langsung di lingkungan pengerjaan. Pemilik melakukan UAT pada instalasi yang digunakan. Tes audit halaman diperbarui untuk tidak menganggap endpoint foto privat tanpa foto sebagai halaman HTML; perilaku 404 tersebut diperiksa pada tes khusus foto. Tidak ada pengiriman WhatsApp atau perubahan konfigurasi provider.

### 45.5 Paket dan uji penerimaan lokal

DPE_Update_v47_Akun.zip adalah patch di atas v46: 22 file project dan backup sembilan file sebelumnya. Tidak memerlukan migration, Composer/npm update, build Vite, atau perubahan .env/APP_KEY. Paket tidak memuat database, unggahan pengguna, dependency, cache, maupun README instalasi. Instruksi pemasangan diberikan langsung di chat di bawah tautan ZIP. Nama/kontak/foto/kata sandi berubah hanya melalui tindakan pengguna sesudah pemasangan, bukan melalui instalasi patch.

UAT: pasang di atas v46, Ctrl+F5, lalu buka dashboard Teacher. Pastikan empat card pada blok Monitoring laporan hilang, sedangkan ringkasan aktivitas dan Jadwal hari ini tetap tampil. Buka Profil Saya, periksa cabang/kelas dan tombol kembali, edit nama/email/WhatsApp, lalu coba foto JPG/PNG/WebP yang memenuhi batas. Periksa pratinjau dan hasil setelah menyimpan. Buka Pengaturan, uji kata sandi lama yang salah serta konfirmasi yang tidak sama, lalu ubah dengan nilai valid; setelah keluar, login memakai kata sandi baru. Ulangi Pengaturan pada akun Siswa/Parent dan cocokkan anak terkait. Periksa tampilan HP/desktop serta menu monitoring/absensi/jadwal yang sebelumnya diterima. Jika kata sandi lama terlupa, reset dilakukan oleh Admin yang berwenang.

### 45.6 Sisa progres

Menu Teacher dan Siswa/Parent pada sidebar kini sudah aktif seluruhnya. Tiga menu yang masih perlu diselesaikan:

| Role | Menu |
| --- | --- |
| Super Admin | Pengaturan Sistem |
| Admin Cabang | Pembayaran, Pengaturan Cabang |

Setelah penyelesaian sidebar masih ada pemeriksaan alur lengkap antarrole, pemeriksaan akhir tampilan/penggunaan, penyiapan data/aset klien, keputusan dan implementasi WhatsApp, pemasangan hosting, serta uji penerimaan/serah terima. WhatsApp tetap menunggu keputusan klien; perubahan nomor kontak pada Profil Saya tidak memilih provider atau alur kirim. Penerimaan akhir seluruh aplikasi belum dinyatakan selesai.

## 46. Pembaruan 14 Oktober 2026 — v48: tiga menu sidebar terakhir

Pemilik telah memasang dan menerima v47, kemudian menanyakan alur penggantian password Teacher. Alur yang terkonfirmasi: Teacher mengganti password sendiri dengan password lama, password baru, pengulangan password baru, serta pop-up konfirmasi. Setelah berhasil, Teacher login ulang. Password lama tidak dapat ditampilkan kembali. Jika lupa, Super Admin dapat mereset melalui Pengguna → pilih Teacher → Edit → Password dan Konfirmasi password → Simpan perubahan. Admin Cabang belum mempunyai fitur reset password Teacher; permintaan dapat diteruskan kepada Super Admin. Pemilik memahami alur dan meminta progres berikutnya. Pertanyaan tersebut tidak dianggap permintaan menambahkan fitur reset baru.

Pemeriksaan panduan versi 1.29 dan source v47 menemukan tiga menu yang masih dinonaktifkan. Pengerjaan halaman, batas akses, penyimpanan dengan pemeriksaan form lama, status pembayaran, serta hubungan sakelar pengingat dengan notifikasi disampaikan sebelum perubahan backend. v48 melanjutkan aplikasi Laravel yang sama.

### 46.1 Pengaturan Sistem Super Admin

Menu Pengaturan Sistem membuka halaman HTML. Super Admin mengatur nama/deskripsi internal lembaga, email/telepon kontak, tanggal tenggat laporan bulanan, target video bulanan, dan pengingat tenggat/video dalam aplikasi. Halaman menyediakan tautan menuju Program, Kompetensi & Skill, Pengguna, Pengaturan Portal, Template Rapor, dan Konten Publik. Identitas company profile dan kop rapor tetap memakai pengelolaan kontennya masing-masing; pengaturan internal tidak menayangkan ulang konten publik.

Tanggal tenggat dibatasi 1–31. Tenggat pada bulan yang lebih pendek mengikuti tanggal terakhir bulan tersebut melalui layanan penyiapan periode yang sudah ada. Target video dibatasi 0–32767 sesuai kolom backend; 0 berarti tanpa target. Nilai ini digunakan ketika menyiapkan periode baru. Periode yang sudah dibuat tidak diubah massal. Target bulan pertama yang memerlukan penyesuaian tetap ditetapkan Admin berdasarkan jadwal siswa.

Sakelar pengingat sekarang dipakai oleh layanan notifikasi: jika dimatikan, pemberitahuan tenggat dan kelengkapan video tidak ditampilkan. Pemberitahuan persetujuan, pembayaran, kehadiran, serta pemberitahuan khusus tetap tersedia. Catatan dan tanda sudah dibaca tidak dihapus. Mengaktifkan kembali sakelar menampilkan pengingat yang masih relevan dengan tanda baca sebelumnya.

Jika singleton system_settings belum tersedia, GET menampilkan usulan awal dan petunjuk untuk menyimpan; GET tidak membuat record. Penyimpanan pertama memeriksa form kosong yang sama dan memakai insertOrIgnore untuk mencegah dua singleton. Referensi logo yang sudah ada dipertahankan; form tidak menerima logo_file_id, identitas akun, atau ID cabang. Endpoint JSON system-settings lama tetap tersedia untuk kompatibilitas.

### 46.2 Pengaturan Cabang Admin

Menu Pengaturan Cabang/Cabang Saya menampilkan cabang dalam penugasan Admin yang berlaku hari ini: nama/kode, alamat, zona waktu, tanggal rekap pembayaran, visibilitas absensi portal, dan status. Cabang tanpa penugasan, penugasan mendatang, atau penugasan yang berakhir tidak memberikan akses. Cabang nonaktif dapat dilihat sebagai riwayat dalam penugasan, tetapi alamatnya tidak dapat diubah. Admin tanpa penugasan mendapat petunjuk menghubungi Super Admin.

Admin hanya mengubah alamat melalui form terpisah dengan tombol Kembali/Batal dan pop-up konfirmasi. Alamat wajib diisi, maksimal 5000 karakter. Nama/kode, zona waktu, tanggal rekap, status cabang, visibilitas portal, identitas Admin, dan ID cabang tidak boleh dikirim sebagai perubahan form. Super Admin tetap mengelola bidang tersebut melalui modulnya. Teacher/Siswa ditolak pada URL langsung maupun penyimpanan. Super Admin dapat membuka modul Admin dengan cakupan seluruh cabang.

### 46.3 Pembayaran Admin Cabang

Menu Pembayaran menampilkan tagihan sesuai bulan, cabang, pencarian nama siswa, status, dan pagination 15 baris. Status memakai aturan PortalPaymentService yang sama dengan portal anak: Belum Unggah Bukti, Menunggu Validasi, Tervalidasi, Ditolak, atau Perlu Pemeriksaan Admin. Bukti berstatus lama/tidak dikenal tetap perlu pemeriksaan. Penolakan, unggah ulang, persetujuan, dan pembatalan persetujuan mengikuti data bukti yang tersimpan. Duplikasi tagihan pada bulan kalender yang sama ditandai perlu pemeriksaan walaupun tanggal charge_month berbeda. Detail cabang lain tidak dibuka saat menandai duplikasi.

Cakupan mengikuti cabang pada tagihan, bukan penugasan Teacher atau enrollment siswa saat ini. Pergantian hak akses/penugasan Admin diperiksa ulang pada setiap permintaan. Daftar, dropdown, detail tagihan, gambar bukti, dan unduhan tidak dapat digunakan untuk membuka cabang lain. Teacher/Siswa serta akun nonaktif ditolak. Status diproses dalam batch; pemeriksaan 31 tagihan menggunakan kurang dari 20 query, tanpa satu query status per baris.

Detail menampilkan nominal/jatuh tempo jika tersedia, status, tabel bukti, pemeriksa/waktu, alasan keputusan, serta riwayat unggah/keputusan. Gambar memakai endpoint privat dan pemeriksaan bytes/hash/MIME dari layanan portal. Gambar hilang/rusak ditandai tidak tersedia. Tidak ada tombol validasi/penolakan pada akun Admin. Super Admin yang membuka detail ini mendapat tautan ke Review Pembayaran, sementara validasi tetap dilakukan melalui modul Super Admin.

Tanggal rekap berasal dari pengaturan cabang untuk bulan yang dipilih dan dipendekkan ke akhir bulan bila perlu. Tanggal rekap tidak membuat jatuh tempo, nominal, denda, atau cicilan. GET tidak membuat tagihan, bukti, keputusan, maupun aktivitas pembayaran.

### 46.4 Unduhan, form lama, dan riwayat pengaturan

Rekap pembayaran menyediakan Excel dan PDF menggunakan penulis ekspor yang telah diperbaiki sebelumnya. Unduhan memuat seluruh hasil filter, termasuk halaman lain. Batasnya 3000 tagihan untuk rekap/Excel dan 300 baris untuk PDF. Hasil melebihi batas diminta dipersempit; data tidak dipotong diam-diam. Fingerprint unduhan mengikat pengguna, role, cabang yang boleh diakses, filter, baris, dan bukti sumber. Perubahan bukti/keputusan/filter/tanggal rekap setelah pratinjau meminta pengguna menampilkan rekap terbaru. Gambar bukti, catatan internal siswa, dan salinan riwayat mentah tidak masuk ekspor.

Pengaturan sistem dan alamat cabang memakai HMAC record serta riwayat penyimpanan. Data dikunci, aktor/cabang diperiksa ulang, lalu versi form diperiksa sebelum menyimpan. Form lama ditolak termasuk ketika nilai diubah lalu dikembalikan ke nilai awal. Fingerprint lama tetap berada pada redirect validasi; pengguna harus memuat ulang dan meninjau data terkini.

Riwayat memakai tabel student_portal_events yang sudah tersedia sejak v40, dengan event_type system_settings_changed, branch_address_changed, dan branch_settings_changed. Catatan pengaturan tidak mempunyai charge_id/payment_proof_id/student_id, sehingga tidak masuk riwayat pembayaran anak atau notifikasi keputusan pembayaran. Tersimpan aktor, waktu, dan sebelum/sesudah. Perubahan pengaturan serta riwayatnya berada dalam satu transaksi; kegagalan riwayat membatalkan perubahan. Layanan cabang/pengaturan lama mengunci record dan mencatat perubahan HTTP ketika tabel riwayat tersedia, sambil tetap mendukung schema backend lama pada pengujian kompatibilitas.

### 46.5 Tampilan dan verifikasi

Halaman baru memakai shell, ikon, warna, dan komponen yang sudah ada. Form pengaturan tidak diberi kartu luar; seksi memakai gap 24–32 px. Filter pembayaran memakai gap 20 px, tombol Tampilkan/Reset gap 12 px dan tinggi sama 50 px, sejajar bagian bawah field. Label checkbox sejajar dengan kontrol. Nama/bulan serta status/keterangan dipisahkan dengan jarak 8 px. Semua judul/isi tabel dan aksi tetap center horizontal/vertikal. CSS khusus halaman baru tidak mengubah dashboard Teacher atau dashboard role lain.

Suite regresi penuh: 2217 tes PHP lulus, 13429 assertions, tanpa error/failure/skipped. Terdapat 38 kasus Pengaturan Sistem/Cabang dan 24 kasus Pembayaran baru, serta satu kasus sakelar pengingat baru. Pengujian terkait diulang setelah penyesuaian pagination halaman kosong: 190 tes/1030 assertions lulus. JavaScript: 49 tes lulus; tidak ada JavaScript baru dalam patch.

HTTP dengan login/cookie nyata empat role: 69 tautan sidebar aktif dan 34 halaman untuk pemeriksaan. Perubahan sistem/alamat, form lama, CSRF, pembatasan role/cabang, detail/gambar bukti, dan kedua format unduhan lulus. Alur jadwal/roster/slot/profil/password v46–v47 tetap diperiksa. HTML/CSS pada lebar 360/390/540/768/1100/1440/1920 px: 126 pemeriksaan tabel/1344 sel center, 21 pemeriksaan jarak form pengaturan, 7 pemeriksaan filter pembayaran, serta pemeriksaan filter/akun sebelumnya. Tidak memakai screenshot browser.

Excel hasil HTTP dibuka dengan openpyxl; ZIP standar, kedua sheet dapat dibaca, 36 sel terisi memiliki garis tipis empat sisi dan perataan center. PDF hasil HTTP diekstrak dan dirender menjadi gambar untuk diperiksa: satu halaman, tabel terbaca, tanpa isi cabang lain. Pengujian ekspor tambahan memeriksa 18 tagihan agar semua halaman tercakup. Semua Blade dikompilasi dan 15 file PHP baru/berubah diperiksa sintaksnya. Database/storage fixture terpisah dari data pemilik.

Pemeriksaan MySQL Laragon dan penggunaan browser HP/Windows tetap dilakukan pemilik pada pemasangan. Pengujian otomatis tidak menjadi pernyataan bahwa seluruh kemungkinan bug atau transaksi MySQL bersamaan telah selesai diuji.

### 46.6 Paket, uji penerimaan, dan progres berikutnya

Paket DPE_Update_v48_Admin_Sistem.zip dibuat di atas v47. Isinya 23 file project, backup delapan source v47, manifest, dan hasil pemeriksaan. Tidak berisi README instalasi, vendor, .env, APP_KEY, database/data produksi, atau unggahan pengguna. Tidak ada migration/dependency/build baru. Perubahan riwayat baru terjadi melalui tindakan pengguna setelah pemasangan. Cara pemasangan PowerShell lengkap diberikan langsung di chat.

Uji penerimaan: (1) Super Admin membuka Pengaturan Sistem, simpan target/tenggat, dan pastikan form lama ditolak; periksa periode lama tetap memakai nilai tersimpan. (2) Admin membuka Pengaturan Cabang, mengubah alamat, lalu kembali ke daftar; bidang milik Super Admin tetap hanya informasi. (3) Admin membuka Pembayaran, memilih bulan yang memang mempunyai tagihan, mencocokkan status dengan portal siswa/Review Pembayaran, membuka bukti, serta mengunduh Excel/PDF. Admin tidak dapat memvalidasi. (4) Periksa Tampilkan/Reset, jarak form, center tabel, dan tampilan HP.

Semua menu yang sudah tercantum pada sidebar empat role kini memiliki halaman; tidak ada entri available=false yang tersisa. Ini menutup tahap penyelesaian menu sidebar, bukan seluruh proyek. Tahap berikutnya: pengujian alur lengkap antarrole dan pemeriksaan akhir tampilan/penggunaan, penyiapan data/aset final klien, keputusan/implementasi WhatsApp, pemasangan hosting, serta uji penerimaan dan serah terima. WhatsApp tetap menunggu keputusan klien; v48 tidak memilih gateway, menghubungkan nomor, atau mengirim pesan.


## 47. Pembaruan 15 Oktober 2026 — v49: warna status dan pemeriksaan alur empat role

Pemilik melanjutkan dari v48 dan meminta setiap status pada akun Admin diberi warna. Kedua screenshot lampiran berhasil dibaca: Aktif pada Pengaturan Cabang serta Tervalidasi/Belum Unggah Bukti pada Pembayaran masih memakai badge abu-abu. Pengerjaan berikutnya mengikuti bagian 46.6, yaitu pemeriksaan alur lengkap antarrole, sekaligus revisi status tersebut. Pembahasan sebelumnya tentang bulan laporan telah dipahami pemilik; satu bulan laporan adalah satu bulan kalender, sedangkan rapor final memakai siklus tiga bulanan.

### 47.1 Warna dan konsistensi status

Komponen dashboard.status menjadi penanda status bersama. Hijau dipakai untuk Aktif, Disetujui/Tervalidasi, Hadir, Selesai, dan target tercapai; kuning/oranye untuk Belum Unggah Bukti, Menunggu Validasi/Diajukan, belum disiapkan, serta kondisi yang perlu pemeriksaan; merah untuk Nonaktif, Ditolak, Perlu revisi/koreksi, tidak hadir, dan dibatalkan; biru untuk Draf, Terjadwal/Berlangsung, serta informasi proses. Status tidak dikenal diberi penanda perlu perhatian, tanpa menganggapnya tervalidasi. Teks status tetap ditampilkan sehingga arti tidak hanya bergantung pada warna.

Perubahan meliputi daftar/detail dan ringkasan Pembayaran, riwayat keputusan bukti, Pengaturan Cabang/visibilitas portal, Data Siswa/Guru, absensi Guru, Perkembangan & Pesan, Laporan Bulanan, Siklus Laporan, Monitoring Laporan, Rapor Tiga Bulan, kelengkapan laporan/pembayaran sumber, ketersediaan Guru, dan sesi pada dashboard. Komponen bersama mengikuti warna yang sama ketika dibuka oleh role staf lain. Badge metadata seperti jumlah baris, judul Filter, dan penanda Internal tetap memakai gaya informasi sebelumnya.

Stylesheet status-badges.css dimuat setelah stylesheet halaman dengan versi filemtime agar perubahan tampil setelah cache diperbarui. Label badge dan isi/header tabel tetap center horizontal/vertikal. Badge panjang dapat membungkus pada layar sempit. Warna teks/latar mempunyai rasio kontras 6,49:1 (hijau), 6,46:1 (oranye), 6,80:1 (merah), dan 7,57:1 (biru). Mode warna paksa mempertahankan garis tepi sistem. Ringkasan pembayaran memisahkan angka/label 8 px, serta mempertahankan jarak filter 20 px, tombol 12 px dan tinggi Tampilkan/Reset 50 px.

### 47.2 Temuan pemeriksaan dan perubahan terbatas

Audit menemukan pencarian string "0" di Perkembangan & Pesan dan Absensi Siswa diabaikan karena pemeriksaan empty(). Masalah direproduksi oleh enam kasus pada Super Admin, Admin, dan Teacher sebelum perubahan. Kedua controller sekarang memeriksa string kosong secara eksplisit. Pencarian "0" menyaring nama siswa sesuai cakupan yang sudah ada, tanpa merekam absensi atau membuat bulan laporan. Temuan dan alasan perubahan backend disampaikan sebelum coding.

Label sesi ongoing pada dashboard staf sebelumnya dapat tampil sebagai Periksa sesi atau Ongoing. Nilai tersebut sekarang ditampilkan sebagai Berlangsung dengan warna biru; nilai in_progress yang sudah didukung tetap dikenali. Halaman detail rapor final juga memperbarui petunjuk lama tentang portal siswa: akses portal tersedia ketika persyaratan dan PDF terpenuhi, sedangkan pengiriman WhatsApp masih menunggu keputusan lembaga.

Tidak ada perubahan kebijakan pembayaran, kepemilikan laporan, batas cabang/role, target/tenggat tersimpan, atau struktur database. v49 tidak menambahkan migration, dependency, build frontend, ataupun JavaScript baru.

### 47.3 Pemeriksaan alur lengkap empat role

Delapan kasus penerimaan baru mencakup enam kasus pencarian di atas, perubahan status cabang/pembayaran yang sebenarnya, dan satu alur berurutan melalui endpoint aplikasi. Teacher melihat siswa yang ditugaskan walaupun bulan laporan belum disiapkan dan tidak memerlukan penugasan cabang tambahan. Admin menyiapkan bulan pertama dengan target eksplisit; perubahan default Super Admin menjadi target 3/tenggat tanggal 20 berlaku bagi bulan baru, sementara Oktober yang sudah disiapkan tetap memakai nilai tersimpan. November dan Desember mempertahankan default baru tanpa pengaturan berulang.

Teacher membuka dialog absensi dan menyimpan kehadiran melalui JSON, mengisi delapan kategori, pesan/catatan, dan video, mengajukan laporan, menerima revisi Admin, memperbaiki, mengajukan ulang, lalu mendapat persetujuan. Siswa/Parent mengunggah bukti, Super Admin menolak, siswa mengunggah ulang, dan Super Admin memvalidasi. Admin tetap tidak dapat memvalidasi melalui URL Super Admin. Laporan publik dibuka setelah persetujuan dan pembayaran; catatan internal tidak muncul.

Setelah tiga bulan, Teacher menyiapkan rapor empat skill dan Note, Admin menyetujui, lalu sistem menghasilkan PDF nyata. Siswa yang berhak dapat membaca dan mengunduh PDF; siswa lain serta Admin cabang lain ditolak. Pembatalan validasi bukti kembali mengunci detail dan URL PDF yang sudah diketahui. Status yang dirender turut diperiksa selama perubahan data tersebut, termasuk revisi merah, diajukan oranye, disetujui hijau, dan sesi berlangsung biru.

### 47.4 Hasil pemeriksaan dan batas verifikasi

Suite regresi final lulus 2225 tes PHP dengan 13661 assertions, tanpa failure/error/skipped. JavaScript tetap 49 tes lulus, termasuk konfirmasi absensi, kirim bukti, dan interaksi akun. Semua Blade dikompilasi; 25 file PHP/Blade baru atau berubah lulus pemeriksaan sintaks.

HTTP menggunakan login, cookie, CSRF, dan database/storage fixture terpisah: 69 tautan sidebar empat role terbuka, 109 halaman berhasil dibaca, dan 123 pemeriksaan lulus. Pemeriksaan meliputi status pembayaran kosong/pending/ditolak/disetujui/ambigu/status lama, detail laporan/progres/absensi, cabang sendiri, penolakan akses cabang/role lain, penolakan validasi oleh Admin, serta formulir tanpa CSRF. Tidak ada menu sidebar dinonaktifkan.

Pemeriksaan struktur HTML dan cascade CSS dilakukan pada lebar 360/390/540/768/1100/1440/1920 px. Hasil: 15253 pemeriksaan sel tabel center, 1463 pemeriksaan badge warna/perataan/pembungkusan/kontras, tujuh pemeriksaan tombol filter pembayaran dan tujuh pemeriksaan jarak ringkasannya; tidak ada ID duplikat atau label tanpa kontrol. Selector khusus progress-bar/scrollbar browser dilewati karena tidak memengaruhi status/tabel. Tidak memakai screenshot browser atau menyatakan seluruh tata letak piksel telah diuji di HP/Windows.

Pengujian memakai PHP 8.3, Laravel 12 dan SQLite fixture. Pemasangan MySQL Laragon serta penggunaan langsung pada browser pemilik tetap memerlukan uji penerimaan. Hasil otomatis tidak menjamin seluruh kemungkinan bug atau transaksi MySQL bersamaan telah diuji.

### 47.5 Paket dan tahap selanjutnya

DPE_Update_v49_Status_Admin_dan_Audit.zip disiapkan di atas v48, berisi 27 file project, backup 24 file v48 yang diganti, manifest SHA256, dan TEST_RESULTS.md. Tidak menyertakan README instalasi, vendor, .env, database, unggahan pengguna, atau kunci akun. Cara pemasangan diberikan langsung di chat dalam satu blok PowerShell mandiri dengan pemilih ZIP, pemeriksaan hash/isi, backup, pemulihan source bila gagal, serta pembersihan cache. Tidak perlu migration, composer install, atau npm build.

Uji penerimaan pemilik: (1) Admin → Pengaturan Cabang: Aktif hijau. (2) Admin → Pembayaran: pilih bulan yang mempunyai tagihan; Belum Unggah Bukti/Menunggu Validasi oranye, Tervalidasi hijau, Ditolak merah pada daftar dan detail. Setelah tindakan siswa/Super Admin, muat halaman Admin untuk melihat data terkini; v49 tidak menambahkan pembaruan realtime antar akun. (3) Periksa status laporan, siswa/guru, dan absensi serta keterbacaan/gulir tabel di HP. (4) Cari "0" pada dua modul menggunakan siswa yang namanya mengandung angka tersebut.

Tahap pemeriksaan otomatis antarrole telah dijalankan. Pekerjaan berikutnya setelah uji v49 adalah uji penerimaan alur dengan data klien dan perbaikan temuan, finalisasi data/aset/konten, penentuan serta implementasi alur WhatsApp, dan persiapan hosting/backup/serah terima. WhatsApp tetap ditunda sesuai keputusan pemilik; v49 tidak memilih provider, menautkan nomor, atau mengirim pesan.


## 48. Pembaruan 16 Oktober 2026 — v50: logo resmi DPE

Pemilik menerima v49 dan memutuskan modul WhatsApp dikerjakan paling terakhir. Pemilik mengonfirmasi seluruh akun dan data aplikasi saat ini hanya data percobaan, belum data asli klien. Konten lembaga masih sementara; klien baru menyediakan logo resmi. Konten/foto/identitas lain belum dianggap final dan akan dikonfirmasi pemilik. Data percobaan tidak dihapus atau direset oleh pembaruan ini.

### 48.1 File logo dan lokasi pemasangan

Lampiran logo DPE.png berhasil dibuka melalui file lokal. Gambar PNG RGBA berukuran 631 × 395 piksel, 119628 byte, mempunyai transparansi dan tulisan hitam. Gambar disalin utuh, tanpa perubahan warna, bentuk, teks, crop, transparansi, atau kompresi ulang, ke public/assets/dpe/images/logo-dpe.png. SHA256: a890ea469aa3a14beb77c17cf97c7dda721b8fbc3e542d4b74f3f607b3c575ff.

Lokasi Laragon: C:\laragon\projectles\dpe-webapp\public\assets\dpe\images\logo-dpe.png. Nama yang dipakai selanjutnya adalah logo-dpe.png, huruf kecil dan tanpa spasi. Blade memanggil asset('assets/dpe/images/logo-dpe.png'); helper asset() sudah menunjuk folder public, sehingga jangan menambahkan public/ di dalam argumennya. Komponen menambahkan filemtime sebagai versi URL agar pembaruan gambar lebih mudah dimuat browser. ZIP sudah memasukkan gambar pada folder tersebut; pemilik tidak perlu menyalin dua kali.

### 48.2 Penggunaan di website

Komponen dpe.logo dipakai pada brand di header/footer publik, login desktop, login HP, dan sidebar dashboard Super Admin, Admin, Teacher, serta Siswa/Parent. Komponen publik/login mempertahankan prioritas gambar identitas yang sudah ditayangkan melalui Konten Publik; logo baru menjadi default saat tidak ada gambar identitas tersebut. Logo dalam draft mengikuti pratinjau editor yang sudah tersedia. Pengelolaan identitas tidak dimatikan dan tidak ada record identitas yang ditulis otomatis. Sidebar memakai aset logo resmi yang sama.

Logo ditampilkan utuh menggunakan object-fit: contain dan object-position: center, di atas bidang putih dengan garis/border lembut. Bidang putih membuat tulisan hitam terbaca pada sidebar dan panel login berwarna biru. Ukuran disesuaikan per konteks: publik desktop 88 × 58 px, publik HP 72 × 48 px, login desktop 96 × 64 px, sidebar 70 × 48 px, dan login HP 152 × 100 px. Semua ukuran mempertahankan perbandingan gambar di dalam bidangnya; tidak meregangkan gambar.

Panel cerita login tetap disembunyikan pada lebar maksimal 800 px seperti sebelumnya; logo tambahan ditampilkan di atas formulir pada HP. Logo dalam panel cerita desktop dan logo HP tidak tampil bersamaan pada ukuran layar yang sama. Tautan logo tetap mengarah ke beranda atau dashboard sesuai konteks, dengan nama aksesibel pada tautan. Gambar di dalam tautan dibuat dekoratif agar pembaca layar tidak mengulang nama lembaga. Jika aset tidak tersedia, komponen menampilkan teks DPE.

Stylesheet branding.css dipakai bersama dan dimuat setelah stylesheet halaman. Aturan hanya mengenai elemen brand/logo serta logo login HP. Tidak mengubah isi tabel, warna status v49, bidang/form login, sistem otentikasi, data pengguna, company profile sementara, maupun kop/template/salinan persetujuan PDF rapor. Logo PDF mengikuti fitur Template Rapor yang sudah ada; pemasangan logo web ini tidak membuat ulang rapor yang telah disetujui.

### 48.3 Verifikasi dan paket

Pengujian terkait yang sudah tersedia lulus 20 tes PHP/171 assertions. Seluruh Blade berhasil dikompilasi dan enam file PHP/Blade baru/berubah lulus pemeriksaan sintaks. Tidak ditambahkan tes yang hanya menyalin pemetaan tampilan logo.

HTTP dengan login/cookie dan database/storage fixture terpisah memeriksa lima halaman publik, login, serta dashboard empat role: sepuluh halaman dan 16 pemeriksaan lulus. Gambar yang disajikan HTTP adalah image/png dan SHA256/bytes sama dengan lampiran asli. CSS yang disajikan sama dengan file paket. Tidak ada referensi logo login lama pada halaman tersebut.

Pemeriksaan struktur HTML/cascade CSS dilakukan pada lebar 360/390/540/768/1100/1440/1920 px: 112 pemeriksaan bidang/logo object-fit dan perbandingan ukuran, serta tujuh pemeriksaan pergantian logo desktop/HP pada login lulus. Tidak ada ID duplikat atau label tanpa kontrol. Pemeriksaan ini bukan screenshot/pixel browser; penggunaan nyata pada browser Laragon/HP tetap menjadi uji penerimaan pemilik. Tidak ada backend, route, JavaScript, dependency, atau migration baru.

Paket DPE_Update_v50_Logo.zip berbasis v49 memuat sembilan file project, backup lima file v49, manifest dan TEST_RESULTS.md. Tidak menyertakan README instalasi, .env, vendor, database, unggahan akun, atau kunci. Cara instalasi diberikan langsung di chat. Setelah pemasangan, pemilik memuat ulang dengan Ctrl+F5 dan memeriksa logo header/footer, login desktop/HP, serta sidebar empat role. Apabila gambar identitas lain sudah ditayangkan melalui Konten Publik, pemilik dapat menggantinya dengan logo resmi melalui pengelolaan tersebut atau menghapus gambar identitas agar default ini dipakai.

### 48.4 Urutan kerja berikutnya

Uji pemakaian selanjutnya menggunakan akun/data percobaan yang sudah tersedia, lalu mencatat temuan per role. Konten/aset final lainnya menunggu klien; konten sementara tidak ditulis sebagai data resmi atau diterbitkan ulang oleh v50. Persiapan hosting/backup dapat dibahas ketika data lingkungan tersedia. WhatsApp menjadi modul implementasi paling terakhir sesuai instruksi pemilik, dilanjutkan pemeriksaan integrasi akhir dan serah terima. Pilihan gateway/alur kirim belum diputuskan.


## 49. Pembaruan 16 Oktober 2026 — persiapan uji pemakaian v50 di Laragon

Pemilik menerima pemasangan logo dan melanjutkan tahap berikutnya. Urutan kerja mengikuti bagian 48.4: uji pemakaian alur lengkap memakai akun dan data percobaan yang tersedia. Konten asli lain masih menunggu klien dan WhatsApp tetap menjadi modul implementasi terakhir. Tahap ini tidak mengubah source aplikasi, backend, database, migration, dependency, atau konten publik. Versi aplikasi tetap v50.

### 49.1 Checklist dan keadaan awal

DPE_UAT_v50.xlsx menyediakan 44 kasus berurutan U01–U44 pada tab Checklist, serta contoh data dan prasyarat pada tab Data Uji. Setiap kasus memuat role, langkah, hasil yang diharapkan, pilihan status, dan hasil nyata/catatan. Status awal seluruh kasus adalah Belum diuji; nilai itu tidak menyatakan hasil pengujian. Pilihan berikutnya: Lulus, Perlu perbaikan, atau Terhalang. Ringkasan jumlah dan persentase menghitung status yang diisi pemilik.

Checklist disusun dengan mencocokkan menu/form dan validasi source v50 serta aturan yang telah dicatat pada dokumen 1.32. Format workbook, formula ringkasan, perubahan status percobaan, pilihan status, dan tampilan kedua tab diperiksa sebelum diserahkan. Hasil manual MySQL Laragon dan browser Windows/HP belum tersedia. Pemeriksaan otomatis v49/v50 tetap merupakan bukti terdahulu; pembuatan checklist tidak menghitungnya sebagai kasus UAT yang telah lulus.

### 49.2 Data uji dan urutan alur

Skenario menggunakan satu profil baru UAT Siswa 0 dengan akun student yang belum terhubung ke anak lain. Tanggal mulai belajar, pendaftaran private, dan penugasan Teacher sama-sama 05 Juli 2026. Siklus Juli–September 2026 dipilih agar tiga bulan dapat dicoba tanpa menunggu tanggal mendatang atau mengubah tanggal komputer. Nama Teacher, cabang, program private, akun pembanding, URL video yang benar-benar dapat dibuka, dan gambar bukti pembayaran percobaan diisi dari lingkungan pemilik. Sandi tidak dicatat dalam workbook.

Super Admin mencatat default target/tenggat sebenarnya sebelum U06, lalu memakai nilai sementara target 4/tenggat tanggal 31. Admin menyiapkan Juli dengan target bulan pertama eksplisit 1. Perubahan default berikutnya menjadi target 3/tenggat tanggal 20 diuji pada Agustus dan September; Juli yang sudah dibuat harus mempertahankan nilai tersimpan. U44 memulihkan default ke nilai semula yang telah dicatat. Tidak ada penghapusan/reset akun atau data percobaan.

Sesi private contoh dibuat pada 10 Juli 2026. Admin tetap memilih siswa melalui Kelola peserta private; pendaftaran dan penugasan Teacher tidak otomatis mengisi roster sesi. Alur absensi memeriksa Batal, konfirmasi, pembaruan tanpa refresh, penyimpanan setelah reload, alasan koreksi, dan riwayat. Pengujian kehadiran Teacher menggunakan kunjungan/sesi yang memenuhi syarat pada hari pelaksanaan sebenarnya, terpisah dari sesi siswa bulan Juli.

Laporan bulanan menguji delapan kategori, tiga bagian publik wajib, video dengan batas tanggal, draf, pengajuan, revisi, dan persetujuan. Kekurangan video atau absensi merupakan pengingat sesuai aturan aplikasi, bukan syarat baru yang ditambahkan oleh checklist. Pembayaran menguji unggah, penolakan, unggah ulang, validasi Super Admin, serta warna status pada akun terkait. Admin hanya dapat melihat pembayaran.

Rapor akhir memeriksa empat skill dan Note, persetujuan final, pembuatan PDF internal, serta syarat akses siswa: tiga laporan bulanan disetujui, rapor final disetujui/PDF terkini, dan tiga pembayaran tervalidasi. Pembatalan validasi harus kembali mengunci laporan/PDF siswa, termasuk URL PDF yang telah diketahui. Catatan internal profil dan bulanan tidak boleh muncul di portal/PDF publik.

### 49.3 Pelaksanaan oleh pemilik dan tindak lanjut

Bagian akhir mencakup penolakan akses role/cabang/anak lain, ekspor Excel/PDF, profil/password sendiri, jadwal dan slot, notifikasi, logo dan tata letak HP/desktop, serta pemulihan pengaturan. Jika akun pembanding atau izin/perangkat kamera/lokasi yang memenuhi syarat belum tersedia, kasus terkait ditandai Terhalang dengan alasan; tidak dianggap Lulus atau bug secara otomatis.

Pemilik memulai U01–U08 pada v50 yang sudah terpasang di C:\laragon\projectles\dpe-webapp, kemudian melaporkan ID dan hasil nyata atau pesan yang muncul. Temuan diperiksa dan diperbaiki berdasarkan bukti sebelum melanjutkan kelompok uji berikutnya. Menyiapkan workbook ini menyelesaikan persiapan UAT, sedangkan pelaksanaan dan penutupan UAT masih menunggu hasil pemilik. Tidak dibuat ZIP pembaruan runtime untuk tahap persiapan ini.


## 50. Pembaruan 16 Oktober 2026 — v51: panel 50 slot dan warna pembayaran siswa

Pemilik mengirim DPE_UAT_v50_Hasil_Analisis.xlsx untuk diperiksa. File tersebut mencatat 39 skenario sesuai kode, tiga sebagian sesuai karena satu masalah warna pembayaran siswa, dua perlu perangkat/browser nyata, dan seluruh 44 UAT browser Laragon masih Terhalang. Angka 49 tes JavaScript serta delapan pemeriksaan sintaks dalam file adalah bukti pemeriksaan pada sesi laporan tersebut, bukan kelulusan UAT manual. Dua temuan dicocokkan dengan source v50: status utama pembayaran siswa belum memakai modifier/komponen bersama secara konsisten, serta panel Admin membatasi daftar ketersediaan ke 12 slot. Pemilik kemudian meminta hanya dua revisi ini dikerjakan dahulu, dengan batas panel baru maksimal 50 slot.

### 50.1 Perubahan yang diterapkan

BranchOperationsService::availabilitySlots menaikkan limit query dari 12 menjadi 50. Pembatasan cabang, tanggal mulai hari ini, relasi Teacher/cabang, urutan tanggal dan jam, serta pemeriksaan ketersediaan ketika menyimpan jadwal tetap mengikuti aturan yang tersedia. Form tambah/edit Jadwal & Sesi Admin menampilkan keterangan Maksimal 50 slot terdekat. Batas ini berlaku pada jumlah baris panel gabungan pada cabang yang berhak, bukan jumlah slot yang boleh disimpan Teacher.

Daftar dan detail Pembayaran siswa menggunakan komponen x-dashboard.status dengan kode/label state yang sudah dihitung PortalPaymentService. Belum Unggah Bukti dan Menunggu Validasi memakai oranye, Ditolak merah, Tervalidasi hijau, dan kondisi perlu pemeriksaan memakai warna perhatian. Ini perubahan presentasi; tidak mengubah aturan penagihan, unggah, keputusan Super Admin, riwayat bukti, pembayaran yang membuka rapor, atau hak akses. CSS komponen yang sudah tersedia tetap menjadi acuan warna dan perataan.

Sebelum mengubah backend, pemilik diberi penjelasan bahwa query perlu dinaikkan agar 50 data benar-benar diambil. Tidak ada route, migration, dependency, atau build frontend baru. WhatsApp, konten final, dan revisi lain tetap menunggu tahap masing-masing.

### 50.2 Pemeriksaan dan bukti

Empat kegagalan regresi berhasil direproduksi pada source lama, dengan satu kasus keadaan kosong lulus. Setelah perubahan, 141 tes PHP terkait/715 assertions dan 49 tes JavaScript lulus, tanpa failure/error/skipped. Enam file PHP/Blade berubah lulus sintaks dan seluruh Blade berhasil dikompilasi. Kasus batas 0/13/50/51 memeriksa hasil query dan baris HTML pada tambah/edit, urutan tanggal, pengecualian data lampau/cabang lain, dan memastikan record tersimpan tidak dihapus. Alur pembayaran memeriksa penanda warna pada daftar/detail siswa serta Admin setelah unggah, penolakan, unggah ulang, validasi, dan pembatalan, sambil mempertahankan larangan validasi bagi Admin.

Pengujian baru memakai PHP 8.3.6 dan SQLite fixture terpisah. Tidak menyatakan suite historis seluruh aplikasi telah dijalankan ulang atau UAT browser/MySQL Laragon telah lulus. Hasil manual 44 kasus tetap menunggu pemilik. File Excel yang diunggah tidak ditimpa atau diubah statusnya.

### 50.3 Paket dan penerimaan pemilik

DPE_Update_v51_Slot50_Status.zip berbasis v50 berisi enam file project (empat aplikasi dan dua tes), enam backup source v50, manifest SHA256, serta TEST_RESULTS.md. Tidak berisi README instalasi, .env, vendor, database, atau unggahan akun. Pemasangan cukup menyalin patch dan membersihkan cache; tidak menjalankan migration, Composer, atau npm. Instruksi PowerShell mandiri diberikan langsung di chat, dengan pemilih ZIP, pemeriksaan SHA256/isi, backup, dan pemulihan source bila gagal.

Penerimaan pemilik: Admin membuka Jadwal & Sesi → Tambah atau Edit pada cabang berhak dengan lebih dari 12 slot, memastikan slot ke-13 sampai ke-50 dapat tampil; bila tersedia 51 slot, panel menampilkan 50 terdekat. Pada akun siswa, periksa daftar serta detail Pembayaran untuk Belum Unggah Bukti/Menunggu Validasi oranye, Ditolak merah, dan Tervalidasi hijau. Pengujian manual dilanjutkan dengan data percobaan; tidak ada penghapusan/reset akun atau data.


## 51. Pembaruan 16 Oktober 2026 — v52: pertahankan posisi sidebar empat role

Pemilik melanjutkan setelah v51 dan melaporkan sidebar semua role kembali ke atas ketika membuka menu di bagian bawah. Contoh Super Admin: gulir ke Konten Publik, klik menu tersebut, lalu halaman tujuan terbuka tetapi sidebar kembali ke awal. Pemilik meminta posisi gulir tetap pada perpindahan halaman untuk Super Admin, Admin Cabang, Teacher, dan Siswa/Parent.

### 51.1 Implementasi

Sidebar layout dashboard memiliki scroll container sendiri dengan overflow; navigasi penuh sebelumnya tidak menyimpan scrollTop. Layout sekarang memberi data-sidebar-scroll-key berupa hash identitas akun dan role, stabil pada semua halaman akun tersebut. Script lokal sidebar-scroll.js dimuat langsung setelah markup sidebar sebelum konten utama, sehingga pemulihan tidak menunggu inisialisasi dashboard di akhir dokumen.

Script membaca dan menyimpan posisi melalui sessionStorage per tab, dengan kunci terpisah antar-akun dan role. Peristiwa scroll, klik sidebar pada fase capture, pagehide, dan tab menjadi tersembunyi menyimpan posisi terkini. Pemulihan dilakukan segera serta pada pageshow untuk navigasi kembali/maju. Posisi nol dikenali, nilai numerik valid dibatasi pada rentang gulir, dan kegagalan akses penyimpanan ditangani agar menu tetap dapat dipakai. Sidebar yang sepenuhnya tersembunyi tidak menimpa nilai tersimpan dengan nol.

Yang diubah hanya scrollTop sidebar. Tautan tetap menggunakan navigasi Laravel yang tersedia; halaman utama tidak dipaksa menggulir, dan perilaku buka/tutup menu HP mengikuti dashboard.js yang sama. Tidak ada perubahan controller, database, role/cabang, daftar menu, CSS, atau dependency. Batas 50 slot serta warna pembayaran siswa v51 tetap menjadi baseline.

### 51.2 Pemeriksaan

Lima kasus PHP gagal pada source v51 sebelum perubahan. Setelah perubahan, 10 tes PHP/127 assertions lulus, mencakup identitas sidebar pada empat role, kestabilan di dashboard/Notifikasi, isolasi antar-akun dan pergantian role, posisi pemuatan script, login, serta dashboard Admin. Seluruh 72 tes JavaScript lulus: 23 kasus baru sidebar dan 49 kasus sebelumnya. Perilaku yang diperiksa meliputi perpindahan menu, posisi nol, isolasi tab/akun, pagehide/pageshow/visibilitychange, rentang dan nilai pecahan, data tidak valid, penyimpanan diblokir, sidebar tersembunyi, serta pemulihan saat dokumen masih dimuat. Dua file PHP/Blade dan dua file JavaScript baru/berubah lulus sintaks; seluruh Blade berhasil dikompilasi.

Pengujian PHP memakai SQLite fixture terpisah dan JavaScript memakai DOM mock. Simulasi lebar tidak diklaim sebagai screenshot browser atau pengujian HP/Windows nyata. Uji penerimaan Laragon tetap dilakukan pemilik setelah pemasangan; hasil UAT sebelumnya tidak diubah menjadi Lulus oleh patch ini.

### 51.3 Paket dan uji pemilik

DPE_Update_v52_Sidebar_Scroll.zip berbasis v51 memuat empat file project (layout, script baru, satu tes PHP, satu tes JavaScript), backup satu layout v51, manifest SHA256, dan TEST_RESULTS.md. Instruksi pemasangan langsung di chat memakai pemilih ZIP, hash/isi, backup, serta pemulihan source bila gagal. Tidak memerlukan migration, Composer, atau npm; tidak berisi README instalasi, .env, database, vendor, atau unggahan.

Setelah pemasangan dan Ctrl+F5, pemilik menggulir sidebar lalu membuka menu bawah pada setiap role. Posisi harus tetap ketika halaman tujuan terbuka. Pada HP sidebar dapat ditutup setelah klik seperti sebelumnya; saat dibuka lagi, posisi gulir dipertahankan. Ulangi pada menu lain, refresh, Back/Forward, posisi paling atas, dan pergantian akun. WhatsApp serta revisi lain mengikuti tahap terpisah sesuai arahan pemilik.


## 52. Pembaruan 16 Oktober 2026 — v53: alamat pada detail lokasi absensi Teacher

Setelah menerima v52, pemilik meminta pembahasan Lokasi masuk/Lokasi selesai pada detail absensi yang hanya bertuliskan GPS tersimpan. Disepakati menampilkan alamat dari titik GPS beserta Lihat Peta, dengan koordinat sebagai fallback jika alamat tidak ditemukan. Pemilik menyetujui implementasi. Sebelum coding, pemilik diberi penjelasan bahwa penerjemahan koordinat memerlukan penambahan backend dan tidak boleh menghambat absensi Teacher.

### 52.1 Tampilan dan cakupan

Admin/Super Admin melihat alamat perkiraan pada Lokasi masuk dan Lokasi selesai, koordinat asli, serta Lihat Peta ke Google Maps pada titik masing-masing. Alamat dimuat tanpa refresh setelah halaman terbuka. Alamat dapat berupa nama jalan/area, kota/kabupaten, provinsi dan negara sesuai cakupan data; tidak menjamin nomor rumah atau bangunan tepat. Foto, akurasi GPS, catatan pengecualian, review dan audit tetap mengikuti fitur yang tersedia.

Data absensi lama dapat memakai fitur ini selama waktu dan koordinat captured masih tersedia. Angka nol merupakan koordinat valid. Belum selesai tetap Belum tercatat; lokasi unavailable tetap Pengecualian lokasi; Izin/Sakit/Tidak hadir manual tetap Tidak berlaku. Koordinat hilang/tidak valid tidak diberi alamat atau tautan titik buatan. Alamat profil cabang/siswa tidak dipakai untuk menebak posisi perangkat.

### 52.2 Backend, provider dan kondisi gagal

TeacherAttendanceLocationService memisahkan pembentukan titik/map URL dari pencarian alamat. Controller show hanya membentuk titik dari record; endpoint JSON location/check-in atau location/check-out memeriksa autentikasi serta hak Admin cabang sebelum lookup/cache. Super Admin mengikuti hak lintas cabang yang sudah tersedia. Teacher/Siswa/tamu/Admin cabang lain/penugasan berakhir ditolak. Endpoint tidak menerima koordinat pengguna dari query string, dan respons memakai private,no-store.

Provider default Photon pada https://photon.komoot.io/reverse menggunakan data OpenStreetMap. Server mengirim koordinat dan opsi pencarian saja, tanpa nama, foto, ID absensi atau data akun. Tidak menambah kunci/layanan berbayar. Server publik menerima penggunaan wajar, dapat membatasi trafik dan tidak menjamin ketersediaan; pemilihan endpoint Photon kompatibel untuk volume hosting yang lebih besar dapat dilakukan kemudian melalui DPE_ATTENDANCE_GEOCODER_URL. DPE_ATTENDANCE_GEOCODER_ENABLED dapat mematikan lookup, dan DPE_ATTENDANCE_GEOCODER_CACHE_STORE mengatur store; default file tidak memerlukan migration/tabel baru. Tidak perlu mengubah .env untuk pemakaian default.

Hasil dicache tujuh hari, kegagalan satu menit, dengan kunci terpisah per provider dan titik. Lock mencegah lookup identik bersamaan tanpa membuat antrean tunggu. Timeout koneksi dua detik/total empat detik; timeout/429/5xx memberi cooldown provider satu menit. Dua titik halaman dicari berurutan. Respons jauh lebih dari 500 meter, tanpa alamat lokal, terlalu besar, tidak valid, atau tipe salah menghasilkan fallback. Teks dinormalisasi dan ditampilkan memakai textContent; kredit OpenStreetMap ditampilkan bersama alamat. SSL tidak dinonaktifkan, redirect provider tidak diikuti, dan URL berisi koordinat tidak dicatat ke log oleh service.

Jika pencarian gagal, koordinat dan Lihat Peta tetap ada. Script tidak mengirim ulang tanpa batas, menolak URL lintas origin, memakai timeout, serta mengabaikan respons terlambat untuk lokasi yang sudah berubah. Browser tanpa kemampuan fetch tetap menerima koordinat/map dari server. Membuka Google Maps sendiri memerlukan internet. Jalur simpan absen masuk/selesai cabang maupun private tidak memanggil geocoder dan tidak diubah oleh v53. Tidak ada migration, penulisan ulang data, perubahan status, atau dependency baru.

### 52.3 Verifikasi

133 tes PHP/847 assertions terkait dan 97 tes JavaScript lulus tanpa failure/error/skipped. Termasuk 41 kasus PHP lokasi dan 25 kasus JavaScript baru. Cakupan meliputi pemisahan titik masuk/selesai, nol, cache file antar-request, kedaluwarsa/pemulihan layanan, gagal/format buruk, sanitasi, pembatasan cabang bahkan setelah cache terisi, pemalsuan koordinat, record lama, manual/belum selesai, dan absen Teacher tanpa panggilan provider. Delapan PHP/Blade dan dua JS/CJS lulus sintaks; seluruh Blade berhasil dikompilasi.

Pengujian memakai PHP 8.3.6, SQLite fixture terpisah, Http::fake dan DOM mock. Upaya koneksi nyata dengan landmark publik tidak berhasil dari lingkungan pengerjaan; tidak menyimpulkan keadaan provider pada jaringan Laragon. Alamat nyata, Google Maps, browser/HP, MySQL dan perangkat kamera/GPS tetap diuji pemilik setelah instalasi. Tidak mengklaim seluruh suite historis atau UAT manual selesai; workbook UAT tidak diubah. WhatsApp tetap tahap implementasi terakhir dan keputusan klien masih menunggu.

### 52.4 Paket dan penerimaan pemilik

DPE_Update_v53_Lokasi_Absensi.zip berbasis v52 berisi 11 file project (delapan aplikasi, tiga tes), tiga backup source v52, manifest SHA256 dan TEST_RESULTS.md. Tidak menyertakan README instalasi, .env, database, vendor atau unggahan. Tidak perlu migration/Composer/npm. Instruksi PowerShell mandiri diberikan langsung di chat dengan pemilih ZIP, hash/isi, backup, serta pemulihan source jika gagal.

Pemilik melakukan Ctrl+F5 dan membuka detail absensi dengan GPS pada Admin/Super Admin, memeriksa alamat serta Lihat Peta untuk masuk/selesai, lalu mencoba data lama, kunjungan cabang/private, titik berbeda dan buka ulang/cache. Periksa pula belum selesai, lokasi unavailable dan status manual. Lanjutkan absen Teacher untuk memastikan foto, titik dan review tetap tersimpan. Server Laragon perlu koneksi HTTPS keluar untuk alamat yang belum dicache; bila gagal, koordinat dan tombol peta harus tetap tersedia.


## 53. Pembaruan 17 Oktober 2026 — v54: sejajarkan tombol peta absensi

Pemilik menerima v53 dan mengirim image(20261016-180630).png. Alamat masuk/selesai telah ditampilkan, tetapi alamat masuk yang lebih panjang membuat Lihat Peta lebih rendah daripada tombol pada lokasi selesai. Pemilik meminta kedua tombol sejajar. Lampiran berhasil dibuka dari file lokal; pesan otomatis gambar tidak ditemukan tidak mencerminkan keadaan lampiran yang tersedia.

### 53.1 Perbaikan tampilan

Dua sel lokasi pada detail absensi diberi modifier dpe-dashboard-detail--location agar label dan komponen lokasi mengisi tinggi sel baris bersama. Baris alamat komponen memakai 1fr, sementara koordinat/tombol/atribusi memakai tinggi alami. Ruang yang tersisa mengikuti alamat, sehingga koordinat dan kedua tombol sejajar tanpa tinggi teks tetap, pemotongan alamat atau posisi absolut. Jarak label ke komponen enam piksel; tata letak HP satu kolom mengikuti dashboard yang sudah tersedia.

Kredit alamat yang masih hidden tetap menyisakan ruang melalui visibility:hidden, dengan override display terbatas pada kredit komponen lokasi. Selector khusus mengatasi aturan hidden !important umum dashboard; elemen tersembunyi tidak terlihat atau dapat difokuskan. Ketika lookup selesai dan JavaScript v53 membuka hidden, kredit tampil seperti sebelumnya. Ini menjaga posisi tombol juga ketika baru satu alamat berhasil dimuat.

Hanya public/assets/dpe/attendance-location.css dan resources/views/admin/teacher-attendance/show.blade.php berubah. Backend, route, konfigurasi, komponen lokasi, JavaScript, koordinat, foto, provider dan alur simpan absensi tetap mengikuti v53. Tidak ada migration/dependency/build baru.

### 53.2 Pemeriksaan dan paket

Sepuluh kasus halaman terkait/74 assertions yang sudah tersedia lulus, meliputi Admin/Super Admin, cabang/private/riwayat, belum selesai, manual, lokasi unavailable, hilang dan di luar rentang. Blade yang berubah lulus sintaks dan seluruh Blade berhasil dikompilasi. Perbandingan source membatasi perubahan pada dua file tersebut. Tidak menambah tes baru untuk perubahan layout kecil dan tidak mengklaim suite v53 penuh dijalankan ulang.

Pengujian memakai PHP 8.3.6 dan SQLite fixture; screenshot awal diperiksa sebagai bukti masalah. Belum dilakukan screenshot/pengukuran piksel browser setelah perubahan. Penerimaan pemilik: Ctrl+F5, buka detail dengan alamat berbeda panjang, periksa tombol sejajar ketika keduanya sudah dimuat maupun ketika salah satu masih menunggu, lalu coba masing-masing tautan dan tata letak HP.

DPE_Update_v54_Tombol_Peta_Sejajar.zip berbasis v53 memuat dua file project, dua backup v53, manifest dan TEST_RESULTS.md. Tidak berisi README instalasi, .env, database, vendor atau unggahan. Instruksi pemasangan diberikan langsung di chat. WhatsApp tetap menunggu keputusan klien dan menjadi tahap implementasi terakhir.


## 54. Pemeriksaan 17 Oktober 2026 — alur lengkap empat role pada v54

Pemilik menerima revisi tombol peta v54 dan menyatakan revisi yang diminta sudah selesai. Pemilik lalu melanjutkan tahap pengujian alur lengkap. Versi aplikasi tetap v54; tidak dibuat patch runtime baru pada tahap ini.

### 54.1 Alur HTTP dan koreksi petunjuk menu

Siswa & Pendaftaran berada pada Super Admin; Admin Cabang memakai Data Siswa. Petunjuk percakapan sebelumnya yang menempatkan Siswa & Pendaftaran pada Admin Cabang dikoreksi sebelum pengujian. Skenario dimulai dengan Super Admin membuat profil siswa baru melalui form, enrollment cabang/program dan penugasan Teacher pemilik laporan. Admin melihat siswa cabangnya, membuat sesi private dan roster; Teacher melihat siswa binaan yang sama, mencatat foto/GPS masuk-selesai dan absensi siswa, lalu Admin memeriksa kehadiran Teacher.

Admin menyiapkan tiga laporan, Teacher mengisi delapan kategori/isi publik/video/catatan internal, mengajukan laporan, memperbaiki revisi dan menerima persetujuan Admin. Super Admin membuat tagihan, Siswa mengunggah bukti, Super Admin menolak/menerima unggah ulang dan memvalidasi. Teacher menyiapkan rapor empat skill/Note, Admin menyetujui dan Teacher menghasilkan PDF; Siswa dapat membuka dan mengunduh. Akun Siswa lain/Admin luar cabang ditolak dan pencabutan validasi pembayaran mengunci kembali halaman serta URL PDF yang sudah diketahui.

Skenario juga memeriksa perubahan default target/tenggat: periode yang sudah dibuat tetap, periode baru mengikuti default baru. Akun/cabang/program/profil Teacher merupakan fixture; transaksi siswa/jadwal/roster/absensi/laporan/tagihan/pembayaran/PDF pada alur diperluas melalui HTTP. Clock uji digeser untuk Oktober-Desember 2026 tanpa mengubah tanggal komputer pemilik. Skenario ini sendiri lulus 184 assertions, termasuk dalam hasil suite penuh.

### 54.2 Hasil dan dokumen

Seluruh 2.275 tes PHP/14.030 assertions dan 97 tes JavaScript lulus tanpa failure/error/skipped. Tidak ditemukan kegagalan otomatis yang memerlukan perubahan aplikasi. Pemeriksaan byte 528 file app/config/public/resources/routes/migrations serta composer.json/composer.lock terhadap v54 tidak menemukan perubahan. Hanya skenario tes pada salinan QA diperluas; sumber aplikasi pemilik tidak perlu ditimpa.

Sebelas workbook/24 sheet/618 sel terisi dibaca: ZIP valid, gridlines tidak disembunyikan, border tipis empat sisi, center horizontal/vertikal dan tidak ada formula tak diharapkan. Empat pratinjau Rincian/Ringkasan siswa dan absensi Teacher dirender untuk pemeriksaan visual; file asli tidak diubah. Sebelas PDF ekspor/14 halaman dapat dibaca/diekstrak. Rapor dari alur HTTP diunduh dan dirender dua halaman A4: nama/four skills/Note/kop terbaca, penutup-tanda tangan berlanjut pada halaman kedua, catatan internal tidak muncul. Peringatan nonfatal metadata ICC logo pada log tidak menghalangi pembacaan PDF; aset/template tidak diubah.

Lingkungan memakai PHP 8.3.6, GD dan dependency PDF versi composer.lock, SQLite :memory:, storage fixture serta DOM mock Node. Dependency dilengkapi hanya di salinan QA. Tidak menyatakan browser Windows/HP, MySQL Laragon, perangkat kamera/GPS, jaringan alamat nyata, kinerja banyak pengguna atau hosting produksi telah lulus. Tidak mengubah workbook UAT terdahulu, data percobaan pemilik, konten publik atau integrasi WhatsApp. Uji penerimaan manual sebelum rilis tetap diperlukan.

### 54.3 Bukti dan tahap berikutnya

DPE_Hasil_Uji_Alur_v54.md merangkum hasil dan batas pengujian. DPE_Bukti_Pengujian_v54.zip menyimpan skenario HTTP yang diperluas, hasil PHPUnit/JavaScript, perbandingan source, pemeriksaan dokumen dan unduhan fixture. Arsip bukti bukan paket instalasi dan tidak mempunyai folder project untuk disalin ke aplikasi. Tidak ada ZIP pembaruan v55 atau migration pada tahap ini.

Tahap yang dapat dipersiapkan berikutnya ialah hosting/backup, sambil menunggu konten/data resmi klien. Informasi yang belum tersedia: apakah domain/hosting sudah ada, penyedia dan jenis panel. WhatsApp tetap fitur implementasi terakhir setelah keputusan klien. Setelah data/konten, hosting dan WhatsApp siap, lanjutkan uji penerimaan di lingkungan tujuan, pemulihan backup dan serah terima. Pengujian manual alur tiga bulan dapat memakai Juli-September 2026 sesuai checklist terdahulu tanpa mengubah tanggal komputer.

## 55. Pembaruan 19 Oktober 2026 — v55: WhatsApp semiotomatis

### 55.1 Keputusan dan cara penggunaan

Pemilik menyetujui kelanjutan coding setelah membahas pengirim, penggunaan WhatsApp Web/aplikasi desktop, dan persiapan testing. Alur terbaru memakai click-to-chat, dengan dua pilihan pada setiap rapor: **Kirim tautan rapor** atau **Kirim file PDF**. Tidak memerlukan API key, QR di dalam DPE, nomor gateway, Composer/npm baru, atau job pengiriman. Nomor Teacher yang disimpan pada profil merupakan data kontak, sedangkan akun WhatsApp aktif di perangkat menentukan pengirim sebenarnya.

Dari **Teacher → Rapor Tiga Bulan → detail rapor → Kirim Rapor via WhatsApp**, petugas memeriksa Teacher, siswa, periode dan kontak utama; memilih metode serta sapaan; memperbarui pratinjau; lalu menekan **Siapkan pesan**. Pesan memakai template bagian 13.5, identitas aktual dan salam menurut waktu cabang. Sapaan dapat netral, Ibu, Bapak, atau nama penerima tanpa menebak dari nomor/hubungan keluarga.

Metode tautan membuka WhatsApp dengan pesan dan tautan portal anak. Metode PDF menyediakan **Unduh PDF**, lalu **Buka WhatsApp**; petugas melampirkan berkas tersebut secara manual. Setelah benar-benar menekan Kirim di WhatsApp, petugas kembali dan memberi konfirmasi manual. Tombol Salin pesan menyediakan pilihan manual ketika clipboard browser tidak tersedia. Riwayat memiliki tautan Lihat pesan agar persiapan sebelumnya dapat diperiksa kembali.

### 55.2 Penerima, izin dan penguncian

Penerima berasal dari tepat satu data wali/penerima siswa yang ditandai utama. Profil tersebut dapat mewakili orang tua atau siswa sesuai isian hubungan yang telah ada. Nomor Indonesia berawalan 0 dinormalisasi menjadi 62; nomor internasional dipertahankan dan URL/daftar beberapa nomor ditolak. Normalisasi tidak membuktikan bahwa nomor terdaftar di WhatsApp atau dimiliki orang tertentu. Petugas memeriksa kembali nomor dan akun aktif pada WhatsApp sebelum kirim.

Teacher pemilik rapor dan Super Admin dapat menyiapkan serta mengonfirmasi pengiriman sendiri. Super Admin tercatat sebagai operator bantuan yang berbeda dari Teacher pengelola. Admin Cabang hanya melihat pratinjau/riwayat rapor cabangnya. Akun lain tetap ditolak melalui pemeriksaan server.

Tindakan persiapan, buka WhatsApp, unduh PDF untuk berbagi, dan konfirmasi memeriksa ulang tiga laporan bulanan Approved, kesesuaian snapshot sumber, final Approved, pembayaran tiga bulan approved, portal anak aktif, kontak utama, dan integritas PDF terkini. Perubahan sumber/kontak menolak form atau persiapan lama. Persiapan memiliki kunci unik dan signature yang mengikat pilihan metode/sapaan serta pesan, sehingga klik ganda menghasilkan satu catatan. Pesan lebih dari 30 menit perlu disiapkan ulang untuk buka/unduh; pengiriman yang sudah dilakukan dapat dikonfirmasi selama sumber dan izin masih sah.

Tautan rapor meminta login akun anak. Login dari tautan internal tersebut kembali ke rapor yang dituju; tujuan luar aplikasi tidak diikuti. Akun anak lain tidak mendapat akses. Pencabutan pembayaran mengunci akses berikutnya. Salinan PDF yang sudah diunduh/dikirim tidak dapat ditarik kembali oleh DPE.

### 55.3 Backend, data dan status

Penambahan backend dan riwayat disampaikan sebelum perubahan. Satu migration menambah tabel **report_whatsapp_shares** dengan FK ke rapor, versi PDF, Teacher, operator dan wali. Tabel menyimpan snapshot kontak/pesan, metode/sapaan, signature, request key serta waktu persiapan/buka/unduh/konfirmasi. Tabel lama dan data percobaan pemilik tidak direset. GuardianService turut memeriksa riwayat baru: kontak yang pernah dipakai tidak dapat dihapus atau dipindahkan ke siswa lain, tetapi nama/nomornya tetap dapat diperbaiki tanpa mengubah snapshot riwayat. Rollback migration ditolak jika riwayat sudah terisi agar catatan tidak terhapus.

Status yang tersedia: Pesan disiapkan; Permintaan buka WhatsApp dicatat; Dikonfirmasi terkirim oleh petugas. Catatan buka hanya membuktikan respons pengalihan dari DPE. Konfirmasi merupakan pernyataan petugas, tidak menyatakan status otomatis diterima/dibaca. Opsi PDF mensyaratkan unduhan dan pernyataan petugas sebelum konfirmasi; aplikasi tidak membuktikan berkas yang akhirnya dilampirkan di WhatsApp. Semua tindakan mutasi memakai POST/CSRF, pembatasan permintaan dan respons privat tanpa cache.

### 55.4 Pemeriksaan

- Regresi PHP: **2.301 tes, 14.131 assertions**, tanpa failure/error/skip, memakai PHP 8.3.6 dan SQLite fixture terpisah.
- Fitur WhatsApp: **26 tes, 131 assertions**, termasuk kedua metode, klik ganda, perubahan kontak/sumber/pembayaran, PDF rusak, kedaluwarsa, batas peran, CSRF, escaping dan tujuan login.
- JavaScript: **101 tes lulus**, termasuk empat tes baru clipboard dan pilihan salin manual.
- HTTP sesi nyata pada server lokal: **16 pemeriksaan lulus**, mencakup login Teacher, CSRF, dua metode, byte PDF, respons handoff, konfirmasi dan login kembali ke rapor. Redirect WhatsApp tidak diikuti; tidak ada pesan nyata dikirim.
- Lint PHP, kompilasi Blade dan cache route berhasil; 18 route WhatsApp terdaftar. PDF fixture hasil unduhan diperiksa empat skill/Note tanpa catatan internal dan dirender untuk pemeriksaan visual.

Pemeriksaan ini tidak mencakup browser Windows/HP, aplikasi WhatsApp sebenarnya, MySQL Laragon atau hosting produksi. Sumber resmi format click-to-chat: https://faq.whatsapp.com/5913398998672934 (nomor internasional dan pesan URL-encoded). Pengiriman nyata dan tampilan desktop/HP diperiksa pemilik pada perangkat yang dipakai.

### 55.5 Paket, pemasangan dan uji pemilik

**DPE_Update_v55_WhatsApp_Semiotomatis.zip** adalah patch di atas v54: 15 file project, backup lima file sumber yang diganti dari arsip v54, manifest SHA256 dan TEST_RESULTS.md. DESIGN.md memakai dokumen eksternal 1.38 sebagai dasar lalu diperbarui menjadi 1.39; backup DESIGN di dalam source ZIP lama masih versi 1.32. Backup otomatis saat pemasangan menyimpan dokumen lokal pengguna yang sesungguhnya. Paket tidak memuat README instalasi, .env, APP_KEY, database, vendor atau unggahan pengguna.

Instruksi PowerShell mandiri diberikan langsung di chat: pilih ZIP, verifikasi hash/isi, periksa kesesuaian source dasar, backup source lokal, salin patch, jalankan **hanya migration 2026_10_19_010000_create_report_whatsapp_shares.php**, lalu bersihkan cache. Tidak menjalankan migrate:fresh, seeder, rollback database, Composer/npm atau membuat APP_KEY. Backup database melalui HeidiSQL sebelum memasang. Jika instalasi gagal, source dipulihkan; tabel/riwayat tambahan tidak dihapus otomatis.

Uji pemilik memakai nomor Teacher dan nomor penerima uji yang berbeda, akun portal anak aktif serta kontak utama valid. Siapkan tiga laporan bulanan, tiga pembayaran approved, final approved dan PDF terbaru untuk periode yang sama. Coba metode tautan sampai login akun anak; untuk penerima melalui HP gunakan alamat aplikasi yang dapat diakses dari HP. Coba metode PDF, lampirkan berkas manual dan kirim, kemudian simpan konfirmasi. Cocokkan penerima/isi/riwayat, uji kontak yang diganti saat formulir masih terbuka, dan pastikan pencabutan pembayaran mengunci berbagi serta akses portal berikutnya. Admin Cabang memeriksa riwayat dan akun anak lain tetap tidak dapat membuka rapor. Muat ulang Ctrl+F5 dan periksa ukuran laptop/HP.

Hosting/domain ditangani teman backend. Setelah patch diterima, langkah berikutnya adalah UAT perangkat nyata, tindak lanjut temuan, data/konten resmi klien, pemasangan hosting, uji backup/pemulihan dan serah terima. Implementasi v55 bukan pernyataan bahwa seluruh UAT/produksi sudah selesai.

## 56. Perbaikan E2E 19 Oktober 2026 — v56: simpan perkembangan sebelum mengelola video

### 56.1 Masalah dan penyebab

Pemilik menemukan isian delapan skill dan deskripsi hilang setelah membuka Tambah video lalu menekan Kembali tanpa membuat video. Tombol lama adalah tautan GET ke halaman video yang berada di luar form perkembangan. Perpindahan tersebut tidak mengirim isian skill/ringkasan/pesan ke server. Tombol Kembali memuat ulang halaman perkembangan dari data terakhir di database, sehingga perubahan yang belum disimpan tidak ikut ditampilkan.

### 56.2 Perilaku setelah perbaikan

Tombol menjadi **Simpan draf & tambah video**. Tombol mengirim form perkembangan melalui endpoint simpan yang sudah ada. Setelah penyimpanan berhasil, server mengarahkan ke form video dengan snapshot terbaru. Menekan Kembali di kanan atas atau Batal pada form video mempertahankan skill, deskripsi, ringkasan, Challenges, pesan orang tua dan catatan internal yang baru disimpan. Video yang dibatalkan tidak dibuat.

Tindakan **Simpan & edit video** dan **Simpan & tinjau hapus** menggunakan alur yang sama. Tinjau hapus membuka halaman konfirmasi yang sudah ada; penghapusan video tetap memerlukan konfirmasi terpisah. Penjelasan di bagian Video belajar menerangkan bahwa pengelolaan video menyimpan draf terlebih dahulu.

Button di luar form ditautkan secara eksplisit ke id form perkembangan melalui atribut form, sehingga delapan skill dan seluruh keterangan dikirim bersama CSRF serta expected_snapshot. Form target video yang terpisah tidak ikut dikirim. Tidak memerlukan JavaScript atau penyimpanan browser tambahan.

### 56.3 Pengamanan dan cakupan

Validasi, transaksi simpan, audit, hak akses dan penguncian periode tetap memakai StudentProgressService. Kategori draf boleh belum dinilai; kategori yang sudah dinilai memerlukan status dan deskripsi bersama seperti aturan sebelumnya. Isian tidak valid mengembalikan pengguna ke editor beserta input dan pesan kesalahan. Form dengan sumber yang berubah ditolak sebelum penyimpanan. Status laporan tetap Draf/Revisi; pengelolaan video tidak mengajukan atau menyetujui laporan.

Tujuan lanjut dibatasi ke route aplikasi. Sebelum menyimpan untuk edit/tinjau hapus, controller memeriksa izin periode dan video milik siswa/bulan/Teacher yang sesuai. UUID salah, video tidak tersedia, kode aksi video tidak dikenal dan video anak lain ditolak sebelum menulis perkembangan. Alur berlaku untuk Teacher pemilik, Admin Cabang berwenang dan Super Admin. Tampilan readonly tetap tidak menyediakan tindakan pengelolaan video.

Dua file aplikasi berubah: StudentProgressController dan view student-progress/edit. Tes StudentProgressWorkflowTest diperluas dan DESIGN diperbarui. Tidak ada route, tabel, migration, dependency atau aset build baru. Data dan riwayat lama dipertahankan; penyimpanan draf baru terjadi ketika pengguna menekan tombol yang menyatakan Simpan.

### 56.4 Pemeriksaan dan paket

Tiga skenario lanjut ke video untuk Teacher/Admin/Super Admin gagal pada controller lama karena belum mendukung simpan lalu buka video. Setelah perbaikan, **164 tes terkait/867 assertions** dan **101 tes JavaScript** lulus. Regresi lengkap juga lulus: **2.317 tes PHP/14.302 assertions**, tanpa failure/error/skip. Kasus baru mencakup kembali tanpa video setelah menyimpan delapan kategori/pesan, draf parsial, validasi yang mempertahankan input, form lama, periode terkunci, edit/tinjau hapus, isolasi video anak lain serta tujuan tidak valid. DOM diperiksa untuk hubungan button ke form, pilihan skill, posisi aksi di kolom terakhir dan ketiadaan form bersarang. Lint tiga file PHP/Blade dan kompilasi seluruh Blade berhasil.

Pengujian memakai PHP 8.3.6, SQLite fixture terpisah, request HTTP Laravel dan pemeriksaan DOM. Browser Windows/HP serta MySQL Laragon belum diuji langsung di lingkungan pengerjaan; pemilik mengulangi skenario pada perangkatnya sesudah pemasangan. Tidak ada pesan WhatsApp nyata dikirim selama pemeriksaan.

Paket **DPE_Fix_v56_Draf_Video.zip** berbasis v55 memuat empat file project, empat backup v55, manifest SHA256 dan TEST_RESULTS.md. Instruksi PowerShell diberikan langsung di chat dengan pemilih ZIP, verifikasi hash/isi, backup source lokal, pemulihan jika gagal dan pembersihan cache. Tidak memerlukan migration/Composer/npm dan tidak memuat .env, database, vendor, cache, unggahan atau README instalasi.

UAT pemilik: pasang patch, Ctrl+F5, buka satu periode Draf/Revisi pada Perkembangan & Pesan. Isi delapan skill, deskripsi dan keterangan. Tekan Simpan draf & tambah video, lalu kembali tanpa menyimpan video. Pastikan seluruh isian tetap ada dan jumlah video tidak bertambah. Ulangi melalui Batal; periksa alur edit serta tinjau hapus tanpa mengonfirmasi hapus. Draf parsial yang valid tetap dapat dilanjutkan. Jika status diisi tanpa deskripsi, form menampilkan kesalahan dan mempertahankan isian lain. Periksa Teacher, Admin Cabang dan Super Admin pada Laragon/browser yang dipakai.

## 57. Perbaikan E2E 20 Oktober 2026 — v57: jarak laporan bulanan pada daftar siklus

Kolom **Laporan bulanan** di halaman **Siklus Laporan** sebelumnya menampilkan paragraf berurutan tanpa jarak khusus, sehingga status setiap bulan terlalu rapat. Admin Cabang dan Super Admin memakai view yang sama, yaitu admin/report-cycles/index.

Daftar bulan sekarang memakai elemen ul/li dengan jarak vertikal **0,75rem (12 px pada ukuran dasar 16 px)**. Tanggal dan badge status berada dalam satu baris flex dengan jarak minimal **1rem (16 px)**. Awal tanggal dan sisi kanan badge sejajar dalam kelompok daftar; pemisah titik tidak diperlukan. Kelompok tetap di tengah kolom. Lebar minimum kolom 17rem menjaga ruang tanggal/status, sementara tabel memakai pembungkus geser horizontal yang sudah tersedia pada layar sempit. Kondisi belum ada laporan mempertahankan pesan kosong sebelumnya.

CSS dibatasi ke kelas dpe-report-cycle-monthly-* di operations.css. Tautan dan komponen status tetap memakai route peran serta label/warna yang tersedia. Urutan bulan, filter, pagination, hak akses dan sumber data mengikuti controller yang sudah ada. Dua file aplikasi berubah: view bersama dan CSS operations; DESIGN turut diperbarui. Tidak memerlukan migration, dependency, JavaScript atau build aset.

Pemeriksaan: **24 tes terkait/67 assertions lulus**; seluruh Blade berhasil dikompilasi. Pemeriksaan HTTP dengan login fixture nyata Admin Cabang dan Super Admin berhasil membuka daftar siklus, menghasilkan tiga pasangan bulan/status, memakai tautan sesuai peran, membuka ketiga laporan, dan memuat CSS baru. Isi tabel report_cycles, monthly_reports, monthly_report_skills dan learning_videos pada fixture identik sebelum/sesudah pemeriksaan. Tidak menambah tes formal untuk perubahan jarak ini. Pemeriksaan visual pada browser Windows/Laragon belum dilakukan di lingkungan pengerjaan karena executable browser tidak tersedia; pemilik memeriksa hasil sesudah pemasangan dan Ctrl+F5.

Paket **DPE_Fix_v57_Jarak_Laporan_Bulanan.zip** berbasis source v56, berisi tiga file project, tiga backup v56, MANIFEST.json dan TEST_RESULTS.md. Instruksi pemasangan PowerShell diberikan di chat; tidak memuat README instalasi, .env, database, vendor atau unggahan pengguna.

## 58. Perbaikan E2E 20 Oktober 2026 — v58: proporsi logo pada PDF rapor

Preview PDF sebelumnya memaksa logo ke ukuran 56pt x 56pt. Logo bawaan memiliki ukuran 631 x 395 piksel; rasio 1,597 berubah menjadi 1 pada hasil PDF, sehingga bentuknya terdistorsi. Properti object-fit pada template sebelumnya tidak mempertahankan rasio pada hasil Dompdf yang diperiksa.

Template PDF kini memakai width/height auto dengan max-width/max-height 56pt. Dompdf menghitung ukuran dari dimensi intrinsik gambar lalu membatasi kedua sisinya, sehingga logo bawaan menjadi sekitar 56pt x 35,055pt. Logo melebar, portrait dan persegi tetap mempertahankan rasionya. Posisi kiri atas serta ruang kop tetap memakai tata letak yang ada; bytes logo dan salinan persetujuan tidak diubah.

Pemeriksaan: **83 tes lifecycle rapor/371 assertions lulus**, termasuk preview, persetujuan, snapshot logo beku, file privat, regenerasi dan riwayat. Geometri gambar pada **tujuh PDF/31 halaman** diperiksa menggunakan MuPDF: rasio tampilan sesuai ukuran sumber, sisi maksimum 56pt dan logo berada di area kop. PDF mencakup logo bawaan, tiga orientasi logo fixture, narasi panjang 19 halaman, periode lintas tahun dan kop maksimum. Poppler merender seluruh halaman untuk pemeriksaan visual; isi, kop, penutup dan penomoran tetap terbaca. Render tiga orientasi fixture tidak mengubah atribut rapor approved pada database fixture. Peringatan metadata ICC logo bersifat nonfatal. MySQL Laragon dan browser Windows pengguna belum diuji langsung.

Tanda tangan pada versi ini masih berupa placeholder. ReportTemplateService menyimpan teks tetap Area tanda tangan - belum diunggah; belum tersedia field unggah, relasi berkas tanda tangan Teacher/Manager, atau penyertaan bytes tanda tangan ke salinan persetujuan. Nama Teacher berasal dari pemilik rapor dan nama Manager berasal dari Template PDF. Sesuai rancangan 12.3, fitur berikutnya perlu mengaitkan tanda tangan Teacher ke identitas guru dan tanda tangan Manager ke pengaturan lembaga, lalu membekukannya saat persetujuan. Patch v58 tidak menambahkan fitur unggah tersebut.

Paket **DPE_Fix_v58_Logo_PDF_Rapor.zip** berbasis source v57 berisi dua file project (view PDF dan DESIGN), dua backup v57, MANIFEST.json dan TEST_RESULTS.md. Perubahan tidak memerlukan migration, dependency atau build aset. Setelah pemasangan dan pembersihan cache Blade, tutup preview lama lalu buka Preview PDF kembali. PDF arsip yang sudah tersimpan tidak ditimpa; koreksi tampilan pada arsip memerlukan tindakan Buat ulang PDF yang sudah tersedia.

## 59. Finalisasi 20 Oktober 2026 — v59: unggah tanda tangan dan salinan PDF

### 59.1 Pengelolaan dan identitas

Teacher aktif mengunggah tanda tangan sendiri melalui **Profil Saya > Tanda tangan Teacher**. Super Admin aktif mengunggah tanda tangan Manager melalui **Rapor Tiga Bulan > Template PDF > Tanda tangan Manager**. Form berdiri sendiri, memakai POST/multipart, CSRF, batas permintaan dan signature versi. Admin Cabang dan akun siswa tidak memiliki route unggah atau akses gambar sumber. Teacher lain tidak dapat membaca/mengganti tanda tangan seseorang dengan mengirim ID Teacher, file, atau nama penandatangan.

Format PNG/JPG, maksimal 512 KB dan 2000 x 2000 piksel. PNG transparan disarankan. Validasi memeriksa isi gambar, ekstensi, ukuran, dimensi dan kemampuan decode; SVG/GIF atau file rusak ditolak. File disimpan di storage privat dengan nama UUID. Endpoint gambar memeriksa pemilik/peran aktif, hash, ukuran dan MIME; respons private/no-store/nosniff. Tautan publik berkas mentah tidak dibuat.

Nama Teacher mengikuti akun pemilik. Nama Manager mengikuti template yang disimpan. Bila nama berubah, versi unggahan atas nama sebelumnya tidak dipakai untuk persetujuan baru dan halaman meminta unggah ulang. Pengguna menyimpan profil/template dahulu sebelum mengunggah. File aktif yang hilang/rusak memunculkan pesan untuk unggah ulang; proses snapshot tidak diam-diam mengabaikan gambar yang sebelumnya dimaksudkan untuk digunakan.

### 59.2 Penyimpanan dan penguncian

Backend diumumkan sebelum implementasi. Satu migration **2026_10_20_010000_create_report_signatures.php** menambah tabel report_signatures. Tabel menyimpan scope pemilik, Teacher bila relevan, versi unggahan, FK file privat, nama saat unggah, operator dan waktu. Seluruh versi unggahan dipertahankan. Satu baris Manager versi 0 menjadi pengunci awal, bukan berkas tanda tangan. Rollback ditolak bila sudah ada unggahan agar riwayat tidak terhapus otomatis. Kolom/tabel dan data laporan lama tidak direset.

Unggah Teacher mengunci akun/profil sendiri; unggah Manager mengunci konfigurasi dan baris pengunci Manager. Pemeriksaan versi dilakukan sebelum menulis berkas. Form kedaluwarsa/klik ganda ditolak. Bila transaksi gagal, berkas baru dibersihkan, termasuk berkas dari percobaan transaksi yang diulang. Berkas lama tetap direferensikan oleh riwayat dan FK mencegah penghapusan metadata sebelum file fisik disentuh.

### 59.3 Rapor, PDF dan persetujuan lama

Preview sebelum persetujuan membaca tanda tangan terkini milik Teacher pemilik dan Manager dengan nama yang cocok. Halaman pemeriksaan rapor menunjukkan ketersediaan kedua tanda tangan. Unggahan/perubahan setelah formulir rapor dibuka membuat formulir tersebut kedaluwarsa; petugas memuat ulang sebelum menyetujui. Pengunci Manager turut diperiksa saat transaksi rapor.

Saat persetujuan, bytes gambar, hash, identitas pemilik, nama dan versi tanda tangan disimpan di **approved_snapshot**. PDF/preview persetujuan/regenerasi membaca gambar dari salinan itu, tanpa mengambil ulang berkas aktif atau URL remote. Mengganti unggahan atau hilangnya file sumber kemudian tidak mengubah persetujuan/PDF lama. Untuk memasukkan gambar baru ke rapor yang sudah disetujui, Teacher memulai revisi, mengajukan kembali, lalu Admin menyetujui; Buat ulang PDF pada persetujuan lama tetap memakai salinan lama.

Rapor lama tanpa key signatures tetap dapat dirender dengan area tanda tangan sebelumnya. Bila belum ada unggahan atau identitas tidak cocok, placeholder tetap dipakai; fitur ini tidak menambahkan syarat wajib tanda tangan yang mengubah alur persetujuan lama. Renderer memeriksa identitas, bytes, MIME dan hash gambar yang tertanam. Tanda tangan dibatasi 170pt x 45pt memakai ukuran auto agar proporsi tetap terjaga. Perbaikan proporsi logo v58 turut dipertahankan.

### 59.4 Pemeriksaan dan paket

**2.347 tes PHP/14.496 assertions lulus**, termasuk **30 kasus fitur baru/194 assertions**. Cakupan baru: form terpisah, unggah PNG/JPG, peran/pemilik, user/profil nonaktif, ekstensi/SVG/GIF/ukuran/dimensi/file rusak, form lama/klik ganda, versi pengganti, retensi/FK, perubahan nama, persetujuan yang kedaluwarsa, pembekuan bytes, gambar sumber hilang, rollback berkas/metadata, snapshot lama dan penolakan gambar remote/identitas salah. Audit halaman umum mengecualikan endpoint gambar privat tanpa unggahan (404); kasus khusus tetap memeriksa 404, izin dan keberhasilan sesudah unggah.

Sesi HTTP dengan database fixture salinan terisolasi berhasil menjalankan login Teacher/Super Admin, form nyata, penolakan CSRF, unggah gambar, pengambilan bytes privat persis, penolakan akses lintas peran, revisi/persetujuan baru dan preview PDF berisi kedua tanda tangan. Tidak ada WhatsApp nyata dikirim. Seluruh Blade dan cache route berhasil dibangun; cache route pengujian dibersihkan. PDF bertanda uji Teacher/Manager diperiksa geometri dan dirender melalui Poppler; gambar proporsional berada pada kolom yang benar tanpa menimpa nama/kop/footer. Pemeriksaan langsung MySQL Laragon/browser Windows pengguna belum dilakukan.

Paket **DPE_Update_v59_Tanda_Tangan_Rapor.zip** berbasis v58, dengan kompatibilitas hash view PDF v57 agar pemilik dapat langsung memasang setelah v57. Instruksi PowerShell di chat memverifikasi ZIP/source, membuat backup source lokal, memakai maintenance sementara, menjalankan hanya migration tanda tangan dan membersihkan cache. Jika gagal, source dipulihkan; tabel/riwayat tidak dihapus otomatis. Backup database melalui HeidiSQL sebelum memasang. Tidak menjalankan reset, seeder, migration lain, Composer/npm atau perubahan APP_KEY. Paket tidak memuat .env, database, unggahan, vendor, PDF fixture atau README instalasi.

UAT: pasang paket, Ctrl+F5, login Teacher lalu simpan tanda tangan di Profil Saya. Login Super Admin, pastikan nama Manager pada Template PDF sudah benar, lalu simpan tanda tangannya pada bagian terpisah di bawah form. Buka kembali rapor yang belum disetujui dan Preview PDF; kedua gambar harus muncul di atas nama masing-masing. Muat ulang halaman tinjauan Admin sebelum persetujuan. Setelah persetujuan, buat PDF final lalu lanjutkan pengujian WhatsApp dengan syarat pembayaran dan laporan bulanan yang sudah tersedia.

## 60. Jarak tombol Template PDF (v60)

Halaman Template PDF Super Admin sebelumnya memakai kelompok tombol umum tanpa margin atas, sehingga tombol Simpan template dan Batal menempel pada textarea terakhir. Aturan font tombol bawaan juga membuat tinggi tombol submit berbeda dari tautan Batal.

Kelompok ini sekarang memiliki class khusus `dpe-report-template-actions`: jarak atas 1,5rem (24px pada ukuran font dasar), jarak antartombol 0,75rem (12px), tinggi minimum kedua tombol 46px, line-height 1,35 dan font-weight 700 yang sama. Pengaturan dibatasi pada kelompok tombol template. Perilaku flex-wrap dan pertumbuhan tombol pada layar kecil mengikuti aturan yang sudah tersedia. CSS memakai URL berversi berdasarkan filemtime agar perubahan terambil setelah muat ulang.

Pemeriksaan: dua tes terkait form template dan form unggah Manager lulus, total 25 assertions; seluruh Blade berhasil dikompilasi. Sesi HTTP pada salinan database fixture memverifikasi login Super Admin, GET Template PDF, method PUT/CSRF tombol Simpan, tautan Batal ke daftar rapor (200), form tanda tangan yang tetap terpisah dan CSS terbaru yang disajikan. Pemeriksaan visual browser Windows pengguna belum dilakukan; UAT: pasang v60, Ctrl+F5, periksa jarak dari textarea serta kesamaan tinggi kedua tombol pada desktop dan layar kecil.

Paket `DPE_Fix_v60_Gap_Tombol_Template_PDF.zip` memuat dua berkas tampilan/CSS dan dokumen ini, disertai backup versi v59 serta hasil pemeriksaan. Instruksi PowerShell di chat memverifikasi hash ZIP dan source, membuat backup lokal, memperbarui berkas dan membersihkan cache Blade. Tidak ada migration atau build aset pada perbaikan ini.

## 61. Pilihan pratinjau WhatsApp saat rapor dibuka ulang (v61)

Sebelumnya tombol Perbarui pratinjau mengirim GET berisi method dan salutation pada URL. Tautan Riwayat WhatsApp dari detail rapor tidak membawa parameter tersebut, sehingga setiap pembukaan ulang memakai Kirim tautan rapor dan Netral (Anda).

Form sekarang memakai POST ber-CSRF ke endpoint pratinjau, memvalidasi kedua pilihan, memeriksa akses akun terhadap rapor, lalu menyimpan hanya method dan salutation dalam sesi dengan kunci akun serta ID rapor. Respons mengarah kembali ke halaman WhatsApp rapor yang sama. Akun Admin Cabang, Teacher dan Super Admin masing-masing memiliki pilihan sendiri; pilihan pada rapor/siklus lain juga terpisah. Pilihan berlaku selama sesi login aktif dan terhapus saat logout/sesi berakhir. Catatan singkat pada halaman menjelaskan cakupan ini. Tidak ada pengaturan bersama yang ditulis oleh Admin untuk Teacher.

Pesan, kontak penerima dan tanda tangan pratinjau dihitung kembali saat halaman dimuat. Parameter GET yang sudah ada tetap dapat menampilkan pratinjau sementara dan tidak menimpa pilihan sesi. Persiapan pesan yang berhasil turut mengingat pilihan yang benar-benar dipakai. Membuka pesan dalam riwayat tetap memakai method, salutation dan pesan asli record; membaca riwayat tidak mengganti preferensi pratinjau. Admin tetap hanya dapat meninjau dan memperbarui pratinjau sendiri; operasi persiapan, unduh, buka WhatsApp dan konfirmasi pengiriman tetap mengikuti izin sebelumnya.

Pemeriksaan akhir: **181 tes PHP/1.053 assertions lulus**, mencakup workflow WhatsApp, lifecycle rapor tiga bulan dan audit regresi halaman. Kasus tambahan meliputi pembukaan ulang ketiga role, pemisahan akun/rapor, opsi query sementara, input salah yang tidak menimpa pilihan, akses lintas cabang/pemilik, CSRF, pesan yang sesuai pilihan, retensi isi riwayat dan logout. PHP lint, kompilasi seluruh Blade serta kompilasi cache route berhasil.

Sesi HTTP dengan database fixture salinan memverifikasi login Admin, CSRF 419, POST PDF/Ibu yang kembali ke halaman WhatsApp, isi pesan yang sesuai, tombol persiapan Admin tetap nonaktif, kembali ke detail lalu membuka ulang tanpa query, pemisahan sesi Teacher serta logout/login yang mengembalikan default. Hash seluruh data bisnis fixture sebelum/sesudah sama. Tidak ada WhatsApp nyata dikirim. Browser Windows/Laragon pengguna dan seluruh regresi aplikasi belum dijalankan ulang pada pembaruan ini.

Paket `DPE_Fix_v61_Pilihan_Pratinjau_WhatsApp.zip` berbasis v60, memuat controller, route, Blade, tes regresi dan dokumen ini, disertai backup v60 dan hasil pemeriksaan. Pemasangan memverifikasi hash dan source, membuat backup, menyalin berkas lalu membersihkan cache route dan Blade. Tidak memerlukan migration atau build aset. UAT: pasang v61, Ctrl+F5, buka Riwayat WhatsApp, pilih PDF/Ibu, Perbarui pratinjau, kembali ke rapor lalu buka Riwayat WhatsApp lagi; pilihan dan pesan harus tetap PDF/Ibu pada akun tersebut.

## 62. Sapaan Bapak/Ibu atau Wali dan acuan salam waktu (v62)

Pilihan sapaan ditambah **Bapak/Ibu atau Wali**, dengan kode internal `wali` yang sesuai batas kolom salutation 8 karakter. Pilihan ini tersedia pada halaman WhatsApp ketiga role, diterima oleh validasi pratinjau/persiapan dan digunakan pada pembuka serta seluruh paragraf yang menyapa penerima. Contoh: Selamat sore Bapak/Ibu atau Wali, mohon maaf mengganggu waktunya. Pengingatan pilihan v61 berlaku untuk opsi baru ini; default tetap Netral (Anda).

Salam waktu mengikuti `timezone_name` cabang. Bila zona tersebut tidak valid, sistem memakai zona waktu aplikasi. Aturan yang sudah ada tetap: 00.00–10.59 Selamat pagi, 11.00–14.59 Selamat siang, 15.00–17.59 Selamat sore, 18.00–23.59 Selamat malam. Salam dihitung saat pratinjau dimuat/diperbarui. Halaman tidak memperbarui teks dengan jam berjalan. Saat persiapan, pesan dihitung kembali dan harus sesuai tanda tangan pratinjau; melewati batas salam membuat pratinjau lama ditolak sampai dimuat ulang. Pesan yang sudah disiapkan tetap menyimpan teks/salam saat itu. Untuk memperbaruinya gunakan Siapkan pesan baru.

Pemeriksaan: **42 tes WhatsApp/325 assertions lulus**, termasuk opsi baru pada Admin, Teacher dan Super Admin, ingatan sesi, persiapan/riwayat, batas 11.00/15.00/18.00 dan tengah malam, zona Jakarta/Makassar/Jayapura serta fallback aplikasi, pratinjau kedaluwarsa ketika salam berganti, dan riwayat yang tetap utuh. PHP lint lolos; cache route dan seluruh Blade berhasil dikompilasi. Sesi HTTP pada database fixture salinan memverifikasi pilihan PDF/Wali, isi pesan, pembukaan ulang, pemisahan Teacher, CSRF, izin Admin dan logout; seluruh data bisnis tetap sama dan tidak ada WhatsApp nyata dikirim. Browser Windows/Laragon pengguna dan regresi seluruh aplikasi belum dijalankan ulang pada pembaruan ini.

Paket `DPE_Update_v62_Sapaan_Wali_WhatsApp.zip` mencakup v61 dan dapat dipasang langsung pada source v60 atau v61 yang sesuai hash. Paket berisi service, controller, route, Blade, tes dan dokumen ini, disertai backup basis v61 serta hasil pemeriksaan. Installer mencadangkan source aktual pengguna dan membersihkan cache route/Blade. Tidak diperlukan migration, reset database atau build aset. UAT: Ctrl+F5, pilih Bapak/Ibu atau Wali lalu Perbarui pratinjau; periksa pembuka/isi pesan dan buka ulang halaman untuk memastikan pilihan diingat selama sesi login.

## 63. Penyempurnaan tampilan tanda tangan PDF (v63)

Judul kedua kolom tanda tangan menjadi **Teacher's Sign** dan **Manager of Dharma Private English**, mengikuti contoh pemilik. Judul dan nama penandatangan dicetak tebal. Kedua nama diapit satu pasang kurung pada tampilan PDF; pembungkusan baris menghitung kurung serta lebar font tebal agar nama panjang tetap berada pada kolomnya. Nama akun, template, identitas penandatangan dan salinan persetujuan tidak ditulis ulang.

Batas gambar diperbesar dari 170 x 45pt menjadi 200 x 90pt, dengan width/height auto agar rasio asli tetap terjaga untuk orientasi portrait, landscape maupun persegi. Area gambar menyediakan tinggi 96pt, jarak atas 8pt, serta jarak nama 6pt. Tinggi judul kedua kolom disamakan menjadi 28pt. Gambar dengan resolusi kecil mengikuti ukuran intrinsiknya; PNG dengan ruang kosong berlebih perlu dipotong pada berkas sumber supaya goresan memanfaatkan area tersebut.

Template PDF dan layanan penyimpanan tidak menambahkan latar kotak-kotak atau menghapus transparansi. PNG dapat berisi piksel latar buram; ekstensi PNG tidak menjamin kanal alpha yang transparan. Petunjuk unggah pada Profil Saya dan Template PDF sekarang menjelaskan penggunaan PNG yang benar-benar transparan tanpa pola kotak yang menyatu, serta pengurangan ruang kosong di sekitar goresan. Bytes gambar tetap dipertahankan; patch ini tidak memproses ulang tanda tangan yang diunggah.

Pemeriksaan: **156 tes terkait/918 assertions lulus** pada lifecycle rapor, unggah/tanda tangan dan WhatsApp yang tersedia dalam workspace. Empat tes render kunci diulang pada source akhir dan lulus dengan 41 assertions. PHP lint dan kompilasi seluruh Blade berhasil. **14 PDF/45 halaman** dirender melalui Poppler dan diperiksa geometri dengan MuPDF: label/nama, font tebal, kurung, batas halaman/kolom, rasio gambar, kop, footer, narasi 19 halaman dan nama panjang. Delapan variasi khusus mencakup PNG transparan, pola kotak buram, JPEG, landscape sangat lebar, portrait sangat tinggi, gambar kecil, nama panjang dan snapshot lama tanpa tanda tangan; satu render basis dipakai untuk membandingkan ukuran sebelumnya. PNG transparan mempertahankan soft mask, sedangkan pola kotak yang sudah berupa piksel tetap tampil. Database fixture identik sebelum/sesudah render. Tidak menambah tes formal baru untuk penyempurnaan tampilan ini. Browser Windows/MySQL pengguna belum diuji langsung.

Paket `DPE_Fix_v63_Tampilan_Tanda_Tangan_PDF.zip` berbasis v62 memuat view PDF, renderer (pemformatan nama), petunjuk unggah bersama dan dokumen ini, disertai backup serta hasil pemeriksaan. Tidak memerlukan migration, build aset, perubahan APP_KEY atau pengaturan database. Setelah pemasangan, muat ulang dan buka kembali Preview PDF. Berkas PDF final yang sudah tersimpan tetap memakai tampilan lama sampai pengguna memilih **Buat ulang PDF** dengan alasan. Jika mengganti gambar pada rapor yang sudah disetujui, mulai revisi, ajukan kembali dan setujui agar gambar baru masuk ke salinan persetujuan; regenerasi pada persetujuan lama tetap memakai gambar lama.

## 64. Jarak konfirmasi dan informasi PDF tersimpan (v64)

Kelompok tombol pada Konfirmasi tindakan di detail rapor tiga bulan sebelumnya tidak memiliki jarak atas khusus, sehingga menempel pada textarea Alasan. Kelompok ini kini memakai class dpe-final-confirm-actions dengan margin atas 1,5rem (24px), gap tombol 0,75rem (12px), tinggi minimum 46px, line-height 1,35 dan font-weight 700. Tombol konfirmasi serta Batal memakai pengaturan yang sama. Aturan berlaku pada komponen konfirmasi bersama, termasuk Buat ulang PDF; perilaku flex-wrap pada layar kecil tetap mengikuti kelompok aksi yang tersedia.

Pada tabel PDF tersimpan, keterangan small sebelumnya bersifat inline sehingga teks Revisi dan status persetujuan, serta waktu dan nama pembuat, tersambung. Tabel sekarang memiliki class khusus dpe-final-pdf-table. Setiap pasangan informasi dibungkus dpe-final-pdf-meta dengan display grid, gap 0,35rem dan line-height 1,55. Kolom Persetujuan menempatkan Revisi pada baris atas dan Persetujuan saat ini/Arsip persetujuan sebelumnya pada baris bawah. Kolom Dibuat menempatkan tanggal/jam pada baris atas dan nama pembuat pada baris bawah. Tanggal juga memakai elemen time dengan datetime ISO 8601; format waktu yang terlihat dan sumber nama tetap sama. Teks panjang dapat membungkus di dalam kolomnya.

View dan CSS dipakai bersama oleh Teacher, Admin Cabang serta Super Admin. Form POST, CSRF, alasan wajib, pemeriksaan versi, status tombol, tujuan Batal, serta tautan Buka/Unduh tetap mengikuti alur yang tersedia. CSS dimuat dengan query filemtime sehingga versi baru dapat diambil sesudah muat ulang. Perbaikan v63 pada isi PDF dan tanda tangan tetap utuh.

Pemeriksaan: **83 tes lifecycle rapor/371 assertions lulus** dan seluruh Blade berhasil dikompilasi. Pemeriksaan HTTP memakai database fixture salinan dan login nyata untuk ketiga role: halaman detail dan konfirmasi regenerate merespons 200, kedua versi PDF serta status persetujuan saat ini/arsip terbaca pada pasangan elemen terpisah, tanggal dan nama pembuat terpisah, tautan sesuai role, form POST/CSRF/snapshot/alasan wajib/tombol Batal benar, dan CSS terbaru disajikan dengan URL berversi. Seluruh data bisnis dan versi PDF fixture identik sebelum/sesudah. Tidak menambah tes formal baru untuk perubahan tampilan ini. Pemeriksaan visual pada browser Windows/Laragon pengguna belum dilakukan karena executable browser tidak tersedia.

Paket `DPE_Fix_v64_Jarak_Konfirmasi_dan_Tabel_PDF.zip` berbasis v63 berisi view detail rapor, CSS quarterly-reports dan dokumen ini, disertai tiga backup dan hasil pemeriksaan. Pemasangan mencadangkan source aktual dan membersihkan cache Blade. Tidak memerlukan migration, reset database atau build aset. UAT: Ctrl+F5, buka Periksa Rapor > Buat ulang PDF, periksa jarak dari Alasan ke kedua tombol, lalu periksa dua baris informasi pada kolom Persetujuan dan Dibuat. Tidak perlu membuat ulang file PDF untuk perubahan tampilan halaman ini.

## 65. Tipografi dashboard dan navigasi Rapor Siswa (v65)

Empat kartu ringkasan dashboard siswa sebelumnya memakai ukuran nilai umum 34px (30px pada layar sangat kecil), sehingga teks tanggal bergabung dan status pembayaran terlihat terlalu dominan. Section ringkasan kini diberi class dpe-portal-metrics. CSS portal mengatur nilai di dalam section ini menjadi 24px, font-weight 700, line-height 1,3 dan letter-spacing -0,3px; pada lebar layar maksimal 600px ukurannya 22px. Teks panjang dapat membungkus. Ukuran, padding dan grid kartu mengikuti aturan yang tersedia. Ukuran label serta catatan tetap memakai komponen kartu yang sama. Aturan khusus ini tidak mengubah tipografi kartu pada dashboard role lain.

Dua tombol Laporan Bulanan dan PDF Tiga Bulan pada Rapor Siswa ternyata merupakan tautan anchor ke section laporan-bulanan dan rapor-final di halaman yang sama. Tujuan anchor sudah benar; kedua tombol tidak membuka halaman lain atau mengganti jenis laporan. Pada tampilan dengan kedua section terlihat, pengaruh kliknya tidak jelas. Sesuai izin pemilik untuk menghapus kontrol yang berulang, kedua pintasan ini dihapus. Tombol Kembali ke Dashboard dan Lihat Pembayaran disatukan dalam satu kelompok navigasi dengan flex-wrap. Kedua daftar, judul section, pagination serta tautan per laporan tetap ditampilkan. ID section dipertahankan agar tautan langsung yang sudah memakai fragment tetap dapat digunakan.

Pemeriksaan: **74 tes portal siswa/358 assertions lulus**, mencakup dashboard, data satu siswa, akses laporan bulanan/PDF, penguncian pembayaran, pembayaran, role dan privasi. Seluruh Blade berhasil dikompilasi. Pemeriksaan HTTP dengan login siswa fixture memverifikasi empat kartu dan nilai data aktual, CSS portal terbaru melalui URL filemtime, dua tombol navigasi dengan tujuan Dashboard/Pembayaran (200), kedua section laporan, pembukaan tiga laporan bulanan dan satu rapor tiga bulan (200), serta penghapusan dua anchor dari kelompok aksi. Seluruh data bisnis fixture identik sebelum/sesudah. Perbaikan isi PDF v63 dan tampilan konfirmasi v64 tetap sesuai hash rilis. Tidak menambah tes formal baru untuk perubahan tampilan ini. Pemeriksaan visual browser Windows/Laragon pengguna belum dilakukan karena executable browser tidak tersedia.

Paket `DPE_Fix_v65_Tampilan_Portal_Siswa.zip` berbasis v64 memuat dashboard siswa, halaman daftar rapor siswa, CSS portal dan dokumen ini, disertai empat backup serta hasil pemeriksaan. Tidak memerlukan migration, perubahan data atau build aset. UAT: pasang paket dan Ctrl+F5; periksa ukuran teks pada empat kartu tanpa mengubah ukuran kartunya. Buka Rapor Siswa, periksa kelompok Kembali ke Dashboard/Lihat Pembayaran, lalu buka laporan melalui tombol pada tabel masing-masing.

## 66. Tombol Detail pada Guru & Penugasan Super Admin (v66)

Daftar penugasan guru sebelumnya membuka detail melalui tautan pada nama guru atau cabang, sedangkan kolom Aksi hanya menyediakan Edit. Akses detail sekarang dibuat eksplisit: setiap baris memiliki tombol **Detail** dan **Edit** pada kolom Aksi. Detail menuju halaman ringkasan penugasan untuk baris tersebut, memakai route show yang sudah tersedia. Nama guru dan nama cabang ditampilkan sebagai teks tebal tanpa tautan. Kode cabang serta tanggal/status mengikuti data yang sama.

Kedua tombol memakai kelompok dpe-operations-actions yang sudah dimuat oleh layout dashboard: jarak 12px, tinggi minimum tombol 44px, rata tengah pada tabel dan dapat membungkus pada lebar sempit. Tidak diperlukan perubahan CSS global, route, controller atau database.

Pemeriksaan: **16 tes pengelolaan penugasan/40 assertions lulus** dan seluruh Blade berhasil dikompilasi. Sesi HTTP dengan login Super Admin pada database fixture salinan yang disiapkan dengan dua penugasan (aktif dan selesai) memverifikasi nama/cabang tanpa tautan, dua tombol per baris, pembukaan Detail dan Edit masing-masing (200), serta kecocokan guru dan cabang pada halaman detail. Seluruh data bisnis fixture identik selama pemeriksaan GET. Tidak menambah tes formal baru untuk penyesuaian navigasi tampilan ini. Pemeriksaan visual browser Windows/Laragon pengguna belum dilakukan karena executable browser tidak tersedia.

Paket `DPE_Fix_v66_Tombol_Detail_Penugasan_Guru.zip` berbasis v65 memuat view daftar penugasan dan dokumen ini, disertai dua backup serta hasil pemeriksaan. Tidak memerlukan migration atau build aset. UAT: pasang paket, Ctrl+F5, buka Guru & Penugasan dan gunakan Detail di kolom Aksi; periksa halaman ringkasan yang sesuai, kembali ke daftar lalu buka Edit. Nama guru/cabang tidak lagi menjadi tautan.

## 67. Label peran Teacher pada layout dashboard (v67)

Label peran teacher di layout dashboard diubah dari **Guru** menjadi **Teacher**. Satu label bersama ini dipakai pada kartu Masuk sebagai di sidebar, identitas akun kanan atas, serta keterangan peran di heading Workspace. Perubahan hanya mengganti teks label; inisial GR, kode role teacher, nama menu, route, izin akses dan data mengikuti implementasi yang tersedia. Label Super Admin, Admin Cabang serta Siswa / Parent tetap sesuai perannya.

Pemeriksaan: **5 tes sidebar dashboard/71 assertions lulus** dan seluruh Blade berhasil dikompilasi. Sesi HTTP dengan login fixture memverifikasi label Teacher pada dashboard, Profil Saya dan Notifikasi, serta label ketiga role lain pada dashboard masing-masing (enam halaman, semuanya 200). Perbandingan view sebelum/sesudah memastikan satu penggantian label saja; seluruh data bisnis fixture identik sebelum/sesudah. Tidak menambah tes formal baru untuk perubahan teks ini. Pemeriksaan visual browser Windows/Laragon pengguna belum dilakukan.

Paket `DPE_Fix_v67_Label_Teacher.zip` berbasis v66 berisi layout dashboard dan dokumen ini, disertai dua backup serta hasil pemeriksaan. Pemasangan membersihkan cache Blade; tidak memerlukan migration atau build aset. UAT: muat ulang halaman akun guru sesudah pemasangan dan periksa tulisan Teacher pada sidebar serta identitas akun kanan atas.

## 68. Absensi tiga bulan siswa dan ikon kata sandi (v68)

Revisi setelah pengujian bersama client menambahkan **Absensi Tiga Bulan** pada kelompok Perjalanan Belajar di sidebar siswa. Halaman menyediakan tiga pemilih bulan/tahun yang dapat diubah masing-masing, termasuk bulan yang tidak berurutan. Pilihan awal adalah bulan berjalan di Asia/Makassar beserta dua bulan sebelumnya. Setiap perubahan yang valid langsung membuka kembali halaman dengan ketiga pilihan tersebut; tidak ada tombol Filter/Tampilkan/Reset pada pemilih ini. Ketiga bulan harus berbeda. Pilihan dibatasi pada format tahun-bulan valid dalam rentang 1900-01 sampai 9999-12. Server memvalidasi jumlah dan format pilihan, termasuk ketika parameter URL diubah langsung.

Setiap bulan menampilkan rekap dan seluruh catatan pertemuan yang tersedia: tanggal/jam, cabang/program/kelas, teacher dan status kehadiran. Data dibaca melalui layanan portal serta perhitungan kontribusi absensi yang sudah dipakai sistem. Hanya siswa yang terhubung ke akun aktif yang dapat diakses. Pendaftaran, keanggotaan kelas, tanggal mulai les, bulan aktual sesi dan zona waktu cabang tetap menentukan kelayakan. Sesi yang belum berlangsung tidak ditampilkan. Catatan individual/internal tidak dikirim ke tampilan ini. Halaman absensi tidak memerlukan pembukaan rapor atau validasi pembayaran.

Visibilitas mengikuti cabang setiap sesi: saat OFF, baris, nama cabang dan kontribusi hitungannya tidak ditampilkan; cabang lain yang ON tetap dapat terlihat. Bila seluruh data tidak tersedia untuk ditampilkan, halaman menyediakan keadaan kosong. Belum dicatat tetap dibedakan dari Tidak Hadir. Sesi dibatalkan serta sesi asal yang telah diganti tidak dihitung sebagai pertemuan berlangsung; sesi pengganti mengikuti catatan sebenarnya. Data absensi tidak dibuat atau diubah saat halaman dibuka. Halaman menggunakan pengamanan role student dan respons private/no-store yang tersedia.

Form ubah kata sandi bersama untuk siswa dan teacher mengganti tombol teks Tampilkan/Sembunyikan dengan ikon mata dari komponen yang sama dengan login. Ikon ditempatkan di sisi kanan di dalam kolom input, dengan ruang teks yang cukup dan area tombol 44px. Ketiga kolom memiliki nama aksesibel masing-masing; aria-label dan aria-pressed mengikuti keadaan tampil/sembunyi. JavaScript mempertahankan SVG dan nilai input saat berpindah keadaan. Validasi, konfirmasi, penyimpanan kata sandi, serta keluar dari akun setelah berhasil mengikuti perilaku yang tersedia. Pratinjau foto pada script bersama tetap berfungsi.

Pemeriksaan: **23 tes baru absensi/75 assertions lulus**, mencakup bulan independen dan lintas tahun, data satu siswa, larangan role lain/akun tidak aktif/tidak terhubung, perpindahan cabang serta ON/OFF, validasi pilihan, pendaftaran/kelas, sesi aktual, jadwal pengganti, keadaan belum dicatat dan pembacaan tanpa perubahan data. **146 tes regresi portal/pengaturan/sidebar dengan 725 assertions lulus**. Total **169 tes PHP/800 assertions**, ditambah **9 tes JavaScript** untuk pemilihan bulan, duplikasi/pilihan kosong, ikon kata sandi dan pratinjau foto. PHP lint, kompilasi seluruh Blade serta cache route berhasil.

Pemeriksaan HTTP dengan login dan database percobaan terpisah memverifikasi menu aktif, tiga pemilih tanpa tombol filter, tiga tabel bulan dengan status asli, urutan bulan pilihan, file CSS/JavaScript terbaru, tiga tombol SVG pada Pengaturan Siswa dan Teacher, penolakan akses Teacher ke absensi siswa, serta data bisnis identik sebelum/sesudah. Pemeriksaan visual browser Windows/Laragon/MySQL client belum dilakukan.

Paket `DPE_Update_v68_Absensi_Siswa_dan_Ikon_Mata.zip` berbasis source v67 terbaru yang diunggah pemilik berisi 14 file project, backup 10 file sebelumnya, manifest dan hasil pemeriksaan. Pemasangan membersihkan cache route serta Blade. Tidak diperlukan migration, reset database, perubahan .env/APP_KEY atau build aset. UAT: Ctrl+F5, login siswa lalu pilih Absensi Tiga Bulan, ubah masing-masing bulan dan periksa catatan/rekap. Ubah visibilitas cabang melalui Super Admin lalu muat ulang halaman siswa. Buka Pengaturan dan uji ketiga ikon mata.

## 69. Pertemuan tercatat pada dashboard siswa (v69)

Kartu **Program aktif** pada dashboard siswa diganti dengan **Pertemuan bulan ini**. Informasi program tetap dapat dilihat pada Profil Siswa. Dashboard tetap memiliki empat kartu dengan ukuran dan tipografi yang tersedia. Nilai baru mengikuti format **0/8, 1/8, 2/8**, dan seterusnya. Penyebut delapan menyatakan target normal bulanan pada indikator dashboard. Pertemuan tambahan tetap tercermin, misalnya 9/8 atau 10/8. Rekap absensi dan laporan bulanan tetap menghitung jumlah pertemuan sebenarnya sesuai aturan yang tersedia.

Pembilang menghitung pertemuan bulan berjalan Asia/Makassar yang sudah memiliki status Hadir, Terlambat, Tidak Hadir, Sakit atau Izin. Sumbernya adalah kontribusi absensi yang tersedia pada layanan portal. Pertemuan belum dicatat, status lama yang tidak dikenali/kosong, sesi mendatang, sesi dibatalkan, dan sesi asal yang sudah diganti jadwalnya tidak menambah angka. Pelaksanaan pengganti dapat dihitung setelah berlangsung dan statusnya dicatat. Koreksi atau pengosongan status langsung tercermin ketika dashboard dimuat ulang. Bulan mengikuti waktu sistem dan berganti otomatis; parameter bulan/siswa pada URL tidak mengubah identitas maupun periode kartu.

Visibilitas mengikuti cabang sesi yang sama dengan menu Absensi Tiga Bulan. Saat seluruh cabang yang terkait bulan tersebut OFF, kartu menampilkan tanda em dash beserta keterangan Absensi disembunyikan oleh cabang; angka tersembunyi tidak dikirim ke view. Jika ada campuran cabang ON/OFF, hanya kontribusi cabang ON yang dihitung dan keterangan menyebut Catatan cabang yang ditampilkan. Cabang ON tanpa catatan menampilkan 0/8. Sesi bulan berjalan dibaca sekali dan dipakai bersama oleh kartu serta Jadwal terdekat.

Pemeriksaan: **107 tes PHP terkait/550 assertions lulus** pada portal, absensi siswa dan sidebar. Lima tes tambahan menguji empat kartu/0/8, perkembangan 1/8 dan 2/8, seluruh status yang dihitung, koreksi, pengecualian sesi/status, tambahan pertemuan, pergantian bulan, visibilitas campuran/semua OFF, serta isolasi siswa/periode. PHP lint dan kompilasi seluruh Blade berhasil. Pemeriksaan HTTP dengan login fixture memverifikasi tepat empat kartu dan nilai **2/8** yang berasal dari dua catatan Oktober, tiga bulan pilihan tanpa filter, tabel absensi, ikon mata pada pengaturan siswa/teacher serta aset terbaru. Data bisnis fixture identik sebelum/sesudah seluruh GET. Pemeriksaan visual Windows/Laragon/MySQL pemilik belum dijalankan langsung.

Paket **DPE_Update_v69_Pertemuan_Dashboard_Siswa.zip** bersifat kumulatif: seluruh revisi v68 ikut disertakan, sehingga dapat dipasang langsung pada source v67 atau v68 yang sesuai hash. Paket memuat 15 file project, backup kedua basis, manifest dan hasil pemeriksaan. Installer mencadangkan file aktual, menerima hash basis yang tercantum dan memasang file baru jika belum tersedia. Tidak diperlukan migration, reset database, perubahan .env/APP_KEY atau build aset. UAT: pasang paket dan Ctrl+F5, buka dashboard siswa lalu catat absensi melalui Teacher dan muat ulang dashboard untuk memeriksa perubahan 1/8 menjadi 2/8; periksa kembali ON/OFF cabang.

## 70. Ikon mata pada password pengguna Super Admin (v70)

Form bersama **Tambah Pengguna** dan **Edit Pengguna** kini memiliki ikon mata di sisi kanan kolom Password serta Konfirmasi password. Keduanya memakai komponen SVG, CSS dan perilaku JavaScript yang sama dengan Pengaturan akun pada v68. Masing-masing kolom dapat ditampilkan atau disembunyikan secara terpisah; keadaan awal tetap password tersembunyi. Ruang kanan input disediakan agar tulisan tidak menimpa ikon. Nama aksesibel dan aria-pressed mengikuti keadaan tampil/sembunyi.

Kedua kontrol menggunakan type button sehingga klik ikon tidak mengirim form. Perpindahan tipe input mempertahankan nilai yang sedang diketik serta SVG. Aturan password wajib pada pembuatan akun, password opsional saat edit, konfirmasi, minimal delapan karakter, CSRF dan penyimpanan mengikuti alur yang tersedia. Script melewati tombol menu akun di header karena tombol tersebut tidak menunjuk input password.

Pemeriksaan: **13 tes pengelolaan pengguna/43 assertions** dan **5 tes JavaScript pengaturan akun** lulus. Seluruh Blade berhasil dikompilasi. Pemeriksaan HTTP dengan login Super Admin pada database fixture salinan memverifikasi dua tombol SVG pada setiap form, tombol tidak bertipe submit, target input yang sesuai, keadaan awal tersembunyi, atribut validasi pembuatan/edit, email opsional, CSRF serta metode update. Kedua tombol hasil render dijalankan dengan script nyata pada lingkungan DOM simulasi: tampil/sembunyi bekerja terpisah, nilai dan SVG dipertahankan, serta menu akun di header dilewati. CSS/JavaScript berversi disajikan sekali pada setiap halaman. Seluruh data bisnis fixture identik sebelum/sesudah GET. Pemeriksaan visual browser Windows/Laragon/MySQL pemilik belum dijalankan langsung; tidak menambah tes formal baru untuk perubahan tampilan ini.

Paket **DPE_Fix_v70_Ikon_Mata_Form_Pengguna.zip** berbasis v69 berisi dua file project (form bersama dan dokumen ini), dua backup, manifest serta hasil pemeriksaan. CSS/JavaScript bersama yang sudah tersedia pada v69 digunakan kembali. Installer memverifikasi hash, membuat backup source aktual dan membersihkan cache Blade. Tidak diperlukan migration atau build aset. UAT: pasang paket, Ctrl+F5, buka Pengguna > Tambah Pengguna, isi kedua kolom lalu klik ikon mata masing-masing dua kali; periksa nilai tidak berubah dan form baru tersimpan ketika tombol Simpan pengguna ditekan. Coba juga form Edit Pengguna dengan kedua password dikosongkan.

## 71. PDF dan WhatsApp semiotomatis laporan bulanan (v71)

Detail Laporan Bulanan pada Teacher, Admin Cabang dan Super Admin kini menyediakan **Preview PDF bulanan** serta **WhatsApp & riwayat**. Alur WhatsApp memakai tampilan, pilihan cara berbagi/sapaan, salam waktu cabang, salin pesan, pembukaan WhatsApp dan konfirmasi manual yang sama dengan rapor tiga bulan. Teacher pemilik menjadi pengirim utama; Super Admin dapat membantu dengan identitas operator tercatat. Admin Cabang dapat meninjau laporan/riwayat dalam cakupan cabangnya dan mengingat pilihan pratinjau sendiri, tetapi tidak dapat mempersiapkan atau mengoperasikan pengiriman. Pilihan disimpan per akun dan laporan bulanan selama sesi login, terpisah dari preferensi rapor tiga bulan.

Pengiriman dan preview PDF memerlukan persetujuan laporan bulan yang bersangkutan serta pembayaran bulan tersebut yang tervalidasi. Berbagi juga memeriksa satu penerima utama dengan nomor valid, Teacher aktif dan akun portal siswa aktif. Laporan dari dua bulan lainnya dapat tetap draf dan pembayarannya belum tersedia. Pilihan **Kirim tautan rapor** mengarah ke halaman bulanan milik siswa melalui login yang tersedia; metode ini tidak membuat atau mewajibkan PDF. Pilihan **Kirim file PDF** menyiapkan arsip PDF secara otomatis saat Siapkan pesan ditekan, lalu menyediakan Unduh PDF dan Buka WhatsApp. Teacher melampirkan PDF dan menekan Kirim di WhatsApp secara manual. Konfirmasi di website mensyaratkan pembukaan WhatsApp pada record yang sama dan, untuk PDF, unduhan file pada record tersebut. Status merupakan konfirmasi petugas, bukan tanda diterima/dibaca dari WhatsApp.

PDF menggunakan mesin, view, kop, logo proporsional, font, tabel biru, footer dan kedua kolom tanda tangan yang sama dengan PDF tiga bulan. **Learning Period hanya satu bulan** dari report_month laporan, misalnya October 2026; waktu pembuatan atau pengiriman tidak menggantinya. Isi penilaian memakai delapan kategori bulanan beserta status **Improving, Stable atau Needs Improvement** yang sudah tersimpan pada persetujuan. Kolom ini diberi judul Progress; rubric A/B/C rapor tiga bulan tidak ditambahkan pada penilaian bulanan. Ringkasan perkembangan, Challenges, Teacher's Message dan tautan video memakai konten publik yang telah disetujui. Catatan internal tidak dimasukkan ke PDF. Tautan video dapat diklik; video tidak ditanam sebagai media di dalam PDF. Deskripsi panjang dibagi menjadi lanjutan baris tabel, label Confidence/Participation membungkus pada pemisahnya, serta judul bagian bulanan dijaga bersama awal paragraf.

Konten penilaian diambil dari salinan persetujuan yang tersedia, sehingga laporan lama yang sudah disetujui dengan snapshot versi 2 dapat langsung dipakai. Kop dan gambar tanda tangan yang berlaku saat Siapkan pesan dimasukkan ke salinan dokumen PDF, lengkap dengan nama, kurung, rasio gambar serta transparansi PNG. Rekap absensi mengikuti visibilitas cabang dan data terkini pada saat PDF disiapkan; OFF menghasilkan attendance null tanpa hitungan tersembunyi. File menyimpan salinan publik, hash, nomor versi, pembuat dan waktu pembuatan. Persiapan ulang dengan sumber sama memakai file valid yang sama. Perubahan template, tanda tangan, absensi atau visibilitas menghasilkan sumber berbeda dan file baru ketika pesan PDF berikutnya disiapkan. Arsip sebelumnya tetap disimpan. Ini berbeda dari tanda tangan rapor tiga bulan yang tetap memakai salinan persetujuan finalnya.

Dua tabel baru, monthly_report_files dan monthly_report_whatsapp_shares, memisahkan arsip dan riwayat bulanan dari rapor tiga bulan. File fisik berada pada folder privat monthly-report-pdfs. Setiap pembukaan, unduhan dan konfirmasi memeriksa kembali akun/cakupan, pemilik record, penerima, persetujuan, pembayaran, sumber dokumen serta integritas bytes PDF. Perubahan sumber menolak tindakan dengan pesan untuk menyiapkan ulang; OFF setelah persiapan turut memblokir unduhan PDF lama melalui pengiriman tersebut. Pesan yang sudah disiapkan tetap tersimpan persis untuk riwayat. Tindakan buka/unduh kedaluwarsa setelah 30 menit, sementara pengiriman yang telah dilakukan tetap dapat dikonfirmasi bila sumber masih sesuai. Kunci permintaan unik mencegah duplikasi; kegagalan pembuatan record membersihkan file baru dan membatalkan penulisan database. Foreign key membatasi penghapusan arsip; rollback migration ditolak jika tabel berisi riwayat. Endpoint baru menggunakan autentikasi, role, CSRF, pembatasan frekuensi untuk mutasi, dan respons private/no-store.

Pemeriksaan akhir: **369 tes PHP/2.191 assertions lulus**, mencakup **34 tes baru pengiriman bulanan/216 assertions**, WhatsApp tiga bulan, lifecycle kedua laporan, tanda tangan, portal siswa dan absensi. **9 tes JavaScript** yang tersedia lulus. PHP lint, kompilasi seluruh Blade dan cache route berhasil. Migration baru dijalankan pada database SQLite percobaan dan pemasangan ulang kedua path menghasilkan Nothing to migrate. Perbandingan hash memastikan 17 file project berubah/ditambahkan dan tidak ada file source dihapus.

HTTP dengan login nyata pada database percobaan terpisah memverifikasi CSRF 419 tanpa token, ingatan pilihan PDF/Wali, preview dan unduhan PDF privat, penerima/pesan yang tepat pada redirect WhatsApp, konfirmasi PDF dan tautan, pembatasan Admin Cabang, tautan laporan melalui akun siswa, serta satu file arsip yang tidak berulang. Semua GET mempertahankan data bisnis; selama mutasi hanya tabel arsip/berbagi serta metadata file yang berubah. Tidak ada permintaan jaringan ke WhatsApp atau pesan nyata dikirim. **Enam PDF/21 halaman** dirender dengan Poppler dan diperiksa memakai MuPDF, termasuk absensi ON/OFF, deskripsi/nama panjang, tanda tangan PNG transparan, PDF unduhan HTTP dan pembanding tiga bulan. Learning Period, batas teks, pengulangan kop, tautan video, kedua tanda tangan dan rasio gambar sesuai; PNG terakhir ditinjau visual. Browser Windows, PowerShell dan MySQL Laragon pemilik belum dijalankan langsung.

Paket **DPE_Update_v71_PDF_dan_WhatsApp_Bulanan.zip** berbasis v70 berisi 17 file project, delapan backup, manifest dan hasil pemeriksaan. Installer memverifikasi hash, mencadangkan source aktual, mengaktifkan maintenance sementara bila aplikasi sebelumnya aktif, menjalankan hanya dua migration baru, lalu membersihkan cache route dan Blade. Jika gagal, source dipulihkan dan tabel yang sudah dibuat dipertahankan. Tidak diperlukan build aset, seeder, reset database atau perubahan .env/APP_KEY. UAT: pasang paket, Ctrl+F5, Teacher > Laporan Bulanan > detail laporan yang telah disetujui/dibayar. Periksa Preview PDF dan Learning Period satu bulan. Buka WhatsApp & riwayat, pilih PDF/sapaan, Perbarui pratinjau, Siapkan pesan, Unduh PDF, Buka WhatsApp, lampirkan/kirim secara manual lalu konfirmasikan. Ulangi dengan metode tautan serta periksa riwayat Admin dan ON/OFF absensi cabang.

## 72. Perbaikan jarak nama siswa pada PDF (v72)

Kolom label Student's Name sebelumnya memakai lebar tetap 110 pt sehingga nama siswa tampak berjarak terlalu jauh dari tanda titik dua. Renderer bersama kini mengukur lebar label dengan font tebal dan ukuran aktual, kemudian menambahkan jarak 4 pt. Lebar pembungkusan nama dihitung dari ruang yang tersisa; baris lanjutannya tetap sejajar dengan awal nama. Data nama siswa tidak diubah. Perbaikan berlaku pada preview dan hasil render PDF bulanan maupun tiga bulan.

Snapshot PDF bulanan menyertakan revisi tata letak dari renderer. Persiapan pesan PDF setelah pembaruan menghasilkan versi file baru, kemudian persiapan dengan sumber sama memakai versi baru tersebut. File sebelumnya tetap valid sebagai arsip dengan bytes yang sama. Pesan yang disiapkan sebelum pembaruan perlu disiapkan ulang agar sumber dan file sesuai. PDF tiga bulan yang sudah tersimpan diperbarui melalui Buat ulang PDF; preview memakai renderer terbaru.

Pemeriksaan: 41 tes PHP terkait pengiriman bulanan, render tiga bulan dan tanda tangan lulus (277 assertions). Empat PDF/11 halaman dirender dengan Poppler dan diperiksa visual serta koordinat teks. Jarak label–nama turun dari 13,52 pt menjadi 4 pt pada kedua jenis PDF. Nama normal, nama Unicode panjang dan nama tanpa spasi tampil lengkap, tidak melampaui batas, dan baris lanjutan sejajar. Periode, kop setiap halaman serta area tanda tangan tetap sesuai. Pemeriksaan arsip pada database SQLite percobaan membuktikan pergantian versi, pemakaian ulang versi baru dan pelestarian bytes arsip lama. PHP lint dan kompilasi seluruh Blade berhasil. PowerShell dan MySQL Laragon pemilik belum dijalankan langsung.

Paket DPE_Fix_v72_Jarak_Nama_Siswa_PDF.zip berbasis v71 berisi empat file project, backup v71, manifest, installer PowerShell dan hasil pemeriksaan. Installer memverifikasi isi serta basis source, mencadangkan file aktual dan menjalankan view:clear; kegagalan memulihkan source sebelumnya. Tidak ada migration atau perubahan konfigurasi. Setelah pemasangan, Ctrl+F5 lalu buka Preview PDF; siapkan ulang pesan PDF bulanan untuk unduhan terbaru atau gunakan Buat ulang PDF untuk arsip tiga bulan.

## 73. Filter Kelompok Kelas menurut peran (v73)

Menu Kelompok Kelas Super Admin kini menyediakan filter Cabang, Kelompok, Program dan Teacher. Admin Cabang memakai filter Kelompok, Program dan Teacher tanpa kontrol pilihan Cabang. Pencarian kode/nama serta status Aktif/Nonaktif tetap tersedia. Filter memakai metode GET, dapat digabungkan dengan hubungan AND, menampilkan jumlah hasil dan mempertahankan pilihan saat berpindah halaman. Tombol Tampilkan menerapkan pilihan; Reset membuka daftar dalam cakupan semula. Hasil kosong memiliki penjelasan dan tautan Reset filter.

Kedua controller memakai layanan daftar bersama. Cakupan dasar tetap BranchOperationsService: Super Admin dapat melihat seluruh cabang, sedangkan Admin hanya cabang penugasan yang berlaku. Menyembunyikan kontrol Cabang tidak menghapus pemeriksaan server; ID cabang, kelompok, program atau Teacher dari luar cakupan Admin ditolak. Pilihan kelompok/program/Teacher diambil dari kelompok dalam cakupan tersebut, termasuk relasi historis yang sudah nonaktif agar arsip tetap dapat dicari. Filter Teacher mengacu pada Teacher penanggung jawab pada kolom daftar. Pilihan tetap tersedia ketika gabungan filter menghasilkan nol baris sehingga pengguna dapat memperbaiki pilihan. Halaman anggota kelompok dan jadwal/sesi mempertahankan filter sebelumnya.

Panel menggunakan CSS khusus halaman dengan grid tiga/dua/satu kolom, jarak field 20 px, jarak tombol 12 px dan tinggi tombol 50 px. Semua kontrol berlabel dan ID unik. Tidak ada dependensi JavaScript atau build aset baru.

Pemeriksaan: 104 tes PHP/421 assertions lulus, mencakup filter individu/gabungan, batas akses, query tidak valid, pencarian angka 0, arsip nonaktif, pagination, serta pengelolaan kelas dan operasi Admin sebelumnya. Satu tes lama untuk hasil status diperbarui agar memeriksa baris paginator karena nama kelompok kini juga tersedia pada dropdown filter. Lima file PHP lolos lint dan seluruh Blade berhasil dikompilasi. HTTP dengan login nyata pada database percobaan terpisah memeriksa kedua role, pilihan terpilih, dua halaman hasil, Reset, keadaan kosong, penolakan empat ID luar cakupan dan penolakan akses Teacher. Data bisnis serta database fixture asal identik sebelum/sesudah seluruh GET. Enam HTML disimpan untuk pemeriksaan; label, ID, aset dan aturan CSS pada tujuh lebar 360–1920 px diperiksa (14 kombinasi). Browser piksel, PowerShell Windows dan MySQL Laragon pemilik belum dijalankan langsung.

Paket DPE_Update_v73_Filter_Kelompok_Kelas.zip berbasis v72 memuat sembilan file project, lima backup, manifest, installer dan hasil pemeriksaan. Installer memverifikasi source, mencadangkan file aktual, memasang file baru dan menjalankan view:clear; kegagalan memulihkan source sebelumnya. Tidak diperlukan migration atau perubahan konfigurasi. UAT: pasang v73, Ctrl+F5, buka Kelompok Kelas pada Super Admin lalu coba empat filter dan gabungannya; ulangi pada Admin dan pastikan kontrol Cabang tidak ada, pilihan hanya data cabangnya, pagination mempertahankan pilihan dan Reset mengembalikan daftar.

## 74. Hubungan siswa dan Teacher pada direktori Admin (v74)

Data Siswa Admin kini memiliki kolom Teacher pengajar. Nama pengajar ditampilkan per baris, berasal dari hubungan mengajar yang masih berlaku pada hari berjalan di zona waktu aplikasi. Data Guru Admin memiliki kolom Siswa yang diajar, menampilkan jumlah siswa unik, maksimal tiga nama menurut abjad dan tombol Lihat semua. Tombol membuka bagian Siswa yang diajar di Detail Guru melalui anchor teacher-students. Daftar lengkap memakai pagination 15 baris, mempertahankan anchor saat berpindah halaman dan menyediakan tombol Detail siswa. Keadaan tanpa hubungan aktif dijelaskan pada masing-masing tabel.

Layanan TeachingDirectoryService menjadi sumber kedua arah. Penugasan siswa–Teacher aktif dimasukkan tanpa mensyaratkan kepemilikan rapor. Keanggotaan kelompok harus aktif, kelompok aktif, serta pendaftaran siswa sesuai cabang/program kelompok dan masih berlaku. Siswa harus aktif dan sudah mulai belajar; Teacher serta akun Teacher harus aktif. Tanggal mulai dan berakhir termasuk hari batasnya. Duplikasi dari beberapa kelompok, beberapa penugasan atau gabungan keduanya digabung menurut ID siswa/Teacher. Pengiriman rapor, sesi sesaat dan riwayat lama tidak menambah hubungan mengajar aktif pada direktori ini.

Semua pasangan dibatasi cabang yang dapat dikelola Admin. Penugasan langsung tidak memiliki kolom cabang: jika Teacher mempunyai penugasan cabang aktif, pendaftaran siswa harus berada pada cabang yang sama. Jika Teacher tidak memiliki penugasan cabang aktif, penugasan langsung menggunakan pendaftaran siswa sebagai acuan, sesuai dukungan tutor private. Kelompok memiliki cabang eksplisit sehingga dapat menunjukkan hubungan mengajar walaupun Teacher belum memiliki penugasan cabang tersendiri. Pendaftaran yang ditampilkan pada Data Siswa serta Detail Guru dan riwayat cabang pada direktori Guru dibatasi cakupan Admin. Super Admin yang membuka direktori Admin tetap dapat melihat seluruh cabang.

Teacher yang benar-benar mengajar siswa dalam cakupan Admin ikut terlihat pada Data Guru dan dapat dibuka melalui Detail. Hak baca ini tidak memberikan hak edit baru: edit profil tetap memerlukan penugasan cabang aktif sesuai TeacherPolicy. Tombol edit disembunyikan pada profil yang hanya mempunyai hubungan mengajar; URL edit dan penyimpanan tetap ditolak. Siswa/Teacher di luar cakupan dan role lain tetap ditolak. Daftar nama dan ringkasan mengambil satu query gabungan per kumpulan ID halaman, tanpa query relasi per baris. CSS khusus mempertahankan nama per baris, jarak 6–8 px, tombol dan teks rata tengah serta pembungkusan nama panjang.

Pemeriksaan: 70 tes PHP/241 assertions lulus, termasuk 23 tes relasi baru serta 47 tes pengelolaan Guru/Siswa sebelumnya. Dua fixture schema lama dilengkapi tabel relasi kosong untuk pengujian fitur baru; database aplikasi tidak diubah. Kasus mencakup penugasan langsung tanpa pemilik rapor, kelompok saja, tutor private, siswa ganda, Teacher/siswa nonaktif, masa penugasan, batas tanggal, cakupan beberapa cabang, izin baca/edit dan escaping teks. Delapan file PHP lolos lint dan seluruh Blade berhasil dikompilasi. HTTP dengan login nyata empat role memeriksa kolom pengajar, hitungan 18 siswa unik, tiga nama ringkas, halaman daftar 15+3 siswa, tombol Detail siswa, Teacher melalui kelompok, keadaan kosong, isolasi cabang dan akses Super Admin. Delapan HTML diperiksa, semua GET mempertahankan data bisnis dan database fixture asal tetap sama. Pengukuran kumpulan relasi menghasilkan satu query untuk ringkasan Teacher dan satu query untuk nama pengajar siswa. Browser piksel, PowerShell Windows dan MySQL Laragon pemilik belum dijalankan langsung.

Paket DPE_Update_v74_Relasi_Siswa_Teacher_Admin.zip berbasis v73 berisi 14 file project, sembilan backup, manifest, installer dan hasil pemeriksaan. Installer memverifikasi basis, mencadangkan file aktual, memasang file baru dan menjalankan view:clear; kegagalan memulihkan source sebelumnya. Tidak diperlukan migration, build aset atau perubahan konfigurasi. UAT: pasang v74, Ctrl+F5, buka Data Siswa dan cocokkan kolom Teacher dengan penugasan/kelompok aktif; buka Data Guru, cocokkan jumlah dan tiga nama, klik Lihat semua lalu buka Detail siswa. Ulangi dengan siswa beberapa kelompok dan pastikan hanya dihitung sekali.


## 75. Akun siswa dan pendaftaran cabang otomatis oleh Admin (v75)

Sidebar Admin Cabang memperoleh menu **Pengguna**. Menu ini menyediakan daftar akun siswa, Tambah pengguna dan Detail akun. Akun yang terlihat mempunyai pendaftaran berjalan atau akan datang di cabang aktif yang saat ini dikelola Admin; setiap halaman berisi 15 akun. Akun tanpa profil, siswa cabang lain dan pendaftaran yang telah berakhir tidak memberikan akses ke detail akun. Pendaftaran dan riwayat cabang lain tidak disertakan pada tabel maupun detail menu baru. Pengelolaan role, pengubahan akun dan status melalui menu Pengguna Super Admin mengikuti alur yang tersedia.

Form tambah pengguna memuat nama lengkap siswa, username, email opsional, password dan konfirmasi dengan ikon mata, program belajar aktif, tanggal mulai belajar/pendaftaran, serta sekolah dan kelas opsional. Nama siswa digunakan untuk akun dan profil anak. Role selalu Siswa / Parent; akun baru langsung aktif. Untuk Admin dengan satu cabang aktif, cabang ditampilkan otomatis dan tidak dapat dipilih bebas. Apabila data penugasan lama memiliki beberapa cabang aktif sekaligus, Admin wajib memilih salah satu cabangnya agar sistem tidak menebak cabang. Tanpa penugasan cabang aktif atau tanpa program aktif, form menjelaskan prasyarat dan tombol simpan dinonaktifkan. Backend tetap menolak penyimpanan yang tidak memenuhi prasyarat.

Satu kali simpan membuat User student, profil Student aktif dan StudentEnrollment pada cabang Admin, program pilihan dan tanggal mulai yang sama. Enrollment tidak mempunyai tanggal akhir saat dibuat. Ketiganya berada dalam satu transaksi: kegagalan profil atau pendaftaran membatalkan akun juga. Admin tidak perlu membuat profil kedua maupun menghubungkan cabang lagi melalui Super Admin. Penugasan Teacher, kelompok kelas, kontak wali dan foto dapat dilengkapi melalui fitur operasional yang sudah tersedia. Pendaftaran dengan tanggal mulai hari ini atau sebelumnya langsung tersedia pada Data Siswa; tanggal mendatang tetap terdaftar dan terlihat pada Pengguna dengan status Akan dimulai, kemudian tersedia pada Data Siswa saat mulai. Detail akun menyajikan tautan profil hanya ketika StudentPolicy mengizinkannya.

Route khusus menu ini memerlukan akun Admin aktif, autentikasi, perlindungan sesi dan CSRF. Role ditetapkan server sebagai student; permintaan yang mencoba membuat role lain ditolak. ID akun/profil yang disisipkan tidak dapat menghubungkan akun lama. Service membaca ulang akun, penugasan cabang dan program di dalam transaksi dengan penguncian, sehingga form lama tidak dapat menyimpan ke cabang yang sudah tidak dikelola atau memakai program nonaktif. Password disimpan sebagai hash, tidak ditampilkan pada detail dan tidak diisi ulang ketika validasi gagal. Email kosong disimpan null; username dan email tetap harus unik. Respons menu memakai private/no-store.

Pemeriksaan: **124 tes PHP / 620 assertions lulus**, terdiri dari 34 tes baru pengelolaan pengguna siswa (257 assertions) serta 90 tes regresi onboarding/pengguna Super Admin, pengelolaan Guru/Siswa Admin dan direktori mengajar (363 assertions). Kasus baru mencakup pembuatan tiga record, login siswa dan profil cabang, email opsional, ikon mata, larangan role lain, admin nonaktif/tamu, cabang asing, penugasan kedaluwarsa/mendatang/berubah, program nonaktif, beberapa cabang, input rusak, password tidak sama, duplikasi, pengembalian transaksi saat layanan profil/enrollment gagal, akses akun, tanggal mendatang, escaping dan pembacaan tanpa perubahan data. Lima file PHP lolos lint, seluruh Blade berhasil dikompilasi, dan route berhasil dicache lalu dibersihkan.

Pengujian HTTP dengan CSRF aktif memakai salinan database fixture terpisah dan lima login. Penyimpanan menambahkan tepat satu akun, satu profil dan satu enrollment pada cabang Admin. Data Siswa dan profil portal siswa baru menampilkan hubungan cabang/program yang benar. Pemeriksaan juga mencakup enam HTML, aset ikon password, penolakan CSRF 419, pemalsuan role/cabang, konfirmasi password, duplikasi dan pembatasan role/detail. GET tidak mengubah data bisnis; database fixture asal tetap identik. Browser piksel, PowerShell Windows dan MySQL Laragon pemilik belum diuji langsung.

Paket DPE_Update_v75_Pengguna_Siswa_Admin.zip berbasis v74 berisi 10 file project, tiga backup, manifest, installer dan hasil pemeriksaan. Installer memverifikasi source, mencadangkan file aktual, memasang file dan membersihkan cache route serta Blade. Kegagalan pemasangan memulihkan file sebelumnya. Tidak diperlukan migration, build aset, perubahan .env/APP_KEY atau reset data. UAT: pasang paket, Ctrl+F5, login Admin lalu Pengguna > Tambah pengguna. Periksa cabang otomatis, pilih program dan tanggal mulai hari ini, simpan tanpa email, cek Detail dan Data Siswa, lalu login dengan akun siswa baru. Ulangi pemeriksaan dari Admin cabang lain untuk memastikan akun tidak terlihat di luar cabangnya.


## 76. Nama dan waktu pemberi persetujuan (v76)

Daftar Laporan Bulanan dan Rapor Tiga Bulan kini menampilkan nama pemberi persetujuan, label Admin Cabang atau Super Admin, serta tanggal/jam di bawah badge Disetujui. Tampilan bersama berlaku untuk Teacher, Admin dan Super Admin dalam cakupan akses yang sudah tersedia. Ringkasan status pada detail Rapor Tiga Bulan memperoleh informasi yang sama; pemberi persetujuan pada detail laporan bulanan ditampilkan menggunakan komponen yang seragam. Riwayat absensi pada akun Teacher dan tabel Kehadiran Guru pada Admin/Super Admin juga menampilkan pemberi persetujuan serta waktu pemeriksaan di bawah status Disetujui. Detail pemeriksaan kehadiran yang sudah tersedia tetap berfungsi.

Identitas berasal dari approved_by_user_id / approved_at milik laporan dan reviewed_by_user_id / reviewed_at milik kehadiran, bukan akun yang sedang membaca atau pembuat PDF. Nama menggunakan full_name dengan username sebagai cadangan. Akun petugas yang sudah nonaktif tetap dapat diidentifikasi melalui relasi data historis. Jika nama atau waktu belum tercatat pada data lama, tampilan menjelaskan Nama belum tersedia atau Waktu persetujuan belum tersedia; sistem tidak menebak identitas. Nama dan label role mengikuti data akun petugas yang tersedia saat halaman dibuka; riwayat tindakan dan salinan isi/PDF tidak diubah oleh pembaruan tampilan ini.

Metadata hanya ditampilkan pada persetujuan yang masih berstatus approved. Setelah rapor dibuka untuk revisi, metadata persetujuan saat ini tidak lagi ditampilkan. Persetujuan berikutnya mengikuti akun peninjau dan waktu terbaru yang dicatat workflow. Riwayat persetujuan sebelumnya tetap dapat diperiksa. Tanggal ditampilkan dalam bahasa Indonesia dan zona waktu cabang dengan penanda zona, misalnya WITA atau WIB, serta atribut time datetime ber-offset. Zona cabang lama yang tidak valid menggunakan zona aplikasi sebagai cadangan. CSS khusus membungkus nama panjang, memberi jarak empat piksel antarbaris dan delapan piksel dari badge, serta dimuat satu kali per halaman melalui komponen Blade.

Daftar laporan memuat relasi approvedBy sekaligus; riwayat kehadiran Teacher memuat reviewedBy sekaligus, sehingga tidak memerlukan query pengguna per baris. Tabel Admin Kehadiran Guru sudah memuat relasi pemeriksa. Semua pembatasan role, kepemilikan laporan dan cabang dipertahankan; tidak ada endpoint baru, perubahan hak menyetujui, migration maupun penambahan informasi pemeriksa pada PDF atau portal siswa.

Pemeriksaan: **263 tes PHP / 1.925 assertions lulus**. Lima belas tes baru / 359 assertions memeriksa enam kombinasi pembaca dan pemberi persetujuan pada daftar/detail kedua laporan; identitas dari login saat approval meski payload memalsukan akun/waktu; tiga pembaca daftar kehadiran; status belum disetujui dengan kolom lama tertinggal; data historis kosong; peninjau nonaktif/username cadangan; zona WITA/WIB dan offset; revisi lalu persetujuan petugas baru; larangan akses Admin/Teacher lain dan siswa/tamu; escaping serta GET tanpa perubahan data. Sebanyak 248 tes regresi / 1.566 assertions memeriksa siklus laporan bulanan/tiga bulan, kehadiran, PDF bulanan dan pengiriman WhatsApp. Empat file PHP lolos lint; seluruh Blade berhasil dikompilasi dan hasil PHP kompilasinya lolos pemeriksaan sintaks.

HTTP dengan empat login nyata dan salinan database fixture terpisah memverifikasi 15 HTML: daftar/detail laporan bulanan dan tiga bulan pada Teacher/Admin/Super Admin serta tiga daftar kehadiran. Nama pemeriksa, role, offset waktu +08:00, satu stylesheet per halaman, akses siswa ditolak dan menu Pengguna v75 tetap berfungsi. Semua GET mempertahankan data bisnis, database fixture asal tetap identik. Browser piksel, PowerShell Windows dan MySQL Laragon client belum dijalankan langsung.

Paket DPE_Update_v76_Nama_Pemberi_Persetujuan.zip berbasis v75 berisi 13 file project, sepuluh backup, manifest, installer dan hasil pemeriksaan. Installer memverifikasi basis, mencadangkan file aktual, memasang pembaruan dan membersihkan cache Blade; kegagalan memulihkan file sebelumnya. Tidak perlu migration, perubahan .env/APP_KEY, build aset, persetujuan ulang atau pembuatan ulang PDF untuk menampilkan informasi yang sudah tersimpan. UAT: pasang v76 dan Ctrl+F5. Pada daftar laporan bulanan Admin, pilih status Disetujui atau Semua status; cocokkan nama/waktu dengan petugas yang sebelumnya menyetujui. Periksa detail dan daftar rapor tiga bulan, akun Teacher serta Kehadiran Guru dengan filter Disetujui. Pastikan informasi yang sama terlihat bagi pengguna yang berbeda dalam cakupan akses yang sah.


## 77. Font biasa pada informasi pemberi persetujuan (v77)

Nama pemberi persetujuan pada komponen bersama ditampilkan sebagai teks biasa, sama dengan kalimat Disetujui oleh, role dan waktu. Ukuran metadata tetap 12 px dengan bobot normal 400. Markup strong pada nama dihapus. Aturan CSS tambahan yang khusus berada di dalam metadata persetujuan memastikan elemen strong pada markup lama juga mengikuti ukuran induk dan bobot 400, dengan spesifisitas lebih tinggi daripada aturan umum nama pada tabel dashboard yang memakai 16 px. Nama siswa, judul, badge status serta bagian tabel lainnya mengikuti tampilan yang sudah tersedia.

Komponen yang sama digunakan pada laporan bulanan, rapor tiga bulan dan kehadiran Teacher, sehingga perubahan berlaku pada seluruh lokasi informasi persetujuan v76. Identitas, role, waktu, zona cabang, riwayat dan aturan persetujuan mengikuti catatan sistem yang tersedia. Tidak ada perubahan data ataupun endpoint.

Pemeriksaan: 15 tes persetujuan yang sudah tersedia dengan 359 assertions lulus. HTTP empat login memeriksa 15 HTML pada ketiga role dan memastikan metadata tidak memuat strong/b, hanya satu stylesheet per halaman, serta stylesheet memuat ukuran 12 px dan bobot 400. GET tidak mengubah data bisnis dan database fixture asal tetap sama. Pemeriksaan ini memvalidasi HTML dan CSS; browser piksel, Windows PowerShell serta MySQL Laragon client belum dijalankan langsung.

Paket DPE_Update_v77_Font_Persetujuan_Normal.zip berbasis v76 memuat tiga file project, tiga backup, manifest, installer dan petunjuk. Installer memverifikasi basis, mencadangkan source aktual, memasang perubahan dan membersihkan cache Blade. Tidak diperlukan migration, build aset atau pembuatan PDF ulang. Setelah pemasangan, tekan Ctrl+F5 lalu periksa nama pemeriksa pada kolom Status; nama harus berukuran sama dengan teks Disetujui oleh dan tanpa cetak tebal.


## 78. Informasi persetujuan rata tengah (v78)

Teks Disetujui oleh, nama pemeriksa, role serta waktu pada komponen persetujuan diratakan ke tengah. Margin kiri dan kanan otomatis menjaga posisi blok tetap di tengah ketika lebar maksimum berlaku. Font tetap normal 12 px dengan bobot 400 sebagaimana v77. Perubahan berlaku pada daftar/detail laporan dan kehadiran yang memakai komponen yang sama. Tidak ada perubahan data, workflow, PDF atau hak akses.

Pemeriksaan terbatas pada CSS: text-align center, margin kiri/kanan otomatis serta ukuran dan bobot font dipertahankan. Tidak ditambahkan tes baru untuk perubahan tata letak kecil ini; 15 tes fungsi persetujuan / 359 assertions dan pemeriksaan HTML pada v77 sudah lulus sebelum perubahan alignment. Browser piksel dan PowerShell Windows belum dijalankan langsung.

Paket DPE_Update_v78_Persetujuan_Center.zip dapat dipasang setelah v76 atau v77 dan sudah mencakup revisi font normal v77. Paket memuat tiga file project, tiga backup referensi, manifest dan installer. Installer mencadangkan file aktual, memverifikasi source, memasang pembaruan dan membersihkan cache Blade. Setelah berhasil, tekan Ctrl+F5. Tidak diperlukan migration atau pembuatan PDF ulang.


## 79. Seri jadwal mingguan dan jadwal Teacher per minggu (v79)

Admin cabang dan Super Admin dapat memilih Sekali pertemuan atau Berulang mingguan melalui Tambah sesi. Mode mingguan memakai satu kelompok kelas, cabang, program, Teacher, lokasi dan pasangan jam untuk satu seri. Admin hanya dapat mengelola cabang dalam penugasannya; Super Admin dapat mengelola semua cabang. Pilih tanggal awal/akhir maksimal tiga bulan kalender, hari berulang (boleh lebih dari satu) dan jam mulai/selesai pada hari yang sama. Kalender menentukan tanggal berdasarkan hari, sehingga pergantian bulan maupun tahun tetap mengikuti hari yang dipilih. Kelas lain atau jam lain dibuat sebagai seri tersendiri; satu Teacher boleh mengajar beberapa kelas pada satu hari selama waktunya tidak bertabrakan.

Pratinjau menampilkan tanggal, jam, jumlah pertemuan dan masalah per tanggal. Tanggal libur atau tanggal lain dapat dicentang Kecualikan, lalu pratinjau diperbarui. Tahap ini belum membuat sesi. Semua tanggal yang dipilih harus lolos pemeriksaan agar penyimpanan berjalan; minimal satu tanggal harus tersisa. Pemeriksaan mencakup waktu mendatang, keaktifan referensi, kecocokan kelompok/cabang/program, tanggung jawab Teacher pada setiap tanggal, sesi Teacher yang bertabrakan lintas cabang, bentrok kelompok meskipun Teacher berbeda, serta slot ketersediaan yang telah diisi. Slot manual tetap opsional; tanggal tanpa slot tidak otomatis diblokir. Jam yang tepat bersebelahan diperbolehkan. Sesi dibatalkan dan sumber yang telah memiliki pengganti aktif tidak memblokir jadwal baru. Tanggal libur nasional tidak dikecualikan otomatis.

Penyimpanan membuat satu LessonSession terpisah per tanggal dan menghubungkannya ke lesson_schedule_series. Setiap sesi mempunyai ID, peserta, absensi dan catatan belajar sendiri. Pengecualian tidak membuat sesi kosong. Form pratinjau memakai token terenkripsi yang mengikat konfigurasi, akun, operasi dan batas satu jam; nilai Teacher, cabang atau jam yang disisipkan di POST simpan tidak mengganti konfigurasi yang ditinjau. Tanggung jawab dan konflik diperiksa kembali dalam transaksi saat penyimpanan. Request key unik mencegah pengiriman ulang form yang sama membuat sesi ganda. Kegagalan pada salah satu tanggal membatalkan seluruh transaksi, termasuk jika kegagalan terjadi setelah sesi pertama ditulis.

Detail sesi menyediakan tautan Lihat seri jadwal. Detail seri menampilkan semua pertemuan, 30 per halaman, dengan pilihan Pertemuan ini saja dan Ini dan berikutnya. Edit satu pertemuan mempertahankan cabang/program/kelompok seri dan tidak mengubah sesi lainnya. Perubahan massal dimulai dari sesi acuan yang masih mendatang, terjadwal dan belum memiliki catatan. Teacher, jam dan catatan awal dapat diganti, atau sesi yang masih memenuhi syarat dibatalkan. Menggeser tanggal acuan menggeser tanggal berikutnya dengan selisih hari yang sama. Jumlah sesi tetap dan pengecualian yang telah dibuat tetap diikuti; pengubahan ini tidak menambah tanggal baru atau memperpanjang seri secara otomatis.

Pertemuan sebelum acuan tetap tersimpan. Pertemuan yang sudah dimulai/berlalu, berstatus selain Terjadwal, mempunyai waktu aktual, absensi Teacher/siswa, catatan belajar individual/riwayat pembelajaran, challenge, pesan Teacher atau hubungan jadwal pengganti dipertahankan. Pratinjau menampilkan jadwal asli pada baris yang dipertahankan. Bentrok antartanggal usulan dan terhadap sesi yang dipertahankan tetap diperiksa. Perubahan data sejak pratinjau menolak penyimpanan dan menampilkan pratinjau terbaru untuk ditinjau kembali. ID sesi serta tanggal identitas occurrence tetap dipertahankan ketika jadwal dipindahkan. Riwayat perubahan massal menyimpan actor, waktu, dan snapshot sebelum/sesudah; halaman menampilkan petugas, waktu dan jumlah pertemuan yang diubah. Pembatalan massal hanya mengubah status pada sesi yang memenuhi syarat, tanpa mengganti jam, Teacher ataupun catatan.

Jadwal Mengajar Teacher secara bawaan menampilkan Senin–Minggu pada minggu berjalan, dikelompokkan per tanggal dan diurutkan menurut waktu pelaksanaan/rencana. Tersedia pintasan Hari ini, Minggu ini dan Bulan ini, serta rentang tanggal untuk melihat seluruh tiga bulan dengan pagination 12 sesi. Cakupan tetap sesi milik Teacher yang sedang masuk. Tampilan siswa, slot ketersediaan dan daftar siswa binaan mempertahankan periode bawaan sebelumnya.

Migration 2026_10_23_010000_create_lesson_schedule_series.php menambah dua tabel seri/riwayat serta dua kolom nullable pada lesson_sessions, tanpa mengganti sesi lama. Charset/collation tabel baru mengikuti basis MySQL proyek, utf8mb4/utf8mb4_0900_ai_ci. Kombinasi seri dan tanggal occurrence unik. Rollback schema ditolak jika sudah terdapat seri atau riwayat, agar data tidak dihapus. Installer menjalankan migration ini saja, membersihkan cache route/Blade dan mencadangkan file aktual di samping project. Bila pemasangan gagal, source dipulihkan; schema tambahan yang sudah dibuat tidak dihapus otomatis. Paket berbasis v78, tanpa dependency baru, perubahan .env atau reset database. ZIP dipilih melalui blok PowerShell, diekstrak sementara di luar project lalu dibersihkan.

Pemeriksaan mencakup tes baru jadwal berulang dan regresi operasi cabang, jadwal/slot Teacher, pengelolaan sesi, absensi siswa/Teacher, pembelajaran, portal absensi dan informasi persetujuan. HTTP empat login nyata memeriksa 42 permintaan, CSRF nyata, pratinjau tanpa mutasi, seri 25 pertemuan, kelas kedua 26 pertemuan dengan jam berbeda, perubahan 24 sesi dengan satu sesi bercatatan dipertahankan, pagination tiga bulan serta penolakan akses lintas cabang/role. Database fixture asal tidak berubah. Migration kosong naik/turun mempertahankan tabel dan sesi lama, dan pemasangan migration ulang menjadi no-op. SQL migration MySQL dikompilasi dan encoding/FK ditinjau; runtime pengujian memakai SQLite. Pemeriksaan sintaks mencakup 13 file PHP dan 241 hasil kompilasi Blade. Windows PowerShell, MySQL Laragon client serta pengukuran piksel browser belum dijalankan langsung.

UAT: setelah pemasangan, buka Admin atau Super Admin > Jadwal & Sesi/Sesi Belajar > Tambah sesi > Berulang mingguan. Coba satu kelompok, Senin/Rabu pukul 10.00–11.30 selama tiga bulan; kecualikan satu tanggal di pratinjau dan simpan. Cocokkan jumlah/tanggal pada detail seri. Buat seri kelas kedua pada hari yang sama pukul 11.30–13.00. Masuk sebagai Teacher, periksa minggu berjalan dan pilih rentang seluruh seri. Lengkapi satu pertemuan lalu dari pertemuan mendatang lain pilih Ini dan berikutnya; pastikan baris yang memiliki catatan dipertahankan. Catat absensi/catatan secara terpisah pada tanggal yang tepat.


## 80. Satu tombol masuk pembuatan sesi (v80)

Tombol Jadwal berulang pada header daftar Sesi Belajar Super Admin dan Jadwal & Sesi Admin dihapus karena berfungsi sebagai pintasan ke mode yang sudah tersedia di dalam Tambah sesi. Pengguna memulai melalui Tambah sesi, lalu memilih Sekali pertemuan atau Berulang mingguan pada bagian atas form. Tombol Kembali ke dashboard dan tautan detail seri pada sesi yang sudah terhubung tetap tersedia. Tidak ada perubahan route, validasi, hak akses, database atau perilaku jadwal berulang.

Revisi dilakukan pada view daftar bersama untuk kedua role. Tes alur yang sudah ada disesuaikan untuk memeriksa tombol Tambah sesi pada daftar, ketiadaan pintasan Jadwal berulang, dan keberadaan kedua mode pada form. Tidak ditambahkan tes baru untuk penghapusan tombol ini. Paket v80 berbasis v79 memuat view, tes yang disesuaikan dan DESIGN.md, dengan installer yang memverifikasi source, membuat backup file aktual di samping project, serta membersihkan cache Blade. Tidak perlu migration atau build aset. Blok PowerShell pemilih ZIP dibungkus dalam satu blok luar agar try/catch/finally tidak dijalankan terpisah saat ditempel ke konsol.


## 81. Verifikasi PDF bulanan setelah penyimpanan JSON (v81)

Ditemukan bug pada hash snapshot PDF bulanan. Snapshot dibentuk di PHP, dihitung hash-nya dari json_encode sesuai urutan field, kemudian disimpan pada kolom JSON monthly_report_files.public_snapshot. MySQL dapat menormalisasi urutan kunci objek JSON saat penyimpanan/pembacaan. Pemeriksaan file menghitung hash ulang dari urutan yang dibaca dan menganggap snapshot berubah, meskipun isi rapor serta byte PDF utuh. Kondisi ini menghasilkan pesan PDF bulanan hilang atau berubah ketika Teacher menekan Unduh PDF. Dua tes reproduksi gagal sebelum perbaikan: verifikasi file setelah perubahan urutan objek dan kestabilan hash snapshot.

Hash PDF bulanan kini memakai urutan kunci objek yang ditetapkan secara rekursif sebelum json_encode. Urutan elemen daftar tetap dipertahankan, termasuk daftar video. Nilai, tipe data, identitas laporan dan isi penilaian tetap diperiksa. Data asli snapshot yang dirender/disimpan tidak diurutkan ulang oleh perubahan ini; hanya representasi untuk penghitungan hash yang distabilkan. MonthlyReportWhatsappService memakai pemeriksaan hash yang sama saat mencocokkan file dengan snapshot laporan saat ini. Pemeriksaan MIME, prefix lokasi file, keberadaan file, penanda PDF, ukuran byte dan SHA256 byte tetap berlaku.

Hash lama yang cocok persis dengan snapshot asli masih dapat diverifikasi dan PDF valid dapat digunakan ulang. Arsip lama yang sudah kehilangan kecocokan akibat perubahan urutan JSON tidak ditulis ulang atau dianggap valid tanpa pembuktian. Teacher perlu kembali ke WhatsApp & riwayat, pilih Kirim file PDF, kemudian Siapkan pesan baru. Sistem membuat versi PDF baru dengan hash stabil, sementara arsip/file/riwayat lama tetap tersimpan. Tidak memerlukan pengisian penilaian atau persetujuan ulang selama laporan tetap memenuhi syarat berbagi. Berbagi link tetap mengikuti alur sebelumnya. Perubahan ini hanya mencakup PDF bulanan; alur PDF tiga bulan tidak diubah.

Pemeriksaan: 272 tes / 1.755 assertions lulus, terdiri dari 39 tes pengiriman bulanan (termasuk lima kasus regresi baru) dan 233 tes WhatsApp tiga bulan, siklus laporan bulanan/tiga bulan serta tanda tangan. Kasus baru mencakup perubahan urutan objek bersarang, urutan daftar/nilai/tipe yang tetap memengaruhi hash, penolakan perubahan isi, kompatibilitas PDF hash lama yang valid, serta pemulihan arsip lama melalui penyiapan pesan baru tanpa mengubah riwayat sebelumnya. File yang rusak dan sumber rapor/akses yang berubah tetap ditolak. Tiga file PHP lolos lint.

HTTP pada salinan database dan storage privat terpisah memeriksa penolakan PDF lama yang diurutkan ulang, penyiapan versi baru, perubahan urutan snapshot versi baru, unduh PDF utuh dengan checksum/ukuran cocok, pencatatan buka WhatsApp dan konfirmasi. Tidak ada permintaan keluar ke WhatsApp; tautan wa.me hanya diperiksa pada header redirect. Database fixture asal tetap identik. Runtime memakai SQLite dengan simulasi penataan ulang objek JSON yang didokumentasikan MySQL, bukan server MySQL langsung. Acuan: https://dev.mysql.com/doc/refman/8.0/en/json.html, bagian Normalization, Merging, and Autowrapping of JSON Values.

Paket DPE_Fix_v81_Unduh_PDF_Bulanan.zip berbasis v80 berisi dua service, tes regresi dan DESIGN.md. Installer memverifikasi source, mencadangkan file aktual di samping project, memasang perubahan dan membersihkan cache Blade. Tidak ada migration, dependency baru, perubahan .env atau penghapusan file rapor. Windows PowerShell belum dijalankan langsung. UAT: Ctrl+F5, buka Teacher > Laporan Bulanan > Periksa laporan yang disetujui > WhatsApp & riwayat > Kirim file PDF > Siapkan pesan baru > Unduh PDF. Pastikan PDF bisa dibuka dan menggunakan periode bulanan yang benar.


## 82. Checkbox hari dan pemulihan form dari pratinjau jadwal (v82)

Checkbox hari berulang sebelumnya terkena min-height 50 px serta padding input teks dari CSS dashboard, sehingga fokus tampak sebagai kotak memanjang. Aturan yang khusus untuk checkbox hari menetapkan ukuran/min-height 18 px, padding nol, lebar tetap dan label sejajar. Bayangan fokus input teks dilepas pada checkbox ini; penanda fokus keyboard tetap tersedia melalui focus-visible dengan outline yang mengikuti ukuran checkbox. Input teks dan checkbox pada fitur lain tetap mengikuti aturan masing-masing.

Kembali ke form di kanan atas pratinjau dan Kembali di bagian bawah kini membuka form dengan seluruh isian semula: cabang, Teacher, program, kelompok, lokasi, rentang tanggal, jam, hari dan topik/materi/aktivitas. Data tersimpan sebagai draf sesi per pratinjau, bukan flash input yang hanya bertahan sesaat. Draf tetap tersedia sesudah permintaan halaman lain, kunjungan kembali berulang atau penyegaran pratinjau. Masing-masing draf terikat akun, prefix role, jenis operasi dan, untuk perubahan seri, ID seri/pertemuan acuan. URL kembali hanya membawa UUID draf, bukan isi form. Maksimal 20 draf terakhir disimpan per akun/prefix untuk membatasi ukuran sesi. Penyimpanan ini dimulai setelah pratinjau valid; tidak menambahkan tabel atau otomatis membuat sesi belajar.

Tombol gabungan Batal / kembali dipisah menjadi Batal dan Kembali. Batal mengirim POST dengan CSRF, menghapus draf pratinjau itu saja, lalu membuka form pada kondisi awal. Pilihan hari, Teacher, program, kelompok, jam dan catatan yang sebelumnya diisi dibersihkan; nilai bawaan form, seperti cabang tunggal dan tanggal awal, mengikuti form baru. Kembali mempertahankan draf serta isian. Kedua tombol terpisah dari Perbarui pratinjau dan Simpan jadwal terpilih, dengan baris aksi yang dapat membungkus pada layar sempit. Pengaturan pengecualian tanggal dilakukan pada tahap pratinjau.

Pada pratinjau perubahan seri, Kembali mempertahankan perubahan yang belum disimpan. Batal membuang perubahan form dan menampilkan nilai sesi yang sebelumnya tersimpan, tanpa mengubah sesi atau riwayat perubahan massal. Null pada catatan dipertahankan sebagai isian kosong saat kembali. Validasi yang gagal sesudah kembali tetap menampilkan isian percobaan terakhir pada redirect form. Token pratinjau, pemeriksaan bentrok, akses cabang dan perlindungan data jadwal tetap mengikuti aturan yang sudah tersedia.

Pemeriksaan: 200 tes / 1.096 assertions lulus, termasuk 61 tes seri jadwal dengan sepuluh kasus draf baru serta regresi operasi cabang, workspace Teacher dan sidebar. Kasus baru memeriksa kedua tautan kembali, seluruh nilai form, permintaan halaman lain, kembali berulang, beberapa draf terpisah, pembatalan satu draf, refresh/perubahan isian, validasi gagal, batas akun/prefix, CSRF serta pembatalan form perubahan seri tanpa perubahan database. HTTP nyata pada Admin dan Super Admin memeriksa 25 permintaan dengan sesi tersimpan di disk, kembali setelah dua halaman lain, checkbox hari yang tetap terpilih, dua tautan kembali yang sama, pembatalan yang mengosongkan isian dan penolakan CSRF. Data bisnis serta database fixture asal tetap identik. Tiga file PHP dan 241 hasil kompilasi Blade lolos lint. Pemeriksaan checkbox mencakup aturan CSS dan markup; pengukuran piksel browser belum dijalankan langsung.

Paket DPE_Fix_v82_Form_Jadwal_Berulang.zip berbasis v81 memuat delapan file project, backup referensi, manifest, installer dan petunjuk. Installer memverifikasi source, mencadangkan file aktual di samping project serta membersihkan cache route/Blade. Tidak perlu migration, dependency baru atau perubahan .env. Runtime tes memakai SQLite; PowerShell Windows belum dijalankan langsung. UAT pada Admin dan Super Admin: isi form jadwal berulang, pilih beberapa hari, buka Pratinjau, kemudian coba Kembali ke form dan Kembali. Isian harus utuh. Ulangi lalu klik Batal: form kembali ke kondisi awal. Periksa checkbox dengan klik mouse dan Tab/Space keyboard.
