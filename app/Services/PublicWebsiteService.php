<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\Program;
use App\Models\PublicContentSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PublicWebsiteService
{
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private bool $preview = false;

    public function data(array $overrides = []): array
    {
        $this->preview = $overrides !== [];
        $sections = Schema::hasTable('public_content_sections')
            ? PublicContentSection::query()->with('imageFile')->where('is_published', true)
                ->orderBy('display_order')->orderBy('section_key')->get()->keyBy('section_key')
            : collect();

        foreach ($overrides as $key => $payload) {
            if (! $payload['is_published']) {
                $sections->forget($key);
                continue;
            }
            $record = new PublicContentSection($payload + ['section_key' => $key]);
            $record->setRelation('imageFile', $payload['image_file_id'] ? FileAsset::find($payload['image_file_id']) : null);
            $sections->put($key, $record);
        }
        $sections = $sections->sort(fn ($a, $b) => ($a->display_order <=> $b->display_order) ?: strcmp($a->section_key, $b->section_key));

        $hasManagedContent = Schema::hasTable('public_content_sections') && PublicContentSection::query()->exists();
        $hasManagedContent = $hasManagedContent || (Schema::hasTable('website_content_changes')
            && \App\Models\WebsiteContentChange::whereIn('action', ['publish', 'hide', 'remove_content'])->exists());
        $sample = (bool) config('dpe_public.sample_content') && ! $hasManagedContent && ! $this->preview;
        $identity = $this->section($sections->get('identity'), 'Dharma Private English');
        $identityBody = $this->body($sections->get('identity')?->body);
        $identity['footer_text'] = array_key_exists('footer_text', $identityBody) ? $this->text($identityBody, 'footer_text') : 'Setiap langkah belajar berarti.';
        $hero = $this->section($sections->get('home.hero'), 'Bahasa Inggris untuk langkah besarmu.');
        $heroBody = $this->body($sections->get('home.hero')?->body);
        $hero += ['primary_label' => 'Lihat program', 'primary_url' => '#program', 'secondary_label' => 'Hubungi kami', 'secondary_url' => '#kontak'];
        foreach (['primary_label', 'secondary_label', 'primary_url', 'secondary_url'] as $field) {
            if (array_key_exists($field, $heroBody)) {
                $hero[$field] = $this->text($heroBody, $field);
            }
        }
        foreach (['primary_url', 'secondary_url'] as $field) {
            if (! WebsiteContentDefinition::validLink($hero[$field])) {
                $hero[$field] = '';
            }
        }
        if ($sample) {
            $hero['description'] = 'Temukan pilihan belajar yang sesuai dengan tujuanmu. Mulai perjalanan bahasa Inggris bersama Dharma Private English.';
            $hero['eyebrow'] = 'Mulai dari langkah kecil';
        }

        $intro = $this->section($sections->get('home.intro'));
        $about = [
            'intro' => $this->section($sections->get('about.intro')),
            'vision' => $this->section($sections->get('about.vision')),
            'mission' => $this->section($sections->get('about.mission')),
            'approaches' => $this->prefixed($sections, 'about.approach.'),
        ];
        $benefits = $this->prefixed($sections, 'home.benefit.');
        if ($sample) {
            $intro = ['title' => 'Setiap langkah belajar punya tujuan.', 'description' => 'Kenali pilihan program dan temukan cara belajar bahasa Inggris yang sesuai untukmu.', 'image' => null, 'eyebrow' => 'Tentang DPE'];
            $about['intro'] = ['title' => 'Ruang untuk belajar dan bertumbuh.', 'description' => 'Dharma Private English adalah tempat les bahasa Inggris. Halaman ini disiapkan untuk memperkenalkan profil lembaga, pendekatan belajar, dan pilihan program kepada calon siswa.', 'image' => null, 'eyebrow' => 'Tentang kami'];
            $about['vision'] = ['title' => 'Visi kami', 'description' => 'Membuka kesempatan untuk belajar bahasa Inggris dan mengembangkan kepercayaan diri.', 'image' => null, 'eyebrow' => ''];
            $about['mission'] = ['title' => 'Misi kami', 'description' => 'Mendampingi proses belajar melalui latihan, materi yang relevan, dan pilihan kelas sesuai kebutuhan.', 'image' => null, 'eyebrow' => ''];
            $benefits = [
                ['title' => 'Pilihan sesuai tujuan', 'description' => 'Kenali program reguler dan privat sebelum menentukan langkah belajar.', 'image' => null],
                ['title' => 'Latihan keterampilan', 'description' => 'Kembangkan speaking, listening, reading, dan writing secara bertahap.', 'image' => null],
                ['title' => 'Belajar lebih dekat', 'description' => 'Cari informasi cabang dan konsultasikan kebutuhan belajar kamu.', 'image' => null],
            ];
            $about['approaches'] = [
                ['title' => 'Kenali tujuanmu', 'description' => 'Mulai dengan memahami kebutuhan dan tujuan belajar bahasa Inggris.', 'image' => null],
                ['title' => 'Temukan program', 'description' => 'Diskusikan pilihan kelas yang sesuai dengan kebutuhan belajar.', 'image' => null],
                ['title' => 'Terus berlatih', 'description' => 'Bangun kebiasaan belajar melalui latihan keterampilan bahasa.', 'image' => null],
            ];
        }

        $contactSection = $sections->get('contact');
        $contact = $this->contact($this->body($contactSection?->body));
        $contact['title'] = $contactSection?->title ?: 'Mari mulai percakapan.';
        $contact['description'] = $this->section($contactSection)['description'];

        return [
            'identity' => $identity, 'hero' => $hero, 'intro' => $intro, 'benefits' => $benefits,
            'about' => $about, 'programs' => $this->programs($sections, $sample),
            'branches' => $this->branches($sections, $sample), 'contact' => $contact, 'is_sample' => $sample,
        ];
    }

    public function program(string $id): ?array
    {
        foreach ($this->data()['programs'] as $program) {
            if ($program['id'] === $id) {
                return $program;
            }
        }

        return null;
    }

    /** Verify both the relative key and the physical file, including symlink containment. */
    public function imagePath(FileAsset $file): ?string
    {
        $key = $file->storage_key;
        if (! in_array($file->mime_type, self::IMAGE_TYPES, true) || ! is_string($key)
            || $key === '' || preg_match('~(?:^/|\\\\|\x00|(^|/)\.\.?(/|$)|:)~', $key)) {
            return null;
        }

        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));
        $path = realpath($disk->path($key));
        if ($root === false || $path === false || ! is_file($path)
            || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $image = @getimagesize($path);

        return $mime === $file->mime_type && is_array($image)
            && ($image['mime'] ?? null) === $mime ? $path : null;
    }

    private function section(?PublicContentSection $section, string $fallback = ''): array
    {
        $body = $this->body($section?->body);

        return [
            'title' => $section?->title ?: $fallback,
            'description' => $this->text($body, 'description'),
            'eyebrow' => $this->text($body, 'eyebrow'),
            'image' => $section?->imageFile && $this->imagePath($section->imageFile)
                ? route($this->preview ? 'superadmin.website.image' : 'public.image', $section->imageFile->id) : null,
            'caption' => $this->text($body, 'caption'), 'heading' => $this->text($body, 'heading'),
            'heading_description' => $this->text($body, 'heading_description'), 'detail' => $this->text($body, 'detail'),
        ];
    }

    private function prefixed(Collection $sections, string $prefix): array
    {
        return $sections->filter(fn ($section) => str_starts_with($section->section_key, $prefix))
            ->map(fn ($section) => $this->section($section))->values()->all();
    }

    private function programs(Collection $sections, bool $sample): array
    {
        if ($sample) {
            return array_map(fn ($program) => $program + ['duration_label' => '', 'image' => null], config('dpe_public.sample_programs'));
        }

        $metadata = $sections->filter(fn ($section) => str_starts_with($section->section_key, 'program.'));
        if ($metadata->isEmpty()) {
            return [];
        }
        $records = Program::query()->where('is_active', true)
            ->whereIn('id', $metadata->keys()->map(fn ($key) => substr($key, 8)))->get()->keyBy('id');

        return $metadata->map(function ($section) use ($records) {
            $program = $records->get(substr($section->section_key, 8));
            if (! $program) {
                return null;
            }
            $body = $this->body($section->body);

            return $this->section($section) + [
                'id' => $program->id, 'name' => $program->name,
                'type_label' => match ($program->class_type) {
                    'branch' => 'Kelas reguler', 'private' => 'Kelas privat', default => 'Program belajar',
                },
                'audience' => $this->text($body, 'audience'), 'features' => $this->strings($body['features'] ?? []),
                'duration_label' => $this->text($body, 'duration_label'),
            ];
        })->filter()->values()->all();
    }

    private function branches(Collection $sections, bool $sample): array
    {
        if ($sample) {
            return array_map(fn ($name) => [
                'name' => $name, 'address' => '', 'service_hours' => '', 'image' => null,
                'map_url' => null, 'whatsapp_url' => null, 'description' => '',
            ], config('dpe_public.sample_branches'));
        }

        $metadata = $sections->filter(fn ($section) => str_starts_with($section->section_key, 'branch.'));
        if ($metadata->isEmpty()) {
            return [];
        }
        $records = Branch::query()->where('is_active', true)
            ->whereIn('id', $metadata->keys()->map(fn ($key) => substr($key, 7)))->get()->keyBy('id');

        return $metadata->map(function ($section) use ($records) {
            $branch = $records->get(substr($section->section_key, 7));
            if (! $branch) {
                return null;
            }
            $body = $this->body($section->body);
            $address = trim($branch->address ?? '');

            return $this->section($section) + [
                'name' => $branch->name, 'address' => $address,
                'service_hours' => $this->text($body, 'service_hours'),
                'map_url' => $address !== '' ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address) : null,
                'whatsapp_url' => $this->whatsapp($this->text($body, 'whatsapp'), 'Halo DPE, saya ingin mengetahui informasi cabang '.$branch->name.'.'),
            ];
        })->filter()->values()->all();
    }

    private function contact(array $body): array
    {
        $email = $this->text($body, 'email');
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        $socials = [];
        foreach (['instagram', 'facebook', 'youtube', 'tiktok'] as $network) {
            $url = $this->text($body, $network);
            if (filter_var($url, FILTER_VALIDATE_URL) && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
                && ! parse_url($url, PHP_URL_USER) && ! parse_url($url, PHP_URL_PASS)) {
                $socials[] = ['name' => ucfirst($network), 'url' => $url];
            }
        }

        return [
            'whatsapp' => $this->text($body, 'whatsapp'),
            'whatsapp_url' => $this->whatsapp($this->text($body, 'whatsapp')),
            'email' => $email, 'phone' => $this->text($body, 'phone'),
            'service_hours' => $this->text($body, 'service_hours'), 'socials' => $socials,
        ];
    }

    public function whatsapp(string $number, string $message = 'Halo DPE, saya ingin berkonsultasi tentang program bahasa Inggris.'): ?string
    {
        if (! preg_match('/^\+?[\d\s().-]+$/D', $number)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $number);
        if (str_starts_with($digits, '08')) {
            $digits = '62'.substr($digits, 1);
        }
        if (! preg_match('/^[1-9]\d{7,14}$/D', $digits)) {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }

    private function body(?string $value): array
    {
        $value = trim($value ?? '');
        if ($value === '') {
            return [];
        }
        if (str_starts_with($value, '{') || str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);

            return is_array($decoded) && ! array_is_list($decoded) ? $decoded : [];
        }

        return ['description' => $value];
    }

    private function text(array $body, string $key): string
    {
        return is_string($body[$key] ?? null) ? trim($body[$key]) : '';
    }

    private function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, fn ($item) => is_string($item) && trim($item) !== '')) : [];
    }
}
