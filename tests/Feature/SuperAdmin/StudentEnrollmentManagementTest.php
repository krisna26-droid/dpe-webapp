<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Branch;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentEnrollmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperadmin(
        bool $active = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'superadmin-' . Str::random(8),
            'email' => 'superadmin-' . Str::random(8) . '@test.local',
            'password_hash' => bcrypt('password'),
            'full_name' => 'SuperAdmin Test',
            'role_code' => 'superadmin',
            'is_active' => $active,
        ]);
    }

    private function createAdmin(
        bool $active = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'admin-' . Str::random(8),
            'email' => 'admin-' . Str::random(8) . '@test.local',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Admin Test',
            'role_code' => 'admin',
            'is_active' => $active,
        ]);
    }

    private function createStudent(
        bool $active = true
    ): Student {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'student-' . Str::random(8),
            'email' => 'student-' . Str::random(8) . '@test.local',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Student Test',
            'role_code' => 'student',
            'is_active' => $active,
        ]);

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $user->id,
            'full_name' => 'Student Test',
            'began_on' => '2026-01-01',
            'status' => $active ? 'active' : 'inactive',
        ]);
    }

    private function createBranch(
        bool $active = true
    ): Branch {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . Str::upper(Str::random(6)),
            'name' => 'Branch Test',
            'address' => 'Denpasar',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => $active,
        ]);
    }

    private function createProgram(
        bool $active = true
    ): Program {
        return Program::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PROG-' . Str::upper(Str::random(6)),
            'name' => 'English Program',
            'class_type' => 'Regular',
            'monthly_video_target_override' => 4,
            'is_active' => $active,
        ]);
    }

    private function createEnrollment(
        ?Student $student = null,
        ?Branch $branch = null,
        ?Program $program = null,
        string $startsOn = '2026-01-01',
        ?string $endsOn = null
    ): StudentEnrollment {
        $student ??= $this->createStudent();
        $branch ??= $this->createBranch();
        $program ??= $this->createProgram();

        return StudentEnrollment::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ]);
    }

    public function test_superadmin_can_open_enrollment_index(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->get(route('superadmin.student-enrollments.index'))
            ->assertOk()
            ->assertViewIs(
                'superadmin.student-enrollments.index'
            );
    }

    public function test_non_superadmin_cannot_open_enrollment_management(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('superadmin.student-enrollments.index'))
            ->assertForbidden();
    }

    public function test_inactive_superadmin_cannot_access_enrollment_management(): void
    {
        $superadmin = $this->createSuperadmin(false);

        $this->actingAs($superadmin)
            ->get(route('superadmin.student-enrollments.index'))
            ->assertForbidden();
    }

    public function test_superadmin_can_open_enrollment_create_page(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->get(route('superadmin.student-enrollments.create'))
            ->assertOk()
            ->assertViewIs(
                'superadmin.student-enrollments.create'
            );
    }

    public function test_superadmin_can_create_enrollment(): void
    {
        $superadmin = $this->createSuperadmin();

        $student = $this->createStudent();
        $branch = $this->createBranch();
        $program = $this->createProgram();

        $response = $this->actingAs($superadmin)
            ->post(
                route('superadmin.student-enrollments.store'),
                [
                    'student_id' => $student->id,
                    'branch_id' => $branch->id,
                    'program_id' => $program->id,
                    'starts_on' => '2026-10-01',
                    'ends_on' => '2026-12-31',
                ]
            );

        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->first();

        $this->assertNotNull($enrollment);

        $response
            ->assertRedirect(
                route(
                    'superadmin.student-enrollments.show',
                    $enrollment
                )
            )
            ->assertSessionHas(
                'success',
                'Enrollment siswa berhasil dibuat.'
            );

        $this->assertDatabaseHas('student_enrollments', [
            'id' => $enrollment->id,
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'program_id' => $program->id,
        ]);

        $this->assertSame(
            '2026-10-01',
            $enrollment->fresh()->starts_on->format('Y-m-d')
        );

        $this->assertSame(
            '2026-12-31',
            $enrollment->fresh()->ends_on->format('Y-m-d')
        );
    }

    public function test_enrollment_can_have_no_end_date(): void
    {
        $superadmin = $this->createSuperadmin();

        $student = $this->createStudent();
        $branch = $this->createBranch();
        $program = $this->createProgram();

        $this->actingAs($superadmin)
            ->post(
                route('superadmin.student-enrollments.store'),
                [
                    'student_id' => $student->id,
                    'branch_id' => $branch->id,
                    'program_id' => $program->id,
                    'starts_on' => '2026-10-01',
                    'ends_on' => null,
                ]
            )
            ->assertSessionHasNoErrors();

        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->firstOrFail();

        $this->assertSame(
            '2026-10-01',
            $enrollment->starts_on->format('Y-m-d')
        );

        $this->assertNull(
            $enrollment->ends_on
        );
    }

    public function test_inactive_student_cannot_be_enrolled(): void
    {
        $superadmin = $this->createSuperadmin();

        $student = $this->createStudent(false);
        $branch = $this->createBranch();
        $program = $this->createProgram();

        $this->actingAs($superadmin)
            ->post(
                route('superadmin.student-enrollments.store'),
                [
                    'student_id' => $student->id,
                    'branch_id' => $branch->id,
                    'program_id' => $program->id,
                    'starts_on' => '2026-10-01',
                ]
            )
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseMissing(
            'student_enrollments',
            [
                'student_id' => $student->id,
            ]
        );
    }

    public function test_inactive_branch_cannot_receive_enrollment(): void
    {
        $superadmin = $this->createSuperadmin();

        $student = $this->createStudent();
        $branch = $this->createBranch(false);
        $program = $this->createProgram();

        $this->actingAs($superadmin)
            ->post(
                route('superadmin.student-enrollments.store'),
                [
                    'student_id' => $student->id,
                    'branch_id' => $branch->id,
                    'program_id' => $program->id,
                    'starts_on' => '2026-10-01',
                ]
            )
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseMissing(
            'student_enrollments',
            [
                'student_id' => $student->id,
            ]
        );
    }

    public function test_inactive_program_cannot_be_used_for_enrollment(): void
    {
        $superadmin = $this->createSuperadmin();

        $student = $this->createStudent();
        $branch = $this->createBranch();
        $program = $this->createProgram(false);

        $this->actingAs($superadmin)
            ->post(
                route('superadmin.student-enrollments.store'),
                [
                    'student_id' => $student->id,
                    'branch_id' => $branch->id,
                    'program_id' => $program->id,
                    'starts_on' => '2026-10-01',
                ]
            )
            ->assertSessionHasErrors('program_id');

        $this->assertDatabaseMissing(
            'student_enrollments',
            [
                'student_id' => $student->id,
            ]
        );
    }

    public function test_end_date_cannot_be_before_start_date(): void
    {
        $superadmin = $this->createSuperadmin();

        $student = $this->createStudent();
        $branch = $this->createBranch();
        $program = $this->createProgram();

        $this->actingAs($superadmin)
            ->post(
                route('superadmin.student-enrollments.store'),
                [
                    'student_id' => $student->id,
                    'branch_id' => $branch->id,
                    'program_id' => $program->id,
                    'starts_on' => '2026-10-10',
                    'ends_on' => '2026-10-01',
                ]
            )
            ->assertSessionHasErrors('ends_on');
    }

    public function test_overlapping_enrollment_is_rejected(): void
    {
        $superadmin = $this->createSuperadmin();

        $student = $this->createStudent();
        $branch = $this->createBranch();
        $program = $this->createProgram();

        $this->createEnrollment(
            $student,
            $branch,
            $program,
            '2026-01-01',
            '2026-12-31'
        );

        $this->actingAs($superadmin)
            ->post(
                route('superadmin.student-enrollments.store'),
                [
                    'student_id' => $student->id,
                    'branch_id' => $branch->id,
                    'program_id' => $program->id,
                    'starts_on' => '2026-06-01',
                    'ends_on' => '2027-01-01',
                ]
            )
            ->assertSessionHasErrors('student_id');
    }

    public function test_sequential_enrollment_is_allowed(): void
    {
        $superadmin = $this->createSuperadmin();

        $student = $this->createStudent();
        $branch = $this->createBranch();
        $program = $this->createProgram();

        $this->createEnrollment(
            $student,
            $branch,
            $program,
            '2026-01-01',
            '2026-06-30'
        );

        $this->actingAs($superadmin)
            ->post(
                route('superadmin.student-enrollments.store'),
                [
                    'student_id' => $student->id,
                    'branch_id' => $branch->id,
                    'program_id' => $program->id,
                    'starts_on' => '2026-07-01',
                    'ends_on' => null,
                ]
            )
            ->assertSessionHasNoErrors();

        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->whereDate('starts_on', '2026-07-01')
            ->firstOrFail();

        $this->assertSame(
            '2026-07-01',
            $enrollment->starts_on->format('Y-m-d')
        );

        $this->assertNull(
            $enrollment->ends_on
        );
    }

    public function test_student_cannot_have_two_active_enrollments(): void
    {
        $superadmin = $this->createSuperadmin();

        $student = $this->createStudent();

        $branchA = $this->createBranch();
        $branchB = $this->createBranch();

        $programA = $this->createProgram();
        $programB = $this->createProgram();

        $this->createEnrollment(
            $student,
            $branchA,
            $programA,
            '2026-01-01',
            null
        );

        $this->actingAs($superadmin)
            ->post(
                route('superadmin.student-enrollments.store'),
                [
                    'student_id' => $student->id,
                    'branch_id' => $branchB->id,
                    'program_id' => $programB->id,
                    'starts_on' => '2027-01-01',
                    'ends_on' => null,
                ]
            )
            ->assertSessionHasErrors('student_id');
    }

    public function test_superadmin_can_open_enrollment_show_page(): void
    {
        $superadmin = $this->createSuperadmin();
        $enrollment = $this->createEnrollment(
            null,
            null,
            null,
            '2026-01-01',
            '2026-12-31'
        );

        $this->actingAs($superadmin)
            ->get(
                route(
                    'superadmin.student-enrollments.show',
                    $enrollment
                )
            )
            ->assertOk()
            ->assertViewIs(
                'superadmin.student-enrollments.show'
            );
    }

    public function test_superadmin_can_open_enrollment_edit_page(): void
    {
        $superadmin = $this->createSuperadmin();
        $enrollment = $this->createEnrollment(
            null,
            null,
            null,
            '2026-01-01',
            '2026-12-31'
        );

        $this->actingAs($superadmin)
            ->get(
                route(
                    'superadmin.student-enrollments.edit',
                    $enrollment
                )
            )
            ->assertOk()
            ->assertViewIs(
                'superadmin.student-enrollments.edit'
            );
    }

    public function test_superadmin_can_update_enrollment(): void
    {
        $superadmin = $this->createSuperadmin();

        $enrollment = $this->createEnrollment(
            null,
            null,
            null,
            '2026-01-01',
            '2026-06-30'
        );

        $student = $this->createStudent();
        $branch = $this->createBranch();
        $program = $this->createProgram();

        $response = $this->actingAs($superadmin)
            ->put(
                route(
                    'superadmin.student-enrollments.update',
                    $enrollment
                ),
                [
                    'student_id' => $student->id,
                    'branch_id' => $branch->id,
                    'program_id' => $program->id,
                    'starts_on' => '2026-07-01',
                    'ends_on' => '2026-12-31',
                ]
            );

        $response
            ->assertRedirect(
                route(
                    'superadmin.student-enrollments.show',
                    $enrollment
                )
            )
            ->assertSessionHas(
                'success',
                'Enrollment siswa berhasil diperbarui.'
            );

        $updatedEnrollment = $enrollment->fresh();

        $this->assertSame(
            $student->id,
            $updatedEnrollment->student_id
        );

        $this->assertSame(
            $branch->id,
            $updatedEnrollment->branch_id
        );

        $this->assertSame(
            $program->id,
            $updatedEnrollment->program_id
        );

        $this->assertSame(
            '2026-07-01',
            $updatedEnrollment->starts_on->format('Y-m-d')
        );

        $this->assertSame(
            '2026-12-31',
            $updatedEnrollment->ends_on->format('Y-m-d')
        );
    }

    public function test_superadmin_can_delete_enrollment(): void
    {
        $superadmin = $this->createSuperadmin();

        $enrollment = $this->createEnrollment(
            null,
            null,
            null,
            '2026-01-01',
            '2026-12-31'
        );

        $this->actingAs($superadmin)
            ->delete(
                route(
                    'superadmin.student-enrollments.destroy',
                    $enrollment
                )
            )
            ->assertRedirect(
                route(
                    'superadmin.student-enrollments.index'
                )
            )
            ->assertSessionHas(
                'success',
                'Enrollment siswa berhasil dihapus.'
            );

        $this->assertDatabaseMissing(
            'student_enrollments',
            [
                'id' => $enrollment->id,
            ]
        );
    }

    public function test_admin_cannot_delete_enrollment(): void
    {
        $admin = $this->createAdmin();

        $enrollment = $this->createEnrollment(
            null,
            null,
            null,
            '2026-01-01',
            '2026-12-31'
        );

        $this->actingAs($admin)
            ->delete(
                route(
                    'superadmin.student-enrollments.destroy',
                    $enrollment
                )
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'student_enrollments',
            [
                'id' => $enrollment->id,
            ]
        );
    }

    public function test_unauthenticated_user_cannot_access_enrollment_management(): void
    {
        $this->get(
            route('superadmin.student-enrollments.index')
        )->assertRedirect(
            route('login')
        );
    }
}