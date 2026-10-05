<?php

namespace App\Http\Controllers\Teacher;

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
     * Mengajukan laporan bulanan.
     */
    public function submit(
        Request $request,
        MonthlyReport $monthlyReport
    ): JsonResponse {
        $report = $this->workflowService->submit(
            $monthlyReport,
            $request->user()
        );

        return response()->json([
            'message' => 'Laporan berhasil diajukan.',
            'data' => $report,
        ]);
    }
}