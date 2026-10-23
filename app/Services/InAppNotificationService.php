<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\QuarterlyReportEvent;
use App\Models\ReportStatusEvent;
use App\Models\StudentPortalEvent;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InAppNotificationService
{
    public const TYPES = ['all' => 'Semua jenis', 'reports' => 'Laporan', 'reminders' => 'Tenggat & video',
        'payments' => 'Pembayaran', 'attendance' => 'Kehadiran Teacher', 'system' => 'Pemberitahuan khusus'];

    public function __construct(private OperationsAccessService $access, private OperationsMonitoringService $monitor) {}

    /** Read existing workflow records; opening a page never creates workflow events. */
    public function items(User $user): Collection
    {
        $actor = $this->access->actor($user);
        $items = collect();
        $since = now()->subDays(90);
        $until = now();
        $student = $actor->role_code === 'student';
        if (! $student) {
            $reports = $this->access->monthly($actor)->where('status', '!=', 'approved')
                ->with(['student', 'branch'])->get();
            foreach ($reports as $report) {
                $context = $report->student?->full_name.' · '.$report->report_month->format('m/Y').' · '.$report->branch?->name;
                $path = $this->access->reportPath($actor, $report);
                $latest = $report->statusEvents()->orderByDesc('occurred_at')->orderByDesc('id')->first();
                $version = [$report->status, $latest?->id, $report->teacher_id, $report->branch_id];
                if ($report->status === 'submitted' && in_array($actor->role_code, ['admin', 'superadmin'], true)) {
                    $items->push($this->item(['monthly-review', $report->id, $version], 'reports', 'Laporan bulanan menunggu tinjauan', $context,
                        $report->submitted_at ?? $report->updated_at, $path, $report->branch?->timezone_name));
                }
                if ($report->status === 'revision' && $actor->role_code === 'teacher') {
                    $items->push($this->item(['monthly-revision', $report->id, $version], 'reports', 'Laporan bulanan perlu direvisi', $context,
                        $latest?->occurred_at ?? $report->updated_at, $path, $report->branch?->timezone_name));
                }
                $deadline = $this->monitor->deadline($report);
                if ($deadline) {
                    $title = $deadline === 'overdue' ? 'Tenggat laporan terlewati' : 'Tenggat laporan mendekat';
                    $items->push($this->item(['monthly-due', $report->id, $version, $report->due_on->toDateString(), $deadline], 'reminders', $title,
                        $context.' · tenggat '.$report->due_on->format('d/m/Y'), $report->due_on, $path, $report->branch?->timezone_name));
                }
                $today = now($this->access->timezone($report->branch?->timezone_name))->toDateString();
                if ($report->report_month->toDateString() <= $today && $report->student?->began_on?->toDateString() <= $today) {
                    $video = $this->monitor->videoState($report);
                    if ($video['missing'] === null || $video['missing'] > 0) {
                        $items->push($this->item(['monthly-video', $report->id, $version, $video['fingerprint']], 'reminders', 'Target video perlu dilengkapi',
                            $context.' · '.$video['label'], $report->updated_at, $path, $report->branch?->timezone_name));
                    }
                }
            }
            $finals = $this->access->quarterly($actor)->where('status', '!=', 'approved')
                ->with(['student', 'branch', 'cycle'])->get();
            foreach ($finals as $report) {
                $latest = $report->events()->orderByDesc('occurred_at')->orderByDesc('id')->first();
                $path = $this->access->reportPath($actor, $report);
                $context = $report->student?->full_name.' · '.$report->cycle?->start_month?->format('m/Y').'–'.$report->cycle?->end_month?->format('m/Y');
                $identity = [$report->id, $report->status, $report->revision_number, $latest?->id, $report->teacher_id];
                if (($actor->role_code === 'teacher' && $report->status === 'revision') || ($actor->role_code !== 'teacher' && $report->status === 'submitted')) {
                    $items->push($this->item(['final-task', $identity], 'reports', $report->status === 'revision' ? 'Rapor tiga bulan perlu direvisi' : 'Rapor tiga bulan menunggu tinjauan',
                        $context, $latest?->occurred_at ?? $report->updated_at, $path, $report->branch?->timezone_name));
                }
                $due = $report->cycle?->share_due_on;
                $phase = $this->monitor->dueState($due?->toDateString(), false, $report->branch?->timezone_name);
                if ($phase) {
                    $items->push($this->item(['final-due', $identity, $due->toDateString(), $phase], 'reminders',
                        $phase === 'overdue' ? 'Tenggat rapor tiga bulan terlewati' : 'Tenggat rapor tiga bulan mendekat',
                        $context.' · tenggat '.$due->format('d/m/Y'), $due, $path, $report->branch?->timezone_name));
                }
            }
        }

        // Approval notifications include metadata only; portal actions retain their payment/content gates.
        $events = ReportStatusEvent::whereIn('report_id', $this->access->monthly($actor)->select('id'))
            ->where('to_status', 'approved')->whereBetween('occurred_at', [$since, $until])->where('actor_user_id', '!=', $actor->id)
            ->with(['report.student', 'report.branch'])->get();
        foreach ($events as $event) {
            $report = $event->report;
            $items->push($this->item(['monthly-event', $event->id], 'reports', 'Laporan bulanan disetujui',
                $report->student?->full_name.' · '.$report->report_month->format('m/Y'), $event->occurred_at,
                $this->access->reportPath($actor, $report), $report->branch?->timezone_name));
        }
        $events = QuarterlyReportEvent::whereIn('report_id', $this->access->quarterly($actor)->select('id'))
            ->where('event_type', 'approve')->whereBetween('occurred_at', [$since, $until])->where('actor_user_id', '!=', $actor->id)
            ->with(['report.student', 'report.branch', 'report.cycle'])->get();
        foreach ($events as $event) {
            $report = $event->report;
            $items->push($this->item(['final-event', $event->id], 'reports', 'Rapor tiga bulan disetujui',
                $report->student?->full_name.' · '.$report->cycle?->start_month?->format('m/Y').'–'.$report->cycle?->end_month?->format('m/Y'),
                $event->occurred_at, $this->access->reportPath($actor, $report), $report->branch?->timezone_name));
        }
        // A pending proof remains actionable even if it predates the history window.
        if ($actor->role_code === 'superadmin') {
            $charges = $this->access->charges($actor)->whereHas('paymentProofs', fn ($proof) => $proof->where('status', 'pending'))
                ->with(['student', 'branch', 'paymentProofs' => fn ($proof) => $proof->where('status', 'pending')])->get();
            foreach ($charges as $charge) {
                foreach ($charge->paymentProofs as $proof) {
                    $items->push($this->item(['payment-pending', $proof->id], 'payments', 'Bukti pembayaran menunggu validasi',
                        $charge->student?->full_name.' · '.$charge->charge_month->format('m/Y'), $proof->submitted_at,
                        route('superadmin.payment-review.show', $proof, false), $charge->branch?->timezone_name));
                }
            }
        }
        $events = StudentPortalEvent::whereIn('charge_id', $this->access->charges($actor)->select('id'))
            ->where('event_type', 'payment_reviewed')->whereBetween('occurred_at', [$since, $until])
            ->where('actor_user_id', '!=', $actor->id)->with(['charge.student', 'charge.branch'])->get();
        foreach ($events as $event) {
            $status = $event->after_snapshot['status'] ?? null;
            if (! in_array($status, ['approved', 'rejected'], true)) {
                continue;
            }
            $charge = $event->charge;
            $path = $student ? route('student.payments.show', $charge, false)
                : ($actor->role_code === 'superadmin' && $event->payment_proof_id
                    ? route('superadmin.payment-review.show', $event->payment_proof_id, false) : route('monitoring.index', ['month' => $charge->charge_month->format('Y-m')], false));
            $items->push($this->item(['payment-event', $event->id], 'payments', $status === 'approved' ? 'Pembayaran tervalidasi' : 'Bukti pembayaran ditolak',
                $charge->student?->full_name.' · '.$charge->charge_month->format('m/Y'), $event->occurred_at, $path, $charge->branch?->timezone_name));
        }
        if (! $student) {
            $attendanceStatus = $actor->role_code === 'teacher' ? 'needs_correction' : 'pending_review';
            $attendances = $this->access->attendances($actor)->where('status', $attendanceStatus)->with(['teacher.user', 'branch'])->get();
            foreach ($attendances as $attendance) {
                $path = $actor->role_code === 'teacher' ? route('inbox.attendance', $attendance, false) : route('admin.teacher-attendance.show', $attendance, false);
                $items->push($this->item(['attendance-task', $attendance->id, $attendanceStatus, $attendance->reviewed_at?->toIso8601String()], 'attendance',
                    $attendanceStatus === 'pending_review' ? 'Kehadiran Teacher perlu diperiksa' : 'Kehadiran Teacher perlu dikoreksi',
                    $attendance->teacher?->user?->full_name.' · '.$attendance->recordDate()?->format('d/m/Y').' · '.$attendance->branch?->name,
                    $attendance->reviewed_at ?? $attendance->created_at, $path, $attendance->branch?->timezone_name));
            }
        }
        foreach (Notification::where('user_id', $actor->id)->whereBetween('created_at', [$since, $until])->get() as $notification) {
            // Legacy records have no verifiable object scope; do not follow arbitrary action_path values.
            $items->push($this->item(['legacy', $notification->id, $notification->title, $notification->body], 'system',
                $notification->title, $notification->body, $notification->created_at, null, null));
        }
        $reads = DB::table('in_app_notification_reads')->where('user_id', $actor->id)->pluck('read_at', 'notification_key');

        if (SystemSetting::find(1)?->in_app_reminders_enabled === false) {
            $items = $items->reject(fn ($item) => $item['type'] === 'reminders');
        }

        return $items->unique('key')->map(fn ($item) => $item + ['read' => $reads->has($item['key'])])
            ->sort(fn ($a, $b) => ($b['timestamp'] <=> $a['timestamp']) ?: strcmp($a['key'], $b['key']))->values();
    }

    private function item(array $identity, string $type, string $title, string $body, $at, ?string $path, ?string $zone): array
    {
        $time = $at ?? now();

        return ['key' => hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR)), 'type' => $type, 'title' => $title, 'body' => $body,
            'timestamp' => $time->getTimestamp(), 'timeLabel' => $time->copy()->timezone($this->access->timezone($zone))->format('d/m/Y H:i'), 'path' => $path];
    }

    public function read(User $user, array $keys, bool $requirePath = false): Collection
    {
        return DB::transaction(function () use ($user, $keys, $requirePath) {
            $actor = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $items = $this->items($actor)->keyBy('key');
            foreach ($keys as $key) {
                abort_unless($items->has($key), 404);
                abort_if($requirePath && ! $items[$key]['path'], 404);
            }
            foreach (array_unique($keys) as $key) {
                DB::table('in_app_notification_reads')->insertOrIgnore(['user_id' => $actor->id, 'notification_key' => $key, 'read_at' => now()]);
            }

            return $items->only($keys)->values();
        }, 3);
    }
}
