<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuarterlyReportFileStoreRequest;
use App\Models\FileAsset;
use App\Models\ReportCycle;
use App\Models\User;
use App\Services\QuarterlyReportFileService;
use Illuminate\Http\JsonResponse;

class QuarterlyReportFileController extends Controller
{
    public function __construct(
        private readonly QuarterlyReportFileService $service
    ) {}

    public function store(
        QuarterlyReportFileStoreRequest $request
    ): JsonResponse {
        $quarterlyReportFile = $this->service->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Quarterly report file created successfully.',
            'data' => $quarterlyReportFile,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $quarterlyReportFile = $this->service->findById($id);

        return response()->json([
            'data' => $quarterlyReportFile,
        ]);
    }

    public function byCycle(string $cycleId): JsonResponse
    {
        $cycle = ReportCycle::query()->findOrFail($cycleId);

        $quarterlyReportFiles = $this->service->getByCycle($cycle);

        return response()->json([
            'data' => $quarterlyReportFiles,
        ]);
    }

    public function byFile(string $fileId): JsonResponse
    {
        $file = FileAsset::query()->findOrFail($fileId);

        $quarterlyReportFile = $this->service->getByFile($file);

        return response()->json([
            'data' => $quarterlyReportFile,
        ]);
    }

    public function byGenerator(string $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);

        $quarterlyReportFiles = $this->service->getByGenerator($user);

        return response()->json([
            'data' => $quarterlyReportFiles,
        ]);
    }
}
