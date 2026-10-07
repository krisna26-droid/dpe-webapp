<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentProofStoreRequest;
use App\Models\MonthlyCharge;
use App\Models\PaymentProof;
use App\Models\User;
use App\Services\PaymentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentProofController extends Controller
{
    public function __construct(
        private PaymentProofService $paymentProofService
    ) {}

    public function store(
        PaymentProofStoreRequest $request
    ): JsonResponse {
        $paymentProof = $this->paymentProofService->create(
            $request->validated()
        );

        return response()->json(
            $paymentProof,
            201
        );
    }

    public function show(
        string $paymentProof
    ): JsonResponse {
        $result = $this->paymentProofService->findById(
            $paymentProof
        );

        return response()->json($result);
    }

    public function byCharge(
        string $charge
    ): JsonResponse {
        $monthlyCharge = MonthlyCharge::query()
            ->findOrFail($charge);

        $paymentProofs = $this->paymentProofService
            ->getByCharge($monthlyCharge);

        return response()->json($paymentProofs);
    }

    public function review(
        Request $request,
        string $paymentProof
    ): JsonResponse {
        $paymentProofModel = $this->paymentProofService
            ->findById($paymentProof);

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'max:255',
            ],
            'reviewed_by_user_id' => [
                'required',
                'uuid',
                'exists:users,id',
            ],
            'rejection_reason' => [
                'nullable',
                'string',
            ],
        ]);

        $reviewer = User::query()
            ->findOrFail(
                $validated['reviewed_by_user_id']
            );

        $result = $this->paymentProofService->review(
            $paymentProofModel,
            $reviewer,
            $validated['status'],
            $validated['rejection_reason'] ?? null
        );

        return response()->json($result);
    }
}
