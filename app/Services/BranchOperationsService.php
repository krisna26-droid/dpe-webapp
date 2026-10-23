<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\LessonSession;
use App\Models\MonthlyReport;
use App\Models\Program;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BranchOperationsService
{
    public function __construct(private StudentProgressAccessService $access) {}

    public function actor(User $actor): void
    {
        abort_unless($actor->is_active && in_array($actor->role_code, ['admin', 'superadmin'], true), 403);
    }

    public function branches(User $actor): Builder
    {
        $this->actor($actor);

        return Branch::query()->when($actor->role_code === 'admin',
            fn ($q) => $q->whereIn('id', $this->access->branchIds($actor)));
    }

    public function authorizeBranch(User $actor, string $branchId): void
    {
        abort_unless($this->branches($actor)->whereKey($branchId)->exists(), 403);
    }

    public function groups(User $actor): Builder
    {
        return ClassGroup::query()->whereIn('branch_id', $this->branches($actor)->select('id'));
    }

    public function memberships(User $actor): Builder
    {
        return ClassGroupMembership::query()->whereIn('class_group_id', $this->groups($actor)->select('id'));
    }

    public function sessions(User $actor): Builder
    {
        return LessonSession::query()->whereIn('branch_id', $this->branches($actor)->select('id'));
    }

    public function authorize(User $actor, Model $record): void
    {
        $query = match (true) {
            $record instanceof ClassGroup => $this->groups($actor),
            $record instanceof ClassGroupMembership => $this->memberships($actor),
            $record instanceof LessonSession => $this->sessions($actor),
            default => abort(403),
        };
        abort_unless($query->whereKey($record->getKey())->exists(), 403);
    }

    /** Include genuine student/class/session responsibilities, not just a branch assignment. */
    public function teachers(array $branchIds, string $from = '1000-01-01', string $to = '9999-12-31'): Builder
    {
        $sessionIds = LessonSession::whereIn('branch_id', $branchIds)
            ->whereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) BETWEEN ? AND ?', [$from, $to])
            ->whereRaw('LOWER(TRIM(status)) NOT IN (?, ?, ?)', ['cancelled', 'canceled', 'dibatalkan'])
            ->notSuperseded()->select('teacher_id');
        $reportIds = MonthlyReport::whereIn('branch_id', $branchIds)
            ->whereRaw('SUBSTR(report_month, 1, 7) BETWEEN ? AND ?', [substr($from, 0, 7), substr($to, 0, 7)])
            ->select('teacher_id');
        $slotIds = TeacherAvailability::whereIn('branch_id', $branchIds)->whereDate('available_on', '>=', $from)->whereDate('available_on', '<=', $to)->select('teacher_id');

        return Teacher::query()->where(function ($q) use ($branchIds, $from, $to, $sessionIds, $reportIds, $slotIds) {
            $q->whereHas('branchAssignments', fn ($a) => $a->whereIn('branch_id', $branchIds)
                ->whereDate('starts_on', '<=', $to)->where(fn ($a) => $a->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from)))
                ->orWhereHas('classGroups', fn ($g) => $g->whereIn('branch_id', $branchIds)->where('is_active', true))
                ->orWhereHas('studentAssignments', fn ($a) => $a->whereDate('starts_on', '<=', $to)
                    ->where(fn ($a) => $a->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from))
                    ->whereHas('student', fn ($s) => $s->where('status', 'active')->whereDate('began_on', '<=', $to)
                        ->where(fn ($s) => $s->whereNull('student_teacher_assignments.ends_on')
                            ->orWhereColumn('students.began_on', '<=', 'student_teacher_assignments.ends_on'))
                        ->whereHas('enrollments', fn ($e) => $e->whereIn('branch_id', $branchIds)
                            ->whereDate('starts_on', '<=', $to)
                            ->where(fn ($e) => $e->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from))
                            ->where(fn ($e) => $e->whereNull('ends_on')->orWhereColumn('ends_on', '>=', 'student_teacher_assignments.starts_on'))
                            ->where(fn ($e) => $e->whereNull('student_teacher_assignments.ends_on')
                                ->orWhereColumn('starts_on', '<=', 'student_teacher_assignments.ends_on'))
                            ->where(fn ($e) => $e->whereNull('ends_on')->orWhereColumn('ends_on', '>=', 'students.began_on')))))
                ->orWhereIn('id', $sessionIds)->orWhereIn('id', $reportIds)->orWhereIn('id', $slotIds);
        });
    }

    public function activeTeachers(array $branchIds, ?string $day = null): Builder
    {
        return $this->teachers($branchIds, $day ?? '1000-01-01', $day ?? '9999-12-31')
            ->where('is_active', true)->whereHas('user', fn ($q) => $q->where('is_active', true)->where('role_code', 'teacher'));
    }

    public function validateTeacher(User $actor, array $data, ?Model $current = null): void
    {
        $this->authorizeBranch($actor, $data['branch_id']);
        $field = $current instanceof ClassGroup || ! array_key_exists('teacher_id', $data) ? 'default_teacher_id' : 'teacher_id';
        $teacherId = $data[$field] ?? null;
        if (! $teacherId) {
            return;
        }
        $sameSchedule = ! $current instanceof LessonSession || (
            Carbon::parse($data['planned_start_at'])->eq($current->planned_start_at)
            && Carbon::parse($data['planned_end_at'])->eq($current->planned_end_at)
            && (! $current->isCancelled() || in_array($data['status'], ['cancelled', 'canceled', 'dibatalkan'], true)));
        if ($current && $current->branch_id === $data['branch_id'] && $current->getAttribute($field) === $teacherId && $sameSchedule) {
            return; // Existing historical references are retained; the shared service checks other changes.
        }
        $day = isset($data['planned_start_at']) ? Carbon::parse($data['planned_start_at'])->toDateString() : null;
        if (! $this->activeTeachers([$data['branch_id']], $day)->whereKey($teacherId)->exists()) {
            throw ValidationException::withMessages([$field => 'Pilih Teacher aktif yang memiliki penugasan cabang, siswa, kelas, atau sesi pada cabang/periode ini.']);
        }
    }

    public function students(User $actor): Builder
    {
        return Student::query()->whereHas('enrollments', fn ($q) => $q->whereIn('branch_id', $this->branches($actor)->select('id')));
    }

    public function availabilitySlots(User $actor): Collection
    {
        return TeacherAvailability::whereIn('branch_id', $this->branches($actor)->select('id'))
            ->whereDate('available_on', '>=', today())->with(['teacher.user', 'branch'])
            ->orderBy('available_on')->orderBy('starts_at')->limit(50)->get();
    }

    public function options(User $actor, ?Model $current = null): array
    {
        $branches = $this->branches($actor)->where(fn ($q) => $q->where('is_active', true)
            ->when($current?->branch_id, fn ($q, $id) => $q->orWhere('id', $id)))->orderBy('name')->get();
        $ids = $branches->pluck('id')->all();
        $teacherBranches = [];
        foreach ($ids as $id) {
            foreach ($this->activeTeachers([$id])->pluck('id') as $teacherId) {
                $teacherBranches[$teacherId][] = $id;
            }
        }
        $field = $current instanceof ClassGroup ? 'default_teacher_id' : 'teacher_id';
        $selected = $current?->getAttribute($field);
        if ($selected && in_array($current->branch_id, $ids, true)) {
            $teacherBranches[$selected] = array_values(array_unique([...($teacherBranches[$selected] ?? []), $current->branch_id]));
        }
        $teachers = Teacher::with('user')->whereIn('id', array_keys($teacherBranches))->get()
            ->sortBy(fn ($t) => mb_strtolower($t->user?->full_name ?: $t->user?->username ?: $t->id))->values();
        $programs = Program::where(fn ($q) => $q->where('is_active', true)
            ->when($current?->program_id, fn ($q, $id) => $q->orWhere('id', $id)))->orderBy('name')->get();
        $classGroups = $this->groups($actor)->where(fn ($q) => $q->where('is_active', true)
            ->when($current?->class_group_id, fn ($q, $id) => $q->orWhere('id', $id)))
            ->with(['branch', 'program'])->orderBy('name')->get();
        $reschedulableSessions = $this->sessions($actor)->with(['branch', 'program'])
            ->where(fn ($q) => $q->notSuperseded()->when($current?->rescheduled_from_session_id,
                fn ($q, $id) => $q->orWhere('id', $id)))
            ->when($current instanceof LessonSession, fn ($q) => $q->whereKeyNot($current->id))
            ->orderByDesc('planned_start_at')->limit(100)->get();
        if ($current instanceof LessonSession && $current->rescheduled_from_session_id
            && ! $reschedulableSessions->contains('id', $current->rescheduled_from_session_id)) {
            $source = $this->sessions($actor)->whereKey($current->rescheduled_from_session_id)->with(['branch', 'program'])->first();
            if ($source) { $reschedulableSessions->push($source); }
        }

        return compact('branches', 'teachers', 'teacherBranches', 'programs', 'classGroups', 'reschedulableSessions')
            + ['operationRoutePrefix' => 'admin', 'recordVersion' => $current ? $this->version($current) : null];
    }

    public function filters(Request $request, string $kind): array
    {
        $actor = $request->user();
        $values = $request->validate([
            'q' => ['nullable', 'string', 'max:120'], 'branch_id' => ['nullable', 'string', Rule::exists('branches', 'id')],
            'program_id' => ['nullable', 'string', Rule::exists('programs', 'id')],
            'teacher_id' => ['nullable', 'string', Rule::exists('teachers', 'id')],
            'status' => ['nullable', Rule::in($kind === 'sessions' ? ['scheduled', 'ongoing', 'completed', 'cancelled'] : ['active', 'inactive'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => array_merge(['nullable', 'date_format:Y-m-d'], $request->filled('from') ? ['after_or_equal:from'] : []),
        ]);
        if (! empty($values['branch_id'])) { $this->authorizeBranch($actor, $values['branch_id']); }
        $branches = $this->branches($actor)->orderBy('name')->get();
        $teachers = $this->teachers($branches->pluck('id')->all())->with('user')->get()->sortBy('user.full_name')->values();
        if (! empty($values['teacher_id'])) {
            abort_unless($teachers->contains('id', $values['teacher_id']), 403);
        }

        return ['filters' => $values, 'operationsFilterOptions' => [
            'kind' => $kind, 'branches' => $branches, 'teachers' => $teachers, 'programs' => Program::orderBy('name')->get(),
            'action' => route('admin.'.match ($kind) { 'groups' => 'class-groups', 'memberships' => 'class-group-memberships', default => 'lesson-sessions' }.'.index'),
        ], 'operationRoutePrefix' => 'admin'];
    }

    public function version(Model $record): string
    {
        $values = array_intersect_key($record->getRawOriginal(), array_flip($record->getFillable()));
        ksort($values);

        return hash_hmac('sha256', $record::class.'|'.json_encode($values, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function checkVersion(Model $record, array $data): void
    {
        if (! hash_equals($this->version($record), (string) ($data['record_version'] ?? ''))) {
            throw ValidationException::withMessages(['record_version' => 'Data telah berubah sejak halaman dibuka. Muat ulang halaman, periksa data terbaru, lalu simpan kembali.']);
        }
    }
}
