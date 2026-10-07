<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Carbon;

class TeacherPolicy
{
    /**
     * Menentukan apakah user boleh melihat profil teacher.
     */
    public function view(User $user, Teacher $teacher): bool
    {
        return $this->canAccessTeacher($user, $teacher);
    }

    /**
     * Menentukan apakah user boleh mengubah profil teacher.
     */
    public function update(User $user, Teacher $teacher): bool
    {
        return $this->canAccessTeacher($user, $teacher);
    }

    /**
     * Mengecek akses berdasarkan teacher branch assignment
     * dan branch assignment admin yang masih aktif hari ini.
     */
    private function canAccessTeacher(
        User $user,
        Teacher $teacher
    ): bool {
        // Superadmin dapat mengakses seluruh teacher.
        if ($user->role_code === 'superadmin') {
            return true;
        }

        // Selain admin tidak memiliki akses.
        if (
            $user->role_code !== 'admin'
            || ! $user->is_active
        ) {
            return false;
        }

        $today = Carbon::today();

        /*
         * Admin hanya boleh mengakses teacher jika:
         *
         * 1. Teacher memiliki branch assignment yang aktif.
         * 2. Admin memiliki branch assignment yang aktif.
         * 3. Branch tersebut sama.
         */
        return $teacher->branchAssignments()
            ->whereDate('starts_on', '<=', $today)
            ->where(function ($query) use ($today) {
                $query
                    ->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $today);
            })
            ->whereIn('branch_id', function ($query) use ($user, $today) {
                $query
                    ->select('branch_id')
                    ->from('branch_admin_assignments')
                    ->where('admin_user_id', $user->id)
                    ->whereDate('starts_on', '<=', $today)
                    ->where(function ($query) use ($today) {
                        $query
                            ->whereNull('ends_on')
                            ->orWhereDate('ends_on', '>=', $today);
                    });
            })
            ->exists();
    }
}