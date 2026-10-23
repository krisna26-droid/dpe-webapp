<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PaymentProof;
use App\Models\StudentPortalEvent;
use App\Services\PortalPaymentService;
use Illuminate\Http\Request;

class PaymentReviewController extends Controller
{
    public function __construct(private PortalPaymentService $payments) {}

    public function index(Request $request)
    {
        $this->payments->actor($request->user(), 'superadmin');
        $filters = $request->validate(['status' => ['nullable', 'in:all,pending,approved,rejected'], 'q' => ['nullable', 'string', 'max:100']]);
        $status = $filters['status'] ?? 'pending';
        $search = trim($filters['q'] ?? '');
        $proofs = PaymentProof::with(['charge.student', 'charge.branch', 'uploadedBy'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->whereHas('charge.student', fn ($q) => $q->where('full_name', 'like', '%'.$search.'%')))
            ->orderByDesc('submitted_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('superadmin.payment-review.index', compact('proofs', 'status', 'search'));
    }

    public function show(Request $request, PaymentProof $paymentProof)
    {
        $this->payments->actor($request->user(), 'superadmin');
        $paymentProof = $paymentProof->fresh(['charge.student', 'charge.branch', 'imageFile', 'uploadedBy', 'reviewedBy']);
        $snapshot = $this->payments->proofSignature($paymentProof);
        $imageAvailable = $this->payments->imageBytes($paymentProof) !== null;
        $events = StudentPortalEvent::where('payment_proof_id', $paymentProof->id)->with('actor')->orderByDesc('id')->get();

        return view('superadmin.payment-review.show', compact('paymentProof', 'snapshot', 'imageAvailable', 'events'));
    }

    public function review(Request $request, PaymentProof $paymentProof)
    {
        $this->payments->review($request->user(), $paymentProof, $request->all());

        return redirect()->route('superadmin.payment-review.show', $paymentProof)->with('success', 'Keputusan pembayaran berhasil disimpan.');
    }

    public function image(Request $request, PaymentProof $paymentProof)
    {
        $this->payments->actor($request->user(), 'superadmin');
        $bytes = $this->payments->imageBytes($paymentProof);
        abort_unless($bytes !== null, 404);

        return response($bytes)->header('Content-Type', $paymentProof->imageFile->mime_type)
            ->header('Content-Disposition', 'inline; filename="Bukti-Pembayaran-'.$paymentProof->id.'"');
    }

    public function settings(Request $request)
    {
        $this->payments->actor($request->user(), 'superadmin');
        $branches = Branch::orderBy('name')->get()->map(fn ($branch) => ['branch' => $branch, 'snapshot' => $this->payments->settingSignature($branch)]);

        return view('superadmin.payment-review.settings', compact('branches'));
    }

    public function settingSave(Request $request, Branch $branch)
    {
        $this->payments->saveVisibility($request->user(), $branch, $request->all());

        return redirect()->route('superadmin.portal-settings.index')->with('success', 'Pengaturan absensi portal untuk cabang ini berhasil disimpan.');
    }
}
