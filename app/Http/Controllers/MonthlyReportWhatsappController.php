<?php

namespace App\Http\Controllers;

use App\Models\MonthlyReport;
use App\Models\MonthlyReportWhatsappShare;
use App\Services\MonthlyReportWhatsappService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MonthlyReportWhatsappController extends Controller
{
    public function __construct(private MonthlyReportWhatsappService $service) {}

    public function index(Request $request, MonthlyReport $monthlyReport): View
    {
        $context = $this->service->context($request->user(), $monthlyReport);
        $saved = $request->session()->get($this->optionsKey($request, $monthlyReport), []);
        $options = $this->service->options(array_replace($saved, $request->only('method', 'salutation')));
        $key = (string) Str::uuid();
        $message = $this->service->message($context, $options['method'], $options['salutation']);

        return view('quarterly-reports.whatsapp', ['context' => $context, 'options' => $options, 'share' => null,
            'requestKey' => $key, 'message' => $message,
            'expectedSnapshot' => $this->service->previewSignature($context, $options, $key, $message)] + $this->common($request, $monthlyReport));
    }

    public function preview(Request $request, MonthlyReport $monthlyReport): RedirectResponse
    {
        $this->service->actor($request->user(), $monthlyReport);
        $options = $request->validate([
            'method' => ['required', 'string', Rule::in(array_keys(MonthlyReportWhatsappService::METHODS))],
            'salutation' => ['required', 'string', Rule::in(array_keys(MonthlyReportWhatsappService::SALUTATIONS))],
        ]);
        $request->session()->put($this->optionsKey($request, $monthlyReport), $options);

        return redirect()->route($this->base($request).'.whatsapp.index', $monthlyReport);
    }

    public function prepare(Request $request, MonthlyReport $monthlyReport): RedirectResponse
    {
        $share = $this->service->prepare($request->user(), $monthlyReport, $request->only('method', 'salutation', 'request_key', 'expected_snapshot'));
        $request->session()->put($this->optionsKey($request, $monthlyReport), [
            'method' => $share->method, 'salutation' => $share->salutation,
        ]);

        return redirect()->route($this->base($request).'.whatsapp.show', [$monthlyReport, $share])
            ->with('success', 'Pesan siap. Buka WhatsApp dan periksa kembali sebelum menekan Kirim.');
    }

    public function show(Request $request, MonthlyReport $monthlyReport, MonthlyReportWhatsappShare $share): View
    {
        $context = $this->service->context($request->user(), $monthlyReport);
        abort_unless($share->report_id === $monthlyReport->id, 404);
        $reasons = $context['reasons'];
        if (! hash_equals($share->source_signature, $context['signature'])) {
            $reasons[] = 'Data berubah atau pesan disiapkan petugas lain. Siapkan pesan baru sebelum melanjutkan.';
        }
        $expired = $share->prepared_at->lt(now()->subMinutes(30));

        return view('quarterly-reports.whatsapp', ['context' => $context, 'share' => $share,
            'options' => ['method' => $share->method, 'salutation' => $share->salutation], 'message' => $share->message,
            'operationReasons' => array_values(array_unique($reasons)), 'expired' => $expired, 'requestKey' => null, 'expectedSnapshot' => null]
            + $this->common($request, $monthlyReport));
    }

    public function open(Request $request, MonthlyReport $monthlyReport, MonthlyReportWhatsappShare $share): RedirectResponse
    {
        return redirect()->away($this->service->operation($request->user(), $monthlyReport, $share, 'open'));
    }

    public function download(Request $request, MonthlyReport $monthlyReport, MonthlyReportWhatsappShare $share): Response
    {
        $bytes = $this->service->operation($request->user(), $monthlyReport, $share, 'download');

        return response($bytes, 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="DPE-Rapor-Bulanan-'.$monthlyReport->report_month->format('Y-m').'.pdf"']);
    }

    public function confirm(Request $request, MonthlyReport $monthlyReport, MonthlyReportWhatsappShare $share): RedirectResponse
    {
        $request->validate(['sent_confirmed' => ['required', 'accepted']]);
        $this->service->operation($request->user(), $monthlyReport, $share, 'confirm');

        return redirect()->route($this->base($request).'.whatsapp.show', [$monthlyReport, $share])
            ->with('success', 'Konfirmasi Anda tersimpan. Ini catatan manual, bukan bukti diterima atau dibaca dari WhatsApp.');
    }

    public function pdfPreview(Request $request, MonthlyReport $monthlyReport): Response
    {
        $bytes = app(\App\Services\MonthlyReportPdfService::class)->preview($request->user(), $monthlyReport);

        return response($bytes, 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="DPE-Rapor-Bulanan-'.$monthlyReport->report_month->format('Y-m').'.pdf"']);
    }

    private function base(Request $request): string
    {
        return explode('.', $request->route()->getName())[0].'.monthly-reports';
    }

    private function optionsKey(Request $request, MonthlyReport $report): string
    {
        return 'monthly_report_whatsapp_preview.'.$request->user()->id.'.'.$report->id;
    }

    private function common(Request $request, MonthlyReport $report): array
    {
        $actor = $this->service->actor($request->user(), $report);

        return ['reportLabel' => 'Laporan bulanan', 'report' => $report->fresh(['student', 'cycle', 'teacher.user', 'branch']), 'routeBase' => $this->base($request),
            'canSend' => in_array($actor->role_code, ['teacher', 'superadmin'], true),
            'methods' => MonthlyReportWhatsappService::METHODS, 'salutations' => MonthlyReportWhatsappService::SALUTATIONS,
            'shareStatuses' => MonthlyReportWhatsappService::STATUSES,
            'shares' => MonthlyReportWhatsappShare::where('report_id', $report->id)->with('actor')->orderByDesc('prepared_at')->orderByDesc('id')->paginate(10)->withQueryString()];
    }
}
