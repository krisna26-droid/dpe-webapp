<?php

namespace App\Services;

use App\Models\MonthlyCharge;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentProofService
{
    public function create(array $data): PaymentProof
    {
        return DB::transaction(function () use ($data) {
            return PaymentProof::query()->create([
                'id' => $data['id'] ?? (string) Str::uuid(),
                'charge_id' => $data['charge_id'],
                'image_file_id' => $data['image_file_id'],
                'uploaded_by_user_id' => $data['uploaded_by_user_id'],
                'submitted_at' => $data['submitted_at'] ?? now(),
                'status' => $data['status'],
                'reviewed_by_user_id' => $data['reviewed_by_user_id'] ?? null,
                'reviewed_at' => $data['reviewed_at'] ?? null,
                'rejection_reason' => $data['rejection_reason'] ?? null,
            ]);
        });
    }

    public function findById(string $id): PaymentProof
    {
        return PaymentProof::query()
            ->findOrFail($id);
    }

    public function getByCharge(
        MonthlyCharge $charge
    ) {
        return PaymentProof::query()
            ->where('charge_id', $charge->id)
            ->orderByDesc('submitted_at')
            ->get();
    }

    public function review(
        PaymentProof $paymentProof,
        User $reviewer,
        string $status,
        ?string $rejectionReason = null
    ): PaymentProof {
        return DB::transaction(function () use (
            $paymentProof,
            $reviewer,
            $status,
            $rejectionReason
        ) {
            $paymentProof->update([
                'status' => $status,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);

            return $paymentProof->refresh();
        });
    }
}
