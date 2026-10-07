<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\Program;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ClassGroupMembershipService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClassGroupMembershipManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperadmin(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Super Admin',
            'role_code' => 'superadmin',
            'is_active' => true,
        ]);
    }

    private function makeBranch(array $overrides = []): Branch
    {
        return Branch::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'code' => 'BR-'.strtoupper(Str::random(6)),
            'name' => 'Test Branch',
            'address' => 'Test Address',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => true,
        ], $overrides));
    }

    private function makeProgram(array $overrides = []): Program
    {
        return Program::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'code' => 'PRG-'.strtoupper(Str::random(6)),
            'name' => 'Test Program',
            'class_type' => 'regular',
            'monthly_video_target_override' => null,
            'is_active' => true,
        ], $overrides));
    }

    private function makeTeacher(array $overrides = []): Teacher
    {
        return Teacher::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'full_name' => 'Test Teacher',
            'is_active' => true,
        ], $overrides));
    }

    private function makeStudent(array $overrides = []): Student
    {
        $portalUser = User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'student_'.Str::lower(Str::random(8)),
            'email' => Str::uuid().'@example.test',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test Student User',
            'role_code' => 'student',
            'is_active' => true,
        ]);

        return Student::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Test Student',
            'school_name' => 'Test School',
            'grade_name' => 'Grade 6',
            'began_on' => '2026-01-01',
            'status' => 'active',
            'special_notes_internal' => null,
        ], $overrides));
    }

    private function makeClassGroup(
        Branch $branch,
        Program $program,
        ?Teacher $teacher = null
    ): ClassGroup {
        return ClassGroup::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'default_teacher_id' => $teacher?->id,
            'code' => 'CG-'.strtoupper(Str::random(6)),
            'name' => 'Test Class Group',
            'is_active' => true,
        ]);
    }

    public function test_superadmin_can_create_membership(): void
    {
        $this->actingAs($this->makeSuperadmin());

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $classGroup = $this->makeClassGroup($branch, $program);
        $student = $this->makeStudent();

        $membership = app(ClassGroupMembershipService::class)->create([
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-31',
        ]);

        $this->assertDatabaseHas('class_group_memberships', [
            'id' => $membership->id,
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
        ]);

        $this->assertSame(
            '2026-10-01',
            $membership->fresh()->starts_on->format('Y-m-d')
        );

        $this->assertSame(
            '2026-12-31',
            $membership->fresh()->ends_on->format('Y-m-d')
        );
    }

    public function test_membership_can_have_no_end_date(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $classGroup = $this->makeClassGroup($branch, $program);
        $student = $this->makeStudent();

        $membership = app(ClassGroupMembershipService::class)->create([
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);

        $this->assertNull($membership->ends_on);
    }

    public function test_inactive_class_group_is_rejected(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();

        $classGroup = $this->makeClassGroup($branch, $program);
        $classGroup->update(['is_active' => false]);

        $student = $this->makeStudent();

        $this->expectException(ValidationException::class);

        app(ClassGroupMembershipService::class)->create([
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);
    }

    public function test_inactive_student_is_rejected(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $classGroup = $this->makeClassGroup($branch, $program);

        $student = $this->makeStudent([
            'status' => 'inactive',
        ]);

        $this->expectException(ValidationException::class);

        app(ClassGroupMembershipService::class)->create([
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);
    }

    public function test_end_date_cannot_be_before_start_date(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $classGroup = $this->makeClassGroup($branch, $program);
        $student = $this->makeStudent();

        $this->expectException(ValidationException::class);

        app(ClassGroupMembershipService::class)->create([
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-10-01',
        ]);
    }

    public function test_same_student_cannot_have_two_active_memberships_in_same_class_group(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $classGroup = $this->makeClassGroup($branch, $program);
        $student = $this->makeStudent();

        $service = app(ClassGroupMembershipService::class);

        $service->create([
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);

        $this->expectException(QueryException::class);

        $service->create([
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
            'starts_on' => '2026-11-01',
            'ends_on' => null,
        ]);
    }

    public function test_same_student_can_join_another_class_group(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();

        $classGroupA = $this->makeClassGroup($branch, $program);
        $classGroupB = $this->makeClassGroup($branch, $program);

        $student = $this->makeStudent();

        $service = app(ClassGroupMembershipService::class);

        $membershipA = $service->create([
            'class_group_id' => $classGroupA->id,
            'student_id' => $student->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);

        $membershipB = $service->create([
            'class_group_id' => $classGroupB->id,
            'student_id' => $student->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);

        $this->assertNotSame(
            $membershipA->id,
            $membershipB->id
        );

        $this->assertDatabaseCount(
            'class_group_memberships',
            2
        );
    }

    public function test_ended_membership_can_be_followed_by_new_membership_in_same_class_group(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $classGroup = $this->makeClassGroup($branch, $program);
        $student = $this->makeStudent();

        $service = app(ClassGroupMembershipService::class);

        $oldMembership = $service->create([
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-06-30',
        ]);

        $newMembership = $service->create([
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
            'starts_on' => '2026-07-01',
            'ends_on' => null,
        ]);

        $this->assertNotSame(
            $oldMembership->id,
            $newMembership->id
        );

        $this->assertDatabaseCount(
            'class_group_memberships',
            2
        );
    }

    public function test_membership_can_be_updated(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $classGroup = $this->makeClassGroup($branch, $program);
        $student = $this->makeStudent();

        $service = app(ClassGroupMembershipService::class);

        $membership = $service->create([
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);

        $updated = $service->update($membership, [
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
            'starts_on' => '2026-10-15',
            'ends_on' => '2026-12-31',
        ]);

        $this->assertSame(
            '2026-10-15',
            $updated->starts_on->format('Y-m-d')
        );

        $this->assertSame(
            '2026-12-31',
            $updated->ends_on->format('Y-m-d')
        );
    }

    public function test_superadmin_can_open_membership_index(): void
    {
        $superadmin = $this->makeSuperadmin();

        $this->actingAs($superadmin)
            ->get(route('superadmin.class-group-memberships.index'))
            ->assertOk();
    }

    public function test_non_superadmin_cannot_open_membership_management(): void
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'teacher_'.Str::lower(Str::random(8)),
            'email' => Str::uuid().'@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test Teacher',
            'role_code' => 'teacher',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('superadmin.class-group-memberships.index'))
            ->assertForbidden();
    }

    public function test_superadmin_can_open_membership_create_page(): void
    {
        $superadmin = $this->makeSuperadmin();

        $this->actingAs($superadmin)
            ->get(route('superadmin.class-group-memberships.create'))
            ->assertOk();
    }

    public function test_superadmin_can_create_membership_through_http(): void
    {
        $superadmin = $this->makeSuperadmin();

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $classGroup = $this->makeClassGroup($branch, $program);
        $student = $this->makeStudent();

        $this->actingAs($superadmin)
            ->post(route('superadmin.class-group-memberships.store'), [
                'class_group_id' => $classGroup->id,
                'student_id' => $student->id,
                'starts_on' => '2026-10-01',
                'ends_on' => '2026-12-31',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $createdMembership = ClassGroupMembership::query()
            ->where('class_group_id', $classGroup->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $this->assertSame(
            '2026-10-01',
            $createdMembership->starts_on->format('Y-m-d')
        );

        $this->assertSame(
            '2026-12-31',
            $createdMembership->ends_on->format('Y-m-d')
        );
    }

    public function test_http_validation_rejects_end_date_before_start_date(): void
    {
        $superadmin = $this->makeSuperadmin();

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $classGroup = $this->makeClassGroup($branch, $program);
        $student = $this->makeStudent();

        $this->actingAs($superadmin)
            ->post(route('superadmin.class-group-memberships.store'), [
                'class_group_id' => $classGroup->id,
                'student_id' => $student->id,
                'starts_on' => '2026-10-10',
                'ends_on' => '2026-10-01',
            ])
            ->assertSessionHasErrors('ends_on');
    }

    public function test_superadmin_can_open_membership_show_page(): void
    {
        $superadmin = $this->makeSuperadmin();

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $classGroup = $this->makeClassGroup($branch, $program);
        $student = $this->makeStudent();

        $membership = app(ClassGroupMembershipService::class)->create([
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);

        $this->actingAs($superadmin)
            ->get(route(
                'superadmin.class-group-memberships.show',
                $membership
            ))
            ->assertOk();
    }

    public function test_superadmin_can_open_membership_edit_page(): void
    {
        $superadmin = $this->makeSuperadmin();

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $classGroup = $this->makeClassGroup($branch, $program);
        $student = $this->makeStudent();

        $membership = app(ClassGroupMembershipService::class)->create([
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);

        $this->actingAs($superadmin)
            ->get(route(
                'superadmin.class-group-memberships.edit',
                $membership
            ))
            ->assertOk();
    }

    public function test_superadmin_can_update_membership_through_http(): void
    {
        $superadmin = $this->makeSuperadmin();

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $classGroup = $this->makeClassGroup($branch, $program);
        $student = $this->makeStudent();

        $membership = app(ClassGroupMembershipService::class)->create([
            'class_group_id' => $classGroup->id,
            'student_id' => $student->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);

        $this->actingAs($superadmin)
            ->put(
                route(
                    'superadmin.class-group-memberships.update',
                    $membership
                ),
                [
                    'class_group_id' => $classGroup->id,
                    'student_id' => $student->id,
                    'starts_on' => '2026-10-15',
                    'ends_on' => '2026-12-31',
                ]
            )
            ->assertRedirect()
            ->assertSessionHas('success');

        $updatedMembership = ClassGroupMembership::query()
            ->findOrFail($membership->id);

        $this->assertSame(
            '2026-10-15',
            $updatedMembership->starts_on->format('Y-m-d')
        );

        $this->assertSame(
            '2026-12-31',
            $updatedMembership->ends_on->format('Y-m-d')
        );
    }

    public function test_unauthenticated_user_cannot_access_membership_management(): void
    {
        $this->get(route('superadmin.class-group-memberships.index'))
            ->assertRedirect();
    }

    public function test_inactive_superadmin_cannot_access_membership_management(): void
    {
        $superadmin = $this->makeSuperadmin();

        $superadmin->update([
            'is_active' => false,
        ]);

        $this->actingAs($superadmin)
            ->get(route('superadmin.class-group-memberships.index'))
            ->assertForbidden();
    }

    public function test_membership_create_requires_required_fields(): void
    {
        $superadmin = $this->makeSuperadmin();

        $this->actingAs($superadmin)
            ->post(
                route('superadmin.class-group-memberships.store'),
                []
            )
            ->assertSessionHasErrors([
                'class_group_id',
                'student_id',
                'starts_on',
            ]);
    }

    public function test_membership_create_rejects_end_date_before_start_date(): void
    {
        $superadmin = $this->makeSuperadmin();

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $classGroup = $this->makeClassGroup($branch, $program);
        $student = $this->makeStudent();

        $this->actingAs($superadmin)
            ->post(
                route('superadmin.class-group-memberships.store'),
                [
                    'class_group_id' => $classGroup->id,
                    'student_id' => $student->id,
                    'starts_on' => '2026-10-10',
                    'ends_on' => '2026-10-01',
                ]
            )
            ->assertSessionHasErrors([
                'ends_on',
            ]);
    }

    
}
