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
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherAttendanceExceptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 10, 10, 8, 0, 0, 'Asia/Makassar')
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function user(string $role, string $name): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => Str::lower($role.'_'.Str::random(8)),
            'email' => Str::uuid().'@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => $name,
            'role_code' => $role,
            'is_active' => true,
        ]);
    }

    private function branch(string $name = 'Cabang Test'): Branch
    {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-'.strtoupper(Str::random(6)),
            'name' => $name,
            'address' => 'Alamat test',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 20,
            'is_active' => true,
        ]);
    }

    private function program(): Program
    {
        return Program::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PRG-'.strtoupper(Str::random(6)),
            'name' => 'Program Test',
            'class_type' => 'regular',
            'monthly_video_target_override' => null,
            'is_active' => true,
        ]);
    }

    private function teacher(Branch $branch): array
    {
        $teacherUser = $this->user('teacher', 'Guru Test');
        $teacher = Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $teacherUser->id,
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ]);

        TeacherBranchAssignment::query()->create([
            'id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'starts_on' => '2026-01-01',
            'ends_on' => null,
        ]);

        return [$teacherUser, $teacher];
    }

    private function lessonSession(
        Branch $branch,
        Teacher $teacher,
        Program $program,
        string $start = '2026-10-10 07:00:00'
    ): LessonSession {
        return LessonSession::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'teaching_location' => 'student_home',
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => $start,
            'planned_end_at' => '2026-10-10 08:00:00',
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => null,
            'topic' => 'Sesi yang belum diabsen',
            'material' => null,
            'activity' => null,
        ]);
    }

    private function assignAdmin(User $admin, Branch $branch): void
    {
        BranchAdminAssignment::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'admin_user_id' => $admin->id,
            'starts_on' => '2026-01-01',
            'ends_on' => null,
        ]);
    }

    public function test_admin_can_see_and_mark_due_session_as_sick(): void
    {
        $admin = $this->user('admin', 'Admin Cabang');
        $branch = $this->branch();
        [$teacherUser, $teacher] = $this->teacher($branch);
        $lessonSession = $this->lessonSession($branch, $teacher, $this->program());
        $this->assignAdmin($admin, $branch);

        $this->actingAs($admin)
            ->get(route('admin.teacher-attendance.index', [
                'date_from' => '2026-10-10',
                'date_to' => '2026-10-10',
            ]))
            ->assertOk()
            ->assertSee('Sesi yang belum diabsen')
            ->assertSee('Belum ada catatan');

        $response = $this->actingAs($admin)->post(
            route('admin.teacher-attendance.sessions.mark', $lessonSession),
            [
                'attendance_type' => 'sick',
                'manual_note' => 'Guru mengirim surat sakit.',
                'date_from' => '2026-10-10',
                'date_to' => '2026-10-10',
            ]
        );

        $response->assertRedirect(route('admin.teacher-attendance.index', [
            'date_from' => '2026-10-10',
            'date_to' => '2026-10-10',
        ]));

        $this->assertDatabaseHas('teacher_attendances', [
            'session_id' => $lessonSession->id,
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'attendance_type' => 'sick',
            'status' => 'recorded',
            'recorded_by_user_id' => $admin->id,
            'manual_note' => 'Guru mengirim surat sakit.',
        ]);

        $this->assertNotNull(
            TeacherAttendance::query()->where('session_id', $lessonSession->id)->value('recorded_at')
        );
    }

    public function test_leave_and_sick_require_a_note(): void
    {
        $admin = $this->user('admin', 'Admin Cabang');
        $branch = $this->branch();
        [, $teacher] = $this->teacher($branch);
        $lessonSession = $this->lessonSession($branch, $teacher, $this->program());
        $this->assignAdmin($admin, $branch);

        $this->actingAs($admin)
            ->from(route('admin.teacher-attendance.index'))
            ->post(route('admin.teacher-attendance.sessions.mark', $lessonSession), [
                'attendance_type' => 'leave',
            ])
            ->assertRedirect(route('admin.teacher-attendance.index'))
            ->assertSessionHasErrors('manual_note');

        $this->assertDatabaseCount('teacher_attendances', 0);
    }

    public function test_admin_cannot_mark_exception_in_another_branch(): void
    {
        $admin = $this->user('admin', 'Admin Cabang A');
        $branchA = $this->branch('Cabang A');
        $branchB = $this->branch('Cabang B');
        [, $teacherB] = $this->teacher($branchB);
        $lessonSession = $this->lessonSession($branchB, $teacherB, $this->program());
        $this->assignAdmin($admin, $branchA);

        $this->actingAs($admin)
            ->post(route('admin.teacher-attendance.sessions.mark', $lessonSession), [
                'attendance_type' => 'absent',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('teacher_attendances', 0);
    }

    public function test_teacher_cannot_check_in_after_admin_marks_exception(): void
    {
        $admin = $this->user('admin', 'Admin Cabang');
        $branch = $this->branch();
        [$teacherUser, $teacher] = $this->teacher($branch);
        $lessonSession = $this->lessonSession($branch, $teacher, $this->program());
        $this->assignAdmin($admin, $branch);

        $this->actingAs($admin)->post(
            route('admin.teacher-attendance.sessions.mark', $lessonSession),
            [
                'attendance_type' => 'leave',
                'manual_note' => 'Izin keluarga.',
            ]
        )->assertRedirect();

        $this->actingAs($teacherUser)
            ->from(route('teacher.attendance.index'))
            ->post(route('teacher.attendance.check-in', $lessonSession), [
                'photo' => UploadedFile::fake()->createWithContent(
                    'attendance.png',
                    base64_decode(
                        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                        true
                    )
                ),
                'location_status' => 'captured',
                'latitude' => '-8.65',
                'longitude' => '115.21',
                'accuracy' => '10',
            ])
            ->assertRedirect(route('teacher.attendance.index'))
            ->assertSessionHasErrors('attendance');

        $this->assertDatabaseHas('teacher_attendances', [
            'session_id' => $lessonSession->id,
            'attendance_type' => 'leave',
        ]);
    }

    public function test_cancelled_session_cannot_receive_manual_exception(): void
    {
        $admin = $this->user('admin', 'Admin Cabang');
        $branch = $this->branch();
        [, $teacher] = $this->teacher($branch);
        $lessonSession = $this->lessonSession($branch, $teacher, $this->program());
        $lessonSession->update(['status' => 'cancelled']);
        $this->assignAdmin($admin, $branch);

        $this->actingAs($admin)
            ->from(route('admin.teacher-attendance.index'))
            ->post(route('admin.teacher-attendance.sessions.mark', $lessonSession), [
                'attendance_type' => 'absent',
            ])
            ->assertRedirect(route('admin.teacher-attendance.index'))
            ->assertSessionHasErrors('attendance');

        $this->assertDatabaseCount('teacher_attendances', 0);
    }
}
