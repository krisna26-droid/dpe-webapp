<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\Program;
use App\Models\PublicContentSection;
use App\Models\User;
use App\Models\WebsiteContentChange;
use App\Models\WebsiteContentDraft;
use App\Services\WebsiteContentDefinition;
use App\Services\WebsiteContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebsiteContentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $sa;
    private User $otherSa;
    private Program $program;
    private Branch $branch;
    private WebsiteContentService $content;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['dpe_public.sample_content' => false]);
        $this->sa = $this->user('superadmin');
        $this->otherSa = $this->user('superadmin');
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => 'Program Operasional', 'class_type' => 'branch', 'is_active' => true]);
        $this->branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => 'Cabang Operasional', 'address' => 'Alamat operasional asli', 'is_active' => true, 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20]);
        $this->live('home.hero', 'Judul website sebelumnya', ['description' => 'Deskripsi website sebelumnya', 'legacy_extra' => 'Pertahankan field lama']);
        $this->live('contact', 'Kontak website sebelumnya', ['email' => 'old@example.test']);
        $this->content = app(WebsiteContentService::class);
    }

    private function user(string $role, bool $active = true): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(12), 'full_name' => 'Operator '.$role.' '.Str::random(4), 'password_hash' => 'fixture-only', 'role_code' => $role, 'is_active' => $active]);
    }

    private function live(string $key, string $title, array $body = [], bool $visible = true, ?string $image = null): PublicContentSection
    {
        return PublicContentSection::create(['id' => (string) Str::uuid(), 'section_key' => $key, 'title' => $title, 'body' => json_encode($body), 'is_published' => $visible, 'display_order' => 1, 'image_file_id' => $image]);
    }

    private function key(string $kind): string
    {
        return match ($kind) { 'program' => 'program.'.$this->program->id, 'branch' => 'branch.'.$this->branch->id, default => $kind };
    }

    private function input(string $key, array $changes = [], ?User $actor = null): array
    {
        return $changes + ['title' => 'Judul draft baru', 'description' => 'Isi draft baru', 'display_order' => 2, 'is_published' => true,
            'expected_snapshot' => $this->content->editor($actor ?? $this->sa, $key)['expectedSnapshot']];
    }

    private function save(string $key, array $changes = []): void
    {
        $this->actingAs($this->sa)->put(route('superadmin.website.save', $key), $this->input($key, $changes))->assertRedirect(route('superadmin.website.edit', $key))->assertSessionHasNoErrors();
    }

    private function publish(string $key): void
    {
        $this->post(route('superadmin.website.publish', $key), ['expected_snapshot' => $this->content->editor($this->sa, $key)['expectedSnapshot']])
            ->assertRedirect(route('superadmin.website.edit', $key))->assertSessionHasNoErrors();
    }

    private function capture(string $name, $response): void
    {
        if ($dir = getenv('DPE_V44_QA_DIR')) {
            \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
            file_put_contents($dir.'/'.$name.'.html', $response->getContent());
        }
    }

    public static function tabs(): array
    {
        return array_map(fn ($key) => [$key], array_keys(WebsiteContentDefinition::TABS));
    }

    #[DataProvider('tabs')]
    public function test_all_tabs_are_real_html_with_navigation_and_no_read_side_effects(string $tab): void
    {
        $before = PublicContentSection::all()->toJson();
        $page = $this->actingAs($this->sa)->get(route('superadmin.website.index', ['tab' => $tab]))->assertOk()
            ->assertSee('Konten Publik')->assertSee('Simpan Draft')->assertSee(WebsiteContentDefinition::TABS[$tab]);
        $page->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $this->assertSame($before, PublicContentSection::all()->toJson());
        $this->assertDatabaseCount('website_content_drafts', 0);
        $this->assertDatabaseCount('website_content_changes', 0);
        $this->capture('tab-'.$tab, $page);
        Http::assertNothingSent();
    }

    public static function kinds(): array
    {
        return array_map(fn ($key) => [$key], ['identity', 'home.hero', 'home.intro', 'home.benefit.1', 'about.intro', 'about.vision', 'about.mission', 'about.approach.1', 'program', 'branch', 'contact']);
    }

    #[DataProvider('kinds')]
    public function test_each_kind_saves_previews_and_publishes_only_its_section(string $kind): void
    {
        $key = $this->key($kind);
        $before = PublicContentSection::all()->toJson();
        $this->save($key);
        $this->assertSame($before, PublicContentSection::all()->toJson());
        $edit = $this->get(route('superadmin.website.edit', $key))->assertOk()->assertSee('Pratinjau Draft')->assertSee('Tayangkan')->assertSee('Kembali ke Konten Publik');
        $preview = $this->get(route('superadmin.website.preview', $key))->assertOk()->assertSee('Pratinjau draft')->assertSee('Isi draft baru');
        $this->assertStringContainsString('no-store', $preview->headers->get('Cache-Control'));
        $this->capture('edit-'.$kind, $edit); $this->capture('preview-'.$kind, $preview);
        $this->publish($key);
        $record = PublicContentSection::where('section_key', $key)->firstOrFail();
        $this->assertTrue($record->is_published);
        $this->assertStringContainsString('Isi draft baru', $record->body);
        $this->assertDatabaseCount('website_content_drafts', 0);
        $this->assertSame(1, WebsiteContentChange::where('section_key', $key)->where('action', 'publish')->count());
        if ($key !== 'contact') {
            $this->assertSame('Kontak website sebelumnya', PublicContentSection::where('section_key', 'contact')->first()->title);
        }
        Http::assertNothingSent();
    }

    public function test_new_and_existing_drafts_do_not_change_public_pages_or_other_drafts(): void
    {
        $this->save('home.hero');
        $this->save('about.intro', ['title' => 'Draft bagian lain']);
        $this->get('/')->assertOk()->assertSee('Judul website sebelumnya')->assertDontSee('Judul draft baru')->assertDontSee('Draft bagian lain');
        $this->publish('home.hero');
        $this->get('/')->assertOk()->assertSee('Judul draft baru')->assertDontSee('Judul website sebelumnya')->assertDontSee('Draft bagian lain');
        $this->assertDatabaseHas('website_content_drafts', ['section_key' => 'about.intro']);
        $body = json_decode(PublicContentSection::where('section_key', 'home.hero')->first()->body, true);
        $this->assertSame('Pertahankan field lama', $body['legacy_extra']);
    }

    public static function forbiddenActors(): array
    {
        return [['guest'], ['admin'], ['teacher'], ['student'], ['inactive']];
    }

    #[DataProvider('forbiddenActors')]
    public function test_other_roles_and_inactive_accounts_cannot_read_preview_or_mutate(string $role): void
    {
        $this->save('home.hero');
        $input = $this->input('home.hero');
        $this->app['auth']->forgetGuards();
        if ($role !== 'guest') {
            $this->actingAs($this->user($role === 'inactive' ? 'superadmin' : $role, $role !== 'inactive'));
        }
        foreach ([['get', 'index', []], ['get', 'edit', ['key' => 'home.hero']], ['get', 'preview', ['key' => 'home.hero']],
            ['put', 'save', ['key' => 'home.hero']], ['post', 'publish', ['key' => 'home.hero']], ['post', 'hide', ['key' => 'home.benefit.1']],
            ['delete', 'discard', ['key' => 'home.hero']], ['delete', 'remove', ['key' => 'home.benefit.1']]] as [$verb, $action, $params]) {
            $response = $this->{$verb}(route('superadmin.website.'.$action, $params), $input);
            $role === 'guest' ? $response->assertRedirect(route('login')) : $response->assertForbidden();
        }
        $this->assertDatabaseCount('website_content_drafts', 1);
        $this->assertDatabaseCount('website_content_changes', 1);
        $this->assertSame('Judul website sebelumnya', PublicContentSection::where('section_key', 'home.hero')->first()->title);
    }

    public function test_stale_edit_publish_and_double_click_do_not_overwrite_or_duplicate_publication(): void
    {
        $old = $this->input('home.hero');
        $this->save('home.hero');
        $this->putJson(route('superadmin.website.save', 'home.hero'), $old)->assertUnprocessable()->assertJsonValidationErrors('content');
        $expected = $this->content->editor($this->sa, 'home.hero')['expectedSnapshot'];
        $this->actingAs($this->otherSa)->put(route('superadmin.website.save', 'home.hero'), $this->input('home.hero', ['title' => 'Draft operator kedua'], $this->otherSa))->assertSessionHasNoErrors();
        $this->actingAs($this->sa)->postJson(route('superadmin.website.publish', 'home.hero'), ['expected_snapshot' => $expected])->assertUnprocessable();
        $this->publish('home.hero');
        $count = WebsiteContentChange::count();
        $this->postJson(route('superadmin.website.publish', 'home.hero'), ['expected_snapshot' => $expected])->assertUnprocessable();
        $this->assertSame($count, WebsiteContentChange::count());
        $this->assertSame('Draft operator kedua', PublicContentSection::where('section_key', 'home.hero')->first()->title);
    }

    public function test_actor_key_and_draft_source_are_bound_to_confirmation(): void
    {
        $input = $this->input('home.hero');
        $this->actingAs($this->otherSa)->putJson(route('superadmin.website.save', 'home.hero'), $input)->assertUnprocessable();
        $this->actingAs($this->sa)->putJson(route('superadmin.website.save', 'about.intro'), $input)->assertUnprocessable();
        $this->assertDatabaseCount('website_content_drafts', 0);
    }

    public function test_legacy_api_changes_and_deleted_source_require_review_before_publish(): void
    {
        $this->save('home.hero');
        PublicContentSection::where('section_key', 'home.hero')->update(['title' => 'Perubahan dari API lama']);
        $editor = $this->content->editor($this->sa, 'home.hero');
        $this->assertTrue($editor['conflict']);
        $this->postJson(route('superadmin.website.publish', 'home.hero'), ['expected_snapshot' => $editor['expectedSnapshot']])->assertUnprocessable();
        $this->assertSame('Perubahan dari API lama', PublicContentSection::where('section_key', 'home.hero')->first()->title);
        $this->save('home.hero', ['title' => 'Versi sudah diperiksa']);
        $this->publish('home.hero');
        $this->assertSame('Versi sudah diperiksa', PublicContentSection::where('section_key', 'home.hero')->first()->title);
    }

    public function test_discard_removes_only_draft_and_preserves_live_data_and_history(): void
    {
        $before = PublicContentSection::all()->toJson();
        $this->save('home.hero');
        $this->delete(route('superadmin.website.discard', 'home.hero'), ['expected_snapshot' => $this->content->editor($this->sa, 'home.hero')['expectedSnapshot']])->assertSessionHasNoErrors();
        $this->assertSame($before, PublicContentSection::all()->toJson());
        $this->assertDatabaseCount('website_content_drafts', 0);
        $this->assertDatabaseHas('website_content_changes', ['action' => 'discard_draft']);
        $this->get(route('superadmin.website.preview', 'home.hero'))->assertNotFound();
    }

    public function test_hide_program_preserves_master_and_does_not_reintroduce_samples(): void
    {
        config(['dpe_public.sample_content' => true]);
        $key = $this->key('program');
        $before = $this->program->fresh()->getRawOriginal();
        $this->save($key); $this->publish($key);
        $this->get('/program')->assertSee($this->program->name);
        $this->post(route('superadmin.website.hide', $key), ['expected_snapshot' => $this->content->editor($this->sa, $key)['expectedSnapshot']])->assertSessionHasNoErrors();
        $this->get('/program')->assertOk()->assertDontSee($this->program->name)->assertDontSee('Informasi contoh');
        $this->assertSame($before, $this->program->fresh()->getRawOriginal());
        $this->get('/program/'.$this->program->id)->assertNotFound();
    }

    public function test_visibility_is_pending_until_publish_and_master_status_is_rechecked(): void
    {
        $key = $this->key('branch');
        $this->save($key); $this->publish($key);
        $this->save($key, ['is_published' => false]);
        $this->get('/cabang')->assertSee($this->branch->name);
        $this->get(route('superadmin.website.preview', $key))->assertOk()->assertDontSee($this->branch->name);
        $this->publish($key);
        $this->get('/cabang')->assertDontSee($this->branch->name);
        $this->save($key); $this->branch->update(['is_active' => false]);
        $this->postJson(route('superadmin.website.publish', $key), ['expected_snapshot' => $this->content->editor($this->sa, $key)['expectedSnapshot']])->assertUnprocessable();
        $this->assertFalse(PublicContentSection::where('section_key', $key)->first()->is_published);
    }

    public function test_draft_images_are_private_then_public_and_revoked_when_replaced(): void
    {
        $this->save('home.hero', ['image' => UploadedFile::fake()->image('kegiatan.jpg', 160, 100)]);
        $fileId = WebsiteContentDraft::first()->payload['image_file_id'];
        $this->get('/gambar-publik/'.$fileId)->assertNotFound();
        $this->get(route('superadmin.website.image', $fileId))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->publish('home.hero');
        $this->get('/gambar-publik/'.$fileId)->assertOk();
        $this->save('home.hero', ['remove_image' => true]);
        $this->get('/gambar-publik/'.$fileId)->assertOk();
        $this->publish('home.hero');
        $this->get('/gambar-publik/'.$fileId)->assertNotFound();
        Storage::disk('local')->assertExists(FileAsset::findOrFail($fileId)->storage_key);
    }

    public function test_private_payment_image_id_cannot_be_selected_or_read_through_preview(): void
    {
        $file = UploadedFile::fake()->image('bukti.png');
        $path = $file->storeAs('portal-proofs', 'private.png', 'local');
        $asset = FileAsset::create(['id' => (string) Str::uuid(), 'storage_key' => $path, 'mime_type' => 'image/png', 'size_bytes' => $file->getSize(), 'original_name' => 'bukti.png']);
        $this->save('home.hero', ['image_file_id' => $asset->id]);
        $this->assertNull(WebsiteContentDraft::first()->payload['image_file_id']);
        $this->get(route('superadmin.website.image', $asset->id))->assertNotFound();
        $this->get('/gambar-publik/'.$asset->id)->assertNotFound();
    }

    public static function invalidValues(): array
    {
        return [['primary_url', 'javascript:alert(1)'], ['primary_url', '//evil.test'], ['primary_url', 'https://user:pass@example.test'],
            ['primary_url', '/superadmin/dashboard'], ['display_order', -1], ['display_order', 32768], ['title', []], ['title', str_repeat('a', 256)],
            ['description', str_repeat('a', 5001)], ['expected_snapshot', []], ['expected_snapshot', 'wrong'], ['is_published', 'wrong'],
            ['email', 'invalid-address'], ['whatsapp', 'abc123'], ['instagram', 'http://example.test'], ['instagram', 'https://user:pass@example.test'],
            ['features', implode("\n", array_fill(0, 21, '0'))]];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_input_does_not_save_or_change_live_data(string $field, mixed $value): void
    {
        $key = in_array($field, ['email', 'whatsapp', 'instagram'], true) ? 'contact' : ($field === 'features' ? $this->key('program') : 'home.hero');
        $before = PublicContentSection::all()->toJson();
        $this->actingAs($this->sa)->putJson(route('superadmin.website.save', $key), $this->input($key, [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, PublicContentSection::all()->toJson());
        $this->assertDatabaseCount('website_content_drafts', 0);
        $this->assertDatabaseCount('website_content_changes', 0);
    }

    public function test_upload_validation_rejects_svg_spoofed_files_and_excessive_dimensions(): void
    {
        foreach ([UploadedFile::fake()->createWithContent('bad.svg', '<svg><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('fake.jpg', '<?php echo "bad";'), UploadedFile::fake()->image('wide.png', 4097, 1)] as $image) {
            $this->actingAs($this->sa)->putJson(route('superadmin.website.save', 'home.hero'), $this->input('home.hero', ['image' => $image]))->assertUnprocessable()->assertJsonValidationErrors('image');
        }
        $this->assertDatabaseCount('file_assets', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_failed_audit_rolls_back_metadata_and_removes_the_new_upload(): void
    {
        $before = PublicContentSection::all()->toJson();
        WebsiteContentChange::creating(fn () => throw new \RuntimeException('Fixture audit failure'));
        try {
            $this->withoutExceptionHandling()->actingAs($this->sa)->put(route('superadmin.website.save', 'home.hero'),
                $this->input('home.hero', ['image' => UploadedFile::fake()->image('new.png')]));
            $this->fail('Failure must roll back.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fixture audit failure', $e->getMessage());
        } finally {
            WebsiteContentChange::flushEventListeners();
        }
        $this->assertSame($before, PublicContentSection::all()->toJson());
        $this->assertDatabaseCount('website_content_drafts', 0); $this->assertDatabaseCount('file_assets', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_text_and_links_are_escaped_and_zero_feature_is_preserved(): void
    {
        $this->save('home.hero', ['title' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(2)>']);
        $this->get(route('superadmin.website.preview', 'home.hero'))->assertOk()->assertSee(e('<script>alert(1)</script>'), false)->assertDontSee('<script>alert(1)</script>', false);
        $this->publish('home.hero');
        $this->get('/')->assertOk()->assertDontSee('<img src=x onerror=alert(2)>', false);
        $key = $this->key('program'); $this->save($key, ['features' => "0\nLatihan speaking"]);
        $this->get(route('superadmin.website.preview', $key))->assertOk()->assertSee('Latihan speaking');
        $this->publish($key);
        $body = json_decode(PublicContentSection::where('section_key', $key)->first()->body, true);
        $this->assertSame(['0', 'Latihan speaking'], $body['features']);
    }

    public function test_contacts_buttons_footer_and_legacy_plain_text_are_applied(): void
    {
        $this->save('contact', ['email' => 'info@example.test', 'phone' => '0361 123456', 'whatsapp' => '081234567890', 'instagram' => 'https://instagram.com/example']);
        $this->publish('contact');
        $this->get('/')->assertOk()->assertSee('info@example.test')->assertSee('0361 123456')->assertSee('https://instagram.com/example', false)->assertSee('wa.me/6281234567890', false)->assertDontSee('Kontak contoh');
        $this->save('contact', ['instagram' => 'HTTPS://instagram.com/example-upper']); $this->publish('contact');
        $this->get('/kontak')->assertOk()->assertSee('HTTPS://instagram.com/example-upper', false);
        $this->save('identity', ['title' => 'Identitas baru', 'footer_text' => 'Footer baru']); $this->publish('identity');
        $this->get('/')->assertSee('Footer baru')->assertSee('Identitas baru');
        $this->save('identity', ['footer_text' => '']); $this->publish('identity'); $this->get('/')->assertDontSee('Footer baru')->assertDontSee('Setiap langkah belajar berarti.');
        PublicContentSection::where('section_key', 'home.hero')->update(['body' => 'Deskripsi lama berupa teks biasa']);
        $input = $this->input('home.hero'); unset($input['description']);
        $this->put(route('superadmin.website.save', 'home.hero'), $input)->assertSessionHasNoErrors(); $this->publish('home.hero');
        $this->get('/')->assertSee('Deskripsi lama berupa teks biasa');
    }

    public function test_program_listing_skips_orphan_metadata_and_keeps_pagination_and_search(): void
    {
        $orphan = $this->program->id; $this->live('program.'.$orphan, 'Metadata yatim'); $this->program->delete();
        for ($i = 0; $i < 17; $i++) {
            Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Program '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'class_type' => 'branch', 'is_active' => true]);
        }
        $this->actingAs($this->sa)->get(route('superadmin.website.index', ['tab' => 'program']))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 17 && $rows->count() === 15);
        $this->get(route('superadmin.website.index', ['tab' => 'program', 'page' => 2]))->assertOk()->assertSee('Program 16');
        $this->get(route('superadmin.website.index', ['tab' => 'program', 'q' => 'Program 16']))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $this->get(route('superadmin.website.edit', 'program.'.$orphan))->assertNotFound();
    }

    public function test_unknown_keys_master_ids_and_invalid_filters_are_rejected(): void
    {
        $this->actingAs($this->sa)->get(route('superadmin.website.edit', 'unmapped.secret'))->assertNotFound();
        $this->get(route('superadmin.website.edit', 'program.'.Str::uuid()))->assertNotFound();
        $this->getJson(route('superadmin.website.index', ['tab' => ['bad']]))->assertUnprocessable();
        $this->getJson(route('superadmin.website.create', ['kind' => 'identity']))->assertUnprocessable();
        $this->get(route('superadmin.website.create', ['kind' => 'home.benefit']))->assertOk();
        $this->assertDatabaseCount('website_content_drafts', 0);
    }

    public function test_private_image_access_is_denied_for_guest_and_other_roles(): void
    {
        $this->save('identity', ['image' => UploadedFile::fake()->image('logo.png')]);
        $file = WebsiteContentDraft::first()->payload['image_file_id'];
        $this->app['auth']->forgetGuards();
        $this->get(route('superadmin.website.image', $file))->assertRedirect(route('login'));
        foreach (['admin', 'teacher', 'student'] as $role) {
            $this->actingAs($this->user($role))->get(route('superadmin.website.image', $file))->assertForbidden();
        }
        $this->actingAs($this->sa)->get(route('superadmin.website.preview', 'identity'))->assertOk()->assertSee(route('superadmin.website.image', $file), false);
        $this->get('/')->assertDontSee($file);
        $this->publish('identity'); $this->get('/')->assertSee('/gambar-publik/'.$file, false);
    }

    public function test_changed_or_missing_image_cannot_be_published_from_an_old_review(): void
    {
        $this->save('home.hero', ['image' => UploadedFile::fake()->image('foto.png')]);
        $asset = FileAsset::firstOrFail(); $old = $this->content->editor($this->sa, 'home.hero')['expectedSnapshot'];
        Storage::disk('local')->delete($asset->storage_key);
        $this->postJson(route('superadmin.website.publish', 'home.hero'), ['expected_snapshot' => $old])->assertUnprocessable();
        $this->postJson(route('superadmin.website.publish', 'home.hero'), ['expected_snapshot' => $this->content->editor($this->sa, 'home.hero')['expectedSnapshot']])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertSame('Judul website sebelumnya', PublicContentSection::where('section_key', 'home.hero')->first()->title);
        $this->save('home.hero', ['remove_image' => true]); $this->publish('home.hero');
        $this->assertNull(PublicContentSection::where('section_key', 'home.hero')->first()->image_file_id);
    }

    public function test_upload_size_and_mutually_exclusive_photo_actions_are_validated(): void
    {
        $image = UploadedFile::fake()->image('large.png');
        $oversize = UploadedFile::fake()->createWithContent('large.png', file_get_contents($image->getRealPath()).str_repeat('x', 4 * 1024 * 1024));
        foreach ([['image' => $oversize], ['image' => UploadedFile::fake()->image('new.png'), 'remove_image' => true]] as $input) {
            $this->actingAs($this->sa)->putJson(route('superadmin.website.save', 'home.hero'), $this->input('home.hero', $input))->assertUnprocessable()->assertJsonValidationErrors('image');
        }
        $this->assertDatabaseCount('file_assets', 0); $this->assertDatabaseCount('website_content_drafts', 0);
    }

    public function test_publish_and_discard_are_atomic_when_audit_write_fails(): void
    {
        $this->save('home.hero'); $before = PublicContentSection::all()->toJson();
        WebsiteContentChange::creating(fn () => throw new \RuntimeException('Fixture publication audit failure'));
        try {
            $this->content->publish($this->sa, 'home.hero', $this->content->editor($this->sa, 'home.hero')['expectedSnapshot']);
            $this->fail('Publish must roll back.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fixture publication audit failure', $e->getMessage());
        } finally {
            WebsiteContentChange::flushEventListeners();
        }
        $this->assertSame($before, PublicContentSection::all()->toJson());
        $this->assertDatabaseCount('website_content_drafts', 1); $this->assertDatabaseCount('website_content_changes', 1);
    }

    public function test_hiding_last_optional_content_keeps_it_hidden_without_sample_fallbacks(): void
    {
        config(['dpe_public.sample_content' => true]);
        $key = 'home.benefit.1'; $this->save($key, ['title' => 'Keunggulan untuk disembunyikan']); $this->publish($key);
        $this->post(route('superadmin.website.hide', $key), ['expected_snapshot' => $this->content->editor($this->sa, $key)['expectedSnapshot']])->assertSessionHasNoErrors();
        $this->get('/')->assertOk()->assertDontSee('Keunggulan untuk disembunyikan')->assertDontSee('Pilihan sesuai tujuan');
        PublicContentSection::query()->update(['is_published' => false]);
        $this->get('/program')->assertOk()->assertDontSee('Informasi contoh')->assertDontSee('Regular');
    }

    public function test_pending_migration_rollback_protects_saved_drafts_and_history(): void
    {
        $this->save('home.hero');
        $migration = require database_path('migrations/2026_10_13_010000_create_website_content_drafts.php');
        try {
            $migration->down(); $this->fail('Rollback with history must be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Rollback ditolak', $e->getMessage());
        }
        $this->assertDatabaseCount('website_content_drafts', 1); $this->assertDatabaseCount('website_content_changes', 1);
    }

    public function test_removing_additional_content_preserves_history_and_never_deletes_a_master(): void
    {
        $key = 'home.benefit.1'; $this->save($key); $this->publish($key); $this->save($key, ['title' => 'Draft pending sebelum hapus']);
        $this->delete(route('superadmin.website.remove', $key), ['expected_snapshot' => $this->content->editor($this->sa, $key)['expectedSnapshot']])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('public_content_sections', ['section_key' => $key]);
        $this->assertDatabaseMissing('website_content_drafts', ['section_key' => $key]);
        $change = WebsiteContentChange::where('action', 'remove_content')->firstOrFail();
        $this->assertSame('Draft pending sebelum hapus', $change->before_payload['draft']['title']);
        $this->delete(route('superadmin.website.remove', $this->key('program')), ['expected_snapshot' => $this->content->editor($this->sa, $this->key('program'))['expectedSnapshot']])->assertNotFound();
        $this->assertDatabaseHas('programs', ['id' => $this->program->id]);
    }

    public function test_empty_site_after_removal_does_not_restore_demo_content_and_draft_does_not_hide_demo(): void
    {
        PublicContentSection::query()->delete(); config(['dpe_public.sample_content' => true]);
        $this->get('/program')->assertSee('Informasi contoh');
        $key = 'home.benefit.1'; $this->save($key); $this->get('/program')->assertSee('Informasi contoh');
        $this->publish($key);
        $this->delete(route('superadmin.website.remove', $key), ['expected_snapshot' => $this->content->editor($this->sa, $key)['expectedSnapshot']])->assertSessionHasNoErrors();
        $this->get('/program')->assertOk()->assertDontSee('Informasi contoh')->assertDontSee('Regular');
    }
}
