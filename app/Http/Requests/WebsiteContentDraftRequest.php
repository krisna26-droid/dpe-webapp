<?php

namespace App\Http\Requests;

use App\Services\PublicWebsiteService;
use App\Services\WebsiteContentDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WebsiteContentDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role_code === 'superadmin' && $this->user()?->is_active;
    }

    public function rules(): array
    {
        $key = (string) $this->route('key');
        $fields = WebsiteContentDefinition::fields($key);
        $rules = ['expected_snapshot' => ['required', 'regex:/\A[a-f0-9]{64}\z/'], 'title' => ['required', 'string', 'max:255'],
            'display_order' => ['required', 'integer', 'between:0,32767'], 'is_published' => ['required', 'boolean'],
            'remove_image' => ['sometimes', 'boolean']];
        foreach ($fields as $field => $label) {
            $rules[$field] = ['nullable', 'string', 'max:'.match ($field) {
                'description', 'features', 'detail' => 5000,
                'primary_url', 'secondary_url', 'instagram', 'facebook', 'youtube', 'tiktok' => 2048,
                'whatsapp', 'phone' => 40,
                'primary_label', 'secondary_label' => 60,
                'email' => 254,
                default => 500,
            }];
        }
        if (isset($fields['email'])) {
            $rules['email'][] = 'email:rfc';
        }
        $rules['image'] = WebsiteContentDefinition::imageAllowed($key)
            ? ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.WebsiteContentDefinition::uploadLimitKb(), 'dimensions:max_width=4096,max_height=4096']
            : ['prohibited'];
        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $key = (string) $this->route('key');
            $fields = WebsiteContentDefinition::fields($key);
            foreach (['primary_url', 'secondary_url', 'instagram', 'facebook', 'youtube', 'tiktok'] as $field) {
                if (isset($fields[$field]) && is_string($value = $this->input($field)) && $value !== '') {
                    $valid = WebsiteContentDefinition::validLink(trim($value));
                    if (in_array($field, ['instagram', 'facebook', 'youtube', 'tiktok'], true)) {
                        $valid = $valid && strtolower(parse_url($value, PHP_URL_SCHEME) ?? '') === 'https';
                    }
                    if (! $valid) {
                        $validator->errors()->add($field, in_array($field, ['instagram', 'facebook', 'youtube', 'tiktok'], true)
                            ? 'Gunakan tautan HTTPS untuk media sosial.' : 'Gunakan tautan http/https atau tujuan halaman yang tersedia.');
                    }
                }
            }
            if (isset($fields['whatsapp']) && is_string($value = $this->input('whatsapp')) && trim($value) !== ''
                && ! app(PublicWebsiteService::class)->whatsapp(trim($value))) {
                $validator->errors()->add('whatsapp', 'Nomor WhatsApp tidak valid. Gunakan 08… atau 62….');
            }
            if (is_string($value = $this->input('features')) && $value !== '') {
                $lines = array_filter(array_map('trim', preg_split('/\R/u', $value)), fn ($line) => $line !== '');
                if (count($lines) > 20 || collect($lines)->contains(fn ($line) => mb_strlen($line) > 250)) {
                    $validator->errors()->add('features', 'Maksimal 20 poin, masing-masing 250 karakter.');
                }
            }
            if ($this->hasFile('image') && $this->boolean('remove_image')) {
                $validator->errors()->add('image', 'Pilih unggah foto baru atau hapus foto, salah satu saja.');
            }
        }];
    }

    public function attributes(): array
    {
        return ['title' => 'judul', 'display_order' => 'urutan', 'image' => 'foto', 'expected_snapshot' => 'versi konten']
            + WebsiteContentDefinition::fields((string) $this->route('key'));
    }

    public function messages(): array
    {
        return ['title.required' => 'Isi judul atau nama lembaga.', 'title.max' => 'Judul maksimal 255 karakter.',
            'display_order.between' => 'Urutan harus antara 0 dan 32.767.', 'image.uploaded' => 'Foto belum berhasil diunggah. Pilih ukuran sesuai batas pada form.',
            'image.max' => 'Ukuran foto melebihi batas unggah yang tercantum pada form.', 'image.image' => 'Pilih berkas foto yang valid.',
            'image.mimes' => 'Gunakan foto JPG, PNG, atau WebP.', 'image.mimetypes' => 'Format berkas foto tidak sesuai.',
            'image.dimensions' => 'Ukuran gambar maksimal 4.096 × 4.096 piksel.', '*.string' => 'Isian :attribute harus berupa teks.',
            'description.max' => 'Deskripsi maksimal 5.000 karakter.'];
    }
}
