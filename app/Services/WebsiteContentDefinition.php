<?php

namespace App\Services;

use Illuminate\Support\Str;

class WebsiteContentDefinition
{
    public const TABS = ['identity' => 'Identitas', 'home' => 'Beranda', 'about' => 'Tentang', 'program' => 'Program', 'branch' => 'Cabang', 'contact' => 'Kontak'];
    public const FIXED = ['identity' => 'Identitas & footer', 'home.hero' => 'Foto & bagian utama', 'home.intro' => 'Pengantar beranda',
        'about.intro' => 'Profil lembaga', 'about.vision' => 'Visi', 'about.mission' => 'Misi', 'contact' => 'Informasi kontak'];
    public const REPEATED = ['home.benefit' => 'Keunggulan layanan', 'about.approach' => 'Cara belajar'];

    public static function kind(string $key): string
    {
        if (isset(self::FIXED[$key])) {
            return $key;
        }
        foreach (['home.benefit', 'about.approach', 'program', 'branch'] as $prefix) {
            if (str_starts_with($key, $prefix.'.')) {
                $suffix = substr($key, strlen($prefix) + 1);
                $valid = in_array($prefix, ['program', 'branch'], true)
                    ? Str::isUuid($suffix) : preg_match('/\A[a-zA-Z0-9_-]{1,80}\z/', $suffix);
                abort_unless($valid, 404);
                return $prefix;
            }
        }
        abort(404);
    }

    public static function tab(string $key): string
    {
        return explode('.', self::kind($key))[0];
    }

    public static function label(string $key): string
    {
        $kind = self::kind($key);
        return self::FIXED[$kind] ?? self::REPEATED[$kind] ?? (self::TABS[$kind] ?? 'Bagian website');
    }

    public static function fields(string $key): array
    {
        $fields = ['description' => 'Deskripsi'];
        return $fields + match (self::kind($key)) {
            'identity' => ['footer_text' => 'Kalimat penutup footer'],
            'home.hero' => ['eyebrow' => 'Teks kecil di atas judul', 'primary_label' => 'Teks tombol utama', 'primary_url' => 'Tujuan tombol utama',
                'secondary_label' => 'Teks tombol kedua', 'secondary_url' => 'Tujuan tombol kedua', 'caption' => 'Keterangan foto'],
            'home.intro' => ['eyebrow' => 'Teks pengantar'],
            'about.intro' => ['eyebrow' => 'Teks pengantar', 'heading' => 'Judul bagian tentang', 'heading_description' => 'Pengantar bagian tentang', 'detail' => 'Paragraf tambahan'],
            'program' => ['audience' => 'Untuk siapa?', 'features' => 'Fokus belajar — satu poin per baris', 'duration_label' => 'Jadwal & durasi'],
            'branch' => ['service_hours' => 'Jam pelayanan', 'whatsapp' => 'Nomor WhatsApp cabang'],
            'contact' => ['whatsapp' => 'Nomor WhatsApp konsultasi', 'email' => 'Email', 'phone' => 'Telepon', 'service_hours' => 'Jam pelayanan',
                'instagram' => 'Tautan Instagram', 'facebook' => 'Tautan Facebook', 'youtube' => 'Tautan YouTube', 'tiktok' => 'Tautan TikTok'],
            default => [],
        };
    }

    public static function imageAllowed(string $key): bool
    {
        return in_array(self::kind($key), ['identity', 'home.hero', 'home.intro', 'home.benefit', 'about.intro', 'about.approach', 'program', 'branch'], true);
    }

    public static function visibilityAllowed(string $key): bool
    {
        return in_array(self::kind($key), ['home.benefit', 'about.approach', 'program', 'branch'], true);
    }

    public static function body(?string $text): array
    {
        $text = trim($text ?? '');
        if ($text === '') {
            return [];
        }
        if (str_starts_with($text, '{') || str_starts_with($text, '[')) {
            $body = json_decode($text, true);
            if (is_array($body) && ! array_is_list($body)) {
                return $body;
            }
        }
        return ['description' => $text];
    }

    public static function defaults(string $key): array
    {
        return match (self::kind($key)) {
            'home.hero' => ['eyebrow' => 'Belajar bersama DPE', 'primary_label' => 'Lihat program', 'primary_url' => '#program',
                'secondary_label' => 'Hubungi kami', 'secondary_url' => '#kontak'],
            'identity' => ['footer_text' => 'Setiap langkah belajar berarti.'],
            default => [],
        };
    }

    public static function validLink(string $url): bool
    {
        return in_array($url, ['', '#program', '#cabang', '#kontak', '#tentang', '/', '/program', '/cabang', '/kontak', '/tentang'], true)
            || (filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)
                && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null);
    }

    public static function uploadLimitKb(): int
    {
        $limits = [4096];
        foreach (['upload_max_filesize' => 0, 'post_max_size' => 128] as $setting => $reserve) {
            $value = trim((string) ini_get($setting));
            if (preg_match('/\A(\d+(?:\.\d+)?)\s*([KMG]?)\z/i', $value, $match) && (float) $match[1] > 0) {
                $bytes = (float) $match[1] * match (strtoupper($match[2])) { 'G' => 1073741824, 'M' => 1048576, 'K' => 1024, default => 1 };
                $limits[] = max(1, (int) floor($bytes / 1024) - $reserve);
            }
        }
        return min($limits);
    }
}
