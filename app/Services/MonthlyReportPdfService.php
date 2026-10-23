<?php

namespace App\Services;

use App\Models\FileAsset;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MonthlyReportPdfService
{
    public function __construct(private StudentProgressAccessService $access, private StudentPortalService $portal,
        private ReportTemplateService $template, private ReportSignatureService $signatures, private MonthlyReportPdfRenderer $renderer) {}

    public function snapshot(MonthlyReport $report): array
    {
        $data = $this->portal->monthlyPublic($report);
        $public = $data['content'];
        if (($report->approved_snapshot['teacher_id'] ?? null) !== $report->teacher_id) {
            throw ValidationException::withMessages(['pdf' => 'Identitas Teacher pada persetujuan bulanan perlu diperiksa Admin.']);
        }
        $skills = [];
        $approvedSkills = collect($report->approved_snapshot['public_content']['skills'] ?? [])->keyBy('code');
        foreach (StudentProgressService::CATEGORIES as $code => $label) {
            $row = $approvedSkills->get($code, []);
            $skills[$code] = ['description' => $row['description'] ?? '', 'score' => $row['trend'] ?? ''];
        }
        $template = $this->template->snapshot();
        $month = $report->report_month->toDateString();
        $snapshot = ['report_kind' => 'monthly', 'pdf_layout_version' => MonthlyReportPdfRenderer::LAYOUT_VERSION,
            'report_id' => $report->id, 'student_id' => $report->student_id,
            'branch_id' => $report->branch_id, 'teacher_id' => $report->teacher_id,
            'report_month' => $month, 'start_month' => $month, 'end_month' => $month,
            'approval_hash' => hash('sha256', json_encode($report->approved_snapshot, JSON_THROW_ON_ERROR)),
            'student_name' => $public['student_name'], 'teacher_name' => $public['teacher_name'],
            'skills' => $skills, 'parent_note' => $public['parent_message'],
            'development_summary' => $public['development_summary'], 'parent_challenges_summary' => $public['parent_challenges_summary'],
            'parent_message' => $public['parent_message'], 'videos' => $public['videos'], 'attendance' => $data['attendance'],
            'template' => $template, 'signatures' => $this->signatures->snapshot($report->teacher_id,
                $public['teacher_name'] ?? '', $template['fields']['manager_name'])];
        $this->renderer->validateSnapshot($snapshot);

        return $snapshot;
    }

    public function preview(User $actor, MonthlyReport $report): string
    {
        $this->access->authorize($actor->fresh() ?? abort(403), $report);
        $report = $report->fresh();

        return $this->renderer->render($this->snapshot($report));
    }

    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($this->orderedSnapshot($snapshot), JSON_THROW_ON_ERROR));
    }

    private function orderedSnapshot(array $value): array
    {
        $isList = array_is_list($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) { $value[$key] = $this->orderedSnapshot($item); }
        }
        if (! $isList) { ksort($value, SORT_STRING); }

        return $value;
    }

    public function matchesSnapshot(string $hash, array $snapshot): bool
    {
        // Keep exact legacy snapshots readable without rewriting their recorded hash.
        return hash_equals($hash, $this->hash($snapshot))
            || hash_equals($hash, hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)));
    }

    public function current(MonthlyReport $report, array $snapshot): ?MonthlyReportFile
    {
        return MonthlyReportFile::where('report_id', $report->id)->whereIn('source_snapshot_hash', [
            $this->hash($snapshot), hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
        ])
            ->orderByDesc('version_number')->with('file')->first();
    }

    public function store(User $actor, MonthlyReport $report, array $snapshot, ?string &$writtenKey): MonthlyReportFile
    {
        $this->access->authorize($actor, $report);
        abort_unless(DB::transactionLevel() > 0 && in_array($actor->role_code, ['teacher', 'superadmin'], true), 403);
        $this->renderer->validateSnapshot($snapshot);
        $existing = $this->current($report, $snapshot);
        if ($existing && $this->verifiedBytes($existing) !== null) {
            return $existing;
        }
        $bytes = $this->renderer->render($snapshot);
        $id = (string) Str::uuid();
        $writtenKey = 'monthly-report-pdfs/'.$id.'.pdf';
        if (! Storage::disk('local')->put($writtenKey, $bytes)) {
            throw ValidationException::withMessages(['pdf' => 'PDF bulanan belum dapat disimpan. Periksa ruang dan izin storage, lalu coba kembali.']);
        }
        $asset = FileAsset::create(['id' => $id, 'storage_key' => $writtenKey,
            'original_name' => 'DPE-Monthly-Evaluation-'.$report->report_month->format('Y-m').'.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => strlen($bytes), 'sha256_hex' => hash('sha256', $bytes)]);

        return MonthlyReportFile::create(['id' => (string) Str::uuid(), 'report_id' => $report->id, 'file_id' => $asset->id,
            'version_number' => (MonthlyReportFile::where('report_id', $report->id)->max('version_number') ?? 0) + 1,
            'generated_by_user_id' => $actor->id, 'generated_at' => now(),
            'source_snapshot_hash' => $this->hash($snapshot), 'public_snapshot' => $snapshot]);
    }

    public function verifiedBytes(MonthlyReportFile $file): ?string
    {
        $file->loadMissing('file');
        $snapshot = $file->public_snapshot;
        if (! is_array($snapshot) || ($snapshot['report_kind'] ?? null) !== 'monthly'
            || ($snapshot['report_id'] ?? null) !== $file->report_id
            || ! $this->matchesSnapshot($file->source_snapshot_hash, $snapshot)
            || ! $file->file || $file->file->mime_type !== 'application/pdf'
            || ! preg_match('~^monthly-report-pdfs/[a-zA-Z0-9_-]+\.pdf$~D', $file->file->storage_key)
            || ! Storage::disk('local')->exists($file->file->storage_key)) {
            return null;
        }
        $bytes = Storage::disk('local')->get($file->file->storage_key);

        return str_starts_with($bytes, '%PDF-') && strlen($bytes) === $file->file->size_bytes
            && is_string($file->file->sha256_hex) && hash_equals($file->file->sha256_hex, hash('sha256', $bytes)) ? $bytes : null;
    }
}
