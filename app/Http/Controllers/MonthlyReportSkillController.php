<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonthlyReportSkillStoreRequest;
use App\Models\LearningSkill;
use App\Models\MonthlyReport;
use App\Services\MonthlyReportSkillService;
use Illuminate\Http\JsonResponse;

class MonthlyReportSkillController extends Controller
{
    public function __construct(
        private MonthlyReportSkillService $service
    ) {}

    public function store(
        MonthlyReportSkillStoreRequest $request
    ): JsonResponse {
        $reportSkill = $this->service->create(
            $request->validated()
        );

        return response()->json(
            $reportSkill,
            201
        );
    }

    public function show(
        string $reportId,
        string $skillId
    ): JsonResponse {
        $reportSkill = $this->service->find(
            $reportId,
            $skillId
        );

        return response()->json($reportSkill);
    }

    public function byReport(
        string $reportId
    ): JsonResponse {
        $report = MonthlyReport::query()
            ->findOrFail($reportId);

        return response()->json(
            $this->service->getByReport($report)
        );
    }

    public function bySkill(
        string $skillId
    ): JsonResponse {
        $skill = LearningSkill::query()
            ->findOrFail($skillId);

        return response()->json(
            $this->service->getBySkill($skill)
        );
    }

    public function update(
        MonthlyReportSkillStoreRequest $request,
        string $reportId,
        string $skillId
    ): JsonResponse {
        $reportSkill = $this->service->update(
            $reportId,
            $skillId,
            $request->validated()
        );

        return response()->json($reportSkill);
    }

    public function destroy(
        string $reportId,
        string $skillId
    ): JsonResponse {
        $this->service->delete(
            $reportId,
            $skillId
        );

        return response()->json([
            'message' => 'Monthly report skill deleted successfully.',
        ]);
    }
}
