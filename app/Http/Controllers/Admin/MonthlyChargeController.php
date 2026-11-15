<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MonthlyCharge\StoreMonthlyChargeRequest;
use App\Http\Requests\MonthlyCharge\UpdateMonthlyChargeRequest;
use App\Models\MonthlyCharge;
use App\Services\BranchPaymentService;
use App\Services\MonthlyChargeStudentFilterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonthlyChargeController extends Controller
{
    public function index(BranchPaymentService $payments): View
    {
        $monthlyCharges = $payments->charges(auth()->user())->with(['student.portalUser', 'branch', 'paymentProofs' => fn ($q) => $q->latest('submitted_at')])
            ->latest('charge_month')->latest('created_at')->paginate(15);

        return view('monthly-charges.index', compact('monthlyCharges'));
    }

    public function create(Request $request, MonthlyChargeStudentFilterService $filter): View
    {
        return view('monthly-charges.create', $filter->options($request->user(), $filter->filters($request, oldInput: true), allowUnavailable: true));
    }

    public function studentOptions(Request $request, MonthlyChargeStudentFilterService $filter): JsonResponse
    {
        return response()->json($filter->jsonOptions($request->user(), $filter->filters($request)))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreMonthlyChargeRequest $request, BranchPaymentService $payments): RedirectResponse
    {
        $data = $request->validated();
        app(MonthlyChargeStudentFilterService::class)->assertSelection($request->user(), $data);
        unset($data['teacher_filter_id']);
        $charge = $payments->createCharge($request->user(), $data);

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
