<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ClassGroupMembership;
use App\Models\LessonSession;
use App\Models\MonthlyReport;
use App\Models\Program;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherWorkspaceService
{
    public const SLOT_TYPES = ['Regular' => 'Regular', 'Private' => 'Private'];
    public const SLOT_STATUSES = ['Available' => 'Tersedia', 'Booked' => 'Sudah terisi', 'Blocked' => 'Tidak tersedia'];

    public function __construct(
        private OperationsAccessService $operations,
        private StudentProgressAccessService $progress,
        private StudentAttendanceService $attendance,
        private BranchOperationsService $versions,
        private TeacherAvailabilityService $availability,
    ) {}

    public function actor(User $user): User
    {
        // Fresh account/profile checks also cover an already-open page after access is revoked.
        $actor = $this->operations->actor($user);
        abort_unless($actor->role_code === 'teacher', 403);
        $this->teacher($actor);

        return $actor;
    }

    public function teacher(User $actor, bool $lock = false): Teacher
    {
        $query = Teacher::query()->where('user_id', $actor->id)->where('is_active', true);
        if ($lock) { $query->lockForUpdate(); }
        $teacher = $query->first();
        abort_unless($teacher, 403, 'Profil Teacher aktif belum tersedia. Hubungi Admin.');

        return $teacher;
    }

    public function sessions(User $actor): Builder
    {
        return LessonSession::query()->where('teacher_id', $this->teacher($actor)->id);
    }

    public function slots(User $actor): Builder
    {
        return TeacherAvailability::query()->where('teacher_id', $this->teacher($actor)->id);
    }

    public function authorizeSession(User $actor, LessonSession $session): void
    {
        abort_unless($this->sessions($actor)->whereKey($session->id)->exists(), 403);
    }

    public function authorizeSlot(User $actor, TeacherAvailability $slot): void
    {
        abort_unless($this->slots($actor)->whereKey($slot->id)->exists(), 403);
    }

    public function month(Request $request): CarbonImmutable
    {
        $values = $request->validate(['month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2000-01', 'before_or_equal:2100-12']]);

        return CarbonImmutable::createFromFormat('!Y-m', $values['month'] ?? now()->format('Y-m'));
    }

    /** Same responsibility sources as operational Teacher choices, including direct pupil assignments. */
    public function branches(User $actor, string $from = '1000-01-01', string $to = '9999-12-31'): Builder
    {
        $teacherId = $this->teacher($actor)->id;

        return Branch::query()->where(function ($q) use ($teacherId, $from, $to) {
            $q->whereHas('teacherBranchAssignments', fn ($a) => $a->where('teacher_id', $teacherId)
                ->whereDate('starts_on', '<=', $to)->where(fn ($a) => $a->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from)))
                ->orWhereHas('classGroups', fn ($g) => $g->where('default_teacher_id', $teacherId)->where('is_active', true))
                ->orWhereHas('lessonSessions', fn ($s) => $s->where('teacher_id', $teacherId)
                    ->whereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) BETWEEN ? AND ?', [$from, $to])
                    ->whereRaw('LOWER(TRIM(status)) NOT IN (?, ?, ?)', ['cancelled', 'canceled', 'dibatalkan'])->notSuperseded())
                ->orWhereIn('id', MonthlyReport::where('teacher_id', $teacherId)->select('branch_id')
                    ->whereRaw('SUBSTR(report_month, 1, 7) BETWEEN ? AND ?', [substr($from, 0, 7), substr($to, 0, 7)]))
                ->orWhereIn('id', TeacherAvailability::where('teacher_id', $teacherId)->select('branch_id')
                    ->whereDate('available_on', '>=', $from)->whereDate('available_on', '<=', $to))
                ->orWhereHas('studentEnrollments', fn ($e) => $e->whereDate('starts_on', '<=', $to)
                    ->where(fn ($e) => $e->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from))
                    ->whereHas('student', fn ($s) => $s->where('status', 'active')->whereDate('began_on', '<=', $to)
                        ->where(fn ($s) => $s->whereNull('student_enrollments.ends_on')
                            ->orWhereColumn('students.began_on', '<=', 'student_enrollments.ends_on'))
                        ->whereHas('studentTeacherAssignments', fn ($a) => $a->where('teacher_id', $teacherId)
                            ->whereDate('starts_on', '<=', $to)
                            ->where(fn ($a) => $a->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from))
                            ->where(fn ($a) => $a->whereNull('ends_on')->orWhereColumn('ends_on', '>=', 'student_enrollments.starts_on'))
                            ->where(fn ($a) => $a->whereNull('student_enrollments.ends_on')
                                ->orWhereColumn('starts_on', '<=', 'student_enrollments.ends_on'))
                            ->where(fn ($a) => $a->whereNull('ends_on')->orWhereColumn('ends_on', '>=', 'students.began_on')))));
        });
    }

    public function periodFilters(Request $request, User $actor, bool $slots = false, string $defaultPeriod = 'month'): array
    {
        $values = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'branch_id' => ['nullable', 'string', 'max:64'], 'program_id' => ['nullable', 'string', 'max:64'],
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in($slots ? array_keys(self::SLOT_STATUSES) : ['scheduled', 'ongoing', 'completed', 'cancelled', 'rescheduled'])],
        ]);
        $values['from'] = $values['from'] ?? ($defaultPeriod === 'week' ? now()->startOfWeek(1) : now()->startOfMonth())->toDateString();
        $values['to'] = $values['to'] ?? ($defaultPeriod === 'week' ? now()->startOfWeek(1)->addDays(6) : now()->endOfMonth())->toDateString();
        if ($values['to'] < $values['from']) {
            throw ValidationException::withMessages(['to' => 'Tanggal sampai harus sama atau setelah tanggal mulai.']);
        }
        $visible = $slots ? $this->slots($actor) : $this->sessions($actor);
        $branches = Branch::whereIn('id', (clone $visible)->select('branch_id'))->orderBy('name')->get();
        if (! empty($values['branch_id'])) { abort_unless($branches->contains('id', $values['branch_id']), 403); }
        $programs = $slots ? collect() : Program::whereIn('id', (clone $visible)->select('program_id'))->orderBy('name')->get();
        if (! empty($values['program_id'])) { abort_unless(! $slots && $programs->contains('id', $values['program_id']), 403); }

        return ['filters' => $values, 'branches' => $branches, 'programs' => $programs];
    }

    public function sessionRows(Collection $sessions): Collection
    {
        return $sessions->map(function ($session) {
            $superseded = $this->attendance->isSuperseded($session);
            $key = $superseded ? 'rescheduled' : strtolower(trim($session->status));
            if ($session->isCancelled() && ! $superseded) { $key = 'cancelled'; }
            $status = ['scheduled' => 'Terjadwal', 'ongoing' => 'Berlangsung', 'completed' => 'Selesai',
                'cancelled' => 'Dibatalkan', 'rescheduled' => 'Memiliki pengganti'][$key] ?? $session->status;
            $at = $this->attendance->meetingAt($session);
            $end = $session->getRawOriginal('actual_end_at') ?: $session->getRawOriginal('planned_end_at');

            return ['session' => $session, 'meetingAt' => $at,
                'endsAt' => CarbonImmutable::parse($end, $at->timezoneName), 'participants' => $this->attendance->roster($session),
                'status' => $status, 'statusKey' => $key, 'superseded' => $superseded];
        });
    }

    public function sessionRelations(): array
    {
        return ['branch', 'program', 'classGroup.memberships.student.enrollments',
            'sessionStudents.student.enrollments', 'replacementSessions', 'teacherAttendance'];
    }

    public function applySessionPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->whereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) BETWEEN ? AND ?', [$from, $to]);
    }

    public function students(User $actor, CarbonImmutable $month): Builder
    {
        return $this->progress->students($actor, $month)->with([
            'enrollments' => fn ($e) => $e->whereDate('starts_on', '<=', $month->endOfMonth()->toDateString())
                ->where(fn ($e) => $e->whereNull('ends_on')->orWhereDate('ends_on', '>=', $month->toDateString()))->with(['branch', 'program']),
            'monthlyReports' => fn ($r) => $r->whereDate('report_month', $month->toDateString())
                ->whereIn('id', $this->progress->reports($actor)->select('id'))->with('branch'),
        ]);
    }

    public function studentRows(Collection $students, User $actor, CarbonImmutable $month): Collection
    {
        // Only an ownership indicator, never another Teacher's report content or identity.
        $otherOwners = MonthlyReport::whereIn('student_id', $students->pluck('id'))->whereDate('report_month', $month->toDateString())
            ->whereNotIn('id', $this->progress->reports($actor)->select('id'))->pluck('student_id')->flip();

        return $students->map(fn ($s) => ['student' => $s, 'report' => $s->monthlyReports->first(), 'otherOwner' => $otherOwners->has($s->id)]);
    }

    public function studentGroups(User $actor, Student $student, CarbonImmutable $month): Collection
    {
        $memberships = ClassGroupMembership::where('student_id', $student->id)
            ->whereHas('classGroup', fn ($g) => $g->where('default_teacher_id', $this->teacher($actor)->id)->where('is_active', true))
            ->whereDate('starts_on', '<=', $month->endOfMonth()->toDateString())
            ->where(fn ($m) => $m->whereNull('ends_on')->orWhereDate('ends_on', '>=', $month->toDateString()))
            ->with(['classGroup.branch', 'classGroup.program'])->get();

        return $memberships->filter(fn ($m) => $student->enrollments->contains(fn ($e) => $e->branch_id === $m->classGroup->branch_id
            && $e->program_id === $m->classGroup->program_id
            && max($month->toDateString(), $student->began_on->toDateString(), $m->starts_on->toDateString(), $e->starts_on->toDateString())
                <= min($month->endOfMonth()->toDateString(), $m->ends_on?->toDateString() ?? '9999-12-31', $e->ends_on?->toDateString() ?? '9999-12-31')))
            ->unique('class_group_id')->values();
    }

    public function slotOptions(User $actor, ?TeacherAvailability $current = null): Collection
    {
        $branches = $this->branches($actor)->where('is_active', true)->orderBy('name')->get();
        if ($current && ! $branches->contains('id', $current->branch_id)) {
            $branch = Branch::find($current->branch_id);
            if ($branch) { $branches->push($branch); }
        }

        return $branches;
    }

    public function saveSlot(User $user, array $data, ?TeacherAvailability $current = null): TeacherAvailability
    {
        return DB::transaction(function () use ($user, $data, $current) {
            $actor = $this->actor($user);
            $teacher = $this->teacher($actor, true); // Same serialization key as lesson scheduling.
            if ($current) {
                $current = TeacherAvailability::whereKey($current->id)->lockForUpdate()->firstOrFail();
                $this->authorizeSlot($actor, $current);
                $this->versions->checkVersion($current, $data);
            }
            $sameReference = $current && $data['branch_id'] === $current->branch_id
                && $data['available_on'] === $current->available_on->toDateString();
            if (! $sameReference && ! $this->branches($actor, $data['available_on'], $data['available_on'])
                ->where('is_active', true)->whereKey($data['branch_id'])->exists()) {
                throw ValidationException::withMessages(['branch_id' => 'Pilih cabang aktif dengan penugasan Anda yang berlaku pada tanggal slot.']);
            }
            $data['teacher_id'] = $teacher->id;

            return $current ? $this->availability->update($current, $data) : $this->availability->create($data);
        });
    }

    public function relatedSlotSessions(User $actor, TeacherAvailability $slot): Collection
    {
        // Availability edits never change existing sessions.
        $day = $slot->available_on->toDateString();
        $start = $day.' '.$slot->starts_at;
        $end = $day.' '.$slot->ends_at;

        return $this->sessions($actor)->where('branch_id', $slot->branch_id)
            ->whereRaw('LOWER(TRIM(status)) NOT IN (?, ?, ?)', ['cancelled', 'canceled', 'dibatalkan'])->notSuperseded()
            ->where('planned_start_at', '<', $end)->where('planned_end_at', '>', $start)
            ->with(['branch', 'program', 'classGroup'])->orderBy('planned_start_at')->get();
    }
}
