<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\ClassLearningSession;
use App\Models\ClassLearningSessionEnrollment;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ClassLearningSessionService;
use App\Services\TeacherSlotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeletedStudentClassGroupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $superadmin;
    private Branch $branch;
    private Program $program;
    private ClassGroup $group;
    private ClassLearningSession $session;
    private Student $student;
    private ClassGroupMembership $membership;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new \RuntimeException('Tes ini hanya boleh dijalankan dengan SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Makassar']);
        Carbon::setTestNow(Carbon::parse('2026-11-04 08:00:00', 'Asia/Makassar'));
        $this->admin = $this->user('admin');
        $this->superadmin = $this->user('superadmin');
        $this->branch = Branch::create([
            'id' => (string) Str::uuid(), 'code' => 'DEL-TEST', 'name' => 'Cabang Uji Hapus',
            'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true,
        ]);
        BranchAdminAssignment::create([
            'id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id,
            'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01',
        ]);
        $this->program = Program::create([
            'id' => (string) Str::uuid(), 'code' => 'REG-DEL', 'name' => 'English',
            'class_type' => 'regular', 'is_active' => true,
        ]);
        $teacher = Teacher::create([
            'id' => (string) Str::uuid(), 'user_id' => $this->user('teacher')->id,
            'whatsapp_number' => '081234567890', 'is_active' => true,
        ]);
        $this->group = ClassGroup::create([
            'id' => (string) Str::uuid(), 'branch_id' => $this->branch->id,
            'program_id' => $this->program->id, 'default_teacher_id' => $teacher->id,
            'code' => 'KEL-DEL', 'name' => 'Kelompok Uji Hapus', 'is_active' => true,
        ]);
        $this->session = ClassLearningSession::create([
            'id' => (string) Str::uuid(), 'class_group_id' => $this->group->id,
            'name' => 'Sesi Rabu', 'slot_capacity' => 1,
        ]);
        LessonSession::create([
            'id' => (string) Str::uuid(), 'branch_id' => $this->branch->id,
            'teacher_id' => $teacher->id, 'program_id' => $this->program->id,
            'class_group_id' => $this->group->id, 'class_learning_session_id' => $this->session->id,
            'teaching_location' => 'branch', 'planned_start_at' => '2026-11-04 10:00:00',
            'planned_end_at' => '2026-11-04 11:00:00', 'status' => 'scheduled',
        ]);
        $this->student = $this->student('Siswa Lama Uji');
        $this->membership = $this->member($this->student);
        ClassLearningSessionEnrollment::create([
            'id' => (string) Str::uuid(), 'class_group_membership_id' => $this->membership->id,
            'class_learning_session_id' => $this->session->id, 'weekday' => 3,
            'starts_on' => '2026-09-01',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::create([
            'id' => (string) Str::uuid(), 'username' => Str::random(16),
            'full_name' => ucfirst($role).' Uji', 'password_hash' => 'fixture-hash',
            'role_code' => $role, 'is_active' => true,
        ]);
    }

    private function student(string $name): Student
    {
        $student = Student::create([
            'id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student')->id,
            'full_name' => $name, 'began_on' => '2026-09-01', 'status' => 'active',
        ]);
        StudentEnrollment::create([
            'id' => (string) Str::uuid(), 'student_id' => $student->id,
            'branch_id' => $this->branch->id, 'program_id' => $this->program->id,
            'starts_on' => '2026-09-01',
        ]);

        return $student;
    }

    private function member(Student $student, array $period = []): ClassGroupMembership
    {
        return ClassGroupMembership::create($period + [
            'id' => (string) Str::uuid(), 'student_id' => $student->id,
            'class_group_id' => $this->group->id, 'starts_on' => '2026-09-01',
            'session_selection_from' => '2026-09-01',
        ]);
    }

    public static function operationRoles(): array
    {
        return [['admin'], ['superadmin']];
    }

    #[DataProvider('operationRoles')]
    public function test_admin_deletion_immediately_updates_group_participants_and_keeps_history(string $role): void
    {
        $slots = app(TeacherSlotService::class);
        $this->assertSame(1, $slots->filledCount($this->session->fresh()));

        $this->actingAs($this->admin)
            ->delete(route('admin.students.destroy', $this->student))
            ->assertRedirect(route('admin.students.index'))->assertSessionHasNoErrors();

        $actor = $role === 'admin' ? $this->admin : $this->superadmin;
        $listing = $this->actingAs($actor)->get(route($role.'.class-groups.index'))->assertOk();
        $group = $listing->viewData('classGroups')->firstWhere('id', $this->group->id);
        $this->assertNotNull($group);
        $this->assertSame(0, (int) $group->memberships_count);

        $page = $this->get(route($role.'.class-groups.show', $this->group))
            ->assertOk()->assertSee('Belum ada peserta aktif')->assertSee('Siswa dihapus');
        $active = Str::betweenFirst($page->getContent(), 'id="class-group-active-participants"', '</article>');
        $history = Str::betweenFirst($page->getContent(), 'id="class-group-membership-history"', '</article>');
        $this->assertStringNotContainsString($this->student->full_name, $active);
        $this->assertStringContainsString($this->student->full_name, $history);
        $this->assertSame(0, $slots->filledCount($this->session->fresh()));

        $this->assertDatabaseHas('class_group_memberships', [
            'id' => $this->membership->id, 'student_id' => $this->student->id,
            'ends_on' => '2026-11-04',
        ]);
        $this->assertDatabaseHas('class_learning_session_enrollments', [
            'class_group_membership_id' => $this->membership->id, 'ends_on' => '2026-11-04',
        ]);
        $this->assertTrue(Student::withTrashed()->findOrFail($this->student->id)->trashed());
    }

    public function test_released_slot_can_be_reused_on_the_same_day_without_allowing_overbooking(): void
    {
        $this->actingAs($this->admin)->delete(route('admin.students.destroy', $this->student))
            ->assertRedirect()->assertSessionHasNoErrors();

        $replacement = $this->member($this->student('Siswa Pengganti Uji'));
        $service = app(ClassLearningSessionService::class);
        $service->select($replacement, [$this->session->id.'@3'], '2026-11-04', true);
        $this->assertDatabaseHas('class_learning_session_enrollments', [
            'class_group_membership_id' => $replacement->id,
            'class_learning_session_id' => $this->session->id,
        ]);
        $this->assertSame(1, app(TeacherSlotService::class)->filledCount($this->session->fresh()));

        $overflow = $this->member($this->student('Siswa Melebihi Slot'));
        try {
            $service->select($overflow, [$this->session->id.'@3'], '2026-11-04', true);
            $this->fail('Sesi berkapasitas satu tidak boleh menerima dua siswa aktif.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('learning_session_ids', $exception->errors());
        }
    }

    public function test_normal_membership_end_date_remains_inclusive_and_future_members_are_separate(): void
    {
        $this->membership->update(['ends_on' => '2026-11-04']);
        $past = $this->member($this->student('Siswa Riwayat Uji'), ['ends_on' => '2026-11-03']);
        $future = $this->member($this->student('Siswa Mendatang Uji'), ['starts_on' => '2026-11-05']);

        $this->assertTrue($this->membership->fresh()->isActiveParticipant());
        $this->assertFalse($past->isActiveParticipant());
        $this->assertFalse($future->isActiveParticipant());
        $this->assertSame([$this->membership->id], ClassGroupMembership::activeParticipants()->pluck('id')->all());

        $page = $this->actingAs($this->admin)->get(route('admin.class-groups.show', $this->group))
            ->assertOk()->assertSee('Belum mulai')->assertSee('Selesai');
        $active = Str::betweenFirst($page->getContent(), 'id="class-group-active-participants"', '</article>');
        $this->assertStringContainsString($this->student->full_name, $active);
        $this->assertStringNotContainsString($future->student->full_name, $active);
        $this->assertStringNotContainsString($past->student->full_name, $active);
    }

    public function test_previously_deleted_student_is_excluded_even_if_membership_was_not_closed(): void
    {
        $this->student->delete();

        $this->assertFalse($this->membership->fresh()->isActiveParticipant());
        $this->assertSame(0, ClassGroupMembership::activeParticipants()->count());
        $this->assertSame(0, app(TeacherSlotService::class)->filledCount($this->session->fresh()));
        $this->actingAs($this->admin)->get(route('admin.class-groups.show', $this->group))
            ->assertOk()->assertSee('Belum ada peserta aktif')->assertSee('Siswa dihapus');
        $this->assertDatabaseHas('class_group_memberships', ['id' => $this->membership->id, 'ends_on' => null]);
    }
}
