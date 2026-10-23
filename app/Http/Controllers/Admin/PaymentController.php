<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonthlyCharge;
use App\Models\PaymentProof;
use App\Services\BranchPaymentService;
use App\Services\OperationalExportPdfWriter;
use App\Services\OperationalExportXlsxWriter;
use App\Services\PortalPaymentService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function index(Request $request, BranchPaymentService $payments)
    {
        $data = $payments->recap($request->user(), $payments->filters($request));
        $page = max(1, (int) $request->input('page', 1));
        $data['pageRows'] = new LengthAwarePaginator(array_slice($data['rows'], ($page - 1) * 15, 15), $data['total'], 15, $page,
            ['path' => route('admin.payments.index'), 'query' => $data['filters']]);

        return view('admin.payments.index', $data);
    }

    public function show(Request $request, MonthlyCharge $monthlyCharge, BranchPaymentService $payments)
    {
        return view('admin.payments.show', $payments->detail($request->user(), $monthlyCharge->id));
    }

    public function image(Request $request, PaymentProof $paymentProof, BranchPaymentService $payments, PortalPaymentService $portal)
    {
        $proof = $payments->proof($request->user(), $paymentProof->id);
        $bytes = $portal->imageBytes($proof);
        abort_unless($bytes !== null, 404);

        return response($bytes, 200, ['Content-Type' => $proof->imageFile->mime_type,
            'Content-Disposition' => 'inline; filename="Bukti-Pembayaran-'.$proof->id.'.'.['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$proof->imageFile->mime_type].'"']);
    }

    public function download(Request $request, string $format, BranchPaymentService $payments,
        OperationalExportXlsxWriter $excel, OperationalExportPdfWriter $pdf)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $filters = $payments->filters($request);
        try {
            $request->validate(['expected_snapshot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']]);
            $data = $payments->recap($request->user(), $filters);
            if (! hash_equals($data['snapshot'], (string) $request->input('expected_snapshot'))) {
                throw ValidationException::withMessages(['export' => 'Data atau filter telah berubah. Tampilkan rekap terbaru, lalu unduh kembali.']);
            }
            if ($data['total'] === 0) { throw ValidationException::withMessages(['export' => 'Belum ada tagihan sesuai filter untuk diunduh.']); }
            $bytes = $format === 'xlsx' ? $excel->render($data) : $pdf->render($data);

            return response($bytes, 200, ['Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf',
                'Content-Disposition' => 'attachment; filename="DPE_Pembayaran_'.$filters['month'].'.'.$format.'"',
                'Content-Length' => strlen($bytes), 'X-DPE-Export-Rows' => (string) $data['total']]);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) { throw $e; }

            return redirect()->route('admin.payments.index', $filters)->withErrors($e->errors());
        }
    }
}
