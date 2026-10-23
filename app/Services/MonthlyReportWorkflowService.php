<?php

namespace App\Services;

use App\Models\BranchAdminAssignment;
use App\Models\MonthlyReport;
use App\Models\ReportStatusEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MonthlyReportWorkflowService
{
    public function __construct(private MonthlyReportContentService $content) {}

    /**
     * Guru pemilik mengajukan laporan.
     */
    public function submit(
        MonthlyReport $report,
        User $actor,
        ?array $formData = null
    ): MonthlyReport {
        return DB::transaction(function () use ($report, $actor, $formData) {
            $report = $this->lockReport($report);
            $actor = $actor->fresh() ?? throw new AuthorizationException('Akun petugas tidak tersedia.');

            $this->authorizeTeacherOwner($report, $actor);

            if (! in_array($report->status, ['draft', 'revision'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Laporan hanya dapat diajukan dari status draft atau revision.',
                ]);
            }

            $this->content->assertSnapshot($report, $formData);
            $this->content->assertReady($report);

            $previousStatus = $report->status;

            $report->status = 'submitted';
            $report->submitted_at = Carbon::now();
            $report->approved_at = null;
            $report->approved_by_user_id = null;
            $report->approved_snapshot = null;
            $report->save();

            $this->recordStatusChange(
                $report,
                $actor,
                $previousStatus,
                'submitted'
            );

            return $report->fresh();
        });
    }

    /**
     * Admin cabang atau Super Admin menyetujui laporan.
     */
    public function approve(
        MonthlyReport $report,
        User $actor,
        ?array $formData = null
    ): MonthlyReport {
        return DB::transaction(function () use ($report, $actor, $formData) {
            $report = $this->lockReport($report);
            $actor = $actor->fresh() ?? throw new AuthorizationException('Akun petugas tidak tersedia.');

            $this->authorizeApprover($report, $actor);

            if ($report->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya laporan berstatus submitted yang dapat disetujui.',
                ]);
            }

            $this->content->assertSnapshot($report, $formData);
            $this->content->assertReady($report);
            $snapshot = $this->content->approvalSnapshot($report);

            $previousStatus = $report->status;

            $report->status = 'approved';
            $report->approved_at = Carbon::now();
            $report->approved_by_user_id = $actor->id;
            $report->approved_snapshot = $snapshot;
            $report->save();

            $this->recordStatusChange(
                $report,
                $actor,
                $previousStatus,
                'approved'
            );

            return $report->fresh();
        });
    }

    /**
     * Admin cabang atau Super Admin meminta revisi.
     */
    public function requestRevision(
        MonthlyReport $report,
        User $actor,
        string $comment,
        ?array $formData = null
    ): MonthlyReport {
        $comment = trim($comment);

        if ($comment === '' || mb_strlen($comment) > 255) {
            throw ValidationException::withMessages([
                'comment' => 'Komentar revisi wajib diisi dan maksimal 255 karakter.',
            ]);
        }

        return DB::transaction(function () use (
            $report,
            $actor,
            $comment,
            $formData
        ) {
            $report = $this->lockReport($report);
            $actor = $actor->fresh() ?? throw new AuthorizationException('Akun petugas tidak tersedia.');

            $this->authorizeApprover($report, $actor);

            if ($report->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya laporan berstatus submitted yang dapat diminta revisi.',
                ]);
            }

            $this->content->assertSnapshot($report, $formData);

            $previousStatus = $report->status;

            $report->status = 'revision';
            $report->approved_at = null;
            $report->approved_by_user_id = null;
            $report->approved_snapshot = null;
            $report->save();

            $this->recordStatusChange(
                $report,
                $actor,
                $previousStatus,
                'revision',
                $comment
            );

            return $report->fresh();
        });
    }

    private function lockReport(
        MonthlyReport $report
    ): MonthlyReport {
        return MonthlyReport::query()
            ->whereKey($report->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function authorizeTeacherOwner(
        MonthlyReport $report,
        User $actor
    ): void {
        if (
            ! $actor->is_active
            || $actor->role_code !== 'teacher'
            || ! $actor->teacherProfile?->is_active
            || $actor->teacherProfile?->id !== $report->teacher_id
        ) {
            throw new AuthorizationException(
                'Hanya guru pemilik laporan yang dapat mengajukan laporan ini.'
            );
        }
    }

    private function authorizeApprover(
        MonthlyReport $report,
        User $actor
    ): void {
        if (! $actor->is_active) {
            throw new AuthorizationException(
                'Akun tidak aktif tidak dapat memproses laporan.'
            );
        }

        if ($actor->role_code === 'superadmin') {
            return;
        }

        if ($actor->role_code !== 'admin') {
            throw new AuthorizationException(
                'Hanya Admin cabang atau Super Admin yang dapat memproses laporan.'
            );
        }

        $today = now()->toDateString();

        $hasBranchAssignment = BranchAdminAssignment::query()
            ->where('admin_user_id', $actor->id)
            ->where('branch_id', $report->branch_id)
            ->whereDate('starts_on', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $today);
            })
            ->exists();

        if (! $hasBranchAssignment) {
            throw new AuthorizationException(
                'Admin tidak memiliki penugasan aktif pada cabang laporan ini.'
            );
        }
    }

    private function recordStatusChange(
        MonthlyReport $report,
        User $actor,
        ?string $fromStatus,
        string $toStatus,
        ?string $comment = null
    ): void {
        ReportStatusEvent::query()->forceCreate([
            'id' => (string) Str::uuid7(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'comment' => $comment,
            'occurred_at' => now(),
        ]);
    }
}
