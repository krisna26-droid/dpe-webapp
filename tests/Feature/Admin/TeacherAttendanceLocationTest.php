<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeacherAttendanceLocationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $admin;

    private User $teacherUser;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 10, 16, 10, 0, 0, 'Asia/Makassar'));
        config(['attendance-location.cache_store' => 'array']);
        Cache::store('array')->flush();
        Http::preventStrayRequests();
        Storage::fake('local');
        $this->branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(8), 'name' => 'Cabang Uji', 'address' => 'Alamat kantor bukan titik GPS', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
        $this->admin = $this->user('admin');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'admin_user_id' => $this->admin->id, 'starts_on' => '2026-01-01']);
        $this->teacherUser = $this->user('teacher');
        $this->teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->teacherUser->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'starts_on' => '2026-01-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => $role.Str::random(8), 'password_hash' => bcrypt('password'), 'full_name' => 'Pengguna '.$role, 'role_code' => $role, 'is_active' => true]);
    }

    private function attendance(array $overrides = []): TeacherAttendance
    {
        return TeacherAttendance::create(array_merge([
            'id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id,
            'attendance_mode' => 'branch_visit', 'attendance_date' => '2026-10-16', 'attendance_type' => 'present',
            'check_in_at' => '2026-10-16 09:00:00', 'check_out_at' => '2026-10-16 10:00:00',
            'check_in_location_status' => 'captured', 'check_in_latitude' => '-8.6500000', 'check_in_longitude' => '115.2166667',
            'check_out_location_status' => 'captured', 'check_out_latitude' => '-8.6510000', 'check_out_longitude' => '115.2170000',
            'status' => 'pending_review',
        ], $overrides));
    }

    private function endpoint(TeacherAttendance $attendance, string $side = 'check-in'): string
    {
        return route('admin.teacher-attendance.location', [$attendance, $side]);
    }

    private function provider(): void
    {
        Http::fake(fn ($request) => Http::response(['features' => [[
            'geometry' => ['type' => 'Point', 'coordinates' => [(float) $request['lon'], (float) $request['lat']]],
            'properties' => ['street' => $request['lat'] === '-8.6500000' ? 'Jalan Masuk' : 'Jalan Selesai', 'city' => 'Denpasar', 'state' => 'Bali', 'country' => 'Indonesia'],
        ]]]));
    }

    #[DataProvider('rolesAndModes')]
    public function test_authorized_detail_has_two_map_points_without_waiting_for_address(string $role, string $mode): void
    {
        $attendance = $this->attendance(['attendance_mode' => $mode]);
        $response = $this->actingAs($role === 'admin' ? $this->admin : $this->user('superadmin'))
            ->get(route('admin.teacher-attendance.show', $attendance))->assertOk();
        $response->assertDontSee('GPS tersimpan')->assertSee('Lihat Peta')->assertSee('Alamat belum tersedia.');
        $response->assertSee($this->endpoint($attendance), false)->assertSee($this->endpoint($attendance, 'check-out'), false);
        $response->assertSee('query=-8.6500000%2C115.2166667', false)->assertSee('query=-8.6510000%2C115.2170000', false);
        $this->assertSame(2, substr_count($response->getContent(), 'data-attendance-location '));
        $this->assertSame(1, substr_count($response->getContent(), 'attendance-location.js?v='));
        Http::assertNothingSent();
    }

    public static function rolesAndModes(): array
    {
        return [['admin', 'branch_visit'], ['superadmin', 'branch_visit'], ['admin', 'private_session'], ['superadmin', 'legacy_session']];
    }

    public function test_address_uses_stored_point_ignores_forged_coordinates_and_does_not_change_attendance(): void
    {
        $attendance = $this->attendance();
        $before = $attendance->refresh()->getRawOriginal();
        $this->provider();
        $this->actingAs($this->admin)->getJson($this->endpoint($attendance).'?latitude=0&longitude=0')
            ->assertOk()->assertExactJson(['status' => 'resolved', 'address' => 'Jalan Masuk, Denpasar, Bali, Indonesia'])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson($this->endpoint($attendance, 'check-out'))->assertOk()
            ->assertJsonPath('address', 'Jalan Selesai, Denpasar, Bali, Indonesia');
        $this->assertSame($before, $attendance->refresh()->getRawOriginal());
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['lat'] === '-8.6500000' && $request['lon'] === '115.2166667');
    }

    public function test_same_point_on_old_check_in_and_out_only_needs_one_lookup(): void
    {
        $attendance = $this->attendance(['check_out_latitude' => '-8.6500000', 'check_out_longitude' => '115.2166667']);
        $this->provider();
        $this->actingAs($this->user('superadmin'))->getJson($this->endpoint($attendance))->assertJsonPath('status', 'resolved');
        $this->getJson($this->endpoint($attendance, 'check-out'))->assertJsonPath('status', 'resolved');
        Http::assertSentCount(1);
    }

    #[DataProvider('blockedActors')]
    public function test_unauthorized_actor_is_denied_before_any_lookup(string $actor): void
    {
        $attendance = $this->attendance();
        if ($actor !== 'guest') {
            $user = $actor === 'expired' ? $this->admin : $this->user($actor);
            if ($actor === 'expired') {
                BranchAdminAssignment::query()->update(['ends_on' => '2026-10-15']);
            }
            $this->actingAs($user);
        }
        $this->getJson($this->endpoint($attendance))->assertStatus($actor === 'guest' ? 401 : 403);
        Http::assertNothingSent();
    }

    public static function blockedActors(): array
    {
        return [['guest'], ['admin'], ['teacher'], ['student'], ['expired']];
    }

    public function test_cached_address_is_still_private_from_admin_of_another_branch(): void
    {
        $attendance = $this->attendance();
        $this->provider();
        $this->actingAs($this->admin)->getJson($this->endpoint($attendance))->assertJsonPath('status', 'resolved');
        $otherBranch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(8), 'name' => 'Cabang Lain', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
        $otherAdmin = $this->user('admin');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'branch_id' => $otherBranch->id, 'admin_user_id' => $otherAdmin->id, 'starts_on' => '2026-01-01']);
        $this->actingAs($otherAdmin)->getJson($this->endpoint($attendance))
            ->assertForbidden()->assertDontSee('Jalan Masuk');
        Http::assertSentCount(1);
    }

    #[DataProvider('pointsWithoutAddress')]
    public function test_missing_unavailable_manual_and_invalid_points_do_not_trigger_lookup(array $overrides, string $side, string $message): void
    {
        $attendance = $this->attendance($overrides);
        $this->actingAs($this->admin)->getJson($this->endpoint($attendance, $side))
            ->assertOk()->assertExactJson(['status' => 'unavailable', 'address' => null]);
        $this->get(route('admin.teacher-attendance.show', $attendance))->assertOk()->assertSee($message);
        Http::assertNothingSent();
    }

    public static function pointsWithoutAddress(): array
    {
        return [
            [['check_out_at' => null], 'check-out', 'Belum tercatat'],
            [['attendance_type' => 'sick', 'status' => 'recorded'], 'check-in', 'Tidak berlaku'],
            [['check_in_location_status' => 'unavailable', 'check_in_location_note' => 'GPS tidak tersedia.'], 'check-in', 'Pengecualian lokasi'],
            [['check_in_latitude' => null], 'check-in', 'Koordinat tidak tersedia'],
            [['check_in_longitude' => '181.0000000'], 'check-in', 'Koordinat tidak tersedia'],
            [['check_in_latitude' => '-91.0000000'], 'check-in', 'Koordinat tidak tersedia'],
        ];
    }

    public function test_invalid_side_is_not_a_geocoding_proxy(): void
    {
        $attendance = $this->attendance();
        $this->actingAs($this->admin)->getJson($this->endpoint($attendance, 'anything'))->assertNotFound();
        Http::assertNothingSent();
    }

    #[DataProvider('writeModes')]
    public function test_teacher_can_finish_attendance_without_any_provider_call(string $mode): void
    {
        Http::fake(fn () => Http::response([], 503));
        $photo = fn () => UploadedFile::fake()->createWithContent('camera.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR4nGP4DwQACfsD/fteaysAAAAASUVORK5CYII='));
        $payload = fn () => ['photo' => $photo(), 'location_status' => 'captured', 'latitude' => '-8.65', 'longitude' => '115.2166667', 'accuracy' => 12];
        $session = null;
        if ($mode === 'private_session') {
            $program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(8), 'name' => 'Private', 'class_type' => 'private', 'is_active' => true]);
            $session = LessonSession::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'program_id' => $program->id, 'teaching_location' => 'student_home', 'planned_start_at' => '2026-10-16 09:00:00', 'planned_end_at' => '2026-10-16 11:00:00', 'status' => 'scheduled']);
        }
        $start = $session ? route('teacher.attendance.check-in', $session) : route('teacher.attendance.arrive', $this->branch);
        $this->actingAs($this->teacherUser)->post($start, $payload())->assertRedirect()->assertSessionHasNoErrors();
        $attendance = TeacherAttendance::query()->sole();
        $finish = $session ? route('teacher.attendance.check-out', $session) : route('teacher.attendance.depart', $attendance);
        $this->post($finish, $payload())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($attendance->refresh()->check_out_at);
        $this->assertSame('pending_review', $attendance->status);
        $this->assertSame('-8.6500000', $attendance->check_out_latitude);
        Http::assertNothingSent();
    }

    public static function writeModes(): array
    {
        return [['branch_visit'], ['private_session']];
    }
}
