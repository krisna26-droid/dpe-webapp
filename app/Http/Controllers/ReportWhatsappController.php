<?php

namespace App\Http\Controllers;

use App\Models\QuarterlyReport;
use App\Models\ReportWhatsappShare;
use App\Services\ReportWhatsappService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReportWhatsappController extends Controller
{
    public function __construct(private ReportWhatsappService $service) {}

    public function index(Request $request, QuarterlyReport $quarterlyReport): View
    {
        $context = $this->service->context($request->user(), $quarterlyReport);
        $saved = $request->session()->get($this->optionsKey($request, $quarterlyReport), []);
        $options = $this->service->options(array_replace($saved, $request->only('method', 'salutation')));
        $key = (string) Str::uuid();
        $message = $this->service->message($context, $options['method'], $options['salutation']);

        return view('quarterly-reports.whatsapp', ['context' => $context, 'options' => $options, 'share' => null,
            'requestKey' => $key, 'message' => $message,
            'expectedSnapshot' => $this->service->previewSignature($context, $options, $key, $message)] + $this->common($request, $quarterlyReport));
    }

    public function preview(Request $request, QuarterlyReport $quarterlyReport): RedirectResponse
    {
        $this->service->actor($request->user(), $quarterlyReport);
        $options = $request->validate([
            'method' => ['required', 'string', Rule::in(array_keys(ReportWhatsappService::METHODS))],
            'salutation' => ['required', 'string', Rule::in(array_keys(ReportWhatsappService::SALUTATIONS))],
        ]);
        $request->session()->put($this->optionsKey($request, $quarterlyReport), $options);

        return redirect()->route($this->base($request).'.whatsapp.index', $quarterlyReport);
    }

    public function prepare(Request $request, QuarterlyReport $quarterlyReport): RedirectResponse
    {
        $share = $this->service->prepare($request->user(), $quarterlyReport, $request->only('method', 'salutation', 'request_key', 'expected_snapshot'));
        $request->session()->put($this->optionsKey($request, $quarterlyReport), [
            'method' => $share->method, 'salutation' => $share->salutation,
        ]);

        return redirect()->route($this->base($request).'.whatsapp.show', [$quarterlyReport, $share])
            ->with('success', 'Pesan siap. Buka WhatsApp dan periksa kembali sebelum menekan Kirim.');
    }

    public function show(Request $request, QuarterlyReport $quarterlyReport, ReportWhatsappShare $share): View
    {
        $context = $this->service->context($request->user(), $quarterlyReport);
        abort_unless($share->report_id === $quarterlyReport->id, 404);
        $reasons = $context['reasons'];
        if (! hash_equals($share->source_signature, $context['signature'])) {
            $reasons[] = 'Data berubah atau pesan disiapkan petugas lain. Siapkan pesan baru sebelum melanjutkan.';
        }
        $expired = $share->prepared_at->lt(now()->subMinutes(30));

        return view('quarterly-reports.whatsapp', ['context' => $context, 'share' => $share,
            'options' => ['method' => $share->method, 'salutation' => $share->salutation], 'message' => $share->message,
            'operationReasons' => array_values(array_unique($reasons)), 'expired' => $expired, 'requestKey' => null, 'expectedSnapshot' => null]
            + $this->common($request, $quarterlyReport));
    }

    public function open(Request $request, QuarterlyReport $quarterlyReport, ReportWhatsappShare $share): RedirectResponse
    {
        return redirect()->away($this->service->operation($request->user(), $quarterlyReport, $share, 'open'));
    }

    public function download(Request $request, QuarterlyReport $quarterlyReport, ReportWhatsappShare $share): Response
    {
        $bytes = $this->service->operation($request->user(), $quarterlyReport, $share, 'download');

        return response($bytes, 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="DPE-Rapor-'.$quarterlyReport->cycle->start_month->format('Y-m').'.pdf"']);
    }

    public function confirm(Request $request, QuarterlyReport $quarterlyReport, ReportWhatsappShare $share): RedirectResponse
    {
        $request->validate(['sent_confirmed' => ['required', 'accepted']]);
        $this->service->operation($request->user(), $quarterlyReport, $share, 'confirm');

        return redirect()->route($this->base($request).'.whatsapp.show', [$quarterlyReport, $share])
            ->with('success', 'Konfirmasi Anda tersimpan. Ini catatan manual, bukan bukti diterima atau dibaca dari WhatsApp.');
    }

    private function base(Request $request): string
    {
        return explode('.', $request->route()->getName())[0].'.quarterly-reports';
    }

    private function optionsKey(Request $request, QuarterlyReport $report): string
    {
        return 'report_whatsapp_preview.'.$request->user()->id.'.'.$report->id;
    }

    private function common(Request $request, QuarterlyReport $report): array
    {
        $actor = $this->service->actor($request->user(), $report);

        return ['report' => $report->fresh(['student', 'cycle', 'teacher.user', 'branch']), 'routeBase' => $this->base($request),
            'canSend' => in_array($actor->role_code, ['teacher', 'superadmin'], true),
            'methods' => ReportWhatsappService::METHODS, 'salutations' => ReportWhatsappService::SALUTATIONS,
            'shareStatuses' => ReportWhatsappService::STATUSES,
            'shares' => ReportWhatsappShare::where('report_id', $report->id)->with('actor')->orderByDesc('prepared_at')->orderByDesc('id')->paginate(10)->withQueryString()];
    }
}
