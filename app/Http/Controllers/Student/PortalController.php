<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\MonthlyCharge;
use App\Models\MonthlyReport;
use App\Models\PaymentProof;
use App\Models\QuarterlyReport;
use App\Models\QuarterlyReportFile;
use App\Models\StudentPortalEvent;
use App\Services\PortalPaymentService;
use App\Services\StudentPortalService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PortalController extends Controller
{
    public function __construct(private StudentPortalService $portal, private PortalPaymentService $payments) {}

    public function dashboard(Request $request)
    {
        $student = $this->portal->student($request->user());
        if (! $student) {
            return view('student.portal.unlinked');
        }
        $profile = $this->portal->profile($student);
        $month = now('Asia/Makassar')->format('Y-m');
        $nextMonth = Carbon::parse($month.'-01')->addMonth()->format('Y-m');
        $currentSessions = $this->portal->sessions($student, $month);
        $meetingProgress = $this->portal->meetingProgress($student, $month, $currentSessions);
        $upcoming = $currentSessions->concat($this->portal->sessions($student, $nextMonth))
            ->filter(fn ($row) => $row['future'] && ! $row['cancelled'] && ! $row['superseded'])->sortBy('date')->take(4);
        $reports = $student->monthlyReports()->latest('report_month')->take(6)->get()->map(fn ($report) => ['report' => $report, 'state' => $this->portal->monthlyState($report)]);
        $payments = $student->monthlyCharges()->with('branch')->latest('charge_month')->take(6)->get()
            ->map(fn ($charge) => ['charge' => $charge, 'state' => $this->payments->state($charge)]);
        $available = $reports->filter(fn ($row) => $row['state']['ready']);
        $message = $available->isEmpty() ? null : $this->portal->monthlyPublic($available->first()['report'])['content']['parent_message'];

        return view('student.dashboard', compact('student', 'profile', 'upcoming', 'reports', 'payments', 'available', 'message', 'meetingProgress'));
    }

    public function profile(Request $request)
    {
        $student = $this->portal->requireStudent($request->user());
        $profile = $this->portal->profile($student);

        return view('student.portal.profile', compact('profile'));
    }

    private function month(Request $request): string
    {
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return $validated['month'] ?? now('Asia/Makassar')->format('Y-m');
    }

    public function schedule(Request $request)
    {
        $student = $this->portal->requireStudent($request->user());
        $month = $this->month($request);
        $sessions = $this->portal->sessions($student, $month);
        $history = false;

        return view('student.portal.sessions', compact('month', 'sessions', 'history'));
    }

    public function history(Request $request)
    {
        $student = $this->portal->requireStudent($request->user());
        $month = $this->month($request);
        $sessions = $this->portal->sessions($student, $month)->filter(fn ($row) => ! $row['future']);
        $monthlyReports = $student->monthlyReports()->whereYear('report_month', substr($month, 0, 4))->whereMonth('report_month', substr($month, 5, 2))->get();
        $publicReports = $monthlyReports->filter(fn ($report) => $this->portal->monthlyState($report)['ready'])
            ->map(fn ($report) => ['report' => $report, 'public' => $this->portal->monthlyPublic($report)['content']]);
        $history = true;

        return view('student.portal.sessions', compact('month', 'sessions', 'history', 'publicReports'));
    }

    public function attendance(Request $request)
    {
        $student = $this->portal->requireStudent($request->user());
        $validated = $request->validate([
            'months' => ['sometimes', 'array', 'list', 'size:3'],
            'months.*' => ['required', 'string', 'date_format:Y-m', 'distinct:strict',
                'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31'],
        ], [
            'months.size' => 'Pilih tepat tiga bulan untuk melihat absensi.',
            'months.*.distinct' => 'Pilih tiga bulan yang berbeda.',
            'months.*.date_format' => 'Gunakan pilihan bulan dan tahun yang valid.',
        ]);
        $current = now('Asia/Makassar')->startOfMonth();
        $months = $validated['months'] ?? [
            $current->copy()->subMonthsNoOverflow(2)->format('Y-m'),
            $current->copy()->subMonthNoOverflow()->format('Y-m'),
            $current->format('Y-m'),
        ];
        $attendanceMonths = $this->portal->attendanceMonths($student, $months);

        return view('student.portal.attendance', compact('months', 'attendanceMonths'));
    }

    public function reports(Request $request)
    {
        $student = $this->portal->requireStudent($request->user());
        $monthlyReports = $student->monthlyReports()->latest('report_month')->paginate(12, ['*'], 'monthly_page')->withQueryString();
        $monthlyRows = $monthlyReports->getCollection()->map(fn ($report) => ['report' => $report, 'state' => $this->portal->monthlyState($report)]);
        $cycles = $student->reportCycles()->with('quarterlyReport')->latest('start_month')->paginate(10, ['*'], 'quarterly_page')->withQueryString();
        $quarterlyRows = $cycles->getCollection()->map(fn ($cycle) => ['cycle' => $cycle, 'report' => $cycle->quarterlyReport,
            'state' => $cycle->quarterlyReport ? $this->portal->quarterlyState($cycle->quarterlyReport) : ['ready' => false, 'reasons' => ['Rapor final belum disiapkan Teacher.']]]);

        return view('student.portal.reports', compact('monthlyReports', 'monthlyRows', 'cycles', 'quarterlyRows'));
    }

    public function monthly(Request $request, MonthlyReport $monthlyReport)
    {
        $student = $this->portal->requireStudent($request->user());
        $monthlyReport = $monthlyReport->fresh();
        abort_unless($monthlyReport?->student_id === $student->id, 404);
        $state = $this->portal->monthlyState($monthlyReport);
        $period = $monthlyReport->report_month->locale('id')->translatedFormat('F Y');
        if (! $state['ready']) {
            return view('student.portal.locked', ['period' => $period, 'reasons' => $state['reasons']]);
        }
        $data = $this->portal->monthlyPublic($monthlyReport);
        $public = $data['content'];
        $attendance = $data['attendance'];

        return view('student.portal.monthly', compact('period', 'public', 'attendance'));
    }

    public function quarterly(Request $request, QuarterlyReport $quarterlyReport)
    {
        $student = $this->portal->requireStudent($request->user());
        $quarterlyReport = $quarterlyReport->fresh(['cycle']);
        abort_unless($quarterlyReport?->student_id === $student->id, 404);
        $state = $this->portal->quarterlyState($quarterlyReport);
        $period = $quarterlyReport->cycle->start_month->locale('id')->translatedFormat('F Y').' – '.$quarterlyReport->cycle->end_month->locale('id')->translatedFormat('F Y');
        if (! $state['ready']) {
            return view('student.portal.locked', ['period' => $period, 'reasons' => $state['reasons'], 'months' => $state['months']]);
        }
        $file = $state['file'];
        $public = $file->public_snapshot;

        return view('student.portal.quarterly', compact('quarterlyReport', 'period', 'file', 'public'));
    }

    public function pdf(Request $request, QuarterlyReport $quarterlyReport, QuarterlyReportFile $quarterlyReportFile)
    {
        $bytes = $this->portal->pdf($request->user(), $quarterlyReport, $quarterlyReportFile);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($bytes)->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', $disposition.'; filename="DPE-Rapor-'.$quarterlyReport->cycle->start_month->format('Y-m').'.pdf"');
    }

    public function payments(Request $request)
    {
        $student = $this->portal->requireStudent($request->user());
        $charges = $student->monthlyCharges()->with('branch')->latest('charge_month')->paginate(15);
        $rows = $charges->getCollection()->map(fn ($charge) => ['charge' => $charge, 'state' => $this->payments->state($charge)]);

        return view('student.portal.payments', compact('charges', 'rows'));
    }

    public function payment(Request $request, MonthlyCharge $monthlyCharge)
    {
        $student = $this->payments->owned($request->user(), $monthlyCharge);
        $monthlyCharge = $monthlyCharge->fresh(['branch']);
        $state = $this->payments->state($monthlyCharge);
        $snapshot = $this->payments->chargeSignature($monthlyCharge);
        $events = StudentPortalEvent::where('charge_id', $monthlyCharge->id)->orderByDesc('id')->get();

        return view('student.portal.payment', compact('student', 'monthlyCharge', 'state', 'snapshot', 'events'));
    }

    public function upload(Request $request, MonthlyCharge $monthlyCharge)
    {
        $this->payments->upload($request->user(), $monthlyCharge, $request->all());

        if ($request->expectsJson()) {
            $request->session()->flash('success', 'Bukti berhasil diunggah dan menunggu validasi Super Admin.');

            return response()->json(['message' => 'Bukti berhasil diunggah dan menunggu validasi Super Admin.'], 201);
        }

        return redirect()->route('student.payments.show', $monthlyCharge)->with('success', 'Bukti berhasil diunggah dan menunggu validasi Super Admin.');
    }

    public function uploadContext(Request $request, MonthlyCharge $monthlyCharge)
    {
        $student = $this->payments->owned($request->user(), $monthlyCharge);
        $monthlyCharge = $monthlyCharge->fresh();

        return response()->json([
            'csrf_token' => $request->session()->token(),
            'expected_snapshot' => $this->payments->chargeSignature($monthlyCharge),
            'can_upload' => $student->status === 'active' && $this->payments->state($monthlyCharge)['can_upload'],
        ]);
    }

    public function image(Request $request, PaymentProof $paymentProof)
    {
        $paymentProof = $paymentProof->fresh(['charge', 'imageFile']);
        abort_unless($paymentProof?->charge, 404);
        $this->payments->owned($request->user(), $paymentProof->charge);
        $bytes = $this->payments->imageBytes($paymentProof);
        abort_unless($bytes !== null, 404);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$paymentProof->imageFile->mime_type];

        return response($bytes)->header('Content-Type', $paymentProof->imageFile->mime_type)
            ->header('Content-Disposition', 'inline; filename="Bukti-Pembayaran-'.$paymentProof->id.'.'.$extension.'"');
    }
}
