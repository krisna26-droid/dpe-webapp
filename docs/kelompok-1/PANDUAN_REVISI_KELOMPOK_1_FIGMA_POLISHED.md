# Revisi Kelompok 1 — Public One Page dan Login

Paket ini adalah revisi terbaru untuk tampilan publik satu halaman dan halaman login. Halaman publik tetap menggunakan satu Blade yang dapat di-scroll, navbar tetap menempel di atas, setiap menu mengarah ke section terkait, dan tombol Login mengarah ke route Laravel `login`.

Pada login, komposisi desktop mengikuti frame Figma: panel kiri 40%, panel kanan 60%, tanpa ruang kosong di luar frame. Kartu login dinaikkan agar sejajar dengan komposisi referensi. Tiga kartu informasi di panel kiri dibuat lebih pendek, lebarnya mengikuti kolom cerita, dan posisinya dinaikkan agar sejajar dengan gambar serta heading. Layout mobile tetap menggunakan satu kolom.

## File yang diganti

- `resources/views/layouts/public.blade.php`
- `resources/views/public/home.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/components/dpe/brand.blade.php`
- `public/assets/dpe/site.css`
- `public/assets/dpe/site.js`
- `public/assets/dpe/dpe-logo.png`
- `public/assets/dpe/english-lesson.png`

## Instalasi dari ZIP di PowerShell

Simpan ZIP di luar folder project bila diinginkan, lalu jalankan dari folder utama Laravel. Contoh jika ZIP berada di `C:\laragon\dpe-webapp` dan project berada di `C:\laragon\projectles\dpe-webapp`:

```powershell
$zip = "C:\laragon\dpe-webapp\DPE_Group1_Public_Login_OnePage_FigmaPolished.zip"
$temp = ".\_dpe_group1_polished"

Expand-Archive -Path $zip -DestinationPath $temp -Force
Copy-Item "$temp\*" ".\" -Recurse -Force
Remove-Item $temp -Recurse -Force

php artisan optimize:clear
php artisan serve
```

Perintah `Copy-Item` adalah langkah yang menyalin sekaligus mengganti file yang sama di project. ZIP boleh tetap berada di luar folder project; yang dipakai aplikasi hanyalah file hasil salinannya. Karena revisi ini berupa Blade, CSS, JavaScript, dan aset publik, `npm run build` tidak wajib untuk mencoba perubahan ini. Jalankan `npm run build` bila project Anda memang memakai bundle Vite untuk deployment.

Buka `http://127.0.0.1:8000/` untuk halaman publik dan `http://127.0.0.1:8000/login` untuk halaman login.

## Instalasi di macOS/Linux

```bash
mkdir -p .group1-polished
unzip -o DPE_Group1_Public_Login_OnePage_FigmaPolished.zip -d .group1-polished
cp -R .group1-polished/* .
rm -rf .group1-polished
php artisan optimize:clear
php artisan serve
```
