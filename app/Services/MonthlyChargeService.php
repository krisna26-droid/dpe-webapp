<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\MonthlyCharge;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MonthlyChargeService
{
    public function create(array $data): MonthlyCharge
    {
        return DB::transaction(function () use ($data) {
            $student = Student::query()
                ->lockForUpdate()
                ->findOrFail($data['student_id']);

            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $month = CarbonImmutable::parse($data['charge_month'])->startOfMonth();
            $this->assertUniqueMonth($student->id, $month);

            return MonthlyCharge::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'branch_id' => $branch->id,
                'charge_month' => $month->toDateString(),
                'amount_idr' => $data['amount_idr'] ?? null,
                'due_on' => $data['due_on'] ?? null,
            ]);
        });
    }

    public function update(
        MonthlyCharge $monthlyCharge,
        array $data
    ): MonthlyCharge {
        return DB::transaction(function () use (
            $monthlyCharge,
            $data
        ) {
            $monthlyCharge = MonthlyCharge::whereKey($monthlyCharge->id)->lockForUpdate()->firstOrFail();
            $student = Student::query()
                ->lockForUpdate()
                ->findOrFail($data['student_id']);

            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $month = CarbonImmutable::parse($data['charge_month'])->startOfMonth();
            if ($monthlyCharge->paymentProofs()->exists()) {
                foreach (['student_id' => $student->id, 'branch_id' => $branch->id, 'charge_month' => $month->toDateString()] as $field => $value) {
                    $current = $field === 'charge_month' ? $monthlyCharge->charge_month->copy()->startOfMonth()->toDateString() : $monthlyCharge->$field;
                    if ($current !== $value) {
                        throw ValidationException::withMessages([$field => 'Identitas tagihan yang sudah memiliki bukti pembayaran tidak dapat dipindahkan.']);
                    }
                }
            }
            $this->assertUniqueMonth($student->id, $month, $monthlyCharge->id);

            $monthlyCharge->update([
                'student_id' => $student->id,
                'branch_id' => $branch->id,
                'charge_month' => $month->toDateString(),
                'amount_idr' => $data['amount_idr'] ?? null,
                'due_on' => $data['due_on'] ?? null,
            ]);

            return $monthlyCharge->refresh();
        });
    }

    public function delete(
        MonthlyCharge $monthlyCharge
    ): void {
        DB::transaction(function () use ($monthlyCharge) {
            $monthlyCharge = MonthlyCharge::whereKey($monthlyCharge->id)->lockForUpdate()->firstOrFail();
            if ($monthlyCharge->paymentProofs()->exists()) {
                throw ValidationException::withMessages(['charge' => 'Tagihan dengan bukti pembayaran tidak dapat dihapus karena akan menghilangkan riwayat pembayaran.']);
            }
            $monthlyCharge->delete();
        });
    }

    private function assertUniqueMonth(string $studentId, CarbonImmutable $month, ?string $ignoreId = null): void
    {
        if (MonthlyCharge::where('student_id', $studentId)
            ->whereDate('charge_month', '>=', $month->toDateString())->whereDate('charge_month', '<=', $month->endOfMonth()->toDateString())
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            throw ValidationException::withMessages(['charge_month' => 'Siswa sudah memiliki tagihan pada bulan ini. Buka tagihan yang sudah ada.']);
        }
    }
}
