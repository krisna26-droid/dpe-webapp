<?php

namespace App\Services;

use App\Models\LessonSession;
use App\Models\MonthlyReport;
use App\Models\QuarterlyReport;
use App\Models\QuarterlyReportFile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StudentPortalService
{
    public function __construct(private PortalPaymentService $payments, private MonthlyReportContentService $monthly,
        private QuarterlyReportContentService $quarterly, private StudentAttendanceService $attendance) {}

    public function student(User $actor): ?Student
    {
        $actor = $this->payments->actor($actor, 'student');

        return Student::where('portal_user_id', $actor->id)->first();
    }

    public function requireStudent(User $actor): Student
    {
        $student = $this->student($actor);
        abort_unless($student, 403, 'Akun belum terhubung dengan profil siswa. Hubungi Admin.');

        return $student;
    }

    public function monthlyState(MonthlyReport $report): array
    {
        $reasons = [];
        if ($report->status !== 'approved') {
            $reasons[] = 'Laporan bulan ini belum disetujui Admin.';
        }
        if (! $this->payments->paid($report->student_id, $report->report_month->format('Y-m'))) {
            $reasons[] = 'Pembayaran bulan ini belum tervalidasi oleh Super Admin.';
        }
        $snapshot = $report->approved_snapshot;
        if ($report->status === 'approved' && (! is_array($snapshot) || ($snapshot['snapshot_version'] ?? null) !== 2
            || ($snapshot['report_id'] ?? null) !== $report->id || ($snapshot['student_id'] ?? null) !== $report->student_id
            || ($snapshot['cycle_id'] ?? null) !== $report->cycle_id || ($snapshot['branch_id'] ?? null) !== $report->branch_id
            || ($snapshot['report_month'] ?? null) !== $report->report_month->toDateString()
            || ! is_array($snapshot['public_content'] ?? null)
            || ($snapshot['public_content']['report_month'] ?? null) !== $report->report_month->toDateString())) {
            $reasons[] = 'Salinan laporan yang disetujui belum tersedia. Hubungi Admin.';
        }

        return ['ready' => ! $reasons, 'reasons' => $reasons];
    }

    public function monthlyPublic(MonthlyReport $report): array
    {
        abort_unless($this->monthlyState($report)['ready'], 403);
        $display = $this->monthly->display($report);
        $public = Arr::only($display['public'], ['student_name', 'began_on', 'branch_name', 'teacher_name', 'report_month', 'video_target',
            'development_summary', 'parent_challenges_summary', 'parent_message', 'skills', 'videos']);
        $report->loadMissing('branch');
        $attendance = $report->branch?->portal_attendance_visible ? $this->monthly->attendanceSummary($report) : null;

        return ['content' => $public, 'attendance' => $attendance];
    }

    public function quarterlyState(QuarterlyReport $report): array
    {
        $report->loadMissing('cycle');
        $gate = $this->quarterly->gate($report);
        $reasons = $gate['reasons'];
        foreach ($this->quarterly->cycleErrors($report->cycle) as $error) {
            $reasons[] = $error;
        }
        $snapshot = $report->approved_snapshot;
        if ($report->student_id !== $report->cycle->student_id || ($report->status === 'approved' && (! is_array($snapshot)
            || ($snapshot['report_id'] ?? null) !== $report->id || ($snapshot['student_id'] ?? null) !== $report->student_id
            || ($snapshot['cycle_id'] ?? null) !== $report->cycle_id || ($snapshot['revision_number'] ?? null) !== $report->revision_number
            || ($snapshot['start_month'] ?? null) !== $report->cycle->start_month->toDateString()
            || ($snapshot['end_month'] ?? null) !== $report->cycle->end_month->toDateString()))) {
            $reasons[] = 'Salinan rapor final perlu diperiksa Admin.';
        }
        $file = null;
        if (! $reasons) {
            $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
            $file = $report->files()->where('approval_revision', $report->revision_number)->where('source_snapshot_hash', $hash)->orderByDesc('version_number')->first();
            if (! $file || app(QuarterlyReportPdfService::class)->verifiedBytes($file) === null) {
                $file = null;
                $reasons[] = 'PDF final belum tersedia. Teacher atau Admin perlu menyiapkan file rapor.';
            }
        }

        return ['ready' => ! $reasons, 'reasons' => array_values(array_unique($reasons)), 'file' => $file,
            'months' => collect($gate['months'])->map(fn ($month) => Arr::only($month, ['month', 'label', 'monthly_ok', 'paid']))->all()];
    }

    public function pdf(User $actor, QuarterlyReport $report, QuarterlyReportFile $file): string
    {
        $student = $this->requireStudent($actor);
        $report = $report->fresh(['cycle']);
        abort_unless($report && $report->student_id === $student->id, 404);
        $state = $this->quarterlyState($report);
        abort_unless($state['ready'], 403);
        abort_unless($state['file']?->id === $file->id && $file->quarterly_report_id === $report->id && $file->cycle_id === $report->cycle_id, 404);
        $bytes = app(QuarterlyReportPdfService::class)->verifiedBytes($file->fresh());
        abort_unless($bytes !== null, 404);

        return $bytes;
    }

    public function sessions(Student $student, string $month): Collection
    {
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $student->loadMissing(['enrollments', 'classGroupMemberships']);
        $sessions = LessonSession::whereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) BETWEEN ? AND ?', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->where(fn ($query) => $query->whereHas('sessionStudents', fn ($q) => $q->where('student_id', $student->id))
                ->orWhereHas('classGroup.memberships', fn ($q) => $q->where('student_id', $student->id)))
            ->with(['branch', 'teacher.user', 'program', 'classGroup', 'learningSession', 'replacementSessions',
                'sessionStudents' => fn ($q) => $q->where('student_id', $student->id),
                'classGroup.memberships' => fn ($q) => $q->where('student_id', $student->id),
                'sessionStudents.student.enrollments', 'classGroup.memberships.student.enrollments'])->get();

        return $sessions->filter(fn ($session) => $this->attendance->eligibleToAttach($student, $session)
            && (! $session->class_group_id ? $session->sessionStudents->contains('student_id', $student->id) : true))
            ->map(function ($session) use ($student) {
                $date = $this->attendance->meetingAt($session);
                $superseded = $this->attendance->isSuperseded($session);
                $future = $date->isFuture();
                $row = ['id' => $session->id, 'date' => $date, 'end' => $session->actual_end_at ?: $session->planned_end_at,
                    'branch_name' => $session->branch?->name, 'teacher_name' => $session->teacher?->user?->full_name,
                    'program_name' => $session->program?->name, 'class_name' => $session->classGroup?->name, 'learning_session_name' => $session->learningSession?->name,
                    'location' => $session->isAtStudentHome() ? 'Rumah siswa' : 'Cabang', 'topic' => $session->topic,
                    'material' => $session->material, 'activity' => $session->activity,
                    'future' => $future, 'cancelled' => $session->isCancelled(), 'superseded' => $superseded,
                    'label' => $session->isCancelled() ? 'Dibatalkan' : ($superseded ? 'Jadwal pengganti tersedia' : ($future ? 'Terjadwal' : 'Sudah berlangsung'))];
                if ($session->branch?->portal_attendance_visible && ! $future) {
                    $cell = $this->attendance->cell($session, $student);
                    $row['attendance_label'] = $cell['label'];
                    $row['attendance_summary'] = $cell['contribution'];
                }

                return $row;
            })->sortBy('date')->values();
    }

    public function meetingProgress(Student $student, string $month, Collection $sessions): array
    {
        $start = Carbon::parse($month.'-01');
        $end = $start->copy()->endOfMonth();
        $student->loadMissing('enrollments.branch');
        $enrollments = $student->enrollments->filter(fn ($enrollment) => $enrollment->starts_on->toDateString() <= $end->toDateString()
            && (! $enrollment->ends_on || $enrollment->ends_on->toDateString() >= $start->toDateString()));
        $visibleSessions = $sessions->filter(fn ($row) => ! $row['future'] && array_key_exists('attendance_summary', $row));
        $visible = $visibleSessions->isNotEmpty() || $enrollments->isEmpty()
            || $enrollments->contains(fn ($enrollment) => (bool) $enrollment->branch?->portal_attendance_visible);
        $recorded = $visibleSessions->sum(fn ($row) => $row['attendance_summary']['meeting_count']
            - $row['attendance_summary']['unrecorded'] - $row['attendance_summary']['unknown']);
        $hiddenBranch = $enrollments->contains(fn ($enrollment) => ! $enrollment->branch?->portal_attendance_visible);
        $meta = $start->locale('id')->translatedFormat('F Y').' · '.($hiddenBranch ? 'Catatan cabang yang ditampilkan' : 'Absensi tercatat');

        return ['value' => $visible ? $recorded.'/8' : '—', 'recorded_count' => $visible ? $recorded : null,
            'target' => 8, 'meta' => $visible ? $meta : 'Absensi disembunyikan oleh cabang'];
    }

    public function attendanceMonths(Student $student, array $months): Collection
    {
        return collect($months)->map(function (string $month) use ($student) {
            $sessions = $this->sessions($student, $month)
                ->filter(fn ($row) => ! $row['future'] && array_key_exists('attendance_label', $row))->values();
            $summary = $this->attendance->summarize(collect());
            foreach ($sessions as $session) {
                foreach ($summary as $key => $value) {
                    $summary[$key] += $session['attendance_summary'][$key];
                }
            }

            return ['month' => $month, 'label' => Carbon::parse($month.'-01')->locale('id')->translatedFormat('F Y'),
                'sessions' => $sessions, 'summary' => $summary];
        });
    }

    public function profile(Student $student): array
    {
        $student->loadMissing(['enrollments.branch', 'enrollments.program', 'studentTeacherAssignments.teacher.user']);
        $activeEnrollments = $student->enrollments->filter(function ($enrollment) {
            $zone = $enrollment->branch?->timezone_name;
            $zone = in_array($zone, timezone_identifiers_list(), true) ? $zone : config('app.timezone');

            return $enrollment->isActiveOn(Carbon::today($zone));
        });
        $teachers = $student->studentTeacherAssignments->filter(function ($assignment) {
            $day = now()->toDateString();

            return $assignment->starts_on->toDateString() <= $day && (! $assignment->ends_on || $assignment->ends_on->toDateString() >= $day);
        })->pluck('teacher.user.full_name')->filter()->unique()->values()->all();

        return ['name' => $student->full_name, 'school' => $student->school_name, 'grade' => $student->grade_name,
            'began_on' => $student->began_on, 'status' => $student->status,
            'enrollments' => $activeEnrollments->map(fn ($row) => ['branch' => $row->branch?->name, 'program' => $row->program?->name])->values()->all(), 'teachers' => $teachers];
    }
}
