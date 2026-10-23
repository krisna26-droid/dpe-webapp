<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportCycle;
use App\Models\Student;
use App\Services\ReportCycleService;
use App\Services\StudentProgressAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReportCycleController extends Controller
{
    public function __construct(
        private readonly ReportCycleService $reportCycleService
    ) {
    }

    /**
     * Menampilkan daftar siklus laporan.
     */
    public function index(Request $request): JsonResponse|View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $query = ReportCycle::query();
        if (($filters['q'] ?? '') !== '') {
            $query->whereHas('student', fn ($q) => $q->where('full_name', 'like', '%'.$filters['q'].'%'));
        }

        if ($request->user()->role_code === 'admin') {
            if (! $request->user()->is_active) {
                $query->whereRaw('1 = 0');
            } else {
                $today = now()->toDateString();

                $query->whereHas(
                    'student.enrollments',
                    function ($enrollmentQuery) use ($request, $today) {
                        $enrollmentQuery
                            ->whereDate('starts_on', '<=', $today)
                            ->where(function ($query) use ($today) {
                                $query
                                    ->whereNull('ends_on')
                                    ->orWhereDate('ends_on', '>=', $today);
                            })
                            ->whereIn('branch_id', function ($query) use (
                                $request,
                                $today
                            ) {
                                $query
                                    ->select('branch_id')
                                    ->from('branch_admin_assignments')
                                    ->where(
                                        'admin_user_id',
                                        $request->user()->id
                                    )
                                    ->whereDate('starts_on', '<=', $today)
                                    ->where(function ($query) use ($today) {
                                        $query
                                            ->whereNull('ends_on')
                                            ->orWhereDate('ends_on', '>=', $today);
                                    });
                            });
                    }
                );
            }
        }

        $cycles = $query
            ->orderBy('start_month')
            ->orderBy('student_id')
            ->orderBy('cycle_number')
            ->paginate(15)->withQueryString();

        if (! $request->expectsJson()) {
            $access = app(StudentProgressAccessService::class);
            $cycles->getCollection()->load(['student', 'monthlyReports' => fn ($q) => $q
                ->whereIn('id', $access->reports($request->user())->select('id'))->with('teacher.user')->orderBy('report_month')]);

            return view('admin.report-cycles.index', ['cycles' => $cycles, 'filters' => $filters,
                'operationRoutePrefix' => $request->user()->role_code === 'superadmin' ? 'superadmin' : 'admin']);
        }

        return response()->json([
            'data' => $cycles->items(),
            'meta' => [
                'current_page' => $cycles->currentPage(),
                'last_page' => $cycles->lastPage(),
                'per_page' => $cycles->perPage(),
                'total' => $cycles->total(),
            ],
        ]);
    }

    /**
     * Membuat siklus laporan.
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->filled('student_id')) {
            $validatedStudent = $request->validate([
                'student_id' => [
                    'required',
                    'string',
                    Rule::exists('students', 'id'),
                ],
            ]);

            Gate::authorize(
                'view',
                Student::query()->findOrFail(
                    $validatedStudent['student_id']
                )
            );
        }

        $cycle = $this->reportCycleService->create(
            $request->only([
                'student_id',
                'cycle_number',
                'start_month',
                'end_month',
                'share_due_on',
            ])
        );

        return response()->json([
            'message' => 'Siklus laporan berhasil dibuat.',
            'data' => $cycle,
        ], 201);
    }

    /**
     * Memperbarui siklus laporan.
     */
    public function update(
        Request $request,
        ReportCycle $reportCycle
    ): JsonResponse {
        Gate::authorize(
            'view',
            Student::query()->findOrFail($reportCycle->student_id)
        );

        $cycle = $this->reportCycleService->update(
            $reportCycle,
            $request->only([
                'cycle_number',
                'start_month',
                'end_month',
                'share_due_on',
            ])
        );

        return response()->json([
            'message' => 'Siklus laporan berhasil diperbarui.',
            'data' => $cycle,
        ]);
    }
}
