<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MonthlyCharge\StoreMonthlyChargeRequest;
use App\Http\Requests\MonthlyCharge\UpdateMonthlyChargeRequest;
use App\Models\MonthlyCharge;
use App\Services\BranchPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MonthlyChargeController extends Controller
{
    public function index(BranchPaymentService $payments): View
    {
        $monthlyCharges = $payments->charges(auth()->user())->with(['student.portalUser', 'branch', 'paymentProofs' => fn ($q) => $q->latest('submitted_at')])
            ->latest('charge_month')->latest('created_at')->paginate(15);

        return view('monthly-charges.index', compact('monthlyCharges'));
    }

    public function create(BranchPaymentService $payments): View
    {
        return view('monthly-charges.create', $payments->formOptions(auth()->user()));
    }

    public function store(StoreMonthlyChargeRequest $request, BranchPaymentService $payments): RedirectResponse
    {
        $charge = $payments->createCharge($request->user(), $request->validated());

        return redirect()->route('admin.monthly-charges.show', $charge)->with('success', 'Tagihan bulanan berhasil dibuat.');
    }

    public function show(MonthlyCharge $monthlyCharge, BranchPaymentService $payments): View
    {
        $charge = $payments->charge(auth()->user(), $monthlyCharge->id)->load([
            'student.portalUser', 'branch', 'paymentProofs.imageFile', 'paymentProofs.uploadedBy', 'paymentProofs.reviewedBy',
        ]);

        return view('monthly-charges.show', ['monthlyCharge' => $charge]);
    }

    public function edit(MonthlyCharge $monthlyCharge, BranchPaymentService $payments): View
    {
        $charge = $payments->charge(auth()->user(), $monthlyCharge->id)->load(['student.portalUser', 'branch']);

        return view('monthly-charges.edit', ['monthlyCharge' => $charge, ...$payments->formOptions(auth()->user(), $charge)]);
    }

    public function update(UpdateMonthlyChargeRequest $request, MonthlyCharge $monthlyCharge, BranchPaymentService $payments): RedirectResponse
    {
        $charge = $payments->updateCharge($request->user(), $monthlyCharge, $request->validated());

        return redirect()->route('admin.monthly-charges.show', $charge)->with('success', 'Tagihan bulanan berhasil diperbarui.');
    }

    public function destroy(MonthlyCharge $monthlyCharge, BranchPaymentService $payments): RedirectResponse
    {
        $payments->deleteCharge(auth()->user(), $monthlyCharge);

        return redirect()->route('admin.monthly-charges.index')->with('success', 'Tagihan bulanan berhasil dihapus.');
    }
}
