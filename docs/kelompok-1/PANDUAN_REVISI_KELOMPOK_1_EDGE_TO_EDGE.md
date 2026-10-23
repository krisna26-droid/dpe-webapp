# Revisi Kelompok 1 — Public One Page dan Login

Revisi ini menyatukan tampilan publik ke halaman `resources/views/public/home.blade.php`. Navbar mengarah ke section `#beranda`, `#tentang`, `#program`, `#cabang`, dan `#kontak`, tetap menempel di bagian atas saat halaman di-scroll, dan tombol `Login` mengarah ke route Laravel `login`.

Kontrak autentikasi tidak diubah. Form tetap mengirim `username` dan `password` ke route `login.process`, sehingga controller login dan redirect dashboard tetap memakai implementasi backend yang sudah ada.

## File yang diganti

- `resources/views/layouts/public.blade.php`
- `resources/views/public/home.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/components/dpe/brand.blade.php`
- `public/assets/dpe/site.css`
- `public/assets/dpe/site.js`

## Instalasi dari ZIP

Jalankan perintah berikut dari folder utama project Laravel di VS Code PowerShell:

```powershell
Expand-Archive -Path .\DPE_Group1_Public_Login_OnePage_EdgeToEdge.zip -DestinationPath .\_dpe_group1 -Force
Copy-Item .\_dpe_group1\* .\ -Recurse -Force
Remove-Item .\_dpe_group1 -Recurse -Force
php artisan optimize:clear
npm run build
php artisan serve
```

Buka `http://127.0.0.1:8000/` untuk melihat halaman publik dan `http://127.0.0.1:8000/login` untuk login. Jika dependency frontend belum ada, jalankan `npm install` satu kali sebelum `npm run build`.

Untuk macOS/Linux, padanan ekstraksinya adalah:

```bash
mkdir -p .group1 && unzip -o DPE_Group1_Public_Login_OnePage_EdgeToEdge.zip -d .group1
cp -R .group1/* .
rm -rf .group1
php artisan optimize:clear
npm run build
php artisan serve
```


## Pembaruan login

Versi `Updated` mengecilkan komposisi login dan mengunci layout pada tinggi viewport agar tidak memunculkan scroll pada desktop.


## Penyelarasan dengan Figma

Layout login menggunakan frame maksimum 1340px dengan pembagian kolom 40/60, kartu form maksimum 466px, dan rasio presentasi 16:9 agar tidak melebar pada viewport besar. Logo DPE pada panel login menggunakan aset transparan dari referensi identitas lembaga.


## Penyesuaian edge-to-edge

Layout login kini menggunakan lebar viewport penuh. Proporsi 40/60 dan lebar kartu 69% dari area panel menjaga tampilan tetap sesuai frame Figma tanpa ruang kosong di tepi layar.
