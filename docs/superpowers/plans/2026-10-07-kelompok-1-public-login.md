# Kelompok 1 Public dan Login Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Enam halaman Public/Login responsif dengan konten yang ditayangkan dan paket ZIP siap replace.

**Architecture:** PublicWebsiteService menyiapkan array data publik dari section yang ditayangkan dan record operasional. HomeController menyediakan halaman publik; PublicImageController membatasi file yang ditayangkan. Blade menggunakan layout/komponen bersama serta aset CSS/JS lokal. Form login memakai autentikasi yang sudah ada.

**Tech Stack:** Laravel 12, PHP ^8.2, Blade, vanilla CSS/JavaScript, SQLite untuk test, Playwright untuk pemeriksaan browser.

**Spec:** docs/kelompok-1/SCOPE.md

## Global Constraints

- Arial, warna #2563EB / #13284B / #F4F7FD, radius input/tombol 12px dan kartu 16px; desktop responsif dan mobile 390px.
- CSS dan JavaScript lokal tanpa dependensi baru. Tidak perlu npm/build untuk kelompok ini.
- Teks selalu di-escape. Tidak ada form pertanyaan pengunjung.
- .env pengguna tidak dimasukkan dalam ZIP. Tidak ada migration atau perubahan data operasional.

## Review Focus

- Konten/program/cabang draft dan file privat tidak tampil atau terbuka untuk tamu.
- JSON salah tipe, path berbahaya, MIME aktif, dan URL kontak tidak aman ditangani tanpa 500 atau XSS.
- Penayangan sebagian konten tidak menyebabkan data contoh lain tampil sebagai konten resmi.
- Login empat peran, password salah, akun nonaktif, dan rate limit tetap bekerja.
- Navigasi mobile, filter tanpa hasil, toggle password, dan keyboard bekerja tanpa overflow pada 390px dan 1920px.

### Task 1: Data dan halaman publik

**Files:** Create app/Services/PublicWebsiteService.php, app/Http/Controllers/Public/PublicImageController.php, config/dpe_public.php, tests/Feature/Public/PublicWebsiteTest.php. Modify app/Http/Controllers/Public/HomeController.php, routes/web.php. Create/modify resources/views/public/{home,about,programs,program,branches,contact}.blade.php and resources/views/layouts/public.blade.php, resources/views/components/dpe/*.blade.php.

**Interfaces:** Consumes PublicContentSection/Program/Branch/FileAsset yang sudah ada. Produces PublicWebsiteService::data(): array, PublicWebsiteService::program(string $id): ?array, route home/public.about/public.programs/public.program/public.branches/public.contact/public.image, array site {identity,hero,intro,benefits,about,programs,branches,contact,is_sample}.

- [x] Tambah test rute, hanya konten ditayangkan, contoh local/production, metadata program/cabang, XSS teks, JSON malformed, query kontak bertipe array, gambar privat/published/MIME/path.
- [x] Jalankan php artisan test tests/Feature/Public/PublicWebsiteTest.php; verifikasi gagal pada halaman/data yang belum diimplementasikan.
- [x] Implementasikan service, controller, rute, layout, komponen, dan halaman sesuai spec.
- [x] Jalankan test Task 1; pastikan lolos.
- [x] Commit feat: implement DPE public website and published content.

### Task 2: Tampilan, interaksi, dan login

**Files:** Modify resources/views/auth/login.blade.php. Create public/assets/dpe/{site.css,site.js,english-lesson.png}, tests/Feature/Auth/PublicLoginTest.php. Browser harness disimpan di workspace pengujian.

**Interfaces:** Consumes site array/rute Task 1 serta login.process dan AuthController yang sudah ada. Produces menu mobile data-menu-toggle, filter cabang data-branch-search, toggle password data-password-toggle, dan form login CSRF yang kompatibel.

- [x] Tambah test login username/password untuk empat peran, password salah/nonaktif, old username/error aman; gunakan test rate limit yang sudah ada.
- [x] Jalankan test baru; catat assertion keadaan UI yang belum ada, pisahkan test regresi yang sudah lolos.
- [x] Implementasikan login dan CSS/JS responsif; salin foto ilustrasi dan beri penanda sesuai spec.
- [x] Jalankan test login dan seluruh suite; catat setiap kegagalan baseline/di luar scope secara jelas.
- [x] Periksa browser desktop 1920x1080, mobile 390x844, semua halaman, menu mobile, password toggle, pencarian/filter kosong dan kontak valid. Perbaiki bug yang ditemukan.
- [x] Commit feat: style responsive public pages and login.

### Task 3: Paket penyerahan

**Files:** Create PANDUAN_KELOMPOK_1.md, docs/kelompok-1/KONTRAK_KONTEN.md. ZIP di output di luar repo; berisi seluruh file baru/berubah dengan path relatif asli.

**Interfaces:** Consumes implementasi/test Task 1/2. Produces dpe-kelompok-1-public-login.zip dan langkah pemasangan/expektasi manual.

- [x] Periksa diff, lint, dan review akhir; selesaikan defect penting dengan test regresi.
- [x] Tulis panduan extract/copy ke C:\laragon\projectles\dpe-webapp, php artisan optimize:clear, php artisan serve, URL dan pemeriksaan manual.
- [x] Buat manifest file berubah, ZIP file lengkap; periksa arsip dan pastikan tidak ada .env/vendor/node_modules/.git, serta tidak ada penghapusan yang tersembunyi.
- [x] Simpan ZIP dan berikan tautan unduh dengan instruksi terminal di bawahnya; berhenti sebelum Kelompok 2.
