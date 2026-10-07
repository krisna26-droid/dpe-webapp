<?php

namespace App\Services;

use App\Models\FileAsset;
use App\Models\QuarterlyReportFile;
use App\Models\ReportCycle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuarterlyReportFileService
{
    public function create(array $data): QuarterlyReportFile
    {
        return DB::transaction(function () use ($data) {
            return QuarterlyReportFile::query()->create([
                'id' => $data['id'] ?? (string) Str::uuid(),
                'cycle_id' => $data['cycle_id'],
                'file_id' => $data['file_id'],
                'version_number' => $data['version_number'],
                'generated_by_user_id' => $data['generated_by_user_id'],
                'generated_at' => $data['generated_at'] ?? now(),
                'source_snapshot_hash' => $data['source_snapshot_hash'] ?? null,
            ]);
        });
    }

    public function findById(string $id): QuarterlyReportFile
    {
        return QuarterlyReportFile::query()->findOrFail($id);
    }

    public function getByCycle(ReportCycle $cycle)
    {
        return QuarterlyReportFile::query()
            ->where('cycle_id', $cycle->id)
            ->orderByDesc('version_number')
            ->get();
    }

    public function getByFile(FileAsset $file): QuarterlyReportFile
    {
        return QuarterlyReportFile::query()
            ->where('file_id', $file->id)
            ->firstOrFail();
    }

    public function getByGenerator(User $user)
    {
        return QuarterlyReportFile::query()
            ->where('generated_by_user_id', $user->id)
            ->orderByDesc('generated_at')
            ->get();
    }
}
