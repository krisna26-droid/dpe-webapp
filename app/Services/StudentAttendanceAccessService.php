<?php

namespace App\Services;

use App\Models\BranchAdminAssignment;
use App\Models\LessonSession;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class StudentAttendanceAccessService
{
    public function sessions(User $actor): Builder
    {
        abort_unless($actor->is_active && in_array($actor->role_code, ['superadmin', 'admin', 'teacher'], true), 403);
        $query = LessonSession::query();
        if ($actor->role_code === 'teacher') {
            $teacherId = Teacher::query()->where('user_id', $actor->id)->where('is_active', true)->value('id');

            return $query->where('teacher_id', $teacherId ?? 'no-active-teacher');
        }
        if ($actor->role_code === 'admin') {
            $branches = BranchAdminAssignment::query()->select('branch_id')
                ->where('admin_user_id', $actor->id)->whereDate('starts_on', '<=', today())
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()));
            $query->whereIn('branch_id', $branches);
        }

        return $query;
    }

    public function authorize(User $actor, LessonSession $session): void
    {
        abort_unless($this->sessions($actor)->whereKey($session->id)->exists(), 403);
    }

    public function canManageRoster(User $actor): bool
    {
        return $actor->is_active && in_array($actor->role_code, ['superadmin', 'admin'], true);
    }
}
