<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OperationalExportDataService
{
    public function __construct(private OperationalExportAccessService $access, private OperationsAccessService $operations,
        private StudentProgressAccessService $progressAccess, private StudentAttendanceAccessService $sessionAccess,
        private StudentAttendanceService $attendance, private MonthlyReportContentService $content,
        private OperationsMonitoringService $monitor, private StudentPortalService $portal) {}

    public static function supportedFilters(string $dataset): array
    {
        $fields = ['branch_id', 'teacher_id', 'q', 'status'];
        if (in_array($dataset, ['students', 'student-attendance', 'sessions'], true)) {
            $fields = [...$fields, 'program_id', 'class_type'];
        }
        if (in_array($dataset, ['students', 'student-attendance', 'monthly-reports', 'progress', 'quarterly-reports', 'videos'], true)) {
            $fields[] = 'grade';
        }
        if (in_array($dataset, ['student-attendance', 'sessions'], true)) {
            $fields[] = 'location';
        }
        if ($dataset === 'teacher-attendance') {
            $fields[] = 'mode';
        }
        if ($dataset === 'availability') {
            $fields[] = 'class_type';
        }

        return $fields;
    }

    public function data(User $user, array $input): array
    {
        $actor = $this->access->actor($user);
        $filters = $this->normalize($input);
        $options = $this->access->options($actor, $filters['from'], $filters['to']);
        foreach (['branch_id' => 'branches', 'teacher_id' => 'teachers', 'program_id' => 'programs'] as $key => $option) {
            abort_if($filters[$key] !== '' && ! $options[$option]->contains('id', $filters[$key]), 403);
        }
        $statusLabels = OperationalExportDefinition::statuses($filters['dataset']);
        if ($filters['dataset'] === 'availability') {
            $statusLabels = $this->access->availability($actor)->whereBetween('available_on', [$filters['from'], $filters['to']])
                ->distinct()->pluck('availability_status')->filter()->mapWithKeys(fn ($s) => [(string) $s => (string) $s])->all();
        }
        if ($filters['status'] !== 'all' && ! array_key_exists($filters['status'], $statusLabels)) {
            throw ValidationException::withMessages(['status' => 'Pilih status yang tersedia untuk jenis data ini.']);
        }
        $rows = collect();
        match ($filters['dataset']) {
            'students' => $this->students($actor, $filters, $rows),
            'teachers' => $this->teachers($actor, $filters, $options, $rows),
            'student-attendance', 'sessions' => $this->sessions($actor, $filters, $rows),
            'teacher-attendance' => $this->teacherAttendance($actor, $filters, $rows),
            'monthly-reports', 'progress', 'videos' => $this->monthly($actor, $filters, $rows),
            'quarterly-reports' => $this->quarterly($actor, $filters, $rows),
            'availability' => $this->availability($actor, $filters, $rows),
        };
        $rows = $rows->sortBy('_sort', SORT_STRING)->values();
        $columns = OperationalExportDefinition::columns($filters['dataset']);
        $safeRows = $rows->map(fn ($r) => array_intersect_key($r, array_flip(['_id', ...array_column($columns, 'key')])))->all();
        $snapshot = hash_hmac('sha256', json_encode([$actor->id, $actor->role_code, $filters, $columns, $safeRows], JSON_THROW_ON_ERROR), (string) config('app.key'));
        $classTypes = $options['programs']->pluck('class_type')->merge($this->access->availability($actor)->distinct()->pluck('class_type'))
            ->filter()->unique()->sort()->values();
        $filterLabels = $this->filterLabels($filters, $options, $statusLabels);

        return compact('actor', 'filters', 'columns', 'snapshot', 'statusLabels', 'classTypes', 'filterLabels') + $options + [
            'title' => OperationalExportDefinition::DATASETS[$filters['dataset']], 'description' => OperationalExportDefinition::description($filters['dataset']),
            'rows' => $safeRows, 'total' => count($safeRows), 'generated' => now()->format('d/m/Y H:i:s').' '.config('app.timezone'),
            'supportedFilters' => self::supportedFilters($filters['dataset']),
        ];
    }

    private function normalize(array $input): array
    {
        $f = [];
        foreach (['dataset' => 'students', 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString(),
            'branch_id' => '', 'teacher_id' => '', 'program_id' => '', 'class_type' => '', 'grade' => '', 'q' => '', 'status' => 'all', 'mode' => '', 'location' => ''] as $key => $default) {
            $f[$key] = isset($input[$key]) && $input[$key] !== '' ? trim((string) $input[$key]) : $default;
        }
        abort_unless(array_key_exists($f['dataset'], OperationalExportDefinition::DATASETS), 422);
        $from = CarbonImmutable::parse($f['from']);
        $to = CarbonImmutable::parse($f['to']);
        if ($from > $to || $from->diffInDays($to) > 365) {
            throw ValidationException::withMessages(['to' => 'Tanggal akhir harus setelah tanggal awal. Rentang maksimal 366 hari.']);
        }
        foreach (array_diff(['branch_id', 'teacher_id', 'program_id', 'class_type', 'grade', 'q', 'status', 'mode', 'location'], self::supportedFilters($f['dataset'])) as $key) {
            if ($f[$key] !== '' && $f[$key] !== 'all') {
                throw ValidationException::withMessages([$key => 'Filter ini tidak berlaku untuk jenis data yang dipilih. Tampilkan ulang menggunakan filter yang tersedia.']);
            }
        }

        return $f;
    }

    private function collectRow(Collection $rows, array $f, array $row, array $meta): void
    {
        foreach (['branch_id', 'teacher_id', 'program_id', 'class_type', 'grade', 'mode', 'location'] as $field) {
            if ($f[$field] !== '' && ! in_array($f[$field], (array) ($meta[$field] ?? []), true)) {
                return;
            }
        }
        if ($f['status'] !== 'all' && ($meta['status'] ?? '') !== $f['status']) {
            return;
        }
        if ($f['q'] !== '' && ! str_contains(mb_strtolower(implode(' ', array_filter($row, fn ($v, $k) => ! str_starts_with($k, '_') && is_scalar($v), ARRAY_FILTER_USE_BOTH))), mb_strtolower($f['q']))) {
            return;
        }
        $rows->push($row);
        if ($rows->count() > OperationalExportDefinition::MAX_ROWS) {
            throw ValidationException::withMessages(['export' => 'Hasil melebihi 3.000 baris. Persempit rentang tanggal, nama, cabang, atau Teacher lalu tampilkan kembali. Tidak ada baris yang dipotong.']);
        }
    }

    private function commonQuery(Builder $query, array $f, bool $program = false): Builder
    {
        foreach (['branch_id', 'teacher_id'] as $field) {
            if ($f[$field] !== '') {
                $query->where($field, $f[$field]);
            }
        }
        if ($program && $f['program_id'] !== '') {
            $query->where('program_id', $f['program_id']);
        }

        return $query;
    }

    private function students(User $actor, array $f, Collection $rows): void
    {
        $query = $this->access->enrollments($actor, $f['from'], $f['to'])->with(['branch', 'program', 'student.studentTeacherAssignments', 'student.classGroupMemberships.classGroup',
            'student.monthlyReports' => fn ($q) => $q->whereIn('id', $this->operations->monthly($actor)->select('monthly_reports.id'))
                ->whereRaw('SUBSTR(report_month, 1, 7) BETWEEN ? AND ?', [substr($f['from'], 0, 7), substr($f['to'], 0, 7)]),
            'student.lessonSessions' => fn ($q) => $q->whereIn('lesson_sessions.id', $this->sessionAccess->sessions($actor)->select('lesson_sessions.id'))
                ->whereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) BETWEEN ? AND ?', [$f['from'], $f['to']])->where('status', '!=', 'cancelled')->notSuperseded()]);
        foreach (['branch_id', 'program_id'] as $key) {
            if ($f[$key] !== '') {
                $query->where($key, $f[$key]);
            }
        }
        foreach ($query->orderBy('id')->lazy(150) as $e) {
            $s = $e->student;
            $teacherIds = $s->studentTeacherAssignments->filter(fn ($a) => $a->starts_on->toDateString() <= $f['to']
                && ($a->ends_on === null || $a->ends_on->toDateString() >= max($f['from'], $s->began_on->toDateString())))->pluck('teacher_id')->all();
            $teacherIds = [...$teacherIds, ...$s->monthlyReports->where('branch_id', $e->branch_id)->pluck('teacher_id')->all(),
                ...$s->lessonSessions->where('branch_id', $e->branch_id)->where('program_id', $e->program_id)->pluck('teacher_id')->all()];
            foreach ($s->classGroupMemberships as $membership) {
                $group = $membership->classGroup;
                if ($group?->is_active && $group->branch_id === $e->branch_id && $group->program_id === $e->program_id
                    && max($f['from'], $s->began_on->toDateString(), $e->starts_on->toDateString(), $membership->starts_on->toDateString())
                    <= min($f['to'], $e->ends_on?->toDateString() ?? $f['to'], $membership->ends_on?->toDateString() ?? $f['to'])) {
                    $teacherIds[] = $group->default_teacher_id;
                }
            }
            // Teachers may also be responsible through a class/session/report, without a direct assignment.
            if ($actor->role_code === 'teacher') {
                $teacherIds[] = $this->progressAccess->teacherId($actor);
            }
            $this->collectRow($rows, $f, ['_id' => $e->id, '_sort' => $s->full_name.'|'.$e->id, 'student' => $s->full_name,
                'school' => $s->school_name, 'grade' => $s->grade_name, 'began' => $s->began_on?->toDateString(), 'branch' => $e->branch?->name,
                'program' => $e->program?->name, 'class_type' => $e->program?->class_type, 'starts' => $e->starts_on?->toDateString(),
                'ends' => $e->ends_on?->toDateString(), 'status' => ['active' => 'Aktif', 'inactive' => 'Nonaktif'][$s->status] ?? $s->status],
                ['branch_id' => $e->branch_id, 'teacher_id' => $teacherIds, 'program_id' => $e->program_id, 'class_type' => $e->program?->class_type, 'grade' => $s->grade_name, 'status' => $s->status]);
        }
        if ($actor->role_code === 'superadmin') {
            $unassigned = Student::whereDate('began_on', '<=', $f['to'])->whereDoesntHave('enrollments', fn ($e) => $e->whereDate('starts_on', '<=', $f['to'])
                ->where(fn ($e) => $e->whereNull('ends_on')->orWhereDate('ends_on', '>=', $f['from'])));
            foreach ($unassigned->orderBy('id')->lazy(150) as $s) {
                $this->collectRow($rows, $f, ['_id' => $s->id, '_sort' => $s->full_name.'|'.$s->id, 'student' => $s->full_name, 'school' => $s->school_name,
                    'grade' => $s->grade_name, 'began' => $s->began_on?->toDateString(), 'branch' => 'Tidak ada pendaftaran pada rentang ini', 'program' => null,
                    'class_type' => null, 'starts' => null, 'ends' => null, 'status' => ['active' => 'Aktif', 'inactive' => 'Nonaktif'][$s->status] ?? $s->status],
                    ['grade' => $s->grade_name, 'status' => $s->status]);
            }
        }
    }

    private function teachers(User $actor, array $f, array $options, Collection $rows): void
    {
        foreach ($options['teachers'] as $teacher) {
            if ($f['teacher_id'] !== '' && $f['teacher_id'] !== $teacher->id) {
                continue;
            }
            $ids = $teacher->branchAssignments()->whereDate('starts_on', '<=', $f['to'])->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $f['from']))->pluck('branch_id')
                ->merge($teacher->classGroups()->where('is_active', true)->pluck('branch_id'))
                ->merge($this->operations->monthly($actor)->where('teacher_id', $teacher->id)
                    ->whereRaw('SUBSTR(report_month, 1, 7) BETWEEN ? AND ?', [substr($f['from'], 0, 7), substr($f['to'], 0, 7)])->pluck('branch_id'))
                ->merge($this->operations->quarterly($actor)->where('teacher_id', $teacher->id)->whereHas('cycle', fn ($c) => $c
                    ->whereRaw('SUBSTR(start_month, 1, 7) <= ?', [substr($f['to'], 0, 7)])->whereRaw('SUBSTR(end_month, 1, 7) >= ?', [substr($f['from'], 0, 7)]))->pluck('branch_id'))
                ->merge($this->sessionAccess->sessions($actor)->where('teacher_id', $teacher->id)
                    ->whereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) BETWEEN ? AND ?', [$f['from'], $f['to']])->pluck('branch_id'))
                ->merge($this->operations->attendances($actor)->where('teacher_id', $teacher->id)->whereBetween('attendance_date', [$f['from'], $f['to']])->pluck('branch_id'))
                ->merge($this->access->availability($actor)->where('teacher_id', $teacher->id)->whereBetween('available_on', [$f['from'], $f['to']])->pluck('branch_id'));
            $assignedStudents = $teacher->studentAssignments()->whereDate('starts_on', '<=', $f['to'])
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $f['from']))->pluck('student_id');
            $ids = $ids->merge($this->access->enrollments($actor, $f['from'], $f['to'])->whereIn('student_id', $assignedStudents)->pluck('branch_id'))->unique();
            $visible = $options['branches']->whereIn('id', $ids);
            $active = $teacher->is_active && $teacher->user?->is_active && $teacher->user?->role_code === 'teacher';
            $this->collectRow($rows, $f, ['_id' => $teacher->id, '_sort' => ($teacher->user?->full_name ?? '').'|'.$teacher->id,
                'teacher' => $teacher->user?->full_name, 'whatsapp' => $teacher->whatsapp_number, 'status' => $active ? 'Aktif' : 'Nonaktif',
                'branches' => $visible->pluck('name')->implode(', ')],
                ['branch_id' => $visible->pluck('id')->all(), 'teacher_id' => $teacher->id, 'status' => $active ? 'active' : 'inactive']);
        }
    }

    private function sessions(User $actor, array $f, Collection $rows): void
    {
        $attendance = $f['dataset'] === 'student-attendance';
        $query = $this->commonQuery($this->sessionAccess->sessions($actor), $f, true)
            ->whereRaw('DATE('.($attendance ? 'COALESCE(actual_start_at, planned_start_at)' : 'planned_start_at').') BETWEEN ? AND ?', [$f['from'], $f['to']])
            ->with(['teacher.user', 'branch', 'program', 'classGroup.memberships.student.enrollments', 'classGroup.memberships.student.classGroupMemberships',
                'replacementSessions', 'sessionStudents.student.enrollments', 'sessionStudents.student.classGroupMemberships']);
        foreach ($query->orderBy('id')->lazy(100) as $s) {
            $meta = ['branch_id' => $s->branch_id, 'teacher_id' => $s->teacher_id, 'program_id' => $s->program_id, 'class_type' => $s->program?->class_type, 'location' => $s->teaching_location];
            $roster = $this->attendance->roster($s);
            if (! $attendance) {
                $status = $s->isCancelled() ? 'cancelled' : ($this->attendance->isSuperseded($s) ? 'rescheduled' : $s->status);
                $this->collectRow($rows, $f, ['_id' => $s->id, '_sort' => $s->getRawOriginal('planned_start_at').'|'.$s->id,
                    'date' => $s->getRawOriginal('planned_start_at'), 'end' => $s->getRawOriginal('planned_end_at'), 'actual' => $s->getRawOriginal('actual_start_at'),
                    'branch' => $s->branch?->name, 'program' => $s->program?->name, 'teacher' => $s->teacher?->user?->full_name, 'group' => $s->classGroup?->name,
                    'location' => $s->isAtStudentHome() ? 'Rumah siswa' : 'Cabang', 'status' => OperationalExportDefinition::statuses('sessions')[$status] ?? $status,
                    'participants' => $roster->count()], $meta + ['status' => $status]);
                continue;
            }
            foreach ($roster as $student) {
                $cell = $this->attendance->cell($s, $student);
                $status = $cell['status'];
                $key = $status === 'rescheduled' ? 'rescheduled' : ($cell['reason'] !== null ? 'excluded' : ($status === null ? 'unrecorded' : (isset(StudentAttendanceService::STATUSES[$status]) ? $status : 'unknown')));
                $this->collectRow($rows, $f, ['_id' => $s->id.'|'.$student->id, '_sort' => $this->attendance->meetingAt($s)->format('Y-m-d H:i:s').'|'.$student->full_name.'|'.$student->id,
                    'date' => $this->attendance->meetingAt($s)->format('Y-m-d H:i:s'), 'student' => $student->full_name, 'grade' => $student->grade_name,
                    'teacher' => $s->teacher?->user?->full_name, 'branch' => $s->branch?->name, 'program' => $s->program?->name, 'status' => $cell['label'],
                    'counted' => $cell['counts_as_meeting'] ? 'Ya' : 'Tidak', 'recorded' => $cell['record']?->getRawOriginal('recorded_at')],
                    $meta + ['status' => $key, 'grade' => $student->grade_name]);
            }
        }
    }

    private function teacherAttendance(User $actor, array $f, Collection $rows): void
    {
        $query = $this->commonQuery($this->operations->attendances($actor), $f)->whereBetween('attendance_date', [$f['from'], $f['to']])->with(['branch', 'teacher.user']);
        foreach ($query->orderBy('id')->lazy(150) as $a) {
            $this->collectRow($rows, $f, ['_id' => $a->id, '_sort' => $a->attendance_date?->toDateString().'|'.$a->id,
                'date' => $a->attendance_date?->toDateString(), 'teacher' => $a->teacher?->user?->full_name, 'branch' => $a->branch?->name,
                'mode' => $a->modeLabel(), 'type' => ['present' => 'Hadir', 'absent' => 'Tidak hadir', 'leave' => 'Izin', 'sick' => 'Sakit'][$a->attendance_type] ?? $a->attendance_type,
                'in' => $a->getRawOriginal('check_in_at'), 'out' => $a->getRawOriginal('check_out_at'),
                'status' => OperationalExportDefinition::statuses('teacher-attendance')[$a->status] ?? $a->status],
                ['branch_id' => $a->branch_id, 'teacher_id' => $a->teacher_id, 'status' => $a->status, 'mode' => $a->attendance_mode ?? 'legacy']);
        }
    }

    private function monthly(User $actor, array $f, Collection $rows): void
    {
        $query = $this->commonQuery($this->operations->monthly($actor), $f)
            ->whereRaw('SUBSTR(report_month, 1, 7) BETWEEN ? AND ?', [substr($f['from'], 0, 7), substr($f['to'], 0, 7)])
            ->with(['student', 'teacher.user', 'branch', 'reportSkills']);
        foreach ($query->orderBy('id')->lazy(100) as $report) {
            $display = $this->content->display($report);
            $public = $display['public'];
            $base = ['student' => $public['student_name'] ?? $report->student?->full_name, 'teacher' => $public['teacher_name'] ?? $report->teacher?->user?->full_name,
                'branch' => $public['branch_name'] ?? $report->branch?->name, 'status' => MonthlyReportContentService::STATUSES[$report->status] ?? $report->status];
            $meta = ['branch_id' => $report->branch_id, 'teacher_id' => $report->teacher_id, 'grade' => $report->student?->grade_name, 'status' => $report->status];
            $month = $report->report_month->toDateString();
            if ($f['dataset'] === 'monthly-reports') {
                $video = $this->monitor->videoState($report);
                $this->collectRow($rows, $f, ['_id' => $report->id, '_sort' => $month.'|'.$report->id, 'month' => $month, 'grade' => $report->student?->grade_name,
                    'due' => $report->due_on?->toDateString(), 'videos' => $video['count'], 'target' => $video['target'], 'video_state' => $video['label'], 'summary' => $public['development_summary'] ?? null] + $base, $meta);
            } elseif ($f['dataset'] === 'progress') {
                foreach ($public['skills'] ?? [] as $i => $skill) {
                    $this->collectRow($rows, $f, ['_id' => $report->id.'|'.$i, '_sort' => $month.'|'.$report->id.'|'.sprintf('%02d', $i), 'month' => $month,
                        'category' => $skill['name'] ?? null, 'trend' => $skill['trend'] ?? null, 'description' => $skill['description'] ?? null] + $base, $meta);
                }
            } else {
                foreach ($public['videos'] ?? [] as $i => $video) {
                    $date = $video['video_on'] ?? null;
                    if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || $date < $f['from'] || $date > $f['to']) {
                        continue;
                    }
                    $this->collectRow($rows, $f, ['_id' => $report->id.'|'.$i, '_sort' => $date.'|'.$report->id.'|'.$i, 'date' => $date,
                        'recorder' => $video['teacher_name'] ?? null, 'source' => $display['frozen'] ? 'Salinan persetujuan' : 'Data periode saat ini',
                        'topic' => $video['topic'] ?? null, 'description' => $video['description'] ?? null, 'url' => $video['video_url'] ?? null] + $base, $meta);
                }
            }
        }
    }

    private function quarterly(User $actor, array $f, Collection $rows): void
    {
        $query = $this->commonQuery($this->operations->quarterly($actor), $f)->whereHas('cycle', fn ($c) => $c
            ->whereRaw('SUBSTR(start_month, 1, 7) <= ?', [substr($f['to'], 0, 7)])->whereRaw('SUBSTR(end_month, 1, 7) >= ?', [substr($f['from'], 0, 7)]))
            ->with(['student', 'teacher.user', 'branch', 'cycle']);
        foreach ($query->orderBy('id')->lazy(100) as $r) {
            $state = $this->portal->quarterlyState($r);
            $this->collectRow($rows, $f, ['_id' => $r->id, '_sort' => $r->cycle?->start_month?->toDateString().'|'.$r->id,
                'student' => $r->student?->full_name, 'branch' => $r->branch?->name, 'teacher' => $r->teacher?->user?->full_name,
                'start' => $r->cycle?->start_month?->toDateString(), 'end' => $r->cycle?->end_month?->toDateString(),
                'status' => MonthlyReportContentService::STATUSES[$r->status] ?? $r->status, 'due' => $r->cycle?->share_due_on?->toDateString(),
                'ready' => $state['ready'] ? 'Tersedia' : 'Belum siap', 'reasons' => implode("\n", $state['reasons'])],
                ['branch_id' => $r->branch_id, 'teacher_id' => $r->teacher_id, 'grade' => $r->student?->grade_name, 'status' => $r->status]);
        }
    }

    private function availability(User $actor, array $f, Collection $rows): void
    {
        $query = $this->commonQuery($this->access->availability($actor), $f)->whereBetween('available_on', [$f['from'], $f['to']])->with(['teacher.user', 'branch']);
        foreach ($query->orderBy('id')->lazy(150) as $a) {
            $this->collectRow($rows, $f, ['_id' => $a->id, '_sort' => $a->available_on?->toDateString().'|'.$a->starts_at.'|'.$a->id,
                'date' => $a->available_on?->toDateString(), 'start' => $a->starts_at, 'end' => $a->ends_at,
                'teacher' => $a->teacher?->user?->full_name, 'branch' => $a->branch?->name ?? 'Cabang belum ditetapkan',
                'class_type' => $a->class_type, 'status' => $a->availability_status],
                ['branch_id' => $a->branch_id, 'teacher_id' => $a->teacher_id, 'class_type' => $a->class_type, 'status' => $a->availability_status]);
        }
    }

    private function filterLabels(array $f, array $options, array $statuses): array
    {
        $labels = ['Rentang tanggal' => CarbonImmutable::parse($f['from'])->format('d/m/Y').' – '.CarbonImmutable::parse($f['to'])->format('d/m/Y'),
            'Cabang' => $f['branch_id'] === '' ? 'Semua dalam cakupan' : $options['branches']->firstWhere('id', $f['branch_id'])?->name,
            'Teacher' => $f['teacher_id'] === '' ? 'Semua dalam cakupan' : $options['teachers']->firstWhere('id', $f['teacher_id'])?->user?->full_name];
        foreach (['program_id' => 'Program', 'class_type' => 'Jenis kelas', 'grade' => 'Kelas sekolah', 'q' => 'Pencarian', 'mode' => 'Mode absensi', 'location' => 'Tempat mengajar'] as $key => $name) {
            if ($f[$key] !== '') {
                $labels[$name] = $key === 'program_id' ? $options['programs']->firstWhere('id', $f[$key])?->name : $f[$key];
            }
        }
        $labels['Status'] = $f['status'] === 'all' ? 'Semua status' : ($statuses[$f['status']] ?? $f['status']);

        return $labels;
    }
}
