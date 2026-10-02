<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportCycle;
use App\Services\ReportCycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportCycleController extends Controller
{
    public function __construct(
        private readonly ReportCycleService $reportCycleService
    ) {
    }

    /**
     * Menampilkan daftar siklus laporan.
     */
    public function index(): JsonResponse
    {
        $cycles = ReportCycle::query()
            ->orderBy('start_month')
            ->orderBy('student_id')
            ->orderBy('cycle_number')
            ->paginate(15);

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
