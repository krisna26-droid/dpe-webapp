<?php

namespace App\Services;

use App\Models\ReportCycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class ReportCycleService
{
    /**
     * Membuat siklus laporan baru.
     */
    public function create(array $data): ReportCycle
    {
        $validated = $this->validateData($data);

        return DB::transaction(function () use ($validated) {
            $this->assertNoOverlap(
                $validated['student_id'],
                $validated['start_month'],
                $validated['end_month']
            );

            return ReportCycle::query()->forceCreate([
                'id' => (string) Str::uuid(),
                'student_id' => $validated['student_id'],
                'cycle_number' => $validated['cycle_number'],
                'start_month' => $validated['start_month'],
                'end_month' => $validated['end_month'],
                'share_due_on' => $validated['share_due_on'] ?? null,
            ]);
        });
    }

    /**
     * Memperbarui siklus yang sudah ada.
     *
     * Perubahan rentang tidak ditolak hanya karena laporan
     * bulanan sudah ada. Constraint database tetap berlaku.
     */
    public function update(
        ReportCycle $cycle,
        array $data
    ): ReportCycle {
        $validated = $this->validateData(
            array_merge($data, [
                'student_id' => $cycle->student_id,
            ]),
            $cycle
        );

        return DB::transaction(function () use (
            $cycle,
            $validated
        ) {
            $currentCycle = ReportCycle::query()
                ->whereKey($cycle->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertNoOverlap(
                $currentCycle->student_id,
                $validated['start_month'],
                $validated['end_month'],
                $currentCycle->id
            );

            $currentCycle->cycle_number =
                $validated['cycle_number'];

            $currentCycle->start_month =
                $validated['start_month'];

            $currentCycle->end_month =
                $validated['end_month'];

            $currentCycle->share_due_on =
                $validated['share_due_on'] ?? null;

            $currentCycle->save();

            return $currentCycle->fresh();
        });
    }

    /**
     * Memvalidasi data dan menormalkan tanggal ke awal bulan.
     */
    private function validateData(
        array $data,
        ?ReportCycle $cycle = null
    ): array {
        $rules = [
            'student_id' => [
                'required',
                'string',
                Rule::exists('students', 'id'),
            ],
            'cycle_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('report_cycles', 'cycle_number')
                    ->where(
                        fn ($query) => $query->where(
                            'student_id',
                            $data['student_id'] ?? null
                        )
                    )
                    ->ignore($cycle?->id, 'id'),
            ],
            'start_month' => [
                'required',
                'date',
            ],
            'end_month' => [
                'required',
                'date',
                'after_or_equal:start_month',
            ],
            'share_due_on' => [
                'nullable',
                'date',
            ],
        ];

        $validated = Validator::make($data, $rules)->validate();

        $startMonth = CarbonImmutable::parse(
            $validated['start_month']
        )->startOfMonth();

        $endMonth = CarbonImmutable::parse(
            $validated['end_month']
        )->startOfMonth();

        if ($endMonth->lt($startMonth)) {
            throw ValidationException::withMessages([
                'end_month' =>
                    'Periode akhir siklus tidak boleh sebelum periode awal.',
            ]);
        }

        $validated['start_month'] =
            $startMonth->toDateString();

        $validated['end_month'] =
            $endMonth->toDateString();

        if (!empty($validated['share_due_on'])) {
            $validated['share_due_on'] = CarbonImmutable::parse(
                $validated['share_due_on']
            )->toDateString();
        } else {
            $validated['share_due_on'] = null;
        }

        return $validated;
    }

    /**
     * Menolak irisan bulan dengan siklus lain milik siswa yang sama.
     * Kedua batas rentang termasuk dalam siklus.
     */
    private function assertNoOverlap(
        string $studentId,
        string $startMonth,
        string $endMonth,
        ?string $excludeCycleId = null
    ): void {
        $query = ReportCycle::query()
            ->where('student_id', $studentId)
            ->whereDate('start_month', '<=', $endMonth)
            ->whereDate('end_month', '>=', $startMonth);

        if ($excludeCycleId !== null) {
            $query->where('id', '<>', $excludeCycleId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_month' =>
                    'Rentang siklus bertumpang tindih dengan siklus lain milik siswa ini.',
            ]);
        }
    }
}
