<?php

namespace App\Services;

use App\Models\FileAsset;
use App\Models\QuarterlyReport;
use App\Models\QuarterlyReportFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class QuarterlyReportPdfService
{
    public function __construct(private QuarterlyReportAccessService $access, private QuarterlyReportContentService $content,
        private QuarterlyReportWorkflowService $workflow, private QuarterlyReportPdfRenderer $renderer) {}

    public function preview(User $actor, QuarterlyReport $report): string
    {
        $this->access->authorize($actor, $report);
        $snapshot = $report->status === 'approved' ? $report->approved_snapshot : $this->content->snapshot($report);
        if (! is_array($snapshot)) {
            throw ValidationException::withMessages(['snapshot' => 'Salinan persetujuan belum tersedia.']);
        }

        return $this->renderer->render($snapshot, $report->status !== 'approved');
    }

    public function generate(User $actor, QuarterlyReport $report, array $data, bool $regenerate = false): QuarterlyReportFile
    {
        $writtenKey = null;
        try {
            return DB::transaction(function () use ($actor, $report, $data, $regenerate, &$writtenKey) {
                $actor = $actor->fresh();
                abort_unless($actor, 403);
                $report = $this->workflow->lock($actor, $report, $data);
                $gate = $this->content->gate($report);
                if (! $gate['internal_pdf_ready'] || ! is_array($report->approved_snapshot)) {
                    throw ValidationException::withMessages(['pdf' => 'PDF final memerlukan persetujuan rapor final dan ketiga laporan bulanan. Preview draf tetap tersedia setelah isi lengkap.']);
                }
                $snapshot = $report->approved_snapshot;
                $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
                $existing = $report->files()->where('approval_revision', $report->revision_number)->where('source_snapshot_hash', $hash)->latest('version_number')->first();
                if ($existing && ! $regenerate) {
                    if (! $this->validFile($existing)) {
                        throw ValidationException::withMessages(['pdf' => 'File PDF hilang atau berubah. Gunakan Buat ulang PDF dengan alasan untuk menyimpan versi baru.']);
                    }

                    return $existing;
                }
                $comment = null;
                if ($regenerate) {
                    if (! $existing) {
                        throw ValidationException::withMessages(['pdf' => 'Belum ada PDF untuk persetujuan ini. Gunakan Buat PDF final.']);
                    }
                    $comment = trim(Validator::make($data, ['comment' => ['required', 'string', 'max:255']])->validate()['comment']);
                    if ($comment === '') {
                        throw ValidationException::withMessages(['comment' => 'Alasan pembuatan ulang wajib diisi.']);
                    }
                }
                $bytes = $this->renderer->render($snapshot);
                $id = (string) Str::uuid();
                $writtenKey = 'report-pdfs/'.$id.'.pdf';
                if (! Storage::disk('local')->put($writtenKey, $bytes)) {
                    throw ValidationException::withMessages(['pdf' => 'PDF belum dapat disimpan. Periksa ruang dan izin folder storage, lalu coba kembali.']);
                }
                $asset = FileAsset::create(['id' => $id, 'storage_key' => $writtenKey, 'original_name' => 'DPE-Evaluation-'.$report->cycle->start_month->format('Y-m').'.pdf',
                    'mime_type' => 'application/pdf', 'size_bytes' => strlen($bytes), 'sha256_hex' => hash('sha256', $bytes)]);
                $file = QuarterlyReportFile::create(['id' => (string) Str::uuid(), 'cycle_id' => $report->cycle_id, 'file_id' => $asset->id,
                    'quarterly_report_id' => $report->id, 'version_number' => (QuarterlyReportFile::where('cycle_id', $report->cycle_id)->max('version_number') ?? 0) + 1,
                    'approval_revision' => $report->revision_number, 'generated_by_user_id' => $actor->id, 'generated_at' => now(),
                    'source_snapshot_hash' => $hash, 'public_snapshot' => $snapshot]);
                $this->workflow->event($report, $actor, $regenerate ? 'pdf_regenerated' : 'pdf_generated', $report->status, null, $comment,
                    ['file_id' => $file->id, 'version_number' => $file->version_number, 'approval_revision' => $report->revision_number, 'source_snapshot_hash' => $hash]);

                return $file;
            });
        } catch (Throwable $error) {
            if ($writtenKey) {
                Storage::disk('local')->delete($writtenKey);
            }
            throw $error;
        }
    }

    public function bytes(User $actor, QuarterlyReport $report, QuarterlyReportFile $file): string
    {
        $this->access->authorize($actor, $report);
        abort_unless($file->quarterly_report_id === $report->id && $file->cycle_id === $report->cycle_id, 404);
        $bytes = $this->verifiedBytes($file);
        abort_unless($bytes !== null, 404);

        return $bytes;
    }

    private function validFile(QuarterlyReportFile $file): bool
    {
        return $this->verifiedBytes($file) !== null;
    }

    public function verifiedBytes(QuarterlyReportFile $file): ?string
    {
        $file->loadMissing('file');
        if (! is_array($file->public_snapshot) || ($file->public_snapshot['report_id'] ?? null) !== $file->quarterly_report_id
            || ($file->public_snapshot['cycle_id'] ?? null) !== $file->cycle_id || ! $file->source_snapshot_hash
            || ! hash_equals($file->source_snapshot_hash, hash('sha256', json_encode($file->public_snapshot, JSON_THROW_ON_ERROR)))
            || ! $file->file || $file->file->mime_type !== 'application/pdf'
            || ! preg_match('~^report-pdfs/[a-zA-Z0-9_-]+\.pdf$~D', $file->file->storage_key) || ! Storage::disk('local')->exists($file->file->storage_key)) {
            return null;
        }
        $bytes = Storage::disk('local')->get($file->file->storage_key);

        return str_starts_with($bytes, '%PDF-') && strlen($bytes) === $file->file->size_bytes
            && is_string($file->file->sha256_hex) && hash_equals($file->file->sha256_hex, hash('sha256', $bytes)) ? $bytes : null;
    }
}
