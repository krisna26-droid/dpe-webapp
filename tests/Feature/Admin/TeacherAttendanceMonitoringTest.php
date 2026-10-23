<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\FileAsset;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherAttendanceMonitoringTest extends TestCase
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

    private function makeUser(string $roleCode, string $name): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => Str::lower($roleCode . '_' . Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => $name,
            'role_code' => $roleCode,
            'is_active' => true,
        ]);
    }

    private function makeBranch(string $name): Branch
    {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . strtoupper(Str::random(6)),
            'name' => $name,
            'address' => 'Alamat test',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 20,
            'is_active' => true,
        ]);
    }

    private function makeProgram(): Program
    {
        return Program::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PRG-' . strtoupper(Str::random(6)),
            'name' => 'Program Test',
            'class_type' => 'regular',
            'monthly_video_target_override' => null,
            'is_active' => true,
        ]);
    }

    private function makeTeacher(
        string $name,
        Branch $branch
    ): array {
        $user = $this->makeUser('teacher', $name);
        $teacher = Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
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

        return [$user, $teacher];
    }

    private function makeAttendance(
        Branch $branch,
        Teacher $teacher,
        Program $program,
        string $status = 'pending_review'
    ): TeacherAttendance {
        $session = LessonSession::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => '2026-10-10 09:00:00',
            'planned_end_at' => '2026-10-10 10:00:00',
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => null,
            'topic' => 'Topik monitoring',
            'material' => 'Materi test',
            'activity' => 'Aktivitas test',
        ]);

        return TeacherAttendance::query()->create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'check_in_at' => '2026-10-10 09:01:00',
            'check_in_photo_file_id' => null,
            'check_in_latitude' => '-8.6500000',
            'check_in_longitude' => '115.2166667',
            'check_in_accuracy_m' => '12.50',
            'check_in_location_status' => 'captured',
            'check_in_location_note' => null,
            'check_out_at' => '2026-10-10 10:02:00',
            'check_out_photo_file_id' => null,
            'check_out_latitude' => '-8.6500000',
            'check_out_longitude' => '115.2166667',
            'check_out_accuracy_m' => '14.00',
            'check_out_location_status' => 'captured',
            'check_out_location_note' => null,
            'status' => $status,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'review_note' => null,
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

    public function test_admin_only_sees_attendance_from_assigned_branch(): void
    {
        $admin = $this->makeUser('admin', 'Admin Cabang A');
        $branchA = $this->makeBranch('Cabang A');
        $branchB = $this->makeBranch('Cabang B');
        $program = $this->makeProgram();
        [, $teacherA] = $this->makeTeacher('Guru Cabang A', $branchA);
        [, $teacherB] = $this->makeTeacher('Guru Cabang B', $branchB);
        $attendanceA = $this->makeAttendance($branchA, $teacherA, $program);
        $this->makeAttendance($branchB, $teacherB, $program);
        $this->assignAdmin($admin, $branchA);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.teacher-attendance.index', [
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-10',
            ]));

        $response->assertOk();
        $response->assertSee('Guru Cabang A');
        $response->assertDontSee('Guru Cabang B');
        $response->assertSee(route('admin.teacher-attendance.show', $attendanceA), false);
    }

    public function test_superadmin_can_see_attendance_from_all_branches(): void
    {
        $superadmin = $this->makeUser('superadmin', 'Super Admin');
        $branchA = $this->makeBranch('Cabang A');
        $branchB = $this->makeBranch('Cabang B');
        $program = $this->makeProgram();
        [, $teacherA] = $this->makeTeacher('Guru Cabang A', $branchA);
        [, $teacherB] = $this->makeTeacher('Guru Cabang B', $branchB);
        $this->makeAttendance($branchA, $teacherA, $program);
        $this->makeAttendance($branchB, $teacherB, $program);

        $response = $this
            ->actingAs($superadmin)
            ->get(route('admin.teacher-attendance.index'));

        $response->assertOk();
        $response->assertSee('Guru Cabang A');
        $response->assertSee('Guru Cabang B');
    }

    public function test_admin_can_approve_pending_attendance(): void
    {
        $admin = $this->makeUser('admin', 'Admin Cabang');
        $branch = $this->makeBranch('Cabang Utama');
        $program = $this->makeProgram();
        [, $teacher] = $this->makeTeacher('Guru Review', $branch);
        $attendance = $this->makeAttendance($branch, $teacher, $program);
        $this->assignAdmin($admin, $branch);

        $response = $this
            ->actingAs($admin)
            ->patch(route('admin.teacher-attendance.review', $attendance), [
                'status' => 'approved',
                'review_note' => 'Bukti sesuai.',
            ]);

        $response
            ->assertRedirect(route('admin.teacher-attendance.show', $attendance))
            ->assertSessionHas('success', 'Absensi guru berhasil disetujui.');

        $this->assertDatabaseHas('teacher_attendances', [
            'id' => $attendance->id,
            'status' => 'approved',
            'reviewed_by_user_id' => $admin->id,
            'review_note' => 'Bukti sesuai.',
        ]);
    }

    public function test_admin_cannot_review_attendance_from_another_branch(): void
    {
        $admin = $this->makeUser('admin', 'Admin Cabang A');
        $branchA = $this->makeBranch('Cabang A');
        $branchB = $this->makeBranch('Cabang B');
        $program = $this->makeProgram();
        [, $teacherB] = $this->makeTeacher('Guru Cabang B', $branchB);
        $attendance = $this->makeAttendance($branchB, $teacherB, $program);
        $this->assignAdmin($admin, $branchA);

        $response = $this
            ->actingAs($admin)
            ->patch(route('admin.teacher-attendance.review', $attendance), [
                'status' => 'approved',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('teacher_attendances', [
            'id' => $attendance->id,
            'status' => 'pending_review',
        ]);
    }

    public function test_correction_requires_a_review_note(): void
    {
        $admin = $this->makeUser('admin', 'Admin Cabang');
        $branch = $this->makeBranch('Cabang Utama');
        $program = $this->makeProgram();
        [, $teacher] = $this->makeTeacher('Guru Review', $branch);
        $attendance = $this->makeAttendance($branch, $teacher, $program);
        $this->assignAdmin($admin, $branch);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.teacher-attendance.show', $attendance))
            ->patch(route('admin.teacher-attendance.review', $attendance), [
                'status' => 'needs_correction',
            ]);

        $response->assertRedirect(route('admin.teacher-attendance.show', $attendance));
        $response->assertSessionHasErrors('review_note');
        $this->assertDatabaseHas('teacher_attendances', [
            'id' => $attendance->id,
            'status' => 'pending_review',
        ]);
    }

    public function test_authorized_admin_can_view_private_attendance_photo(): void
    {
        $admin = $this->makeUser('admin', 'Admin Cabang');
        $branch = $this->makeBranch('Cabang Utama');
        $program = $this->makeProgram();
        [, $teacher] = $this->makeTeacher('Guru Review', $branch);
        $attendance = $this->makeAttendance($branch, $teacher, $program);
        $this->assignAdmin($admin, $branch);

        $storageKey = 'teacher-attendance/' . Str::uuid() . '.png';
        Storage::disk('local')->put($storageKey, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        ));
        $photo = FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => $storageKey,
            'original_name' => 'attendance.png',
            'mime_type' => 'image/png',
            'size_bytes' => 68,
            'sha256_hex' => null,
        ]);
        $attendance->update(['check_in_photo_file_id' => $photo->id]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.teacher-attendance.photo', [$attendance, 'check-in']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
    }
}
