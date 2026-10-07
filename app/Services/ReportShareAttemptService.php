<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\QuarterlyReportFile;
use App\Models\ReportShareAttempt;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportShareAttemptService
{
    public function create(array $data): ReportShareAttempt
    {
        return DB::transaction(function () use ($data) {
            return ReportShareAttempt::query()->create([
                'id' => $data['id'] ?? (string) Str::uuid(),
                'quarterly_report_file_id' => $data['quarterly_report_file_id'],
                'teacher_id' => $data['teacher_id'],
                'guardian_id' => $data['guardian_id'],
                'recipient_phone_snapshot' => $data['recipient_phone_snapshot'],
                'status' => $data['status'],
                'opened_at' => $data['opened_at'],
                'confirmed_at' => $data['confirmed_at'] ?? null,
                'teacher_note' => $data['teacher_note'] ?? null,
            ]);
        });
    }

    public function findById(string $id): ReportShareAttempt
    {
        return ReportShareAttempt::query()->findOrFail($id);
    }

    public function getByQuarterlyReportFile(
        QuarterlyReportFile $quarterlyReportFile
    ) {
        return ReportShareAttempt::query()
            ->where(
                'quarterly_report_file_id',
                $quarterlyReportFile->id
            )
            ->orderByDesc('opened_at')
            ->get();
    }

    public function getByTeacher(Teacher $teacher)
    {
        return ReportShareAttempt::query()
            ->where('teacher_id', $teacher->id)
            ->orderByDesc('opened_at')
            ->get();
    }

    public function getByGuardian(Guardian $guardian)
    {
        return ReportShareAttempt::query()
            ->where('guardian_id', $guardian->id)
            ->orderByDesc('opened_at')
            ->get();
    }
}
