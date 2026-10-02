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
use RuntimeException;

class MonthlyReportWorkflowService
{
    /**
     * Guru pemilik mengajukan laporan.
     */
    public function submit(
        MonthlyReport $report,
        User $actor
    ): MonthlyReport {
        return DB::transaction(function () use ($report, $actor) {
            $report = $this->lockReport($report);

            $this->authorizeTeacherOwner($report, $actor);

            if (! in_array($report->status, ['draft', 'revision'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Laporan hanya dapat diajukan dari status draft atau revision.',
                ]);
            }

            $this->validateSubmissionCompleteness($report);

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
        User $actor
    ): MonthlyReport {
        return DB::transaction(function () use ($report, $actor) {
            $report = $this->lockReport($report);

            $this->authorizeApprover($report, $actor);

            if ($report->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya laporan berstatus submitted yang dapat disetujui.',
                ]);
            }

            // Simpan salinan isi laporan saat disetujui.
            $snapshot = [
                'report_id' => $report->id,
                'cycle_id' => $report->cycle_id,
                'student_id' => $report->student_id,
                'branch_id' => $report->branch_id,
                'teacher_id' => $report->teacher_id,
                'report_month' => $report->report_month === null
                    ? null
                    : substr((string) $report->report_month, 0, 10),
                'due_on' => $report->due_on === null
                    ? null
                    : substr((string) $report->due_on, 0, 10),
                'video_target' => $report->video_target,
                'development_summary' => $report->development_summary,
                'parent_challenges_summary' =>
                $report->parent_challenges_summary,
                'parent_message' => $report->parent_message,
                'internal_teacher_note' => $report->internal_teacher_note,
                'skills' => $report->reportSkills()
                    ->get(['skill_id', 'trend', 'description'])
                    ->toArray(),
            ];

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
        string $comment
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
            $comment
        ) {
            $report = $this->lockReport($report);

            $this->authorizeApprover($report, $actor);

            if ($report->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya laporan berstatus submitted yang dapat diminta revisi.',
                ]);
            }

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

    /**
     * Memastikan isi laporan sudah lengkap sebelum diajukan.
     *
     * @throws ValidationException
     */
        
    /**
     * Memastikan isi laporan sudah lengkap sebelum diajukan.
     *
     * @throws ValidationException
     */
    private function validateSubmissionCompleteness(
        MonthlyReport $report
    ): void {
        $errors = [];

        if (blank($report->development_summary)) {
            $errors['development_summary'] =
                'Ringkasan perkembangan siswa wajib diisi.';
        }

        if (blank($report->parent_challenges_summary)) {
            $errors['parent_challenges_summary'] =
                'Ringkasan tantangan siswa wajib diisi.';
        }

        $reportSkills = $report->reportSkills()->get();

        if ($reportSkills->isEmpty()) {
            $errors['skills'] =
                'Minimal satu skill harus ditambahkan ke laporan.';
        }

        if ($reportSkills->isNotEmpty()) {
            $skillIds = $reportSkills
                ->pluck('skill_id')
                ->unique()
                ->values();

            $activeSkillIds = \App\Models\LearningSkill::query()
                ->whereIn('id', $skillIds)
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

            foreach ($reportSkills as $reportSkill) {
                if (! in_array(
                    (string) $reportSkill->skill_id,
                    $activeSkillIds,
                    true
                )) {
                    $errors['skills'] =
                        'Laporan hanya boleh menggunakan skill yang aktif.';
                    break;
                }

                if (blank($reportSkill->description)) {
                    $errors['skills'] =
                        'Deskripsi setiap skill wajib diisi.';
                    break;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
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
            'id' => (string) Str::uuid(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'comment' => $comment,
            'occurred_at' => now(),
        ]);
    }
}
