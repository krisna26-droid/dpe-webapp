<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use App\Services\MonthlyReportWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonthlyReportController extends Controller
{
    public function __construct(
        private readonly MonthlyReportWorkflowService $workflowService
    ) {
    }

    /**
     * Menyetujui laporan bulanan.
     */
    public function approve(
        Request $request,
        MonthlyReport $monthlyReport
    ): JsonResponse {
        $report = $this->workflowService->approve(
            $monthlyReport,
            $request->user()
        );

        return response()->json([
            'message' => 'Laporan berhasil disetujui.',
            'data' => $report,
        ]);
    }

    /**
     * Meminta revisi laporan bulanan.
     */
    public function requestRevision(
        Request $request,
        MonthlyReport $monthlyReport
    ): JsonResponse {
        $request->validate([
            'comment' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $report = $this->workflowService->requestRevision(
            $monthlyReport,
            $request->user(),
            $request->input('comment')
        );

        return response()->json([
            'message' => 'Laporan berhasil diminta untuk direvisi.',
            'data' => $report,
        ]);
    }
}