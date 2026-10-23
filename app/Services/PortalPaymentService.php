<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\MonthlyCharge;
use App\Models\PaymentProof;
use App\Models\Student;
use App\Models\StudentPortalEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class PortalPaymentService
{
    public const LABELS = ['empty' => 'Belum Unggah Bukti', 'pending' => 'Menunggu Validasi', 'approved' => 'Tervalidasi', 'rejected' => 'Ditolak', 'ambiguous' => 'Perlu Pemeriksaan Admin'];

    public function actor(User $actor, string $role): User
    {
        $current = User::find($actor->id);
        abort_unless($current && $current->is_active && $current->role_code === $role, 403);

        return $current;
    }

    public function owned(User $actor, MonthlyCharge $charge): Student
    {
        $actor = $this->actor($actor, 'student');
        $student = Student::where('portal_user_id', $actor->id)->first();
        abort_unless($student && $charge->fresh()?->student_id === $student->id, 404);

        return $student;
    }

    public function monthCharges(string $studentId, string $month)
    {
        return MonthlyCharge::where('student_id', $studentId)->whereYear('charge_month', substr($month, 0, 4))->whereMonth('charge_month', substr($month, 5, 2));
    }

    public function paid(string $studentId, string $month): bool
    {
        $charges = $this->monthCharges($studentId, $month)->get();

        return $charges->count() === 1 && $charges->first()->paymentProofs()->where('status', 'approved')->exists();
    }

    public function state(MonthlyCharge $charge): array
    {
        $proofs = $charge->paymentProofs()->orderByDesc('submitted_at')->orderByDesc('id')->get();
        $unique = $this->monthCharges($charge->student_id, $charge->charge_month->format('Y-m'))->count() === 1;

        return $this->stateFromProofs($proofs, $unique);
    }

    /** Batch the same status rules for a branch recap without one query per row. */
    public function states(Collection $charges): Collection
    {
        if ($charges->isEmpty()) { return collect(); }
        $counts = collect();
        foreach ($charges->groupBy(fn ($charge) => $charge->charge_month->format('Y-m')) as $month => $group) {
            $monthCounts = MonthlyCharge::whereIn('student_id', $group->pluck('student_id'))
                ->whereYear('charge_month', substr($month, 0, 4))->whereMonth('charge_month', substr($month, 5, 2))
                ->select('student_id')->selectRaw('COUNT(*) AS total')->groupBy('student_id')->pluck('total', 'student_id');
            foreach ($monthCounts as $studentId => $count) { $counts[$studentId.'|'.$month] = (int) $count; }
        }
        $charges->load(['paymentProofs' => fn ($q) => $q->orderByDesc('submitted_at')->orderByDesc('id')]);

        return $charges->mapWithKeys(fn ($charge) => [$charge->id => $this->stateFromProofs($charge->paymentProofs,
            $counts->get($charge->student_id.'|'.$charge->charge_month->format('Y-m')) === 1)]);
    }

    private function stateFromProofs(Collection $proofs, bool $unique): array
    {
        $pending = $proofs->contains(fn ($proof) => ! in_array($proof->status, ['approved', 'rejected'], true));
        $code = ! $unique ? 'ambiguous' : ($proofs->contains('status', 'approved') ? 'approved' : ($pending ? 'pending' : ($proofs->isEmpty() ? 'empty' : 'rejected')));

        return ['code' => $code, 'label' => self::LABELS[$code], 'can_upload' => $unique && in_array($code, ['empty', 'rejected'], true), 'proofs' => $proofs,
            'reason' => ! $unique ? 'Ada beberapa tagihan pada bulan yang sama. Hubungi Admin untuk memeriksa periode pembayaran.' : null];
    }

    public function chargeSignature(MonthlyCharge $charge): string
    {
        $charge = $charge->fresh();

        return $this->hash([$charge->getAttributes(), $charge->student?->only(['portal_user_id', 'status']),
            $this->monthCharges($charge->student_id, $charge->charge_month->format('Y-m'))->orderBy('id')->get()->map->getAttributes()->all(),
            $charge->paymentProofs()->orderBy('id')->get()->map->getAttributes()->all(),
            StudentPortalEvent::where('charge_id', $charge->id)->orderBy('id')->pluck('id')->all()]);
    }

    public function proofSignature(PaymentProof $proof): string
    {
        $proof = $proof->fresh(['imageFile', 'charge.student']);
        $bytes = $this->imageBytes($proof);

        return $this->hash([$proof->getAttributes(), $proof->charge?->getAttributes(), $proof->charge?->student?->only(['portal_user_id']),
            $proof->imageFile?->getAttributes(), $bytes === null ? null : hash('sha256', $bytes),
            StudentPortalEvent::where('payment_proof_id', $proof->id)->orderBy('id')->pluck('id')->all()]);
    }

    public function assertSnapshot(string $current, array $data): void
    {
        $validated = Validator::make($data, ['expected_snapshot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']])->validate();
        if (! hash_equals($current, $validated['expected_snapshot'])) {
            throw ValidationException::withMessages(['expected_snapshot' => 'Data berubah sejak halaman dibuka. Muat halaman terbaru dan periksa kembali sebelum menyimpan.']);
        }
    }

    public function upload(User $actor, MonthlyCharge $charge, array $data): PaymentProof
    {
        $this->owned($actor, $charge);
        $image = Validator::make($data, ['image' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120']])->validate()['image'];
        $dimensions = @getimagesize($image->getRealPath());
        if (! $image instanceof UploadedFile || ! $dimensions || $dimensions[0] * $dimensions[1] > 20000000) {
            throw ValidationException::withMessages(['image' => 'Gunakan gambar JPG, PNG, atau WEBP maksimal 5 MB dan 20 megapiksel.']);
        }
        $bytes = file_get_contents($image->getRealPath());
        $writtenKey = null;
        try {
            return DB::transaction(function () use ($actor, $charge, $data, $image, $bytes, &$writtenKey) {
                $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $this->actor($actor, 'student');
                $student = Student::where('portal_user_id', $actor->id)->lockForUpdate()->firstOrFail();
                $charge = MonthlyCharge::whereKey($charge->id)->lockForUpdate()->firstOrFail();
                abort_unless($charge->student_id === $student->id, 404);
                $this->assertSnapshot($this->chargeSignature($charge), $data);
                if ($student->status !== 'active' || ! $this->state($charge)['can_upload']) {
                    throw ValidationException::withMessages(['image' => 'Bukti belum dapat diunggah. Periksa status siswa dan pembayaran pada halaman terbaru.']);
                }
                $id = (string) Str::uuid();
                $mime = $image->getMimeType();
                $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
                $writtenKey = 'payment-proofs/'.$id.'.'.$extension;
                if (! Storage::disk('local')->put($writtenKey, $bytes)) {
                    throw ValidationException::withMessages(['image' => 'Bukti belum dapat disimpan. Coba kembali setelah penyimpanan tersedia.']);
                }
                $asset = FileAsset::create(['id' => $id, 'storage_key' => $writtenKey, 'original_name' => basename(str_replace('\\', '/', $image->getClientOriginalName())),
                    'mime_type' => $mime, 'size_bytes' => strlen($bytes), 'sha256_hex' => hash('sha256', $bytes)]);
                $proof = PaymentProof::create(['id' => (string) Str::uuid7(), 'charge_id' => $charge->id, 'image_file_id' => $asset->id,
                    'uploaded_by_user_id' => $actor->id, 'submitted_at' => now(), 'status' => 'pending']);
                $this->event($actor, 'payment_uploaded', $charge, $proof, null, $proof->getAttributes());

                return $proof;
            });
        } catch (Throwable $error) {
            if ($writtenKey) {
                Storage::disk('local')->delete($writtenKey);
            }
            throw $error;
        }
    }

    public function review(User $actor, PaymentProof $proof, array $data): PaymentProof
    {
        $actor = $this->actor($actor, 'superadmin');
        $validated = Validator::make($data, ['status' => ['required', 'in:approved,rejected'], 'rejection_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:4000'],
            'expected_snapshot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']])->validate();
        if ($validated['status'] === 'approved' && $this->imageBytes($proof) === null) {
            throw ValidationException::withMessages(['status' => 'Gambar bukti tidak tersedia atau berubah. Periksa bukti sebelum memvalidasi.']);
        }

        return app(PaymentProofService::class)->review($proof, $actor, $validated['status'], $validated['rejection_reason'] ?? null, $validated['expected_snapshot']);
    }

    public function imageBytes(PaymentProof $proof): ?string
    {
        $proof->loadMissing('imageFile');
        $asset = $proof->imageFile;
        if (! $asset || ! preg_match('~^payment-proofs/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)$~D', $asset->storage_key)
            || ! in_array($asset->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)
            || $asset->size_bytes < 1 || $asset->size_bytes > 5 * 1024 * 1024 || ! Storage::disk('local')->exists($asset->storage_key)) {
            return null;
        }
        $bytes = Storage::disk('local')->get($asset->storage_key);
        $dimensions = @getimagesizefromstring($bytes);
        if (strlen($bytes) !== $asset->size_bytes || ! $dimensions || ($dimensions['mime'] ?? null) !== $asset->mime_type
            || $dimensions[0] * $dimensions[1] > 20000000
            || ($asset->sha256_hex !== null && ! hash_equals($asset->sha256_hex, hash('sha256', $bytes)))) {
            return null;
        }

        return $bytes;
    }

    public function event(User $actor, string $type, ?MonthlyCharge $charge = null, ?PaymentProof $proof = null, ?array $before = null, ?array $after = null, ?string $reason = null, ?Branch $branch = null): void
    {
        StudentPortalEvent::create(['id' => (string) Str::uuid7(), 'actor_user_id' => $actor->id, 'student_id' => $charge?->student_id,
            'branch_id' => $branch?->id ?? $charge?->branch_id, 'charge_id' => $charge?->id, 'payment_proof_id' => $proof?->id,
            'event_type' => $type, 'before_snapshot' => $before, 'after_snapshot' => $after, 'reason' => $reason, 'occurred_at' => now()]);
    }

    public function settingSignature(Branch $branch): string
    {
        $branch = $branch->fresh();

        return $this->hash([$branch->id, $branch->portal_attendance_visible,
            StudentPortalEvent::where('branch_id', $branch->id)->where('event_type', 'attendance_visibility_changed')->orderBy('id')->pluck('id')->all()]);
    }

    public function saveVisibility(User $actor, Branch $branch, array $data): void
    {
        $this->actor($actor, 'superadmin');
        $validated = Validator::make($data, ['visible' => ['required', 'boolean']])->validate();
        DB::transaction(function () use ($actor, $branch, $data, $validated) {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $this->actor($actor, 'superadmin');
            $branch = Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $this->assertSnapshot($this->settingSignature($branch), $data);
            $before = ['visible' => $branch->portal_attendance_visible];
            $after = ['visible' => (bool) $validated['visible']];
            if ($before === $after) {
                return;
            }
            $branch->forceFill(['portal_attendance_visible' => $after['visible']])->save();
            $this->event($actor, 'attendance_visibility_changed', before: $before, after: $after, branch: $branch);
        }, 3);
    }

    private function hash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
}
