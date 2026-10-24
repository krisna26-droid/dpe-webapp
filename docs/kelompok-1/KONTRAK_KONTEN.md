# Kontrak konten website publik — Kelompok 1

Dokumen ini ditujukan untuk integrasi backend dan pengembangan S08 pada Kelompok 2. Database tetap memakai tabel yang sudah ada. Tidak ada perubahan schema.

## PublicContentSection

Halaman membaca `public_content_sections` dengan `is_published=true`, diurutkan menurut `display_order`, lalu `section_key`. Teks di-escape oleh Blade; HTML pada `title`/`body` ditampilkan sebagai teks, bukan dieksekusi. Semua key sensitif terhadap kapitalisasi.

| `section_key` | `title` | `body` |
| --- | --- | --- |
| `identity` | Nama lengkap lembaga | Teks tagline atau JSON `description`; `image_file_id` dapat berisi logo raster |
| `home.hero` | Judul utama Beranda | Teks deskripsi atau JSON `description`, `eyebrow`; gambar utama pada `image_file_id` |
| `home.intro` | Judul pengenalan | Teks deskripsi atau JSON `description` |
| `home.benefit.<key>` | Judul manfaat | Teks deskripsi atau JSON `description`; satu section per item |
| `about.intro` | Judul profil | Teks deskripsi atau JSON `description`, `eyebrow`; foto pada `image_file_id` |
| `about.vision` | Judul visi | Teks visi atau JSON `description` |
| `about.mission` | Judul misi | Teks misi atau JSON `description` |
| `about.approach.<key>` | Judul pendekatan | Teks deskripsi atau JSON `description`; satu section per item |
| `program.<UUID program>` | Judul internal metadata | JSON `description`, `audience`, `features`, `duration_label`; foto pada `image_file_id` |
| `branch.<UUID cabang>` | Judul internal metadata | JSON `description`, `service_hours`, `whatsapp`; foto pada `image_file_id` |
| `contact` | Judul halaman kontak | JSON `description`, `whatsapp`, `email`, `phone`, `service_hours`, `instagram`, `facebook`, `youtube`, `tiktok` |

`<key>` merupakan nama unik, misalnya `home.benefit.speaking`. `<UUID>` harus sama persis dengan ID record operasional; bukan nama atau kode program/cabang. Nama program/cabang dan alamat cabang diambil dari record operasional, bukan dari judul atau body metadata.

JSON harus berupa objek, bukan daftar. Field teks harus string; `features` merupakan daftar string. Field dengan tipe lain, daftar JSON pada akar, atau JSON yang rusak diabaikan dengan aman. Teks biasa hanya diperlakukan sebagai `description`; field terstruktur membutuhkan objek JSON.

Contoh body metadata program:

```json
{
  "description": "Isi deskripsi resmi dari klien.",
  "audience": "Isi sasaran belajar yang sudah disepakati.",
  "features": ["Materi resmi pertama", "Materi resmi kedua"],
  "duration_label": "Isi durasi/jadwal resmi jika tersedia."
}
```

Contoh bentuk kontak (isi nilai sebenarnya dari klien sebelum ditayangkan):

```json
{
  "description": "Keterangan konsultasi resmi.",
  "whatsapp": "",
  "email": "",
  "phone": "",
  "service_hours": "",
  "instagram": "",
  "facebook": "",
  "youtube": "",
  "tiktok": ""
}
```

## Batas publikasi

- Draft (`is_published=false`) tidak dibaca halaman publik.
- Program/cabang harus `is_active=true` dan mempunyai section metadata terkait yang `is_published=true`. Menambah record operasional tidak otomatis menayangkannya.
- Mengubah atau menonaktifkan program/cabang tidak menghapus riwayat operasional; service ini hanya membaca data.
- Semua informasi contoh berhenti dipakai begitu ada satu section yang ditayangkan. Pengaturan `DPE_PUBLIC_SAMPLE_CONTENT` nonaktif secara default di luar `APP_ENV=local`.
- Backend saat ini menyimpan draft/published pada record yang sama. Menyunting record yang masih `is_published=true` langsung mengubah versi yang terlihat publik. UI dan penyimpanan draft terpisah, pratinjau, lalu penayangan yang terkontrol perlu diselesaikan bersama S08; Kelompok 1 tidak mengklaim menyediakan workflow tersebut.

## Gambar

`image_file_id` mengacu ke `file_assets`. Gambar dibaca lewat `/gambar-publik/{UUID}`, bukan melalui tautan langsung ke penyimpanan privat. Endpoint hanya melayani file yang direferensikan oleh setidaknya satu section yang ditayangkan.

File harus berada secara fisik di disk `local` (`storage/app/private`), path relatif aman dan tetap di dalam akar disk, serta MIME metadata dan MIME file sebenarnya cocok. Format yang diterima: JPEG, PNG, WebP, GIF. SVG, PDF, bukti transfer yang tidak direferensikan oleh konten publik, file hilang, dan path traversal ditolak dengan 404. Endpoint mengirim `nosniff` dan `no-store` agar status penayangan selalu diperiksa pada permintaan baru.

Mencabut penayangan section menghapus akses publik gambar jika tidak ada section lain yang masih ditayangkan dan memakai file yang sama. Jangan memakai bukti pembayaran, foto presensi, atau file laporan sebagai gambar website.

## Tautan kontak

- Nomor WhatsApp boleh memakai `+`, spasi, titik, tanda kurung, atau tanda hubung. Nomor lokal `08…` dikonversi menjadi `628…`. Setelah normalisasi, nomor harus 8–15 digit dan tidak diawali nol. Nomor yang tidak valid tidak menghasilkan tautan.
- Tombol WhatsApp hanya membuka `https://wa.me` dengan pesan konsultasi yang di-encode; tidak mengirim pesan otomatis atau memakai integrasi WA operasional.
- Email harus lolos validasi alamat email PHP. Media sosial harus URL HTTPS tanpa kredensial. Teks tidak pernah digunakan sebagai HTML.
- Peta cabang dibuat melalui pencarian Google Maps berdasarkan alamat operasional yang sudah terisi; tidak menebak koordinat atau alamat yang belum ada.
- Query `program` pada Kontak hanya dipakai jika string, dibatasi 120 karakter, dan di-encode untuk pesan WA. Query array tidak diproses sebagai teks.

## File backend yang berubah

`routes/web.php` menambah enam rute halaman/gambar publik dan detail program. `HomeController` menggunakan `PublicWebsiteService`, dan `PublicImageController` membatasi akses gambar. AuthController, model, seeder, dan migration tetap menggunakan implementasi awal.
