<?php

namespace App\Services;

use App\Models\MonthlyCharge;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentProofService
{
    public function create(array $data): PaymentProof
    {
        return DB::transaction(function () use ($data) {
            MonthlyCharge::whereKey($data['charge_id'])->lockForUpdate()->firstOrFail();
            if ($data['status'] === 'approved') {
                $this->assertSingleApproval($data['charge_id']);
            }

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
        ?string $rejectionReason = null,
        ?string $expectedSnapshot = null
    ): PaymentProof {
        if (! $reviewer->is_active || $reviewer->role_code !== 'superadmin') {
            throw new AuthorizationException('Hanya Super Admin aktif yang dapat memvalidasi pembayaran.');
        }
        if (! in_array($status, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Pilih disetujui atau ditolak.']);
        }
        $rejectionReason = $status === 'rejected' ? trim((string) $rejectionReason) : null;
        if ($status === 'rejected' && ($rejectionReason === '' || mb_strlen($rejectionReason) > 4000)) {
            throw ValidationException::withMessages(['rejection_reason' => 'Alasan penolakan wajib diisi, maksimal 4.000 karakter.']);
        }

        return DB::transaction(function () use (
            $paymentProof,
            $reviewer,
            $status,
            $rejectionReason,
            $expectedSnapshot
        ) {
            $reviewer = User::whereKey($reviewer->id)->lockForUpdate()->firstOrFail();
            if (! $reviewer->is_active || $reviewer->role_code !== 'superadmin') {
                throw new AuthorizationException('Hanya Super Admin aktif yang dapat memvalidasi pembayaran.');
            }
            $current = PaymentProof::whereKey($paymentProof->id)->firstOrFail();
            MonthlyCharge::whereKey($current->charge_id)->lockForUpdate()->firstOrFail();
            $paymentProof = PaymentProof::whereKey($current->id)->lockForUpdate()->firstOrFail();
            if ($paymentProof->charge_id !== $current->charge_id) {
                throw ValidationException::withMessages(['status' => 'Tagihan bukti berubah. Muat data terbaru sebelum memeriksa ulang.']);
            }
            if ($status === 'approved') {
                $this->assertSingleApproval($paymentProof->charge_id, $paymentProof->id);
            }
            if ($expectedSnapshot !== null) {
                app(PortalPaymentService::class)->assertSnapshot(app(PortalPaymentService::class)->proofSignature($paymentProof), ['expected_snapshot' => $expectedSnapshot]);
                if ($status === 'approved' && app(PortalPaymentService::class)->imageBytes($paymentProof) === null) {
                    throw ValidationException::withMessages(['status' => 'Gambar bukti tidak tersedia atau berubah. Periksa bukti sebelum memvalidasi.']);
                }
            }
            $before = $paymentProof->getAttributes();
            $paymentProof->update([
                'status' => $status,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);
            app(PortalPaymentService::class)->event($reviewer, 'payment_reviewed', $paymentProof->charge, $paymentProof, $before, $paymentProof->getAttributes(), $rejectionReason);

            return $paymentProof->refresh();
        });
    }

    private function assertSingleApproval(string $chargeId, ?string $ignoreId = null): void
    {
        if (PaymentProof::where('charge_id', $chargeId)->where('status', 'approved')
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            throw ValidationException::withMessages(['status' => 'Tagihan ini sudah memiliki bukti pembayaran yang disetujui. Periksa bukti tersebut terlebih dahulu.']);
        }
    }
}
