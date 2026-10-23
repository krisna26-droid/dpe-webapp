<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonthlyCharge\StoreMonthlyChargeRequest;
use App\Http\Requests\MonthlyCharge\UpdateMonthlyChargeRequest;
use App\Models\Branch;
use App\Models\MonthlyCharge;
use App\Models\Student;
use App\Services\MonthlyChargeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MonthlyChargeController extends Controller
{
    public function __construct(
        private MonthlyChargeService $monthlyChargeService
    ) {}

    public function index(): View
    {
        $monthlyCharges = MonthlyCharge::query()
            ->with([
                'student.portalUser',
                'branch',
                'paymentProofs' => function ($query) {
                    $query->latest('submitted_at');
                },
            ])
            ->latest('charge_month')
            ->latest('created_at')
            ->paginate(15);

        return view(
            'monthly-charges.index',
            compact('monthlyCharges')
        );
    }

    public function create(): View
    {
        return view(
            'monthly-charges.create',
            $this->formOptions()
        );
    }

    public function store(
        StoreMonthlyChargeRequest $request
    ): RedirectResponse {
        $monthlyCharge = $this->monthlyChargeService->create(
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.monthly-charges.show',
                $monthlyCharge
            )
            ->with(
                'success',
                'Monthly charge berhasil dibuat.'
            );
    }

    public function show(
        MonthlyCharge $monthlyCharge
    ): View {
        $monthlyCharge->load([
            'student.portalUser',
            'branch',
            'paymentProofs.imageFile',
            'paymentProofs.uploadedBy',
            'paymentProofs.reviewedBy',
        ]);

        return view(
            'monthly-charges.show',
            compact('monthlyCharge')
        );
    }

    public function edit(
        MonthlyCharge $monthlyCharge
    ): View {
        $monthlyCharge->load([
            'student.portalUser',
            'branch',
        ]);

        return view(
            'monthly-charges.edit',
            [
                'monthlyCharge' => $monthlyCharge,
                ...$this->formOptions($monthlyCharge),
            ]
        );
    }

    public function update(
        UpdateMonthlyChargeRequest $request,
        MonthlyCharge $monthlyCharge
    ): RedirectResponse {
        $monthlyCharge = $this->monthlyChargeService->update(
            $monthlyCharge,
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.monthly-charges.show',
                $monthlyCharge
            )
            ->with(
                'success',
                'Monthly charge berhasil diperbarui.'
            );
    }

    public function destroy(
        MonthlyCharge $monthlyCharge
    ): RedirectResponse {
        $this->monthlyChargeService->delete(
            $monthlyCharge
        );

        return redirect()
            ->route(
                'superadmin.monthly-charges.index'
            )
            ->with(
                'success',
                'Monthly charge berhasil dihapus.'
            );
    }

    private function formOptions(?MonthlyCharge $current = null): array
    {
        $students = Student::query()
            ->where(function ($query) use ($current) {
                $query->where('status', 'active');

                if ($current?->student_id !== null) {
                    $query->orWhere('id', $current->student_id);
                }
            })
            ->with('portalUser')
            ->orderBy('full_name')
            ->get();

        $selectedBranchId = $current?->branch_id;
        $branches = Branch::query()
            ->where(function ($query) use ($selectedBranchId) {
                $query->where('is_active', true);

                if ($selectedBranchId !== null) {
                    $query->orWhere('id', $selectedBranchId);
                }
            })
            ->orderBy('name')
            ->get();

        return [
            'students' => $students,
            'branches' => $branches,
        ];
    }
}
