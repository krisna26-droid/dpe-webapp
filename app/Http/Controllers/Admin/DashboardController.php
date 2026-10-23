<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\LessonSession;
use App\Models\MonthlyReport;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::today();
        $user = $request->user();
        $branchIds = $this->branchIdsFor($user?->id, $user?->role_code, $today);

        $activeStudents = Student::query()
            ->where('status', 'active')
            ->whereHas('enrollments', function ($query) use ($branchIds, $today) {
                $query
                    ->whereIn('branch_id', $branchIds)
                    ->whereDate('starts_on', '<=', $today)
                    ->where(function ($dateQuery) use ($today) {
                        $dateQuery
                            ->whereNull('ends_on')
                            ->orWhereDate('ends_on', '>=', $today);
                    });
            })
            ->count();

        $activeTeachers = Teacher::query()
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true)->where('role_code', 'teacher'))
            ->where(function ($query) use ($branchIds, $today) {
                $active = fn ($period) => $period->whereDate('starts_on', '<=', $today)
                    ->where(fn ($end) => $end->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today));
                $query->whereHas('branchAssignments', fn ($assignment) => $active($assignment)->whereIn('branch_id', $branchIds))
                    ->orWhereHas('studentAssignments', fn ($assignment) => $active($assignment)
                        ->whereHas('student', fn ($student) => $student->where('status', 'active')
                            ->whereHas('enrollments', fn ($enrollment) => $active($enrollment)->whereIn('branch_id', $branchIds))))
                    ->orWhereHas('classGroups', fn ($group) => $group->where('is_active', true)->whereIn('branch_id', $branchIds))
                    ->orWhereIn('id', MonthlyReport::whereIn('branch_id', $branchIds)->whereDate('report_month', $today->copy()->startOfMonth())->select('teacher_id'));
            })
            ->count();

        $sessionsToday = LessonSession::query()
            ->whereIn('branch_id', $branchIds)
            ->whereDate('planned_start_at', $today)
            ->where('status', '!=', 'cancelled')
            ->count();

        $reportsForReview = MonthlyReport::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'submitted')
            ->count();

        $todaySessions = LessonSession::query()
            ->with(['branch', 'teacher.user'])
            ->whereIn('branch_id', $branchIds)
            ->whereDate('planned_start_at', $today)
            ->where('status', '!=', 'cancelled')
            ->orderBy('planned_start_at')
            ->limit(5)
            ->get();

        $pendingReports = MonthlyReport::query()
            ->with(['student', 'teacher.user', 'branch'])
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'submitted')
            ->orderBy('due_on')
            ->orderBy('report_month')
            ->limit(5)
            ->get();

        $branchNames = Branch::query()
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->pluck('name');

        $branchScopeLabel = match ($branchNames->count()) {
            0 => 'Belum ada cabang aktif',
            1 => (string) $branchNames->first(),
            default => $branchNames->count().' cabang aktif',
        };

        return view('admin.dashboard', [
            'branchScopeLabel' => $branchScopeLabel,
            'todayLabel' => $today->format('d/m/Y'),
            'activeStudents' => $activeStudents,
            'activeTeachers' => $activeTeachers,
            'sessionsToday' => $sessionsToday,
            'reportsForReview' => $reportsForReview,
            'todaySessions' => $todaySessions,
            'pendingReports' => $pendingReports,
        ]);
    }

    /**
     * Mengambil cabang yang boleh dilihat oleh admin saat ini.
     * Super admin dapat melihat seluruh cabang sehingga dashboard ini juga
     * tetap aman ketika dibuka dari menu admin oleh akun super admin.
     */
    private function branchIdsFor(
        ?string $userId,
        ?string $roleCode,
        Carbon $today
    ) {
        if ($roleCode === 'superadmin') {
            return Branch::query()->pluck('id');
        }

        if ($userId === null) {
            return collect();
        }

        return BranchAdminAssignment::query()
            ->where('admin_user_id', $userId)
            ->whereDate('starts_on', '<=', $today)
            ->where(function ($query) use ($today) {
                $query
                    ->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $today);
            })
            ->pluck('branch_id');
    }
}
