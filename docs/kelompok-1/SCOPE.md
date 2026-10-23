# Kelompok 1 — Public dan Login

Acuan: DESIGN pling baru.md versi 1.2, khususnya bagian 5, 6, 18, dan 20; prototipe telah disepakati selesai. Pengguna meminta implementasi per kelompok dengan ZIP berisi file lengkap untuk disalin ke root proyek. Pengguna telah menjalankan aplikasi, membuat database MySQL 8.4.3, melakukan migration, dan berhasil login Super Admin.

## Hasil tahap ini

- P01 Beranda, P02 Login, P03 Tentang, P04 Program beserta detail, P05 Cabang, dan P06 Kontak.
- Laravel 12 dan Blade yang sudah ada; CSS dan JavaScript lokal tanpa dependensi baru. Tidak perlu npm/build untuk kelompok ini.
- Arial, warna #2563EB / #13284B / #F4F7FD, radius input/tombol 12px dan kartu 16px; desktop responsif dan mobile 390px.
- Navigasi publik tanpa sidebar dashboard; foto beranda, tautan konsultasi, pencarian cabang, dan toggle password berfungsi.
- Konten website membaca public_content_sections yang is_published=true. Teks selalu di-escape. Tidak ada form pertanyaan pengunjung.
- Program/cabang memakai record operasional yang sama dan hanya tampil jika mempunyai metadata publik yang ditayangkan; metadata disimpan pada section_key program.{UUID} dan branch.{UUID}.
- Data contoh hanya ditampilkan pada lingkungan local tanpa konten yang sudah ditayangkan, dengan penanda Informasi contoh. APP_ENV=production tidak menampilkan data contoh secara default. Data contoh tidak dimasukkan ke database.
- Nomor WhatsApp, email, alamat, media sosial, dan jam pelayanan tidak dibuat-buat. Jika belum ditayangkan, tampilkan keadaan kosong. Nomor WhatsApp yang valid menghasilkan tautan wa.me dengan pesan konsultasi.
- Gambar privat hanya boleh diakses publik jika file secara eksplisit dipakai oleh section yang ditayangkan; hanya format raster yang tervalidasi, dan path harus aman.
- Autentikasi username/password, pembatasan percobaan login, dan pengalihan empat peran menggunakan backend yang ada.

## Kontrak section yang disiapkan untuk S08

section_key identity, home.hero, home.intro, about.intro, about.vision, about.mission, contact; serta home.benefit.{key}, about.approach.{key}, program.{UUID}, branch.{UUID}. title berisi judul; body boleh teks biasa atau objek JSON. Objek body memakai description, eyebrow, audience, features (daftar teks), duration_label, whatsapp, email, phone, service_hours, instagram, facebook, youtube, tiktok sesuai jenis section. image_file_id memakai FileAsset yang sudah ada. Program/cabang mengambil nama dan alamat operasional; teks tambahan dari metadata publik.

## Batas dan keputusan

- Figma MCP kembali menolak akses karena kuota Starter. Implementasi menggunakan spesifikasi tertulis yang mengizinkan penyempurnaan langsung saat coding; kemiripan seluruh frame Figma belum dapat diverifikasi.
- Foto sementara merupakan ilustrasi yang dibuat menggunakan ImageGen dan diberi penanda; bukan foto asli DPE. Wordmark sementara menggunakan nama DPE, bukan mengaku sebagai logo final.
- Pengelola konten S08, perubahan schema draft/published yang terpisah, sidebar dashboard, dan fitur peran lain dikerjakan pada kelompok berikutnya.
- Tidak ada migration atau perubahan data operasional dalam paket ini; .env pengguna tidak dimasukkan dalam ZIP.

## Penerimaan

Seluruh halaman dan detail program publik dapat dibuka; konten draft/program/cabang yang belum ditayangkan tidak bocor; data contoh berlabel dan tidak hadir di production; file privat dan SVG aktif tidak dapat diakses melalui endpoint gambar publik; tautan kontak aman; login benar/salah/nonaktif bekerja; desktop/mobile tidak overflow, menu mobile, filter cabang, dan toggle password dapat digunakan dengan keyboard; ZIP berisi struktur relatif asli dan panduan PowerShell.
