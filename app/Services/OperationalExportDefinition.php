<?php

namespace App\Services;

class OperationalExportDefinition
{
    public const MAX_ROWS = 3000;
    public const MAX_PDF_ROWS = 300;
    public const DATASETS = [
        'students' => 'Siswa dan pendaftaran', 'teachers' => 'Teacher',
        'student-attendance' => 'Absensi siswa', 'teacher-attendance' => 'Absensi Teacher',
        'monthly-reports' => 'Laporan bulanan', 'progress' => 'Perkembangan siswa',
        'quarterly-reports' => 'Rapor tiga bulan', 'videos' => 'Video belajar',
        'sessions' => 'Jadwal / sesi belajar', 'availability' => 'Slot Teacher',
    ];

    public static function statuses(string $dataset): array
    {
        return match ($dataset) {
            'students', 'teachers' => ['active' => 'Aktif', 'inactive' => 'Nonaktif'],
            'student-attendance' => StudentAttendanceService::STATUSES + ['unrecorded' => 'Belum dicatat', 'excluded' => 'Tidak dihitung', 'unknown' => 'Perlu diperiksa'],
            'teacher-attendance' => ['pending_review' => 'Menunggu pemeriksaan', 'needs_correction' => 'Perlu koreksi', 'approved' => 'Disetujui'],
            'monthly-reports', 'progress', 'quarterly-reports', 'videos' => MonthlyReportContentService::STATUSES,
            'sessions' => ['scheduled' => 'Dijadwalkan', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', 'rescheduled' => 'Dijadwalkan ulang'],
            default => [], // Slot statuses and class types are sourced from scoped records, including legacy values.
        };
    }

    public static function description(string $dataset): string
    {
        return match ($dataset) {
            'students' => 'Satu baris per pendaftaran yang beririsan dengan rentang tanggal. Siswa tanpa pendaftaran ditampilkan kepada Super Admin. Tanggal mulai les juga diperiksa.',
            'teachers' => 'Profil Teacher dalam cakupan Anda. Rentang tanggal menentukan penugasan cabang, kelas, siswa, dan tanggung jawab operasional yang diperiksa.',
            'student-attendance' => 'Satu baris per peserta sesi, berdasarkan waktu mulai aktual jika tersedia. Sesi mendatang, pembatalan, dan sesi pengganti tetap ditandai. Belum dicatat tidak dianggap tidak hadir.',
            'teacher-attendance' => 'Catatan kehadiran yang sudah tersimpan, berdasarkan tanggal absensi. Foto, koordinat, dan catatan pemeriksaan pribadi tidak disertakan.',
            'monthly-reports' => 'Laporan pada bulan kalender dalam rentang tanggal. Video mengikuti salinan persetujuan untuk laporan yang disetujui.',
            'progress' => 'Penilaian kategori pada laporan bulanan dalam rentang tanggal. Isi laporan yang disetujui memakai salinan persetujuan.',
            'quarterly-reports' => 'Rekap operasional siklus yang beririsan dengan rentang tanggal. Kesiapan PDF portal tetap memerlukan persetujuan, pembayaran, dan PDF final yang sah.',
            'videos' => 'Video dari periode laporan bulanan yang dapat Anda akses, lalu disaring berdasarkan tanggal video. Laporan yang disetujui memakai tautan pada salinan persetujuan. Satu video dapat tercantum pada lebih dari satu konteks laporan.',
            'sessions' => 'Jadwal berdasarkan tanggal mulai yang direncanakan. Sesi lama yang sudah diganti dan sesi yang dibatalkan tetap ditandai.',
            default => 'Slot yang tersimpan berdasarkan tanggal ketersediaan. Admin Cabang melihat slot dengan cabang dalam penugasannya; Teacher melihat slot miliknya.',
        };
    }

    public static function columns(string $dataset): array
    {
        $fields = match ($dataset) {
            'students' => [['student', 'Siswa', 29], ['school', 'Sekolah', 28], ['grade', 'Kelas sekolah', 18], ['began', 'Mulai les', 16, 'date'], ['branch', 'Cabang', 24], ['program', 'Program', 24], ['class_type', 'Jenis kelas', 18], ['starts', 'Mulai pendaftaran', 20, 'date'], ['ends', 'Akhir pendaftaran', 20, 'date'], ['status', 'Status siswa', 18]],
            'teachers' => [['teacher', 'Teacher', 29], ['whatsapp', 'Nomor WhatsApp', 23], ['status', 'Status', 18], ['branches', 'Cabang dalam cakupan', 38]],
            'student-attendance' => [['date', 'Waktu sesi', 24, 'datetime'], ['student', 'Siswa', 28], ['grade', 'Kelas sekolah', 18], ['teacher', 'Teacher', 27], ['branch', 'Cabang', 24], ['program', 'Program', 24], ['status', 'Status absensi', 32], ['counted', 'Dihitung sebagai pertemuan', 28], ['recorded', 'Waktu dicatat', 24, 'datetime']],
            'teacher-attendance' => [['date', 'Tanggal', 16, 'date'], ['teacher', 'Teacher', 28], ['branch', 'Cabang', 24], ['mode', 'Mode absensi', 32], ['type', 'Jenis kehadiran', 23], ['in', 'Check-in', 24, 'datetime'], ['out', 'Check-out', 24, 'datetime'], ['status', 'Pemeriksaan', 29]],
            'monthly-reports' => [['month', 'Bulan laporan', 18, 'month'], ['student', 'Siswa', 28], ['grade', 'Kelas sekolah', 18], ['branch', 'Cabang', 24], ['teacher', 'Teacher', 27], ['status', 'Persetujuan', 24], ['due', 'Tenggat', 16, 'date'], ['videos', 'Video valid', 16, 'integer'], ['target', 'Target video', 17, 'integer'], ['video_state', 'Pemeriksaan video', 31], ['summary', 'Ringkasan perkembangan', 56]],
            'progress' => [['month', 'Bulan laporan', 18, 'month'], ['student', 'Siswa', 28], ['branch', 'Cabang', 24], ['teacher', 'Teacher', 27], ['status', 'Persetujuan', 24], ['category', 'Kategori', 27], ['trend', 'Perkembangan', 27], ['description', 'Deskripsi', 68]],
            'quarterly-reports' => [['student', 'Siswa', 28], ['branch', 'Cabang', 24], ['teacher', 'Teacher', 27], ['start', 'Awal siklus', 18, 'month'], ['end', 'Akhir siklus', 18, 'month'], ['status', 'Persetujuan final', 24], ['due', 'Tenggat berbagi', 18, 'date'], ['ready', 'PDF portal', 20], ['reasons', 'Pemeriksaan kesiapan', 64]],
            'videos' => [['date', 'Tanggal video', 18, 'date'], ['student', 'Siswa', 28], ['branch', 'Cabang laporan', 24], ['teacher', 'Penanggung jawab laporan', 30], ['recorder', 'Teacher video', 27], ['status', 'Persetujuan laporan', 25], ['source', 'Sumber isi', 27], ['topic', 'Topik', 35], ['description', 'Deskripsi', 56], ['url', 'Tautan video', 60]],
            'sessions' => [['date', 'Mulai rencana', 24, 'datetime'], ['end', 'Selesai rencana', 24, 'datetime'], ['actual', 'Mulai aktual', 24, 'datetime'], ['branch', 'Cabang', 24], ['program', 'Program', 24], ['teacher', 'Teacher', 27], ['group', 'Kelompok kelas', 26], ['location', 'Tempat mengajar', 23], ['status', 'Status sesi', 28], ['participants', 'Peserta terdaftar', 18, 'integer']],
            default => [['date', 'Tanggal', 16, 'date'], ['start', 'Mulai', 15, 'time'], ['end', 'Selesai', 15, 'time'], ['teacher', 'Teacher', 28], ['branch', 'Cabang', 24], ['class_type', 'Jenis kelas', 20], ['status', 'Ketersediaan', 24]],
        };

        return array_map(fn ($field) => ['key' => $field[0], 'label' => $field[1], 'width' => $field[2], 'type' => $field[3] ?? 'text'], $fields);
    }

    public static function display(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (in_array($type, ['date', 'datetime', 'month'], true)) {
            return \Carbon\CarbonImmutable::parse($value)->format(match ($type) { 'date' => 'd/m/Y', 'month' => 'm/Y', default => 'd/m/Y H:i' });
        }

        return $type === 'time' ? substr((string) $value, 0, 5) : (string) $value;
    }
}
