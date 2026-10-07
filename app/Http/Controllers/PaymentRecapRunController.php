<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRecapRunStoreRequest;
use App\Models\Branch;
use App\Services\PaymentRecapRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentRecapRunController extends Controller
{
    public function __construct(
        private PaymentRecapRunService $paymentRecapRunService
    ) {}

    public function store(
        PaymentRecapRunStoreRequest $request
    ): JsonResponse {
        $paymentRecapRun = $this->paymentRecapRunService->create(
            $request->validated()
        );

        return response()->json(
            $paymentRecapRun,
            201
        );
    }

    public function show(
        string $paymentRecapRun
    ): JsonResponse {
        $result = $this->paymentRecapRunService->findById(
            $paymentRecapRun
        );

        return response()->json($result);
    }

    public function byBranch(
        string $branch
    ): JsonResponse {
        $branchModel = Branch::query()->findOrFail(
            $branch
        );

        $paymentRecapRuns = $this->paymentRecapRunService->getByBranch(
            $branchModel
        );

        return response()->json(
            $paymentRecapRuns
        );
    }

    public function markGenerated(
        Request $request,
        string $paymentRecapRun
    ): JsonResponse {
        $paymentRecapRunModel = $this->paymentRecapRunService->findById(
            $paymentRecapRun
        );

        $validated = $request->validate([
            'generated_by_user_id' => [
                'required',
                'uuid',
                'exists:users,id',
            ],

            'export_file_id' => [
                'nullable',
                'uuid',
                'exists:file_assets,id',
            ],
        ]);

        $user = \App\Models\User::query()->findOrFail(
            $validated['generated_by_user_id']
        );

        $result = $this->paymentRecapRunService->markGenerated(
            $paymentRecapRunModel,
            $user,
            $validated['export_file_id'] ?? null
        );

        return response()->json($result);
    }
}
