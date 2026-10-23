<?php

namespace Tests\Feature\Teacher;

use App\Models\Branch;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 10, 10, 8, 0, 0, 'Asia/Makassar')
        );
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeUser(string $roleCode = 'teacher'): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode.'_'.Str::lower(Str::random(8)),
            'email' => Str::uuid().'@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test '.ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => true,
        ]);
    }

    private function makeBranch(): Branch
    {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-'.strtoupper(Str::random(6)),
            'name' => 'Test Branch',
            'address' => 'Test Address',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 20,
            'is_active' => true,
        ]);
    }

    private function makeProgram(): Program
    {
        return Program::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PRG-'.strtoupper(Str::random(6)),
            'name' => 'Test Program',
            'class_type' => 'regular',
            'monthly_video_target_override' => null,
            'is_active' => true,
        ]);
    }

    private function makeTeacher(): array
    {
        $user = $this->makeUser();
        $teacher = Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ]);

        return [$user, $teacher];
    }

    private function makeSession(
        Branch $branch,
        Teacher $teacher,
        Program $program
    ): LessonSession {
        return LessonSession::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'teaching_location' => 'student_home',
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => '2026-10-10 09:00:00',
            'planned_end_at' => '2026-10-10 10:00:00',
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => null,
            'topic' => 'English Grammar',
            'material' => 'Grammar Book',
            'activity' => 'Discussion',
        ]);
    }

    private function attendancePayload(
        string $locationStatus = 'captured'
    ): array {
        return [
            'photo' => UploadedFile::fake()->createWithContent(
                'teacher-attendance.png',
                base64_decode(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                    true
                )
            ),
            'location_status' => $locationStatus,
            'latitude' => $locationStatus === 'captured' ? '-8.6500000' : null,
            'longitude' => $locationStatus === 'captured' ? '115.2166667' : null,
            'accuracy' => $locationStatus === 'captured' ? '12.50' : null,
            'location_note' => $locationStatus === 'unavailable'
                ? 'Izin lokasi tidak tersedia pada perangkat.'
                : null,
        ];
    }

    public function test_teacher_can_open_today_attendance_page(): void
    {
        [$teacherUser, $teacher] = $this->makeTeacher();
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $session = $this->makeSession($branch, $teacher, $program);

        $response = $this
            ->actingAs($teacherUser)
            ->get(route('teacher.attendance.index'));

        $response->assertOk();
        $response->assertSee($session->topic);
        $response->assertSee('Absen Mulai');
        $response->assertSee('href="'.route('teacher.attendance.index').'"', false);
    }

    public function test_teacher_can_check_in_with_photo_and_location(): void
    {
        [$teacherUser, $teacher] = $this->makeTeacher();
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $session = $this->makeSession($branch, $teacher, $program);

        $response = $this
            ->actingAs($teacherUser)
            ->post(
                route('teacher.attendance.check-in', $session),
                $this->attendancePayload()
            );

        $response->assertRedirect(route('teacher.attendance.index'));
        $response->assertSessionHas('success', 'Absen mulai private berhasil dicatat.');

        $attendance = TeacherAttendance::query()
            ->where('session_id', $session->id)
            ->firstOrFail();

        $this->assertNotNull($attendance->check_in_at);
        $this->assertSame('captured', $attendance->check_in_location_status);
        $this->assertNotNull($attendance->check_in_photo_file_id);
        $this->assertSame('in_progress', $attendance->status);
        Storage::disk('local')->assertExists($attendance->checkInPhoto->storage_key);
    }

    public function test_teacher_can_check_out_with_location_exception_note(): void
    {
        [$teacherUser, $teacher] = $this->makeTeacher();
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $session = $this->makeSession($branch, $teacher, $program);

        $this
            ->actingAs($teacherUser)
            ->post(
                route('teacher.attendance.check-in', $session),
                $this->attendancePayload()
            )
            ->assertRedirect();

        $response = $this
            ->actingAs($teacherUser)
            ->post(
                route('teacher.attendance.check-out', $session),
                $this->attendancePayload('unavailable')
            );

        $response->assertRedirect(route('teacher.attendance.index'));
        $response->assertSessionHas('success', 'Absen selesai berhasil dicatat dan menunggu pemeriksaan.');

        $attendance = TeacherAttendance::query()
            ->where('session_id', $session->id)
            ->firstOrFail();

        $this->assertNotNull($attendance->check_out_at);
        $this->assertSame('unavailable', $attendance->check_out_location_status);
        $this->assertSame('pending_review', $attendance->status);
        $this->assertSame(
            'Izin lokasi tidak tersedia pada perangkat.',
            $attendance->check_out_location_note
        );
    }

    public function test_teacher_cannot_check_in_another_teachers_session(): void
    {
        [$teacherUser, $teacher] = $this->makeTeacher();
        [, $otherTeacher] = $this->makeTeacher();
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $session = $this->makeSession($branch, $otherTeacher, $program);

        $response = $this
            ->actingAs($teacherUser)
            ->post(
                route('teacher.attendance.check-in', $session),
                $this->attendancePayload()
            );

        $response->assertForbidden();
        $this->assertDatabaseCount('teacher_attendances', 0);
    }

    public function test_unavailable_location_requires_reason(): void
    {
        [$teacherUser, $teacher] = $this->makeTeacher();
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $session = $this->makeSession($branch, $teacher, $program);
        $payload = $this->attendancePayload('unavailable');
        $payload['location_note'] = null;

        $response = $this
            ->actingAs($teacherUser)
            ->from(route('teacher.attendance.index'))
            ->post(route('teacher.attendance.check-in', $session), $payload);

        $response->assertRedirect(route('teacher.attendance.index'));
        $response->assertSessionHasErrors('location_note');
        $this->assertDatabaseCount('teacher_attendances', 0);
    }
}
