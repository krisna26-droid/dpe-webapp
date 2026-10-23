<section class="dpe-student-attendance-setup" aria-label="Cara menyiapkan daftar peserta">
    <h3>Menyiapkan daftar siswa untuk absensi</h3>
    @if ($routeBase === 'superadmin.student-attendance')
        <ol>
            <li>Buka <a href="{{ route('superadmin.students.index') }}">Siswa &amp; Pendaftaran</a>. Buat atau pilih profil siswa.</li>
            <li>Di detail siswa, klik <strong>Tambah pendaftaran</strong>. Pilih cabang dan program yang sama dengan sesi, lalu isi tanggal mulai. <a href="{{ route('superadmin.student-enrollments.index') }}">Lihat pendaftaran program</a>.</li>
            @if ($classGroupId)
                <li>Klik <a href="{{ route('superadmin.class-group-memberships.create', ['class_group_id' => $classGroupId]) }}">Tambah anggota kelas ini</a>, pilih siswa, lalu simpan tanggal mulai keanggotaannya.</li>
            @else
                <li>Buka sesi private, centang nama siswa pada <strong>Kelola peserta private</strong>, lalu klik <strong>Simpan peserta</strong>.</li>
            @endif
        </ol>
    @elseif ($routeBase === 'admin.student-attendance')
        <p>Periksa profil dan pendaftaran melalui <a href="{{ route('admin.students.index') }}">Data Siswa</a>. Cabang, program, dan tanggal mulai siswa perlu sesuai dengan sesi.</p>
        @if ($classGroupId)
            <p>Super Admin menambahkan siswa melalui Kelompok Kelas → Detail → Tambah anggota.</p>
        @else
            <p>Pilih siswa pada Kelola peserta private di halaman sesi, lalu klik Simpan peserta.</p>
        @endif
    @else
        @if ($classGroupId)
            <p>Minta Super Admin menyiapkan pendaftaran program dan anggota kelas. Nama siswa mengikuti tanggal keanggotaan serta tanggal mulai les.</p>
        @else
            <p>Minta Admin atau Super Admin memilih siswa pada Kelola peserta private di halaman sesi, lalu menyimpan peserta.</p>
        @endif
        <p>Setelah peserta tersimpan, muat ulang halaman. Teacher mengisi kehadiran dengan checkbox di sebelah nama siswa atau pada rekap bulanan.</p>
    @endif
    <p class="dpe-student-attendance-setup-note">Tanggal mulai belajar, pendaftaran, dan keanggotaan perlu berlaku pada tanggal pertemuan. Checkbox Hadir aktif setelah waktu pertemuan dimulai.</p>
</section>
