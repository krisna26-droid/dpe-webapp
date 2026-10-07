<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportStatusEventStoreRequest;
use App\Models\MonthlyReport;
use App\Models\ReportStatusEvent;
use App\Models\User;
use App\Services\ReportStatusEventService;
use Illuminate\Http\JsonResponse;

class ReportStatusEventController extends Controller
{
    public function __construct(
        private ReportStatusEventService $service
    ) {}

    public function store(
        ReportStatusEventStoreRequest $request
    ): JsonResponse {
        $event = $this->service->create(
            $request->validated()
        );

        return response()->json($event, 201);
    }

    public function show(string $id): JsonResponse
    {
        $event = $this->service->find($id);

        return response()->json($event);
    }

    public function byReport(string $reportId): JsonResponse
    {
        $report = MonthlyReport::query()
            ->findOrFail($reportId);

        return response()->json(
            $this->service->getByReport($report)
        );
    }

    public function byActor(string $userId): JsonResponse
    {
        $user = User::query()
            ->findOrFail($userId);

        return response()->json(
            $this->service->getByActor($user)
        );
    }

    public function update(
        ReportStatusEventStoreRequest $request,
        string $id
    ): JsonResponse {
        $event = $this->service->update(
            $id,
            $request->validated()
        );

        return response()->json($event);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json([
            'message' =>
            'Report status event deleted successfully.',
        ]);
    }
}
