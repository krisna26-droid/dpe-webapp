# Verifikasi Kelompok 1

Tanggal pemeriksaan: 7 Oktober 2026. Source awal `70a0e02e79e242cfc57aa24053938a26ed085ae7`.

## Lingkungan

- PHP CLI 8.3.6, Laravel 12.69.3, dependensi dari `composer.lock` yang sudah ada.
- SQLite untuk test otomatis dan database pratinjau terpisah; konfigurasi pemeriksaan tidak disertakan dalam paket.
- Chromium 153.0.8010.0 melalui Playwright. Browser/server hanya dijalankan untuk pemeriksaan lokal, tanpa layanan eksternal atau nomor kontak sungguhan.
- Tidak mengubah dependency proyek, `.env.example`, migration, seeder, atau AuthController.

## Pengujian aplikasi

Semua **1.190 tests** berhasil, dengan **2.883 assertions**. Sebanyak **20 tests** secara khusus memeriksa Public/Login, termasuk test rate limit yang sudah ada.

Test baru ditulis dan dijalankan sebelum implementasi: 13 kegagalan menunjukkan rute, konten, dan branding yang belum tersedia; regresi autentikasi yang sudah berfungsi tetap berhasil. Setelah implementasi, semua test baru berhasil. Kegagalan awal suite lama akibat extension GD yang belum tersedia pada lingkungan pemeriksaan diselesaikan dengan melengkapi lingkungan tersebut; kode aplikasi tidak diubah untuk menghindari test.

| Area | Pemeriksaan | Hasil |
| --- | --- | --- |
| Halaman publik | Semua rute, navigasi, bahasa Indonesia, detail program | Berhasil |
| Penayangan | Draft tidak tampil; publikasi sebagian tidak dicampur contoh | Berhasil |
| Data operasional | Hanya program/cabang aktif dengan metadata ditayangkan | Berhasil |
| Contoh lokal | Berlabel dan tidak memasukkan data ke database | Berhasil |
| Konten kosong | Tidak menampilkan contoh saat konfigurasi nonaktif | Berhasil |
| Input konten | Escape HTML, JSON rusak/salah tipe, query kontak array | Berhasil |
| Gambar | Published dapat diakses dan dicabut; file privat/draft, SVG, MIME palsu, path traversal ditolak | Berhasil |
| Kontak | URL WhatsApp, email, media sosial, alamat tanpa peta palsu | Berhasil |
| Login | Empat peran, password salah, akun nonaktif, username dipertahankan tanpa password | Berhasil |
| Rate limit | Percobaan keenam dibatasi | Berhasil |

## Browser dan visual

**28 kombinasi halaman/viewport** diperiksa: tujuh halaman (Beranda, Tentang, Program, Detail Regular, Cabang, Kontak, Login) pada empat ukuran berikut.

| Viewport | Pemeriksaan layout | Hasil |
| --- | --- | --- |
| 1920 × 1080 | Desktop | Tidak ada overflow horizontal, gambar rusak, atau error JavaScript |
| 1024 × 768 | Tablet/laptop | Tidak ada overflow horizontal, gambar rusak, atau error JavaScript |
| 390 × 844 | Ponsel | Tidak ada overflow horizontal, gambar rusak, atau error JavaScript |
| 320 × 740 | Ponsel kecil | Tidak ada overflow horizontal, gambar rusak, atau error JavaScript |

Pemeriksaan interaksi: menu mobile dibuka memakai keyboard, ditutup memakai Escape dengan fokus kembali ke tombol; navigasi mobile menuju Cabang; pencarian cabang dengan hasil, tanpa hasil, lalu reset; password ditampilkan/disembunyikan memakai keyboard; submit login salah dengan pesan error dan username tersimpan; navigasi tetap tersedia tanpa JavaScript.

Screenshot halaman diperiksa secara visual, termasuk Beranda dan Login desktop serta Beranda, Cabang, Kontak, dan Login mobile. Foto, tombol, spasi, hierarki teks, dan posisi elemen sesuai acuan tertulis. Semua aset lokal berhasil dimuat.

## Batas verifikasi

Hasil ini membuktikan perilaku pada lingkungan pemeriksaan tersebut. Aplikasi Windows/Laragon pengguna dan MySQL 8.4.3 belum dijalankan oleh pemeriksa. Semua tabel/modifikasi database tetap memakai source awal; tidak ada migration dalam kelompok ini.

Figma MCP terkena batas akses, sehingga kesesuaian seluruh frame tidak dapat diklaim. Implementasi memakai spesifikasi tertulis versi 1.2 dengan aset foto/wordmark sementara yang jelas. Pengelolaan konten S08 dan workflow draft terpisah dilanjutkan pada Kelompok 2.
