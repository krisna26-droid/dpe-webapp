<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\LessonScheduleSeries;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\BranchOperationsService;
use App\Services\LessonSeriesService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LessonSessionFilterNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superadmin;

    private Branch $branch;

    private Branch $foreignBranch;

    private Program $program;

    private Teacher $teacher;

    private Teacher $otherTeacher;

    private ClassGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-29 08:00:00', 'Asia/Makassar'));
        Http::fake();
        $this->admin = $this->user('admin', 'Admin Uji');
        $this->superadmin = $this->user('superadmin', 'Super Admin Uji');
        $this->branch = $this->branch('Cabang Utama');
        $this->foreignBranch = $this->branch('Cabang Luar');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'admin_user_id' => $this->admin->id, 'starts_on' => '2026-01-01']);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => 'REG', 'name' => 'Regular', 'class_type' => 'regular', 'is_active' => true]);
        $this->teacher = $this->teacher('Guru Pilihan');
        $this->otherTeacher = $this->teacher('Guru Lain');
        $this->group = ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id,
            'default_teacher_id' => $this->teacher->id, 'code' => 'A', 'name' => 'Kelas A', 'is_active' => true]);
        foreach (range(1, 18) as $day) {
            LessonSession::create(['id' => (string) Str::uuid()] + $this->data(['planned_start_at' => sprintf('2026-11-%02d 10:00:00', $day), 'planned_end_at' => sprintf('2026-11-%02d 11:00:00', $day)]));
        }
        LessonSession::create(['id' => (string) Str::uuid()] + $this->data(['teacher_id' => $this->otherTeacher->id, 'topic' => 'Jadwal guru lain']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role, string $name): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(15), 'full_name' => $name, 'role_code' => $role, 'password_hash' => 'fixture-hash', 'is_active' => true]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(8), 'name' => $name, 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
    }

    private function teacher(string $name): Teacher
    {
        $teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', $name)->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $teacher->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);

        return $teacher;
    }

    private function data(array $extra = []): array
    {
        return $extra + ['branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id,
            'class_group_id' => $this->group->id, 'teaching_location' => 'branch', 'planned_start_at' => '2026-11-01 13:00:00',
            'planned_end_at' => '2026-11-01 14:00:00', 'status' => 'scheduled', 'topic' => 'Latihan & speaking'];
    }

    private function filters(): array
    {
        return ['q' => 'Latihan & speaking', 'branch_id' => $this->branch->id, 'program_id' => $this->program->id,
            'teacher_id' => $this->teacher->id, 'status' => 'scheduled', 'from' => '2026-11-01', 'to' => '2026-12-31', 'page' => 2];
    }

    private function attribute(TestResponse $response, string $xpath, string $attribute = 'href'): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $element = (new DOMXPath($document))->query($xpath)->item(0);
        $this->assertInstanceOf(DOMElement::class, $element, $xpath);

        return $element->getAttribute($attribute);
    }

    public static function staffRoutes(): array
    {
        return [['admin', 'admin'], ['superadmin', 'admin'], ['superadmin', 'superadmin']];
    }

    #[DataProvider('staffRoutes')]
    public function test_opening_the_time_link_and_returning_keeps_every_filter_and_the_second_page(string $role, string $prefix): void
    {
        $actor = $role === 'admin' ? $this->admin : $this->superadmin;
        $url = route($prefix.'.lesson-sessions.index', $this->filters());
        $list = $this->actingAs($actor)->get($url)->assertOk()->assertViewHas('lessonSessions', fn ($rows) => $rows->total() === 18 && $rows->currentPage() === 2 && $rows->count() === 3);
        $detailUrl = $this->attribute($list, '//table/tbody/tr/td[1]/a');
        $detail = $this->get($detailUrl)->assertOk();
        $backUrl = $this->attribute($detail, '//a[normalize-space(.)="Kembali"]');
        $this->assertSame($url, $backUrl);
        $returned = $this->get($backUrl)->assertOk()->assertViewHas('lessonSessions', fn ($rows) => $rows->total() === 18 && $rows->currentPage() === 2);
        $this->assertSame($this->teacher->id, $this->attribute($returned, '//select[@id="filter-teacher"]/option[@selected]', 'value'));
        $this->assertSame('Latihan & speaking', $this->attribute($returned, '//input[@id="branch-query"]', 'value'));
        $this->assertSame('2026-11-01', $this->attribute($returned, '//input[@id="filter-from"]', 'value'));
        $this->assertSame('2026-12-31', $this->attribute($returned, '//input[@id="filter-to"]', 'value'));
    }

    public function test_another_tab_with_different_filters_does_not_replace_the_original_return_context(): void
    {
        $original = route('admin.lesson-sessions.index', $this->filters());
        $tabA = $this->actingAs($this->admin)->get($original)->assertOk();
        $detailA = $this->attribute($tabA, '//table/tbody/tr/td[1]/a');
        $this->get(route('admin.lesson-sessions.index', ['teacher_id' => $this->otherTeacher->id]))->assertOk()->assertViewHas('lessonSessions', fn ($rows) => $rows->total() === 1);
        $detail = $this->get($detailA)->assertOk();
        $this->assertSame($original, $this->attribute($detail, '//a[normalize-space(.)="Kembali"]'));
    }

    #[DataProvider('staffRoutes')]
    public function test_edit_cancel_and_save_retain_the_return_filters(string $role, string $prefix): void
    {
        $actor = $role === 'admin' ? $this->admin : $this->superadmin;
        $list = $this->actingAs($actor)->get(route($prefix.'.lesson-sessions.index', $this->filters()))->assertOk();
        $detailUrl = $this->attribute($list, '//table/tbody/tr/td[1]/a');
        $detail = $this->get($detailUrl)->assertOk();
        $edit = $this->get($this->attribute($detail, '//a[normalize-space(.)="Edit sesi"]'))->assertOk();
        $this->assertSame($detailUrl, $this->attribute($edit, '//a[normalize-space(.)="Batal"]'));
        $this->assertSame($detailUrl, $this->attribute($edit, '//a[@aria-label="Tutup formulir dan kembali"]'));
        $target = $list->viewData('lessonSessions')->first();
        $data = $this->data(['planned_start_at' => $target->planned_start_at->format('Y-m-d\TH:i'), 'planned_end_at' => $target->planned_end_at->format('Y-m-d\TH:i'),
            'topic' => 'Latihan & speaking diperbarui', 'record_version' => app(BranchOperationsService::class)->version($target->fresh())]);
        $this->put($this->attribute($edit, '//main//form[@method="POST"]', 'action'), $data)->assertSessionHasNoErrors()->assertRedirect($detailUrl);
        $updated = $this->get($detailUrl)->assertOk();
        $this->assertSame(route($prefix.'.lesson-sessions.index', $this->filters()), $this->attribute($updated, '//a[normalize-space(.)="Kembali"]'));
    }

    public function test_failed_validation_keeps_the_filter_context_on_the_edit_form(): void
    {
        $target = LessonSession::where('teacher_id', $this->teacher->id)->firstOrFail();
        $parameters = [$target, 'list' => $this->filters()];
        $editUrl = route('admin.lesson-sessions.edit', $parameters);
        $this->actingAs($this->admin)->from($editUrl)->put(route('admin.lesson-sessions.update', $parameters), $this->data(['topic' => str_repeat('x', 256)]))->assertSessionHasErrors('topic')->assertRedirect($editUrl);
        $edit = $this->get($editUrl)->assertOk();
        $this->assertSame(route('admin.lesson-sessions.show', $parameters), $this->attribute($edit, '//a[normalize-space(.)="Batal"]'));
    }

    public function test_reset_explicitly_clears_the_filters_and_page(): void
    {
        $list = $this->actingAs($this->admin)->get(route('admin.lesson-sessions.index', $this->filters()))->assertOk();
        $reset = $this->attribute($list, '//a[normalize-space(.)="Reset"]');
        $this->assertSame(route('admin.lesson-sessions.index'), $reset);
        $cleared = $this->get($reset)->assertOk()->assertViewHas('filters', [])->assertViewHas('lessonSessions', fn ($rows) => $rows->total() === 19 && $rows->currentPage() === 1);
        $detail = $this->get($this->attribute($cleared, '//table/tbody/tr/td[1]/a'))->assertOk();
        $this->assertSame($reset, $this->attribute($detail, '//a[normalize-space(.)="Kembali"]'));
    }

    public function test_a_direct_detail_link_does_not_reuse_filters_from_an_earlier_visit(): void
    {
        $this->actingAs($this->admin)->get(route('admin.lesson-sessions.index', $this->filters()))->assertOk();
        $target = LessonSession::firstOrFail();
        $detail = $this->get(route('admin.lesson-sessions.show', $target))->assertOk();
        $this->assertSame(route('admin.lesson-sessions.index'), $this->attribute($detail, '//a[normalize-space(.)="Kembali"]'));
    }

    public function test_return_context_cannot_supply_external_redirects_or_replace_record_identifiers(): void
    {
        $target = LessonSession::firstOrFail();
        $detail = $this->actingAs($this->admin)->get(route('admin.lesson-sessions.show', [$target, 'list' => [
            'return_to' => 'https://example.test/outside', 'lesson_session' => 'another-record', 'teacher_id' => ['invalid'], 'page' => '-2', 'q' => 'Latihan & speaking',
        ]]))->assertOk();
        $this->assertSame(route('admin.lesson-sessions.index', ['q' => 'Latihan & speaking']), $this->attribute($detail, '//a[normalize-space(.)="Kembali"]'));
        $this->get(route('admin.lesson-sessions.show', [$target, 'list' => 'invalid']))->assertOk();
    }

    public function test_series_preview_back_links_and_single_or_whole_deletion_keep_the_original_list_context(): void
    {
        $context = ['list' => $this->filters()];
        $this->actingAs($this->admin);
        $service = app(LessonSeriesService::class);
        $preview = $service->previewCreate($this->admin, ['branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id,
            'program_id' => $this->program->id, 'class_group_id' => $this->group->id, 'teaching_location' => 'branch',
            'starts_on' => '2026-11-23', 'ends_on' => '2026-12-07', 'weekdays' => [1], 'starts_at' => '10:00', 'ends_at' => '11:00', 'topic' => 'Latihan & speaking']);
        $store = $this->post(route('admin.lesson-series.store', $context), ['preview_token' => $preview['previewToken']])->assertSessionHasNoErrors();
        $series = LessonScheduleSeries::firstOrFail();
        $store->assertRedirect(route('admin.lesson-series.show', [$series] + $context));
        $page = $this->get($store->headers->get('Location'))->assertOk();
        $this->assertSame(route('admin.lesson-sessions.index', $this->filters()), $this->attribute($page, '//a[normalize-space(.)="Kembali ke Jadwal & Sesi"]'));
        $future = $this->get($this->attribute($page, '//a[normalize-space(.)="Ini dan berikutnya"]'))->assertOk();
        $anchor = $series->sessions()->orderBy('planned_start_at')->firstOrFail();
        $previewPage = $this->post($this->attribute($future, '//main//form[@method="POST"]', 'action'), ['teacher_id' => $this->teacher->id,
            'planned_start_at' => $anchor->planned_start_at->format('Y-m-d\T13:00'), 'planned_end_at' => $anchor->planned_end_at->format('Y-m-d\T14:00'), 'status' => 'scheduled'])->assertOk();
        $back = $this->attribute($previewPage, '//a[normalize-space(.)="Kembali ke form"]');
        parse_str(parse_url($back, PHP_URL_QUERY), $query);
        $this->assertEquals($this->filters(), $query['list']);
        $this->assertArrayHasKey('draft', $query);
        $target = $series->sessions()->orderByDesc('planned_start_at')->firstOrFail();
        $deletion = $page->viewData('deletion');
        $this->delete(route('admin.lesson-series.destroy-meeting', [$series, $target] + $context), ['deletion_version' => $deletion['meeting_versions'][$target->id]])->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.lesson-series.show', [$series, 'page' => 1] + $context).'#series-meetings');
        $remaining = $this->get(route('admin.lesson-series.show', [$series] + $context))->assertOk();
        $this->delete(route('admin.lesson-series.destroy', [$series] + $context), ['deletion_version' => $remaining->viewData('deletion')['version']])->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.lesson-sessions.index', $this->filters()));
    }
}
