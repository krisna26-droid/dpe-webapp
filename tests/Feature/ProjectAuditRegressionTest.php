<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\Guardian;
use App\Models\LearningSkill;
use App\Models\LearningVideo;
use App\Models\LessonSession;
use App\Models\MonthlyCharge;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportSkill;
use App\Models\PaymentProof;
use App\Models\Program;
use App\Models\QuarterlyReportFile;
use App\Models\ReportCycle;
use App\Models\ReportShareAttempt;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\SystemSetting;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\FileAssetService;
use App\Services\MonthlyReportService;
use App\Services\StudentProgressService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $admin;

    private Teacher $teacher;

    private Branch $branch;

    private Program $program;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-11 18:00:00', 'Asia/Makassar'));
        Storage::fake('local');
        $this->superadmin = $this->user('superadmin');
        $this->admin = $this->user('admin');
        $this->branch = $this->branch('DPE Audit');
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'English', 'class_type' => 'branch', 'is_active' => true]);
        $this->teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', 'Dipta Audit')->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        $this->student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student')->id, 'full_name' => 'Siswa Audit', 'began_on' => '2026-10-09', 'status' => 'active']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'starts_on' => '2026-10-09']);
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-10-01']);
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $this->teacher->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-10-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role, string $name = 'User Audit'): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(16), 'password_hash' => 'audit-password-hash', 'full_name' => $name, 'role_code' => $role, 'is_active' => true]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => $name, 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
    }

    private function group(array $attributes = []): ClassGroup
    {
        return ClassGroup::create($attributes + ['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Kelas Audit', 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'default_teacher_id' => $this->teacher->id, 'is_active' => true]);
    }

    private function assignment(): StudentTeacherAssignment
    {
        return StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'starts_on' => '2026-10-09', 'is_report_owner' => false]);
    }

    private function lesson(array $attributes = []): LessonSession
    {
        return LessonSession::create($attributes + ['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'teacher_id' => $this->teacher->id, 'teaching_location' => 'student_home', 'planned_start_at' => '2026-10-11 09:00:00', 'planned_end_at' => '2026-10-11 10:00:00', 'status' => 'scheduled']);
    }

    private function capture(): array
    {
        return ['photo' => UploadedFile::fake()->image('capture.jpg'), 'location_status' => 'unavailable', 'location_note' => 'Lokasi perangkat tidak tersedia.'];
    }

    private function report(): MonthlyReport
    {
        return app(StudentProgressService::class)->prepare($this->admin, $this->student, CarbonImmutable::parse('2026-10-01'), ['teacher_id' => $this->teacher->id, 'video_target' => 2]);
    }

    private function charge(): MonthlyCharge
    {
        return MonthlyCharge::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'charge_month' => '2026-10-01', 'amount_idr' => 500000]);
    }

    private function proof(?MonthlyCharge $charge = null): PaymentProof
    {
        $asset = app(FileAssetService::class)->store(UploadedFile::fake()->image('proof.jpg'), 'payment-proofs');

        return PaymentProof::create(['id' => (string) Str::uuid(), 'charge_id' => ($charge ?? $this->charge())->id, 'image_file_id' => $asset->id, 'uploaded_by_user_id' => $this->student->portal_user_id, 'status' => 'pending']);
    }

    public static function missingPages(): array
    {
        return [['users', 'show'], ['users', 'edit'], ['student-teacher-assignments', 'index'], ['student-teacher-assignments', 'create'], ['student-teacher-assignments', 'show'], ['student-teacher-assignments', 'edit']];
    }

    #[DataProvider('missingPages')]
    public function test_existing_management_routes_render_complete_pages(string $resource, string $action): void
    {
        $record = $resource === 'users' ? $this->student->portalUser : $this->assignment();
        $parameters = in_array($action, ['show', 'edit'], true) ? [$record] : [];
        $this->actingAs($this->superadmin)->get(route('superadmin.'.$resource.'.'.$action, $parameters))->assertOk()->assertDontSee('audit-password-hash');
    }

    public function test_superadmin_can_edit_own_identity_without_changing_role(): void
    {
        $this->actingAs($this->superadmin)->put(route('superadmin.users.update', $this->superadmin), ['username' => $this->superadmin->username, 'full_name' => 'Super Admin Updated', 'role_code' => 'superadmin', 'is_active' => 1])
            ->assertSessionHasNoErrors()->assertRedirect(route('superadmin.users.show', $this->superadmin));
        $this->assertSame('Super Admin Updated', $this->superadmin->fresh()->full_name);
    }

    public function test_editing_a_normal_user_cannot_promote_it_to_superadmin(): void
    {
        $user = $this->student->portalUser;
        $this->actingAs($this->superadmin)->put(route('superadmin.users.update', $user), ['username' => $user->username, 'full_name' => $user->full_name, 'role_code' => 'superadmin', 'is_active' => 1])->assertSessionHasErrors('role_code');
        $this->assertSame('student', $user->fresh()->role_code);
    }

    public function test_preparation_replay_returns_existing_report_after_enrollment_changes(): void
    {
        $report = $this->report();
        $this->student->enrollments()->delete();
        $this->actingAs($this->admin)->post(route('admin.student-progress.prepare', ['student' => $this->student, 'month' => '2026-10']), [])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.student-progress.edit', $report));
        $this->assertDatabaseCount('monthly_reports', 1);
        $this->assertSame(2, $report->fresh()->video_target);
    }

    public function test_reopened_preparation_form_redirects_to_existing_report(): void
    {
        $report = $this->report();
        $this->student->enrollments()->delete();
        $this->actingAs($this->admin)->get(route('admin.student-progress.preparation', ['student' => $this->student, 'month' => '2026-10']))->assertRedirect(route('admin.student-progress.edit', $report));
    }

    public function test_existing_report_replay_still_denies_other_branch_admin_and_teacher(): void
    {
        $this->report();
        foreach ([$this->user('admin'), $this->teacher->user] as $actor) {
            $base = $actor->role_code === 'teacher' ? 'teacher' : 'admin';
            $this->actingAs($actor)->post(route($base.'.student-progress.prepare', ['student' => $this->student, 'month' => '2026-10']), [])->assertForbidden();
        }
    }

    public static function invalidSessions(): array
    {
        return [['class_branch', 'class_group_id'], ['class_program', 'class_group_id'], ['teacher_profile', 'teacher_id'], ['teacher_user', 'teacher_id'], ['branch', 'branch_id'], ['program', 'program_id']];
    }

    #[DataProvider('invalidSessions')]
    public function test_scheduling_rejects_inconsistent_or_inactive_references(string $case, string $field): void
    {
        $group = $this->group();
        if ($case === 'class_branch') {
            $group->update(['branch_id' => $this->branch('Cabang Lain')->id]);
        }
        if ($case === 'class_program') {
            $program = $this->program->replicate();
            $program->id = (string) Str::uuid();
            $program->code = Str::random(12);
            $program->save();
            $group->update(['program_id' => $program->id]);
        }
        if ($case === 'teacher_profile') {
            $this->teacher->update(['is_active' => false]);
        }
        if ($case === 'teacher_user') {
            $this->teacher->user->update(['is_active' => false]);
        }
        if ($case === 'branch') {
            $this->branch->update(['is_active' => false]);
        }
        if ($case === 'program') {
            $this->program->update(['is_active' => false]);
        }
        $this->actingAs($this->superadmin)->post(route('superadmin.lesson-sessions.store'), ['branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'teacher_id' => $this->teacher->id, 'class_group_id' => $group->id, 'planned_start_at' => '2026-10-11 09:00:00', 'planned_end_at' => '2026-10-11 10:00:00', 'status' => 'scheduled'])->assertSessionHasErrors($field);
        $this->assertDatabaseCount('lesson_sessions', 0);
    }

    public function test_teacher_cannot_start_superseded_private_session(): void
    {
        $source = $this->lesson();
        $replacement = $this->lesson(['rescheduled_from_session_id' => $source->id, 'planned_start_at' => '2026-10-11 11:00:00', 'planned_end_at' => '2026-10-11 12:00:00']);
        $this->actingAs($this->teacher->user)->post(route('teacher.attendance.check-in', $source), $this->capture())->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('teacher_attendances', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('teacher-attendance'));
        $this->get(route('teacher.attendance.index'))->assertViewHas('todaySessions', fn ($sessions) => ! $sessions->contains('id', $source->id) && $sessions->contains('id', $replacement->id));
    }

    public function test_cancelled_replacement_does_not_block_original_session(): void
    {
        $source = $this->lesson();
        $this->lesson(['rescheduled_from_session_id' => $source->id, 'status' => ' cancelled ']);
        $this->actingAs($this->teacher->user)->post(route('teacher.attendance.check-in', $source), $this->capture())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('teacher_attendances', ['session_id' => $source->id]);
    }

    public function test_inactive_teacher_profile_cannot_start_new_private_attendance(): void
    {
        $session = $this->lesson();
        $this->teacher->update(['is_active' => false]);
        $this->actingAs($this->teacher->user)->post(route('teacher.attendance.check-in', $session), $this->capture())->assertForbidden();
        $this->assertDatabaseCount('teacher_attendances', 0);
    }

    public static function teachingLocations(): array
    {
        return [['student_home'], ['branch']];
    }

    #[DataProvider('teachingLocations')]
    public function test_superseded_sessions_do_not_create_teacher_absence(string $location): void
    {
        $source = $this->lesson(['teaching_location' => $location]);
        $replacement = $this->lesson(['teaching_location' => $location, 'rescheduled_from_session_id' => $source->id, 'planned_start_at' => '2026-10-12 09:00:00', 'planned_end_at' => '2026-10-12 10:00:00']);
        $this->actingAs($this->admin)->get(route('admin.teacher-attendance.index', ['date_from' => '2026-10-11', 'date_to' => '2026-10-11']))->assertViewHas('pendingSessions', fn ($sessions) => ! $sessions->contains('id', $source->id))->assertViewHas('pendingBranchDays', fn ($rows) => ! $rows->contains('id', $source->id));
        $this->post(route('admin.teacher-attendance.sessions.mark', $source), ['attendance_type' => 'absent'])->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('teacher_attendances', 0);
        $this->assertNotNull($replacement->rescheduled_from_session_id);
    }

    public static function monitoringSources(): array
    {
        return [['past_assignment'], ['session_only']];
    }

    #[DataProvider('monitoringSources')]
    public function test_monitoring_teacher_filter_uses_selected_period_and_actual_sessions(string $source): void
    {
        $this->teacher->branchAssignments()->delete();
        if ($source === 'past_assignment') {
            TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $this->teacher->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
        } else {
            $this->lesson(['planned_start_at' => '2026-09-10 09:00:00', 'planned_end_at' => '2026-09-10 10:00:00']);
        }
        $outsider = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', 'Guru Cabang Lain')->id, 'whatsapp_number' => '08122222222', 'is_active' => true]);
        $this->lesson(['teacher_id' => $outsider->id, 'branch_id' => $this->branch('Cabang Lain')->id, 'planned_start_at' => '2026-09-10 09:00:00', 'planned_end_at' => '2026-09-10 10:00:00']);
        $this->actingAs($this->admin)->get(route('admin.teacher-attendance.index', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']))->assertViewHas('teachers', fn ($teachers) => $teachers->contains('id', $this->teacher->id) && ! $teachers->contains('id', $outsider->id));
    }

    public function test_finite_teacher_assignment_is_active_until_end_of_day(): void
    {
        $assignment = $this->teacher->branchAssignments()->first();
        $assignment->update(['ends_on' => '2026-10-11']);
        $this->actingAs($this->superadmin)->get(route('superadmin.teacher-branch-assignments.show', $assignment))->assertSee('Aktif');
    }

    public function test_membership_and_enrollment_cover_end_date_at_any_time(): void
    {
        $membership = ClassGroupMembership::create(['id' => (string) Str::uuid(), 'class_group_id' => $this->group()->id, 'student_id' => $this->student->id, 'starts_on' => '2026-10-09', 'ends_on' => '2026-10-11']);
        $enrollment = $this->student->enrollments()->first();
        $enrollment->update(['ends_on' => '2026-10-11']);
        $date = Carbon::parse('2026-10-11 23:59:59');
        $this->assertTrue($membership->isActiveOn($date));
        $this->assertTrue($enrollment->isActiveOn($date));
        $this->assertSame('2026-10-11 23:59:59', $date->format('Y-m-d H:i:s'));
    }

    public function test_referenced_photo_is_not_removed_when_metadata_deletion_is_rejected(): void
    {
        $asset = app(FileAssetService::class)->store(UploadedFile::fake()->image('shared.jpg'), 'teacher-photos');
        $this->teacher->update(['photo_file_id' => $asset->id]);
        try {
            app(FileAssetService::class)->delete($asset);
            $this->fail('A referenced asset cannot be deleted.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        Storage::disk('local')->assertExists($asset->storage_key);
        $this->assertDatabaseHas('file_assets', ['id' => $asset->id]);
    }

    public function test_legacy_video_pages_do_not_render_executable_links(): void
    {
        $video = LearningVideo::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'video_on' => '2026-10-10', 'topic' => 'Video lama', 'video_url' => 'javascript:alert(1)']);
        foreach (['index', 'show'] as $action) {
            $this->actingAs($this->superadmin)->get(route('superadmin.learning-videos.'.$action, $action === 'show' ? [$video] : []))->assertOk()->assertDontSee('href="javascript:alert(1)"', false);
        }
    }

    public function test_legacy_video_store_rejects_invalid_url(): void
    {
        $this->actingAs($this->superadmin)->post(route('superadmin.learning-videos.store'), ['student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'video_on' => '2026-10-10', 'topic' => 'Video', 'video_url' => 'not-a-url'])->assertSessionHasErrors('video_url');
        $this->assertDatabaseCount('learning_videos', 0);
    }

    public function test_charge_accepts_optional_due_date_and_normalizes_month(): void
    {
        $this->actingAs($this->superadmin)->post(route('superadmin.monthly-charges.store'), ['student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'charge_month' => '2026-10-15', 'amount_idr' => 500000])->assertSessionHasNoErrors();
        $charge = MonthlyCharge::where('student_id', $this->student->id)->sole();
        $this->assertSame('2026-10-01', $charge->charge_month->toDateString());
        $this->assertNull($charge->due_on);
    }

    public function test_same_student_month_cannot_be_billed_twice_using_different_days(): void
    {
        $this->charge();
        $this->actingAs($this->superadmin)->post(route('superadmin.monthly-charges.store'), ['student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'charge_month' => '2026-10-15', 'amount_idr' => 500000, 'due_on' => null])->assertSessionHasErrors('charge_month');
        $this->assertDatabaseCount('monthly_charges', 1);
    }

    public function test_deleting_charge_cannot_cascade_delete_payment_proofs(): void
    {
        $proof = $this->proof();
        $this->actingAs($this->superadmin)->delete(route('superadmin.monthly-charges.destroy', $proof->charge_id))->assertSessionHasErrors('charge');
        $this->assertDatabaseHas('payment_proofs', ['id' => $proof->id]);
        $this->assertDatabaseHas('monthly_charges', ['id' => $proof->charge_id]);
    }

    public function test_charge_with_payment_proof_cannot_move_to_another_month(): void
    {
        $proof = $this->proof();
        $this->actingAs($this->superadmin)->put(route('superadmin.monthly-charges.update', $proof->charge_id), ['student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'charge_month' => '2026-11-01', 'amount_idr' => 500000, 'due_on' => null])->assertSessionHasErrors('charge_month');
        $this->assertSame('2026-10-01', MonthlyCharge::findOrFail($proof->charge_id)->charge_month->toDateString());
    }

    public function test_payment_reviewer_cannot_be_forged(): void
    {
        $proof = $this->proof();
        $this->actingAs($this->superadmin)->patchJson(route('superadmin.payment-proofs.review', $proof), ['status' => 'approved', 'reviewed_by_user_id' => $this->teacher->user_id])->assertUnprocessable()->assertJsonValidationErrors('reviewed_by_user_id');
        $this->assertSame('pending', $proof->fresh()->status);
    }

    public function test_payment_review_rejects_unrecognized_status(): void
    {
        $proof = $this->proof();
        $this->actingAs($this->superadmin)->patchJson(route('superadmin.payment-proofs.review', $proof), ['status' => 'anything', 'reviewed_by_user_id' => $this->superadmin->id])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_second_payment_approval_returns_validation_error_and_preserves_both_proofs(): void
    {
        $charge = $this->charge();
        $first = $this->proof($charge);
        $second = $this->proof($charge);
        $this->actingAs($this->superadmin)->patchJson(route('superadmin.payment-proofs.review', $first), ['status' => 'approved', 'reviewed_by_user_id' => $this->superadmin->id])->assertOk();
        $this->patchJson(route('superadmin.payment-proofs.review', $second), ['status' => 'approved', 'reviewed_by_user_id' => $this->superadmin->id])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertDatabaseCount('payment_proofs', 2);
    }

    public function test_required_progress_category_cannot_be_deleted(): void
    {
        $skill = LearningSkill::where('code', 'speaking')->firstOrFail();
        $this->actingAs($this->superadmin)->delete(route('superadmin.learning-skills.destroy', $skill))->assertSessionHasErrors('skill');
        $this->assertDatabaseHas('learning_skills', ['id' => $skill->id]);
    }

    public function test_required_progress_category_identity_cannot_be_replaced(): void
    {
        $skill = LearningSkill::where('code', 'speaking')->firstOrFail();
        $this->actingAs($this->superadmin)->put(route('superadmin.learning-skills.update', $skill), ['code' => 'different', 'name' => 'Something Else', 'display_order' => 1, 'is_active' => 1])->assertSessionHasErrors('code');
        $this->assertSame('speaking', $skill->fresh()->code);
    }

    public function test_referenced_extra_skill_returns_validation_error_on_delete(): void
    {
        $report = $this->report();
        $skill = LearningSkill::create(['id' => (string) Str::uuid(), 'code' => 'extra-audit', 'name' => 'Extra', 'display_order' => 20, 'is_active' => true]);
        MonthlyReportSkill::create(['report_id' => $report->id, 'skill_id' => $skill->id, 'trend' => 'Improving', 'description' => 'Deskripsi lama']);
        $this->actingAs($this->superadmin)->delete(route('superadmin.learning-skills.destroy', $skill))->assertSessionHasErrors('skill');
        $this->assertDatabaseHas('monthly_report_skills', ['report_id' => $report->id, 'skill_id' => $skill->id]);
    }

    public static function boundaryEnrollmentDays(): array
    {
        return [['2026-10-10'], ['2026-10-31']];
    }

    #[DataProvider('boundaryEnrollmentDays')]
    public function test_preparation_accepts_single_day_enrollment_including_last_day_of_month(string $day): void
    {
        $this->student->enrollments()->first()->update(['starts_on' => $day, 'ends_on' => $day]);
        $this->actingAs($this->admin)->post(route('admin.student-progress.prepare', ['student' => $this->student, 'month' => '2026-10']), ['teacher_id' => $this->teacher->id, 'video_target' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('monthly_reports', 1);
    }

    public function test_last_day_video_is_included_in_report_and_video_count(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 09:00:00', 'Asia/Makassar'));
        $report = $this->report();
        $video = LearningVideo::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'video_on' => '2026-10-31', 'topic' => 'Video akhir bulan', 'video_url' => 'https://example.test/video/last-day']);
        $this->actingAs($this->teacher->user)->get(route('teacher.student-progress.edit', $report))->assertViewHas('videos', fn ($videos) => $videos->contains('id', $video->id))->assertViewHas('videoCount', 1);
    }

    public function test_cycle_generator_finds_owner_who_started_after_student_registration(): void
    {
        $assignment = $this->assignment();
        $assignment->update(['starts_on' => '2026-10-10', 'is_report_owner' => true]);
        SystemSetting::create(['id' => 1, 'organization_name' => 'DPE Audit', 'default_report_due_day' => 31, 'default_monthly_video_target' => 4, 'in_app_reminders_enabled' => true]);
        $cycle = ReportCycle::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'cycle_number' => 1, 'start_month' => '2026-10-01', 'end_month' => '2026-10-01']);
        $reports = app(MonthlyReportService::class)->generateForCycle($cycle);
        $this->assertCount(1, $reports);
        $this->assertSame($this->teacher->id, $reports->first()->teacher_id);
        $this->assertSame($this->branch->id, $reports->first()->branch_id);
    }

    public function test_legacy_charge_on_last_day_still_blocks_duplicate_month(): void
    {
        $charge = $this->charge();
        $charge->update(['charge_month' => '2026-10-31']);
        $this->actingAs($this->superadmin)->post(route('superadmin.monthly-charges.store'), ['student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'charge_month' => '2026-10-01', 'amount_idr' => 500000])->assertSessionHasErrors('charge_month');
        $this->assertDatabaseCount('monthly_charges', 1);
    }

    public static function invalidMemberships(): array
    {
        return [['no_enrollment'], ['wrong_branch'], ['wrong_program'], ['no_date_overlap']];
    }

    #[DataProvider('invalidMemberships')]
    public function test_class_membership_requires_matching_student_enrollment(string $case): void
    {
        $group = $this->group();
        if ($case === 'no_enrollment') {
            $this->student->enrollments()->delete();
        }
        if ($case === 'wrong_branch') {
            $group->update(['branch_id' => $this->branch('Cabang lain')->id]);
        }
        if ($case === 'wrong_program') {
            $program = $this->program->replicate();
            $program->id = (string) Str::uuid();
            $program->code = Str::random(12);
            $program->save();
            $group->update(['program_id' => $program->id]);
        }
        $this->actingAs($this->superadmin)->post(route('superadmin.class-group-memberships.store'), ['class_group_id' => $group->id, 'student_id' => $this->student->id, 'starts_on' => $case === 'no_date_overlap' ? '2026-10-01' : '2026-10-10', 'ends_on' => $case === 'no_date_overlap' ? '2026-10-05' : null])->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('class_group_memberships', 0);
    }

    public function test_overlapping_finite_memberships_return_validation_error(): void
    {
        $group = $this->group();
        ClassGroupMembership::create(['id' => (string) Str::uuid(), 'class_group_id' => $group->id, 'student_id' => $this->student->id, 'starts_on' => '2026-10-09', 'ends_on' => '2026-10-20']);
        $this->actingAs($this->superadmin)->post(route('superadmin.class-group-memberships.store'), ['class_group_id' => $group->id, 'student_id' => $this->student->id, 'starts_on' => '2026-10-15', 'ends_on' => '2026-10-31'])->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('class_group_memberships', 1);
    }

    public static function inactiveDirectTeachers(): array
    {
        return [['profile'], ['account']];
    }

    #[DataProvider('inactiveDirectTeachers')]
    public function test_direct_assignment_rejects_inactive_teacher(string $case): void
    {
        if ($case === 'profile') {
            $this->teacher->update(['is_active' => false]);
        } else {
            $this->teacher->user->update(['is_active' => false]);
        }
        $this->actingAs($this->superadmin)->post(route('superadmin.student-teacher-assignments.store'), ['student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'starts_on' => '2026-10-09', 'is_report_owner' => 0])->assertSessionHasErrors('teacher_id');
        $this->assertDatabaseCount('student_teacher_assignments', 0);
    }

    public function test_duplicate_open_report_owner_returns_validation_error(): void
    {
        $this->assignment()->update(['is_report_owner' => true]);
        $this->actingAs($this->superadmin)->post(route('superadmin.student-teacher-assignments.store'), ['student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'starts_on' => '2026-10-10', 'is_report_owner' => 1])->assertSessionHasErrors('is_report_owner');
        $this->assertDatabaseCount('student_teacher_assignments', 1);
    }

    public function test_superadmin_update_cannot_disable_own_account_even_with_another_superadmin(): void
    {
        $this->user('superadmin', 'Super Admin kedua');
        $this->actingAs($this->superadmin)->put(route('superadmin.users.update', $this->superadmin), ['username' => $this->superadmin->username, 'full_name' => $this->superadmin->full_name, 'role_code' => 'superadmin', 'is_active' => 0])->assertSessionHasErrors('user');
        $this->assertTrue($this->superadmin->fresh()->is_active);
    }

    public function test_inactive_teacher_profile_cannot_submit_report_through_legacy_route(): void
    {
        $report = $this->report();
        $report->update(['development_summary' => 'Perkembangan', 'parent_challenges_summary' => 'Challenges']);
        MonthlyReportSkill::create(['report_id' => $report->id, 'skill_id' => LearningSkill::where('code', 'speaking')->firstOrFail()->id, 'trend' => 'Improving', 'description' => 'Speaking berkembang.']);
        $this->teacher->update(['is_active' => false]);
        $this->actingAs($this->teacher->user)->postJson(route('teacher.reports.submit', $report))->assertForbidden();
        $this->assertSame('draft', $report->fresh()->status);
    }

    public function test_login_rejects_array_username_without_server_error(): void
    {
        $this->post('/login', ['username' => ['invalid'], 'password' => 'password'])->assertSessionHasErrors('username');
    }

    private function guardianWithShareHistory(): Guardian
    {
        $report = $this->report();
        $asset = app(FileAssetService::class)->store(UploadedFile::fake()->create('report.pdf', 1, 'application/pdf'), 'reports');
        $file = QuarterlyReportFile::create(['id' => (string) Str::uuid(), 'cycle_id' => $report->cycle_id, 'file_id' => $asset->id, 'version_number' => 1, 'generated_by_user_id' => $this->superadmin->id]);
        $guardian = Guardian::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'full_name' => 'Wali Audit', 'whatsapp_number' => '081234567890', 'is_primary' => true]);
        ReportShareAttempt::create(['id' => (string) Str::uuid(), 'quarterly_report_file_id' => $file->id, 'teacher_id' => $this->teacher->id, 'guardian_id' => $guardian->id, 'recipient_phone_snapshot' => $guardian->whatsapp_number, 'status' => 'opened', 'opened_at' => now()]);

        return $guardian;
    }

    public function test_guardian_with_report_share_history_cannot_be_deleted(): void
    {
        $guardian = $this->guardianWithShareHistory();
        $this->actingAs($this->superadmin)->delete(route('superadmin.guardians.destroy', $guardian))->assertSessionHasErrors('guardian');
        $this->assertDatabaseHas('guardians', ['id' => $guardian->id]);
        $this->assertDatabaseCount('report_share_attempts', 1);
    }

    public function test_guardian_with_report_share_history_cannot_move_to_another_student(): void
    {
        $guardian = $this->guardianWithShareHistory();
        $other = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student')->id, 'full_name' => 'Siswa lain', 'began_on' => '2026-10-09', 'status' => 'active']);
        $this->actingAs($this->superadmin)->put(route('superadmin.guardians.update', $guardian), ['student_id' => $other->id, 'full_name' => $guardian->full_name, 'whatsapp_number' => $guardian->whatsapp_number, 'is_primary' => 1])->assertSessionHasErrors('student_id');
        $this->assertSame($this->student->id, $guardian->fresh()->student_id);
    }

    public function test_cycle_range_update_cannot_exclude_an_existing_monthly_report(): void
    {
        $report = $this->report();
        $cycle = $report->cycle;
        $this->actingAs($this->admin)->putJson(route('admin.report-cycles.update', $cycle), ['cycle_number' => $cycle->cycle_number, 'start_month' => '2026-11-01', 'end_month' => '2027-01-01'])->assertUnprocessable()->assertJsonValidationErrors('start_month');
        $this->assertSame('2026-10-01', $cycle->fresh()->start_month->toDateString());
        $this->assertSame($cycle->id, $report->fresh()->cycle_id);
    }

    public static function dashboardRoles(): array
    {
        return [['superadmin'], ['admin'], ['teacher'], ['student']];
    }

    #[DataProvider('dashboardRoles')]
    public function test_all_role_landing_and_list_routes_render_with_aligned_table_actions(string $role): void
    {
        SystemSetting::create(['id' => 1, 'organization_name' => 'DPE Audit', 'default_report_due_day' => 31, 'default_monthly_video_target' => 4, 'in_app_reminders_enabled' => true]);
        $this->assignment();
        $this->report();
        $group = $this->group();
        ClassGroupMembership::create(['id' => (string) Str::uuid(), 'class_group_id' => $group->id, 'student_id' => $this->student->id, 'starts_on' => '2026-10-09']);
        $this->lesson(['class_group_id' => $group->id, 'teaching_location' => 'branch']);
        $this->proof();
        Guardian::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'full_name' => 'Wali Audit', 'whatsapp_number' => '081234567890', 'is_primary' => true]);
        $actor = match ($role) {
            'superadmin' => $this->superadmin,
            'admin' => $this->admin,
            'teacher' => $this->teacher->user,
            'student' => $this->student->portalUser,
        };
        $this->actingAs($actor);
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            // Private images return 404 before upload; dedicated account/signature tests cover them.
            if (in_array($route->getName(), ['teacher.profile.photo', 'teacher.profile.signature.image',
                'superadmin.report-template.signature.image'], true)) { continue; }
            if (! str_starts_with($route->getName() ?? '', $role.'.') || ! in_array('GET', $route->methods(), true) || $route->parameterNames() !== []) {
                continue;
            }
            $checked++;
            $response = $this->get(route($route->getName()));
            $this->assertSame(200, $response->status(), $route->getName());
            $response->assertDontSee('audit-password-hash');
            if (! str_contains($response->headers->get('content-type') ?? '', 'text/html')) {
                continue;
            }
            $dom = new \DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML($response->getContent());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new \DOMXPath($dom);
            foreach ($xpath->query('//table[thead]') as $table) {
                $headers = $xpath->query('./thead/tr/th', $table);
                if ($xpath->query('./thead/tr', $table)->length !== 1 || $xpath->query('./thead/tr/th[@colspan or @rowspan]', $table)->length > 0) {
                    continue;
                }
                $actionIndex = null;
                foreach ($headers as $i => $header) {
                    if (strtolower(trim($header->textContent)) === 'aksi') {
                        $actionIndex = $i;
                    }
                }
                foreach ($xpath->query('./tbody/tr', $table) as $row) {
                    $cells = $xpath->query('./td|./th', $row);
                    $width = 0;
                    foreach ($cells as $cell) {
                        $width += max(1, (int) $cell->getAttribute('colspan'));
                    }
                    $this->assertSame($headers->length, $width, $route->getName().' has a row with a different number of columns.');
                    if ($actionIndex !== null && $cells->length === $headers->length) {
                        foreach ($cells as $i => $cell) {
                            foreach ($xpath->query('.//a|.//button', $cell) as $control) {
                                if (preg_match('/^(edit|hapus)$/i', trim($control->textContent))) {
                                    $this->assertSame($actionIndex, $i, $route->getName().' has an action outside its Aksi column.');
                                }
                            }
                        }
                    }
                }
            }
        }
        $this->assertGreaterThan(0, $checked);
    }
}
