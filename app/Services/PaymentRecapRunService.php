<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\PaymentRecapRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentRecapRunService
{
    public function create(array $data): PaymentRecapRun
    {
        return DB::transaction(function () use ($data) {
            return PaymentRecapRun::query()->create([
                'id' => $data['id'] ?? (string) Str::uuid(),
                'branch_id' => $data['branch_id'],
                'recap_month' => $data['recap_month'],
                'scheduled_on' => $data['scheduled_on'],
                'status' => $data['status'],
                'generated_at' => $data['generated_at'] ?? null,
                'generated_by_user_id' => $data['generated_by_user_id'] ?? null,
                'export_file_id' => $data['export_file_id'] ?? null,
            ]);
        });
    }

    public function findById(string $id): PaymentRecapRun
    {
        return PaymentRecapRun::query()->findOrFail($id);
    }

    public function getByBranch(Branch $branch)
    {
        return PaymentRecapRun::query()
            ->where('branch_id', $branch->id)
            ->orderByDesc('recap_month')
            ->get();
    }

    public function markGenerated(
        PaymentRecapRun $paymentRecapRun,
        User $user,
        ?string $exportFileId = null
    ): PaymentRecapRun {
        return DB::transaction(function () use (
            $paymentRecapRun,
            $user,
            $exportFileId
        ) {
            $paymentRecapRun->update([
                'status' => 'generated',
                'generated_at' => now(),
                'generated_by_user_id' => $user->id,
                'export_file_id' => $exportFileId,
            ]);

            return $paymentRecapRun->refresh();
        });
    }
}
