<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportShareAttemptStoreRequest;
use App\Models\Guardian;
use App\Models\QuarterlyReportFile;
use App\Models\ReportShareAttempt;
use App\Models\Teacher;
use App\Services\ReportShareAttemptService;
use Illuminate\Http\JsonResponse;

class ReportShareAttemptController extends Controller
{
    public function __construct(
        private ReportShareAttemptService $service
    ) {}

    public function store(
        ReportShareAttemptStoreRequest $request
    ): JsonResponse {
        $shareAttempt = $this->service->create(
            $request->validated()
        );

        return response()->json(
            $shareAttempt,
            201
        );
    }

    public function show(string $id): JsonResponse
    {
        $shareAttempt = $this->service->findById($id);

        return response()->json($shareAttempt);
    }

    public function byQuarterlyReportFile(
        string $quarterlyReportFileId
    ): JsonResponse {
        $quarterlyReportFile = QuarterlyReportFile::query()
            ->findOrFail($quarterlyReportFileId);

        return response()->json(
            $this->service->getByQuarterlyReportFile(
                $quarterlyReportFile
            )
        );
    }

    public function byTeacher(string $teacherId): JsonResponse
    {
        $teacher = Teacher::query()
            ->findOrFail($teacherId);

        return response()->json(
            $this->service->getByTeacher($teacher)
        );
    }

    public function byGuardian(string $guardianId): JsonResponse
    {
        $guardian = Guardian::query()
            ->findOrFail($guardianId);

        return response()->json(
            $this->service->getByGuardian($guardian)
        );
    }
}
