<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\Program;
use App\Models\User;
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
    ) {
    }

    private function actor(User $actor, bool $lock = false): User
    {
        $current = User::query()->when($lock, fn ($q) => $q->lockForUpdate())->find($actor->id);
        abort_unless($current?->is_active && $current->role_code === 'admin', 403);

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
            ->whereIn('id', $this->assignments($actor)->select('branch_id'));
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

    public function create(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data) {
            $actor = $this->actor($actor, true);
            $branchIds = $this->assignments($actor)->lockForUpdate()->get()->pluck('branch_id');
            $branches = Branch::query()->whereIn('id', $branchIds)->where('is_active', true)
                ->orderBy('id')->lockForUpdate()->get();
            $branch = $this->resolveBranch($branches, $data['branch_id'] ?? null);
            $program = Program::query()->whereKey($data['program_id'])->lockForUpdate()->first();
            if (! $program?->is_active) {
                throw ValidationException::withMessages(['program_id' => 'Pilih program belajar yang masih aktif.']);
            }

            // Whitelist fields: the caller cannot supply a role, account id or existing profile.
            $user = User::query()->create([
                'id' => (string) Str::uuid(),
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
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
                'began_on' => $data['began_on'],
            ]);
            $this->enrollments->create([
                'student_id' => $student->id,
                'branch_id' => $branch->id,
                'program_id' => $program->id,
                'starts_on' => $data['began_on'],
                'ends_on' => null,
            ]);

            return $user;
        });
    }
}
