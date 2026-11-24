<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class StudentAccountExportService
{
    public function __construct(private UserDirectoryFilterService $directory, private StudentAccountDirectoryService $students) {}

    public function data(User $user, array $filters, string $format): array
    {
        $actor = $this->students->actor($user);
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $filters += ['q' => '', 'role_code' => '', 'status' => '', 'branch_id' => '', 'program_id' => '', 'teacher_id' => ''];
        $limit = $format === 'pdf' ? OperationalExportDefinition::MAX_PDF_ROWS : OperationalExportDefinition::MAX_ROWS;
        $query = $this->directory->query($actor, $filters, studentsOnly: true);
        $accounts = $this->students->withDetails($query->select(['users.id', 'users.full_name', 'users.username', 'users.is_active', 'users.created_at']), $actor, $filters)
            ->orderBy('full_name')->orderBy('id')->limit($limit + 1)->get();
        if ($accounts->count() > $limit) {
            throw ValidationException::withMessages(['export' => 'Ekspor '.strtoupper($format).' maksimal '.$limit.' akun. Persempit filter'.($format === 'pdf' ? ' atau gunakan Excel' : '').'. Data tidak dipotong.']);
        }
        if ($accounts->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'Belum ada akun siswa yang sesuai dengan filter untuk diekspor.']);
        }

        $rows = [];
        $branches = $programs = $teachers = [];
        foreach ($accounts as $account) {
            $detail = $this->students->details($account, $filters['teacher_id']);
            $branches += $detail['branches'];
            $programs += $detail['programs'];
            $teachers += $detail['teachers'];
            // Explicit whitelist: never serialize a User model or expose a
            // password/hash, email, contact, token or other account metadata.
            $rows[] = [
                'student' => $account->full_name,
                'username' => (string) $account->username,
                'branch' => $this->names($detail['branches']),
                'program' => $this->names($detail['programs']),
                'teacher' => $this->names($detail['teachers']),
                'status' => $account->is_active ? 'Aktif' : 'Nonaktif',
                'created' => $account->created_at?->toDateString(),
            ];
        }
        if (strlen(json_encode($rows, JSON_THROW_ON_ERROR)) > 6 * 1024 * 1024) {
            throw ValidationException::withMessages(['export' => 'Isi rekap terlalu besar. Persempit filter lalu unduh kembali. Data tidak dipotong.']);
        }
        $columns = array_map(fn ($column) => ['key' => $column[0], 'label' => $column[1], 'width' => $column[2], 'type' => $column[3] ?? 'text'], [
            ['student', 'Nama siswa', 27], ['username', 'Username', 23], ['branch', 'Cabang', 27],
            ['program', 'Program', 23], ['teacher', 'Guru utama', 25], ['status', 'Status akun', 14],
            ['created', 'Tanggal pembuatan akun', 17, 'date'],
        ]);
        $filterLabels = [
            'Cakupan' => $actor->role_code === 'admin' ? 'Cabang yang dikelola saat ini' : 'Seluruh cabang (Super Admin)',
            'Pencarian' => $filters['q'] === '' ? 'Semua' : $filters['q'],
            'Cabang' => $filters['branch_id'] === '' ? ($actor->role_code === 'admin' ? 'Cabang sendiri' : 'Semua cabang') : ($branches[$filters['branch_id']] ?? '—'),
            'Program' => $filters['program_id'] === '' ? 'Semua program' : ($programs[$filters['program_id']] ?? '—'),
            'Guru utama' => $filters['teacher_id'] === '' ? 'Semua guru' : ($teachers[$filters['teacher_id']] ?? '—'),
            'Status akun' => ['active' => 'Aktif', 'inactive' => 'Nonaktif'][$filters['status']] ?? 'Semua status',
        ];

        return compact('actor', 'columns', 'rows', 'filterLabels') + [
            'title' => 'Rekap Akun Siswa',
            'letterhead' => SystemSetting::find(1)?->organization_name ?: 'Dharma Private English',
            'description' => 'Satu baris per akun siswa. Cabang, program, dan guru utama mengikuti pendaftaran berjalan atau mendatang yang sesuai filter. Rekap untuk administrasi; tidak memuat kata sandi.',
            'usageNote' => 'Gunakan sebagai rekap internal. Bagikan hanya informasi akun yang bersangkutan kepada masing-masing siswa/orang tua.',
            'countLabel' => 'akun siswa', 'footerLabel' => 'DPE | Rekap akun siswa',
            'generated' => now()->format('d/m/Y H:i'), 'total' => count($rows),
        ];
    }

    private function names(array $items): string
    {
        return $items ? implode("\n", array_values($items)) : '—';
    }
}
