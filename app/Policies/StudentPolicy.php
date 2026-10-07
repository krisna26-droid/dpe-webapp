<?php

namespace App\Policies;

use App\Models\BranchAdminAssignment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;

class StudentPolicy
{
    /**
     * Menentukan apakah user boleh melihat profil student.
     */
    public function view(User $user, Student $student): bool
    {
        return $this->canAccessStudent($user, $student);
    }

    /**
     * Menentukan apakah user boleh mengubah profil student.
     */
    public function update(User $user, Student $student): bool
    {
        return $this->canAccessStudent($user, $student);
    }

    /**
     * Mengecek akses admin terhadap student
     * berdasarkan branch assignment yang aktif hari ini.
     */
    private function canAccessStudent(
        User $user,
        Student $student
    ): bool {
        // Superadmin dapat mengakses seluruh student.
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
         * Cari branch yang:
         *
         * 1. Menjadi enrollment aktif student.
         * 2. Menjadi assignment aktif admin.
         *
         * Admin hanya boleh mengakses student jika
         * keduanya berada pada branch yang sama.
         */
        return $student->enrollments()
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