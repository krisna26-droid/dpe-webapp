<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReportTemplateService
{
    public const FIELDS = ['organization_name' => 'Nama lembaga', 'registration_number' => 'Nomor SK', 'slogan' => 'Slogan', 'address' => 'Alamat kop', 'phone' => 'Telepon kop', 'instagram_accounts' => 'Instagram resmi (satu akun per baris)', 'manager_name' => 'Nama Manager', 'documentation_text' => 'Informasi dokumentasi', 'closing_text' => 'Narasi penutup'];

    public function settings(): array
    {
        $row = DB::table('report_template_settings')->where('id', 1)->first();
        $values = $row ? json_decode($row->settings, true, 512, JSON_THROW_ON_ERROR) : [];

        return ['revision' => $row?->revision ?? 0, 'template_version' => config('report-pdf.template_version'),
            'fields' => array_replace(config('report-pdf.defaults'), array_intersect_key($values, self::FIELDS))];
    }

    public function signature(): string
    {
        return hash('sha256', json_encode($this->settings(), JSON_THROW_ON_ERROR));
    }

    public function save(User $actor, array $data): void
    {
        $actor = $actor->fresh();
        abort_unless($actor?->is_active && $actor->role_code === 'superadmin', 403);
        $rules = ['expected_snapshot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']];
        foreach (self::FIELDS as $key => $label) {
            $rules[$key] = ['required', 'string', 'max:'.(in_array($key, ['documentation_text', 'closing_text'], true) ? '8000' : ($key === 'instagram_accounts' ? '1000' : '255'))];
        }
        $valid = Validator::make($data, $rules)->validate();
        DB::transaction(function () use ($actor, $valid) {
            // Lock existing settings; insertOrIgnore also protects the first concurrent insert.
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('report_template_settings')->where('id', 1)->lockForUpdate()->first();
            if (! hash_equals($this->signature(), $valid['expected_snapshot'])) {
                throw ValidationException::withMessages(['expected_snapshot' => 'Template berubah. Muat pengaturan terbaru sebelum menyimpan.']);
            }
            $values = ['settings' => json_encode(array_intersect_key($valid, self::FIELDS), JSON_THROW_ON_ERROR), 'revision' => $this->settings()['revision'] + 1, 'updated_by_user_id' => $actor->id, 'updated_at' => now()];
            if ($existing) {
                DB::table('report_template_settings')->where('id', 1)->update($values);
            } elseif (! DB::table('report_template_settings')->insertOrIgnore(['id' => 1, ...$values])) {
                throw ValidationException::withMessages(['expected_snapshot' => 'Template baru saja disimpan petugas lain. Muat data terbaru.']);
            }
        }, 3);
    }

    public function snapshot(): array
    {
        $data = $this->settings();
        $asset = SystemSetting::with('logoFile')->first()?->logoFile;
        $bytes = $asset && in_array($asset->mime_type, ['image/png', 'image/jpeg', 'image/webp'], true) && $asset->size_bytes <= 262144 && Storage::disk('local')->exists($asset->storage_key)
            ? Storage::disk('local')->get($asset->storage_key) : file_get_contents(public_path('assets/dpe/dpe-logo.png'));
        $info = @getimagesizefromstring($bytes);
        if (! $info || $info[0] * $info[1] > 10000000) {
            $bytes = file_get_contents(public_path('assets/dpe/dpe-logo.png'));
            $info = getimagesizefromstring($bytes);
        }
        // The approved logo bytes stay with the approved template and do not use remote URLs.
        $data['logo_data_uri'] = 'data:'.$info['mime'].';base64,'.base64_encode($bytes);
        $data['signature_notice'] = 'Area tanda tangan - belum diunggah';

        return $data;
    }
}
