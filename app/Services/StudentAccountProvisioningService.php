<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassLearningSession;
use App\Models\Guardian;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentAccountProvisioningService
{
    public function __construct(
        private readonly StudentService $students,
        private readonly StudentEnrollmentService $enrollments,
    ) {}

    private function actor(User $actor, bool $lock = false): User
    {
        $current = User::query()->when($lock, fn ($q) => $q->lockForUpdate())->find($actor->id);
        abort_unless($current?->is_active && in_array($current->role_code, ['admin', 'superadmin'], true), 403);

        return $current;
    }

    private function assignments(User $actor): Builder
    {
        return BranchAdminAssignment::query()->where('admin_user_id', $actor->id)
            ->whereDate('starts_on', '<=', today())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()));
    }

    public function branches(User $actor): Builder
    {
        $actor = $this->actor($actor);

        return Branch::query()->where('is_active', true)
            ->when($actor->role_code === 'admin', fn ($q) => $q->whereIn('id', $this->assignments($actor)->select('branch_id')));
    }

    public function scopeEnrollments(Builder|HasMany $query, User $actor): Builder|HasMany
    {
        // Include scheduled enrolments so a newly created account remains visible.
        // Closed enrolments in another branch never confer access to the login account.
        return $query->whereIn('branch_id', $this->branches($actor)->select('id'))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()));
    }

    public function users(User $actor): Builder
    {
        return User::query()->where('role_code', 'student')
            ->whereHas('studentProfile.enrollments', fn ($q) => $this->scopeEnrollments($q, $actor));
    }

    private function resolveBranch(Collection $branches, ?string $requested): Branch
    {
        if ($branches->isEmpty()) {
            throw ValidationException::withMessages([
                'branch_id' => 'Belum ada penugasan cabang aktif. Hubungi Super Admin sebelum membuat akun siswa.',
            ]);
        }

        $branch = $requested ? $branches->firstWhere('id', $requested)
            : ($branches->count() === 1 ? $branches->first() : null);
        if (! $branch) {
            throw ValidationException::withMessages([
                'branch_id' => 'Pilih cabang yang saat ini Anda kelola. Muat ulang form jika penugasan telah berubah.',
            ]);
        }

        return $branch;
    }

    public function options(User $actor): array
    {
        $branches = $this->branches($actor)->orderBy('name')->get();
        $accountSessions = app(ClassLearningSessionService::class)->options($actor)->filter(fn ($s) => $branches->contains('id', $s->classGroup->branch_id))->values();
        $accountTeachers = collect();
        $accountTeacherBranches = [];
        foreach ($accountSessions as $slot) {
            $zone = $slot->classGroup->branch?->timezone_name ?: config('app.timezone');
            $day = CarbonImmutable::now($zone)->toDateString();
            $lessons = $slot->lessonSessions->filter(fn ($l) => $l->status === 'scheduled' && $l->planned_start_at->toDateString() >= $day && $l->teacher?->is_active && $l->teacher?->user?->is_active && ! $l->teacher?->deleted_at && ! $l->teacher?->user?->deleted_at);
            $slot->setAttribute('account_teacher_ids', $lessons->pluck('teacher_id')->unique()->values()->all());
            foreach ($lessons as $lesson) {
                $accountTeachers->put($lesson->teacher_id, $lesson->teacher);
                $accountTeacherBranches[$lesson->teacher_id][] = $slot->classGroup->branch_id;
            }
        }
        $accountSessions = $accountSessions->filter(fn ($s) => count($s->account_teacher_ids) > 0)->values();
        $accountTeachers = $accountTeachers->sortBy(fn ($t) => $t->user?->full_name)->values();
        $accountSessionChoices = app(ClassLearningSessionService::class)->choices($accountSessions)->map(function ($choice) {
            $zone = $choice['session']->classGroup->branch?->timezone_name ?: config('app.timezone');
            $day = CarbonImmutable::now($zone)->toDateString();
            $choice['teacher_ids'] = $choice['meetings']->filter(fn ($lesson) => $lesson->status === 'scheduled' && $lesson->planned_start_at->toDateString() >= $day
                && $lesson->teacher?->is_active && $lesson->teacher?->user?->is_active && ! $lesson->teacher?->deleted_at && ! $lesson->teacher?->user?->deleted_at)
                ->pluck('teacher_id')->unique()->values()->all();

            return $choice;
        })->filter(fn ($choice) => count($choice['teacher_ids']) > 0)->values();

        return compact('branches', 'accountSessions', 'accountTeachers', 'accountTeacherBranches', 'accountSessionChoices');
    }

    public function create(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data) {
            $actor = $this->actor($actor, true);
            $branchIds = $actor->role_code === 'superadmin' ? Branch::where('is_active', true)->pluck('id') : $this->assignments($actor)->lockForUpdate()->get()->pluck('branch_id');
            $branches = Branch::query()->whereIn('id', $branchIds)->where('is_active', true)
                ->orderBy('id')->lockForUpdate()->get();
            $branch = $this->resolveBranch($branches, $data['branch_id'] ?? null);
            $zone = $branch->timezone_name ?: config('app.timezone');
            $day = CarbonImmutable::now($zone)->toDateString();
            $choices = app(ClassLearningSessionService::class)->selections($data['learning_session_ids'] ?? []);
            $ids = $choices->pluck('id')->unique()->values()->all();
            $selected = ClassLearningSession::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $teacher = Teacher::whereKey($data['teacher_id'] ?? null)->lockForUpdate()->first();
            if (! $teacher?->is_active || ! $teacher->user?->is_active || $teacher->user?->role_code !== 'teacher') {
                throw ValidationException::withMessages(['teacher_id' => 'Pilih guru dengan akun dan profil aktif.']);
            }
            if (! $ids || $selected->count() !== count($ids)) {
                throw ValidationException::withMessages(['learning_session_ids' => 'Pilih sesi belajar yang tersedia.']);
            }
            $groups = ClassGroup::whereIn('id', $selected->pluck('class_group_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($groups->pluck('program_id')->unique()->count() !== 1) {
                throw ValidationException::withMessages(['learning_session_ids' => 'Sesi siswa harus berada pada satu program belajar.']);
            }
            $program = Program::whereKey($groups->first()?->program_id)->lockForUpdate()->first();
            if (! $program?->is_active) {
                throw ValidationException::withMessages(['learning_session_ids' => 'Pilih sesi pada program aktif.']);
            }
            foreach ($choices as $choice) {
                $slot = $selected->firstWhere('id', $choice['id']);
                $group = $groups->get($slot->class_group_id);
                $lessons = LessonSession::where('class_learning_session_id', $slot->id)->where('teacher_id', $teacher->id)->where('branch_id', $branch->id)->where('program_id', $program->id)->where('class_group_id', $group?->id)->whereDate('planned_start_at', '>=', $day)->where('status', 'scheduled')->with('rescheduledFrom')->get();
                if (! $group?->is_active || $group->branch_id !== $branch->id || ! $lessons->contains(fn ($lesson) => $choice['weekday'] === null || app(ClassLearningSessionService::class)->weekday($lesson) === $choice['weekday'])) {
                    throw ValidationException::withMessages(['learning_session_ids' => 'Sesi dan hari harus sesuai cabang, guru, dan kelompok aktif serta memiliki jadwal mendatang. Muat ulang pilihan jika jadwal telah berubah.']);
                }
            }

            // Whitelist fields: the caller cannot supply a role, account id or existing profile.
            $user = User::query()->create([
                'id' => (string) Str::uuid(),
                'username' => $data['username'],
                'email' => null,
                'password_hash' => Hash::make($data['password']),
                'full_name' => $data['full_name'],
                'role_code' => 'student',
                'is_active' => true,
            ]);
            $student = $this->students->create([
                'portal_user_id' => $user->id,
                'full_name' => $data['full_name'],
                'school_name' => $data['school_name'] ?? null,
                'grade_name' => $data['grade_name'] ?? null,
                'began_on' => $day,
            ]);
            $this->enrollments->create([
                'student_id' => $student->id,
                'branch_id' => $branch->id,
                'program_id' => $program->id,
                'starts_on' => $day,
                'ends_on' => null,
            ]);

            app(StudentTeacherAssignmentService::class)->create(['student_id' => $student->id, 'teacher_id' => $teacher->id, 'starts_on' => $day, 'is_report_owner' => true]);
            Guardian::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'full_name' => 'Wali '.Str::limit($student->full_name, 250, ''), 'whatsapp_number' => preg_replace('/[^0-9+]/', '', $data['guardian_phone']), 'is_primary' => true]);
            foreach ($selected->groupBy('class_group_id') as $groupId => $slots) {
                $keys = $choices->filter(fn ($choice) => $slots->contains('id', $choice['id']))->pluck('key')->all();
                app(ClassGroupMembershipService::class)->create(['class_group_id' => $groupId, 'student_id' => $student->id, 'starts_on' => $day, 'learning_session_ids' => $keys]);
            }

            return $user;
        });
    }
}
