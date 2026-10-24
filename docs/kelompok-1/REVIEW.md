# Review akhir Kelompok 1

Review terpisah dengan konteks baru dilakukan terhadap seluruh perubahan source, spesifikasi, bukti pengujian, dan isi ZIP. Hasil: siap diserahkan; tidak ditemukan defect Critical, Important, atau Minor yang perlu dikoreksi. Isi ZIP dibandingkan secara independen dengan diff source awal, tanpa file hilang atau file ekstra, dan seluruh byte cocok.

Keputusan untuk bagian yang tidak diuji sebagai fitur Kelompok 1:

| Perilaku | Keputusan | Batas praktis |
| --- | --- | --- |
| Draft, pratinjau, dan penayangan terpisah | Dikerjakan pada S08/Kelompok 2 sesuai pembagian kelompok | Menyunting record yang tetap ditayangkan langsung mengubah konten yang terlihat publik; dijelaskan dalam kontrak konten |
| Kesesuaian setiap frame Figma | Acuan tertulis DESIGN.md v1.2 dipakai karena akses frame terhalang kuota | Kemiripan seluruh frame belum terverifikasi dan bisa memerlukan penyesuaian setelah akses tersedia |
| Windows/PHP8.4/MySQL pengguna | Bukti test PHP8.3/SQLite disertai panduan pemeriksaan Laragon | Perbedaan lingkungan dikonfirmasi melalui pemeriksaan lokal setelah replace |
| Visi/misi hanya deskripsi tanpa judul | Ikuti validasi backend dan kontrak yang mewajibkan judul | Record yang ditulis langsung ke database tanpa judul dapat tidak tampil |
| Gambar program/cabang yang menjadi nonaktif | Izin gambar mengikuti section yang masih ditayangkan | URL gambar tetap publik sampai seluruh referensi section ke gambar tersebut dicabut penayangannya |

File aplikasi tidak diubah oleh reviewer. Tidak ada perubahan schema, pengiriman pesan, push, atau PR. Penyerahan mengikuti instruksi pengguna: ZIP file lengkap untuk extract/copy/replace.
