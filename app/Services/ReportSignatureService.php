<?php

namespace App\Services;

use App\Models\FileAsset;
use App\Models\ReportSignature;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReportSignatureService
{
    public const MAX_BYTES = 524288;

    public function __construct(private FileAssetService $files) {}

    private function context(User $user, string $scope, bool $lock = false): array
    {
        abort_unless(in_array($scope, ['teacher', 'manager'], true), 404);
        $actor = $lock ? User::whereKey($user->id)->lockForUpdate()->firstOrFail() : $user->fresh();
        abort_unless($actor?->is_active && $actor->role_code === ($scope === 'teacher' ? 'teacher' : 'superadmin'), 403);
        $teacher = null;
        if ($scope === 'teacher') {
            $query = Teacher::where('user_id', $actor->id);
            if ($lock) { $query->lockForUpdate(); }
            $teacher = $query->first();
            abort_unless($teacher?->is_active, 403);
        } elseif ($lock) {
            DB::table('report_template_settings')->insertOrIgnore(['id' => 1,
                'settings' => json_encode(config('report-pdf.defaults'), JSON_THROW_ON_ERROR), 'revision' => 0,
                'updated_by_user_id' => $actor->id, 'updated_at' => now()]);
            DB::table('report_template_settings')->where('id', 1)->lockForUpdate()->first();
            $this->lockManager();
        }
        $name = $teacher ? $actor->full_name : $this->managerName();

        return ['actor' => $actor, 'teacher' => $teacher, 'name' => $name,
            'key' => $teacher ? 'teacher:'.$teacher->id : 'manager'];
    }

    private function managerName(): string
    {
        $row = DB::table('report_template_settings')->where('id', 1)->first();
        $settings = $row ? json_decode($row->settings, true, 512, JSON_THROW_ON_ERROR) : [];

        return $settings['manager_name'] ?? config('report-pdf.defaults.manager_name');
    }

    private function current(string $key): ?ReportSignature
    {
        if (! Schema::hasTable('report_signatures')) { return null; }

        return ReportSignature::where('owner_key', $key)->whereNotNull('file_id')->latest('revision')->with('file')->first();
    }

    private function version(array $context, ?ReportSignature $record): string
    {
        return hash('sha256', json_encode([$context['key'], $context['name'], $record?->getAttributes(),
            $record?->file?->getAttributes()], JSON_THROW_ON_ERROR));
    }

    public function state(User $user, string $scope): array
    {
        $context = $this->context($user, $scope);
        $record = $this->current($context['key']);

        return ['record' => $record, 'version' => $this->version($context, $record), 'signer_name' => $context['name'],
            'name_matches' => $record && $record->signer_name === $context['name'],
            'file_valid' => $record && $this->verifiedBytes($record->file) !== null];
    }

    public function lockManager(): void
    {
        if (Schema::hasTable('report_signatures')) {
            $anchor = ReportSignature::where('owner_key', 'manager')->where('revision', 0)->lockForUpdate()->first();
            if (! $anchor) {
                throw ValidationException::withMessages(['signatures' => 'Data pengunci tanda tangan Manager tidak tersedia. Hubungi pengelola sistem.']);
            }
        }
    }

    public function save(User $user, string $scope, array $data, ?UploadedFile $upload): ReportSignature
    {
        $this->context($user, $scope);
        if (! Schema::hasTable('report_signatures')) {
            throw ValidationException::withMessages(['signature_file' => 'Migrasi tanda tangan belum dipasang.']);
        }
        $valid = Validator::make(array_replace($data, ['signature_file' => $upload]), [
            'signature_version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'signature_file' => ['bail', 'required', 'file', 'image', 'mimes:png,jpg,jpeg', 'extensions:png,jpg,jpeg',
                'max:512', 'dimensions:max_width=2000,max_height=2000'],
            'teacher_id' => ['prohibited'], 'owner_key' => ['prohibited'], 'file_id' => ['prohibited'],
            'uploaded_by_user_id' => ['prohibited'], 'signer_name' => ['prohibited'],
        ], ['signature_file.max' => 'Ukuran tanda tangan maksimal 512 KB.',
            'signature_file.dimensions' => 'Ukuran gambar maksimal 2000 x 2000 piksel.',
            'signature_file.mimes' => 'Gunakan gambar PNG atau JPG.', 'signature_file.extensions' => 'Nama berkas harus berakhiran PNG, JPG atau JPEG.'])->validate();
        $image = @imagecreatefromstring(file_get_contents($upload->getRealPath()));
        if ($image === false) {
            throw ValidationException::withMessages(['signature_file' => 'Gambar tanda tangan rusak atau tidak dapat dibaca.']);
        }
        imagedestroy($image);
        $written = [];
        $retainedId = null;
        try {
            $result = DB::transaction(function () use ($user, $scope, $valid, $upload, &$written): ReportSignature {
                $context = $this->context($user, $scope, true);
                $current = $this->current($context['key']);
                if (! hash_equals($this->version($context, $current), $valid['signature_version'])) {
                    throw ValidationException::withMessages(['signature_version' => 'Tanda tangan atau nama penandatangan berubah. Muat halaman terbaru sebelum mengunggah.']);
                }
                $file = $this->files->store($upload, 'report-signatures');
                $written[] = $file;

                return ReportSignature::create(['id' => (string) Str::uuid(), 'owner_key' => $context['key'],
                    'teacher_id' => $context['teacher']?->id, 'revision' => ($current?->revision ?? 0) + 1,
                    'file_id' => $file->id, 'signer_name' => $context['name'], 'uploaded_by_user_id' => $context['actor']->id]);
            }, 3);
            $retainedId = $result->file_id;

            return $result;
        } finally {
            foreach ($written as $file) {
                if ($file->id !== $retainedId) {
                    try { $this->files->delete($file); } catch (Throwable $cleanup) { report($cleanup); }
                }
            }
        }
    }

    private function verifiedBytes(?FileAsset $file): ?string
    {
        if (! $file || ! preg_match('#^report-signatures/[a-f0-9-]{36}\.(png|jpe?g)$#iD', $file->storage_key)
            || ! in_array($file->mime_type, ['image/png', 'image/jpeg'], true) || $file->size_bytes > self::MAX_BYTES) { return null; }
        $disk = Storage::disk('local');
        if (! $disk->exists($file->storage_key) || $disk->size($file->storage_key) > self::MAX_BYTES) { return null; }
        $bytes = $disk->get($file->storage_key);
        if (strlen($bytes) !== $file->size_bytes || ! is_string($file->sha256_hex)
            || ! hash_equals($file->sha256_hex, hash('sha256', $bytes))) { return null; }
        $info = @getimagesizefromstring($bytes);

        return $info && ($info['mime'] ?? '') === $file->mime_type && $info[0] > 0 && $info[1] > 0
            && $info[0] <= 2000 && $info[1] <= 2000 ? $bytes : null;
    }

    public function image(User $user, string $scope): array
    {
        $state = $this->state($user, $scope);
        abort_unless($state['name_matches'], 404);
        $file = $state['record']->file;
        $bytes = $this->verifiedBytes($file);
        abort_unless($bytes !== null, 404);

        return ['bytes' => $bytes, 'mime' => $file->mime_type];
    }

    public function snapshot(string $teacherId, string $teacherName, string $managerName): array
    {
        $result = [];
        foreach (['teacher' => [$teacherId, $teacherName], 'manager' => [null, $managerName]] as $scope => [$id, $name]) {
            $record = $this->current($id ? 'teacher:'.$id : 'manager');
            $result[$scope] = null;
            if (! $record || $record->signer_name !== $name) { continue; }
            $bytes = $this->verifiedBytes($record->file);
            if ($record->teacher_id !== $id || $bytes === null) {
                throw ValidationException::withMessages(['signatures' => 'Tanda tangan '.$scope.' hilang, berubah, atau tidak cocok. Unggah ulang sebelum melanjutkan.']);
            }
            $result[$scope] = ['id' => $record->id, 'teacher_id' => $id, 'file_id' => $record->file_id,
                'revision' => $record->revision, 'signer_name' => $name, 'sha256' => hash('sha256', $bytes),
                'data_uri' => 'data:'.$record->file->mime_type.';base64,'.base64_encode($bytes)];
        }

        return $result;
    }

    public static function validEmbedded(array $signature, string $name, ?string $teacherId): bool
    {
        $uri = $signature['data_uri'] ?? null;
        if (($signature['teacher_id'] ?? null) !== $teacherId || ($signature['signer_name'] ?? null) !== $name
            || ! is_string($uri) || strlen($uri) > 700000
            || ! preg_match('#^data:(image/(?:png|jpeg));base64,([A-Za-z0-9+/=]+)$#D', $uri, $match)) { return false; }
        $bytes = base64_decode($match[2], true);
        if ($bytes === false || strlen($bytes) > self::MAX_BYTES || ! is_string($signature['sha256'] ?? null)
            || ! hash_equals($signature['sha256'], hash('sha256', $bytes))) { return false; }
        $info = @getimagesizefromstring($bytes);

        return $info && ($info['mime'] ?? '') === $match[1] && $info[0] > 0 && $info[1] > 0
            && $info[0] <= 2000 && $info[1] <= 2000;
    }
}
