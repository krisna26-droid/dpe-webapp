<?php

namespace App\Http\Controllers;

use App\Models\QuarterlyReport;
use App\Models\QuarterlyReportFile;
use App\Models\ReportCycle;
use App\Services\QuarterlyReportAccessService;
use App\Services\QuarterlyReportContentService;
use App\Services\QuarterlyReportPdfService;
use App\Services\QuarterlyReportWorkflowService;
use App\Services\ReportTemplateService;
use App\Services\ReportSignatureService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class QuarterlyReportPageController extends Controller
{
    public function __construct(private QuarterlyReportAccessService $access, private QuarterlyReportContentService $content,
        private QuarterlyReportWorkflowService $workflow, private QuarterlyReportPdfService $pdf, private ReportTemplateService $template) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['start_month' => ['nullable', 'date_format:Y-m'], 'status' => ['nullable', Rule::in(['all', 'unprepared', ...array_keys(QuarterlyReportContentService::STATUSES)])], 'q' => ['nullable', 'string', 'max:100']]);
        $filters += ['start_month' => null, 'status' => 'all', 'q' => null];
        $query = $this->access->cycles($request->user());
        if (filled($filters['start_month'])) {
            $query->whereDate('start_month', $filters['start_month'].'-01');
        }
        if ($filters['status'] === 'unprepared') {
            $query->whereDoesntHave('quarterlyReport');
        } elseif ($filters['status'] !== 'all') {
            $query->whereHas('quarterlyReport', fn ($r) => $r->where('status', $filters['status']));
        }
        if (filled($filters['q'])) {
            $query->whereHas('student', fn ($q) => $q->where('full_name', 'like', '%'.$filters['q'].'%'));
        }
        $cycles = $query->with(['student', 'quarterlyReport.teacher.user', 'quarterlyReport.branch', 'quarterlyReport.approvedBy'])->orderByDesc('start_month')->orderBy('id')->paginate(15)->withQueryString();
        $rows = $cycles->getCollection()->map(function ($cycle) use ($request) {
            $report = $cycle->quarterlyReport;
            $context = $report ? null : $this->content->preparation($cycle);

            return ['cycle' => $cycle, 'report' => $report, 'preparation' => $context,
                'canOpen' => $report && $this->access->reports($request->user())->whereKey($report->id)->exists(),
                'canPrepare' => ! $report && ! $context['errors'] && $this->access->mayPrepareAtBranch($request->user(), $context['branch_id'])];
        });

        return view('quarterly-reports.index', compact('cycles', 'rows', 'filters') + $this->common($request));
    }

    public function prepare(Request $request, ReportCycle $reportCycle): View|RedirectResponse
    {
        $this->access->authorizeCycle($request->user(), $reportCycle);
        if ($existing = $reportCycle->quarterlyReport) {
            $this->access->authorize($request->user(), $existing);

            return redirect()->route($this->base($request).'.show', $existing);
        }
        $context = $this->content->preparation($reportCycle);
        if ($context['branch_id']) {
            abort_unless($this->access->mayPrepareAtBranch($request->user(), $context['branch_id']), 403);
        }
        $owners = $this->access->owners($reportCycle);

        return view('quarterly-reports.prepare', ['cycle' => $reportCycle->load('student'), 'context' => $context, 'owners' => $owners,
            'expectedSnapshot' => $this->content->preparationSignature($reportCycle), 'ownTeacherId' => $this->access->teacherId($request->user())] + $this->common($request));
    }

    public function store(Request $request, ReportCycle $reportCycle): RedirectResponse
    {
        $report = $this->workflow->prepare($request->user(), $reportCycle, $request->only('teacher_id', 'expected_snapshot'));

        return redirect()->route($this->base($request).'.edit', $report)->with('success', 'Draf rapor tiga bulan siap. Teacher pemilik dapat mengisi delapan skill dan Note.');
    }

    public function show(Request $request, QuarterlyReport $quarterlyReport): View
    {
        return $this->page($request, $quarterlyReport);
    }

    public function edit(Request $request, QuarterlyReport $quarterlyReport): View|RedirectResponse
    {
        $this->access->authorize($request->user(), $quarterlyReport);
        if (! in_array($quarterlyReport->status, ['draft', 'revision'], true)) {
            return $this->done($request, $quarterlyReport, 'Isi terkunci. Periksa status dan riwayat rapor.');
        }

        return view('quarterly-reports.edit', ['report' => $quarterlyReport->load(['student', 'cycle', 'teacher.user']),
            'owners' => $this->access->owners($quarterlyReport->cycle), 'expectedSnapshot' => $this->content->signature($quarterlyReport)] + $this->common($request));
    }

    public function save(Request $request, QuarterlyReport $quarterlyReport): RedirectResponse
    {
        $report = $this->workflow->save($request->user(), $quarterlyReport, $request->only('expected_snapshot', 'skills', 'parent_note', 'teacher_id'));

        return redirect()->route($this->base($request).($request->input('next') === 'review' ? '.show' : '.edit'), $report)->with('success', 'Perubahan draf tersimpan.');
    }

    public function confirmation(Request $request, QuarterlyReport $quarterlyReport, string $action): View|RedirectResponse
    {
        $this->access->authorize($request->user(), $quarterlyReport);
        $teacher = $request->user()->role_code === 'teacher';
        abort_unless(($teacher && in_array($action, ['submit', 'reopen'], true)) || (! $teacher && in_array($action, ['approve', 'revision'], true)) || in_array($action, ['generate', 'regenerate'], true), 403);
        $valid = match ($action) {
            'submit' => in_array($quarterlyReport->status, ['draft', 'revision'], true),
            'approve','revision' => $quarterlyReport->status === 'submitted',
            'reopen','generate','regenerate' => $quarterlyReport->status === 'approved',
            default => false,
        };
        if (! $valid) {
            return redirect()->route($this->base($request).'.show', $quarterlyReport)->withErrors(['status' => 'Status rapor berubah. Periksa rapor terbaru.']);
        }

        return $this->page($request, $quarterlyReport, $action);
    }

    private function page(Request $request, QuarterlyReport $report, ?string $action = null): View
    {
        $this->access->authorize($request->user(), $report);
        $report->load(['student', 'cycle', 'teacher.user', 'branch', 'approvedBy']);
        $history = $report->events()->with('actor')->orderByDesc('id')->paginate(10, ['*'], 'history_page')->withQueryString()->fragment('report-history');
        $files = $report->files()->with('generatedBy')->orderByDesc('version_number')->get();
        $display = $report->status === 'approved' ? $report->approved_snapshot : $this->content->snapshot($report);
        $skillLabels = $report->status === 'approved' && is_array($display)
            ? QuarterlyReportContentService::snapshotSkillLabels($display) : QuarterlyReportContentService::SKILLS;

        return view('quarterly-reports.show', ['report' => $report, 'display' => $display, 'readiness' => $this->content->readiness($report),
            'gate' => $this->content->gate($report), 'history' => $history, 'files' => $files, 'action' => $action,
            'expectedSnapshot' => $this->content->signature($report), 'skillLabels' => $skillLabels,
            'legacySkillSnapshot' => $report->status === 'approved' && count($skillLabels) < count(QuarterlyReportContentService::SKILLS)] + $this->common($request));
    }

    public function submit(Request $request, QuarterlyReport $quarterlyReport): RedirectResponse
    {
        return $this->transition($request, $quarterlyReport, 'submit');
    }

    public function approve(Request $request, QuarterlyReport $quarterlyReport): RedirectResponse
    {
        return $this->transition($request, $quarterlyReport, 'approve');
    }

    public function revision(Request $request, QuarterlyReport $quarterlyReport): RedirectResponse
    {
        return $this->transition($request, $quarterlyReport, 'revision');
    }

    public function reopen(Request $request, QuarterlyReport $quarterlyReport): RedirectResponse
    {
        return $this->transition($request, $quarterlyReport, 'reopen');
    }

    private function transition(Request $request, QuarterlyReport $report, string $action): RedirectResponse
    {
        $report = $this->workflow->transition($request->user(), $report, $action, $request->only('expected_snapshot', 'comment'));

        return $this->done($request, $report, ['submit' => 'Rapor final diajukan untuk tinjauan Admin.', 'approve' => 'Rapor final disetujui dan salinan isi persetujuan tersimpan.',
            'revision' => 'Rapor dikembalikan kepada Teacher untuk revisi.', 'reopen' => 'Revisi baru dibuka. PDF lama tetap tersimpan; perubahan perlu diajukan dan disetujui kembali.'][$action]);
    }

    public function generate(Request $request, QuarterlyReport $quarterlyReport): RedirectResponse
    {
        $this->pdf->generate($request->user(), $quarterlyReport, $request->only('expected_snapshot'));

        return $this->done($request, $quarterlyReport, 'PDF final tersimpan. Buka atau unduh melalui daftar PDF.');
    }

    public function regenerate(Request $request, QuarterlyReport $quarterlyReport): RedirectResponse
    {
        $this->pdf->generate($request->user(), $quarterlyReport, $request->only('expected_snapshot', 'comment'), true);

        return $this->done($request, $quarterlyReport, 'Versi PDF baru tersimpan. Versi sebelumnya tetap ada dalam riwayat.');
    }

    public function preview(Request $request, QuarterlyReport $quarterlyReport): Response
    {
        return $this->response($this->pdf->preview($request->user(), $quarterlyReport), 'DPE-Preview.pdf');
    }

    public function download(Request $request, QuarterlyReport $quarterlyReport, QuarterlyReportFile $quarterlyReportFile): Response
    {
        $bytes = $this->pdf->bytes($request->user(), $quarterlyReport, $quarterlyReportFile);

        return $this->response($bytes, 'DPE-Evaluation-v'.$quarterlyReportFile->version_number.'.pdf', $request->boolean('download'));
    }

    private function response(string $bytes, string $filename, bool $download = false): Response
    {
        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function template(Request $request): View
    {
        abort_unless($request->user()->is_active && $request->user()->role_code === 'superadmin', 403);

        return view('quarterly-reports.template', ['settings' => $this->template->settings(), 'fields' => ReportTemplateService::FIELDS,
            'expectedSnapshot' => $this->template->signature(),
            'signatureState' => app(ReportSignatureService::class)->state($request->user(), 'manager')]);
    }

    public function templateSave(Request $request): RedirectResponse
    {
        $this->template->save($request->user(), $request->only('expected_snapshot', ...array_keys(ReportTemplateService::FIELDS)));

        return redirect()->route('superadmin.report-template.edit')->with('success', 'Pengaturan template tersimpan. Rapor yang telah disetujui tetap memakai salinan template sebelumnya.');
    }

    private function done(Request $request, QuarterlyReport $report, string $message): RedirectResponse
    {
        return redirect()->route($this->base($request).'.show', $report)->with('success', $message);
    }

    private function base(Request $request): string
    {
        return explode('.', $request->route()->getName())[0].'.quarterly-reports';
    }

    private function common(Request $request): array
    {
        return ['routeBase' => $this->base($request), 'monthlyBase' => str_replace('quarterly-reports', 'monthly-reports', $this->base($request)),
            'progressBase' => str_replace('quarterly-reports', 'student-progress', $this->base($request)), 'teacher' => $request->user()->role_code === 'teacher',
            'statusLabels' => QuarterlyReportContentService::STATUSES, 'skillLabels' => QuarterlyReportContentService::SKILLS];
    }
}
