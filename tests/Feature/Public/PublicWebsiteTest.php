<?php

namespace Tests\Feature\Public;

use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\Program;
use App\Models\PublicContentSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aV1sAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config(['dpe_public.sample_content' => false]);
    }

    private function section(string $key, string $title, string|array|null $body = null, bool $published = true, ?string $imageId = null): PublicContentSection
    {
        return PublicContentSection::create([
            'id' => (string) Str::uuid(),
            'section_key' => $key,
            'title' => $title,
            'body' => is_array($body) ? json_encode($body) : $body,
            'display_order' => 1,
            'is_published' => $published,
            'image_file_id' => $imageId,
        ]);
    }

    private function programRecord(string $name, bool $active = true): Program
    {
        return Program::create([
            'id' => (string) Str::uuid(), 'code' => (string) Str::uuid(),
            'name' => $name, 'class_type' => 'branch', 'is_active' => $active,
        ]);
    }

    private function branchRecord(string $name, bool $active = true, ?string $address = 'Alamat resmi cabang'): Branch
    {
        return Branch::create([
            'id' => (string) Str::uuid(), 'code' => (string) Str::uuid(),
            'name' => $name, 'address' => $address, 'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 20, 'is_active' => $active,
        ]);
    }

    private function imageAsset(string $mime = 'image/png', ?string $path = null, ?string $bytes = null): FileAsset
    {
        $id = (string) Str::uuid();
        $path ??= 'website/'.$id.'.png';
        $bytes ??= base64_decode(self::PNG);
        if (! str_contains($path, '..')) {
            Storage::disk('local')->put($path, $bytes);
        }

        return FileAsset::create([
            'id' => $id, 'storage_key' => $path, 'original_name' => 'image.png',
            'mime_type' => $mime, 'size_bytes' => strlen($bytes),
        ]);
    }

    public function test_every_public_page_has_shared_navigation_and_is_accessible(): void
    {
        foreach (['/', '/tentang', '/program', '/cabang', '/kontak'] as $url) {
            $this->get($url)->assertOk()->assertSee('Beranda')->assertSee('Tentang')
                ->assertSee('Program')->assertSee('Cabang')->assertSee('Kontak')
                ->assertSee('lang="id"', false);
        }
    }

    public function test_sample_content_is_labelled_and_does_not_insert_operational_data(): void
    {
        config(['dpe_public.sample_content' => true]);
        $this->get('/')->assertOk()->assertSee('Informasi contoh')->assertSee('Regular');
        $this->get('/program/regular')->assertOk()->assertSee('Regular');
        $this->get('/cabang')->assertOk()->assertSee('GatSu')->assertSee('Batubulan');
        $this->assertDatabaseCount('programs', 0);
        $this->assertDatabaseCount('branches', 0);
    }

    public function test_empty_production_site_does_not_show_sample_information(): void
    {
        $this->get('/program')->assertOk()->assertSee('Informasi program belum tersedia')
            ->assertDontSee('Regular')->assertDontSee('Informasi contoh');
        $this->get('/cabang')->assertOk()->assertSee('Informasi cabang belum tersedia')->assertDontSee('GatSu');
        $this->get('/program/regular')->assertNotFound();
    }

    public function test_drafts_do_not_override_published_content(): void
    {
        $this->section('home.hero', 'Judul resmi yang ditayangkan', 'Deskripsi resmi.');
        $this->section('home.intro', 'Judul draft rahasia', 'Draft rahasia.', false);
        $this->section('contact', 'Kontak draft', ['whatsapp' => '6281234567890'], false);

        $this->get('/')->assertOk()->assertSee('Judul resmi yang ditayangkan')
            ->assertSee('Deskripsi resmi.')->assertDontSee('Judul draft rahasia')->assertDontSee('Draft rahasia.');
        $this->get('/kontak')->assertOk()->assertDontSee('6281234567890')->assertDontSee('wa.me');
    }

    public function test_partial_publication_does_not_fill_other_sections_with_samples(): void
    {
        config(['dpe_public.sample_content' => true]);
        $this->section('about.intro', 'Profil resmi', 'Profil yang sudah ditayangkan.');
        $this->get('/program')->assertOk()->assertDontSee('Regular')->assertDontSee('Informasi contoh');
        $this->get('/cabang')->assertOk()->assertDontSee('GatSu');
    }

    public function test_published_text_is_escaped(): void
    {
        $title = '<script>alert("title")</script>';
        $body = '<img src=x onerror=alert(1)>';
        $this->section('home.hero', $title, $body);
        $this->get('/')->assertOk()->assertSee(e($title), false)->assertSee(e($body), false)
            ->assertDontSee($title, false)->assertDontSee($body, false);
    }

    public function test_malformed_json_and_wrong_typed_contact_fields_are_safe(): void
    {
        $this->section('home.hero', 'Judul aman', '{broken json');
        $this->section('contact', 'Hubungi DPE', [
            'whatsapp' => ['6281234567890'], 'email' => ['bad@example.test'],
            'service_hours' => ['invalid'], 'instagram' => 'javascript:alert(1)',
            'phone' => (object) ['value' => 'invalid'],
        ]);
        $this->get('/')->assertOk()->assertSee('Judul aman');
        $this->get('/kontak?program[]=invalid')->assertOk()
            ->assertDontSee('href="javascript:', false)->assertDontSee('wa.me')->assertDontSee('Array');
    }

    public function test_only_active_programs_with_published_metadata_are_public(): void
    {
        $live = $this->programRecord('Program resmi terbuka');
        $draft = $this->programRecord('Program operasional rahasia');
        $inactive = $this->programRecord('Program tidak aktif', false);
        $this->section('program.'.$live->id, 'Judul promosi', [
            'description' => 'Penjelasan program resmi.', 'audience' => 'Siswa sekolah',
            'features' => ['Latihan speaking', 'Latihan reading'],
        ]);
        $this->section('program.'.$draft->id, 'Draft program', 'Rahasia.', false);
        $this->section('program.'.$inactive->id, 'Program tutup', 'Tidak ditayangkan.');

        $this->get('/program')->assertOk()->assertSee($live->name)->assertSee('Penjelasan program resmi.')
            ->assertDontSee($draft->name)->assertDontSee($inactive->name);
        $this->get('/program/'.$live->id)->assertOk()->assertSee('Latihan speaking');
        $this->get('/program/'.$draft->id)->assertNotFound();
        $this->get('/program/'.$inactive->id)->assertNotFound();
    }

    public function test_only_active_branches_with_published_metadata_are_public(): void
    {
        $live = $this->branchRecord('Cabang ditayangkan');
        $private = $this->branchRecord('Cabang operasional rahasia');
        $inactive = $this->branchRecord('Cabang nonaktif', false);
        $this->section('branch.'.$live->id, 'Cabang live', ['service_hours' => 'Senin–Jumat, 09.00–17.00']);
        $this->section('branch.'.$inactive->id, 'Cabang tutup');

        $this->get('/cabang')->assertOk()->assertSee($live->name)->assertSee($live->address)
            ->assertSee('Senin–Jumat, 09.00–17.00')->assertDontSee($private->name)->assertDontSee($inactive->name);
    }

    public function test_branch_without_address_does_not_have_a_misleading_map_link(): void
    {
        $branch = $this->branchRecord('Cabang tanpa alamat', true, null);
        $this->section('branch.'.$branch->id, 'Cabang');
        $this->get('/cabang')->assertOk()->assertSee('Alamat belum tersedia')->assertDontSee('maps/search');
    }

    public function test_valid_contact_data_builds_whatsapp_email_and_social_links(): void
    {
        $this->section('contact', 'Hubungi DPE', [
            'whatsapp' => '+62 812-3456-7890', 'email' => 'info@example.test',
            'service_hours' => 'Senin–Jumat, 09.00–17.00', 'instagram' => 'https://www.instagram.com/example/',
        ]);
        $this->get('/kontak?program=Regular')->assertOk()
            ->assertSee('https://wa.me/6281234567890?text=', false)
            ->assertSee('mailto:info@example.test', false)
            ->assertSee('https://www.instagram.com/example/', false)
            ->assertSee('Senin–Jumat, 09.00–17.00');
    }

    public function test_published_image_is_served_and_can_be_revoked(): void
    {
        Storage::fake('local');
        $file = $this->imageAsset();
        $section = $this->section('home.hero', 'Foto resmi', 'Deskripsi.', true, $file->id);
        $this->get('/gambar-publik/'.$file->id)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/')->assertOk()->assertSee('/gambar-publik/'.$file->id, false);
        $section->update(['is_published' => false]);
        $this->get('/gambar-publik/'.$file->id)->assertNotFound();
    }

    public function test_private_file_and_draft_image_are_not_public(): void
    {
        Storage::fake('local');
        $private = $this->imageAsset();
        $draft = $this->imageAsset();
        $this->section('home.hero', 'Foto draft', null, false, $draft->id);
        $this->get('/gambar-publik/'.$private->id)->assertNotFound();
        $this->get('/gambar-publik/'.$draft->id)->assertNotFound();
    }

    public function test_published_svg_or_spoofed_image_is_not_served(): void
    {
        Storage::fake('local');
        $svg = $this->imageAsset('image/svg+xml', 'website/active.svg', '<svg><script>alert(1)</script></svg>');
        $spoofed = $this->imageAsset('image/png', 'website/fake.png', '<?php echo "secret";');
        $this->section('home.hero', 'SVG', null, true, $svg->id);
        $this->section('about.intro', 'Spoofed image', null, true, $spoofed->id);
        $this->get('/gambar-publik/'.$svg->id)->assertNotFound();
        $this->get('/gambar-publik/'.$spoofed->id)->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('/gambar-publik/'.$svg->id, false);
    }

    public function test_unsafe_storage_path_is_rejected_without_server_error(): void
    {
        Storage::fake('local');
        $file = $this->imageAsset('image/png', '../secret.png');
        $this->section('home.hero', 'Unsafe path', null, true, $file->id);
        $this->get('/gambar-publik/'.$file->id)->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('/gambar-publik/'.$file->id, false);
    }
}
