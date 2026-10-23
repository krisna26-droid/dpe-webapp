<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherAttendanceReviewService
{
    public function __construct(private TeacherAttendanceAuditService $audit) {}

    /**
     * Mengembalikan cabang yang dapat dilihat actor pada saat ini.
     * Super Admin melihat seluruh cabang, sedangkan Admin hanya cabang
     * dengan assignment aktifnya.
     */
    public function visibleBranchIds(User $actor): Collection
    {
        if (! $actor->is_active || ! in_array($actor->role_code, ['admin', 'superadmin'], true)) {
            throw new AuthorizationException(
                'Akun ini tidak dapat mengakses pemeriksaan absensi guru.'
            );
        }

        if ($actor->role_code === 'superadmin') {
            return Branch::query()->pluck('id');
        }

        $today = now()->toDateString();

        return BranchAdminAssignment::query()
            ->where('admin_user_id', $actor->id)
            ->whereDate('starts_on', '<=', $today)
            ->where(function ($query) use ($today) {
                $query
                    ->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $today);
            })
            ->pluck('branch_id');
    }

    public function authorizeView(
        TeacherAttendance $attendance,
        User $actor
    ): void {
        if (! $actor->is_active || ! in_array($actor->role_code, ['admin', 'superadmin'], true)) {
            throw new AuthorizationException(
                'Akun ini tidak dapat mengakses pemeriksaan absensi guru.'
            );
        }

        if ($actor->role_code === 'superadmin') {
            return;
        }

        if (! $this->visibleBranchIds($actor)->contains($attendance->branch_id)) {
            throw new AuthorizationException(
                'Admin tidak memiliki penugasan aktif pada cabang absensi ini.'
            );
        }
    }

    public function review(
        TeacherAttendance $attendance,
        User $actor,
        string $status,
        ?string $reviewNote = null
    ): TeacherAttendance {
        $this->authorizeView($attendance, $actor);

        $reviewNote = trim((string) $reviewNote);

        if ($status === 'needs_correction' && $reviewNote === '') {
            throw ValidationException::withMessages([
                'review_note' => 'Catatan wajib diisi saat meminta koreksi.',
            ]);
        }

        if (! in_array($status, ['approved', 'needs_correction'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Hasil pemeriksaan tidak valid.',
            ]);
        }

        return DB::transaction(function () use (
            $attendance,
            $actor,
            $status,
            $reviewNote
        ) {
            $lockedAttendance = TeacherAttendance::query()
                ->whereKey($attendance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeView($lockedAttendance, $actor);

            if ($lockedAttendance->status !== 'pending_review') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya absensi yang menunggu pemeriksaan yang dapat diproses.',
                ]);
            }

            $lockedAttendance->update([
                'status' => $status,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $reviewNote !== '' ? $reviewNote : null,
            ]);
            $this->audit->record($lockedAttendance, $status, $actor->id, $reviewNote ?: null);

            return $lockedAttendance->fresh([
                'session',
                'teacher.user',
                'branch',
                'reviewedBy',
            ]);
        });
    }
}
