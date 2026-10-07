<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\MonthlyCharge;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MonthlyChargeService
{
    public function create(array $data): MonthlyCharge
    {
        return DB::transaction(function () use ($data) {
            $student = Student::query()
                ->findOrFail($data['student_id']);

            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            return MonthlyCharge::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'branch_id' => $branch->id,
                'charge_month' => $data['charge_month'],
                'amount_idr' => $data['amount_idr'],
                'due_on' => $data['due_on'],
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
            $student = Student::query()
                ->findOrFail($data['student_id']);

            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $monthlyCharge->update([
                'student_id' => $student->id,
                'branch_id' => $branch->id,
                'charge_month' => $data['charge_month'],
                'amount_idr' => $data['amount_idr'],
                'due_on' => $data['due_on'],
            ]);

            return $monthlyCharge->refresh();
        });
    }

    public function delete(
        MonthlyCharge $monthlyCharge
    ): void {
        DB::transaction(function () use ($monthlyCharge) {
            $monthlyCharge->delete();
        });
    }
}
