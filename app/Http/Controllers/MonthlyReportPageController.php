<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\MonthlyReport;
use App\Services\MonthlyReportContentService;
use App\Services\MonthlyReportWorkflowService;
use App\Services\StudentProgressAccessService;
use App\Services\StudentPortalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonthlyReportPageController extends Controller
{
    public function __construct(private StudentProgressAccessService $access, private MonthlyReportContentService $content,
        private MonthlyReportWorkflowService $workflow, private StudentPortalService $portal) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'status' => ['nullable', Rule::in(['all', ...array_keys(MonthlyReportContentService::STATUSES)])],
            'branch_id' => ['nullable', 'uuid'], 'q' => ['nullable', 'string', 'max:100']]);
        $teacher = $request->user()->role_code === 'teacher';
        $filters += ['month' => $teacher ? now()->format('Y-m') : null, 'status' => $teacher ? 'all' : 'submitted', 'branch_id' => null, 'q' => null];
        $query = $this->access->reports($request->user());
        if (filled($filters['month'])) {
            $query->whereDate('report_month', $filters['month'].'-01');
        }
        if (filled($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if (filled($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if (filled($filters['q'])) {
            $query->whereHas('student', fn ($q) => $q->where('full_name', 'like', '%'.$filters['q'].'%'));
        }
        $reports = $query->with(['student', 'branch', 'teacher.user', 'reportSkills', 'approvedBy'])
            ->orderByDesc('report_month')->orderBy('due_on')->orderBy('id')->paginate(15)->withQueryString();
        $rows = $reports->getCollection()->map(fn ($report) => ['report' => $report, 'readiness' => $report->status === 'approved' ? null : $this->content->readiness($report),
            'overdue' => $report->due_on && $report->due_on->toDateString() < $this->content->today($report)]);
        $branches = Branch::whereIn('id', $this->access->reports($request->user())->select('branch_id'))->orderBy('name')->get();

        return view('monthly-reports.index', compact('reports', 'rows', 'branches', 'filters', 'teacher') + $this->common($request));
    }

    public function show(Request $request, MonthlyReport $monthlyReport): View
    {
        return $this->page($request, $monthlyReport);
    }

    public function confirmation(Request $request, MonthlyReport $monthlyReport, string $action): View|RedirectResponse
    {
        $this->access->authorize($request->user(), $monthlyReport);
        $teacher = $request->user()->role_code === 'teacher';
        abort_unless(($teacher && $action === 'submit') || (! $teacher && in_array($action, ['approve', 'revision'], true)), 403);
        if (($action === 'submit' && ! in_array($monthlyReport->status, ['draft', 'revision'], true)) || ($action !== 'submit' && $monthlyReport->status !== 'submitted')) {
            return redirect()->route($this->base($request).'.show', $monthlyReport)->withErrors(['status' => 'Status laporan sudah berubah. Periksa laporan terbaru.']);
        }

        return $this->page($request, $monthlyReport, $action);
    }

    private function page(Request $request, MonthlyReport $report, ?string $action = null): View
    {
        $this->access->authorize($request->user(), $report);
        $report->load(['student', 'branch', 'teacher.user', 'cycle', 'approvedBy']);
        $readiness = $report->status === 'approved' ? null : $this->content->readiness($report);
        $history = $report->statusEvents()->with('actor')->orderByDesc('occurred_at')->orderByDesc('id')->paginate(10, ['*'], 'history_page')->withQueryString()->fragment('report-history');

        return view('monthly-reports.show', ['monthlyReport' => $report, 'display' => $this->content->display($report), 'readiness' => $readiness,
            'attendance' => $this->content->attendanceSummary($report), 'history' => $history, 'action' => $action,
            'deliveryState' => $this->portal->monthlyState($report),
            'expectedSnapshot' => $this->content->signature($report), 'teacher' => $request->user()->role_code === 'teacher'] + $this->common($request));
    }

    public function submit(Request $request, MonthlyReport $monthlyReport): RedirectResponse
    {
        $this->workflow->submit($monthlyReport, $request->user(), $request->only('expected_snapshot'));

        return $this->done($request, $monthlyReport, 'Laporan berhasil diajukan. Isi terkunci selama tinjauan Admin.');
    }

    public function approve(Request $request, MonthlyReport $monthlyReport): RedirectResponse
    {
        $this->workflow->approve($monthlyReport, $request->user(), $request->only('expected_snapshot'));

        return $this->done($request, $monthlyReport, 'Laporan bulanan disetujui. Salinan isi persetujuan tersimpan.');
    }

    public function revision(Request $request, MonthlyReport $monthlyReport): RedirectResponse
    {
        $this->access->authorize($request->user(), $monthlyReport);
        abort_unless($this->access->canManage($request->user()), 403);
        $data = $request->validate(['comment' => ['required', 'string', 'max:255']]);
        $this->workflow->requestRevision($monthlyReport, $request->user(), $data['comment'], $request->only('expected_snapshot'));

        return $this->done($request, $monthlyReport, 'Laporan dikembalikan untuk revisi. Teacher dapat memperbaiki draf.');
    }

    private function done(Request $request, MonthlyReport $report, string $message): RedirectResponse
    {
        return redirect()->route($this->base($request).'.show', $report)->with('success', $message);
    }

    private function base(Request $request): string
    {
        return explode('.', $request->route()->getName())[0].'.monthly-reports';
    }

    private function common(Request $request): array
    {
        return ['routeBase' => $this->base($request), 'progressBase' => str_replace('monthly-reports', 'student-progress', $this->base($request)), 'statusLabels' => MonthlyReportContentService::STATUSES];
    }
}
