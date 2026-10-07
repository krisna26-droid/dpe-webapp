<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonthlyCharge\StoreMonthlyChargeRequest;
use App\Http\Requests\MonthlyCharge\UpdateMonthlyChargeRequest;
use App\Models\MonthlyCharge;
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
            ->with(['student', 'branch'])
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
        return view('monthly-charges.create');
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
            'student',
            'branch',
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
            'student',
            'branch',
        ]);

        return view(
            'monthly-charges.edit',
            compact('monthlyCharge')
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
}
