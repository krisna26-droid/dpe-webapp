<?php

namespace Tests\Unit\Models;

use App\Models\Branch;
use App\Models\MonthlyReport;
use App\Models\ReportCycle;
use App\Models\ReportStatusEvent;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportStatusEventModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        string $roleCode = 'superadmin'
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' =>
            $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => true,
        ]);
    }

    private function makeBranch(): Branch
    {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BRANCH-' . Str::upper(Str::random(8)),
            'name' => 'Test Branch',
            'timezone_name' => 'Asia/Jakarta',
            'payment_recap_day' => 15,
            'is_active' => true,
        ]);
    }

    private function makeStudent(): Student
    {
        $user = $this->makeUser('student');

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $user->id,
            'full_name' => 'Test Student',
            'school_name' => 'Test School',
            'grade_name' => 'Grade 1',
            'began_on' => now()->subYear()->toDateString(),
            'status' => 'active',
        ]);
    }

    private function makeTeacher(): Teacher
    {
        $user = $this->makeUser('teacher');

        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ]);
    }

    private function makeReportCycle(Student $student): ReportCycle
    {
        $cycle = new ReportCycle();

        $cycle->forceFill([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'cycle_number' => 1,
            'start_month' => now()->startOfMonth()->subMonth()->toDateString(),
            'end_month' => now()->startOfMonth()->addMonth()->subDay()->toDateString(),
            'share_due_on' => now()->startOfMonth()->addDays(10)->toDateString(),
        ]);

        $cycle->save();

        return $cycle;
    }

    private function makeReport(
        string $studentId,
        string $teacherId,
        string $branchId,
        string $cycleId
    ): MonthlyReport {
        $report = new MonthlyReport();

        $report->forceFill([
            'id' => (string) Str::uuid(),
            'cycle_id' => $cycleId,
            'student_id' => $studentId,
            'branch_id' => $branchId,
            'teacher_id' => $teacherId,
            'report_month' =>
            now()->startOfMonth()->toDateString(),
            'due_on' =>
            now()->startOfMonth()
                ->addDays(10)
                ->toDateString(),
            'video_target' => 2,
            'status' => 'draft',
            'development_summary' => null,
            'parent_challenges_summary' => null,
            'parent_message' => null,
            'internal_teacher_note' => null,
            'submitted_at' => null,
            'approved_at' => null,
            'approved_by_user_id' => null,
            'approved_snapshot' => null,
        ]);

        $report->save();

        return $report;
    }

    private function makeValidReport(): MonthlyReport
    {
        $branch = $this->makeBranch();
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $cycle = $this->makeReportCycle($student);

        return $this->makeReport(
            $student->id,
            $teacher->id,
            $branch->id,
            $cycle->id,
        );
    }

    public function test_uses_correct_table(): void
    {
        $event = new ReportStatusEvent();

        $this->assertSame(
            'report_status_events',
            $event->getTable()
        );
    }

    public function test_uses_string_non_incrementing_primary_key(): void
    {
        $event = new ReportStatusEvent();

        $this->assertSame(
            'id',
            $event->getKeyName()
        );

        $this->assertFalse($event->incrementing);

        $this->assertSame(
            'string',
            $event->getKeyType()
        );
    }

    public function test_does_not_use_timestamps(): void
    {
        $event = new ReportStatusEvent();

        $this->assertFalse($event->usesTimestamps());
    }

    public function test_can_create_event(): void
    {
        $actor = $this->makeUser();
        $report = $this->makeValidReport();

        $event = ReportStatusEvent::query()->create([
            'id' => (string) Str::uuid(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => null,
            'to_status' => 'submitted',
            'comment' => 'Report submitted.',
        ]);

        $this->assertDatabaseHas(
            'report_status_events',
            [
                'id' => $event->id,
                'report_id' => $report->id,
                'actor_user_id' => $actor->id,
                'from_status' => null,
                'to_status' => 'submitted',
                'comment' => 'Report submitted.',
            ]
        );
    }

    public function test_can_create_event_without_from_status(): void
    {
        $actor = $this->makeUser();
        $report = $this->makeValidReport();

        $event = ReportStatusEvent::query()->create([
            'id' => (string) Str::uuid(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => null,
            'to_status' => 'draft',
            'comment' => null,
        ]);

        $this->assertNull($event->from_status);
        $this->assertNull($event->comment);
    }

    public function test_occurred_at_is_cast_to_datetime(): void
    {
        $actor = $this->makeUser();
        $report = $this->makeValidReport();

        $event = ReportStatusEvent::query()->create([
            'id' => (string) Str::uuid(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'comment' => null,
            'occurred_at' => now(),
        ]);

        $this->assertInstanceOf(
            Carbon::class,
            $event->occurred_at
        );
    }

    public function test_report_relation_works(): void
    {
        $actor = $this->makeUser();
        $report = $this->makeValidReport();

        $event = ReportStatusEvent::query()->create([
            'id' => (string) Str::uuid(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'comment' => null,
        ]);

        $this->assertInstanceOf(
            MonthlyReport::class,
            $event->report
        );

        $this->assertSame(
            $report->id,
            $event->report->id
        );
    }

    public function test_actor_relation_works(): void
    {
        $actor = $this->makeUser();
        $report = $this->makeValidReport();

        $event = ReportStatusEvent::query()->create([
            'id' => (string) Str::uuid(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'comment' => null,
        ]);

        $this->assertInstanceOf(
            User::class,
            $event->actor
        );

        $this->assertSame(
            $actor->id,
            $event->actor->id
        );
    }

    public function test_monthly_report_has_many_status_events(): void
    {
        $actor = $this->makeUser();
        $report = $this->makeValidReport();

        ReportStatusEvent::query()->create([
            'id' => (string) Str::uuid(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => null,
            'to_status' => 'draft',
            'comment' => null,
        ]);

        ReportStatusEvent::query()->create([
            'id' => (string) Str::uuid(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'comment' => null,
        ]);

        $this->assertCount(
            2,
            $report->statusEvents
        );
    }

    public function test_user_has_many_report_status_events(): void
    {
        $actor = $this->makeUser();
        $report = $this->makeValidReport();

        ReportStatusEvent::query()->create([
            'id' => (string) Str::uuid(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => null,
            'to_status' => 'draft',
            'comment' => null,
        ]);

        ReportStatusEvent::query()->create([
            'id' => (string) Str::uuid(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'comment' => null,
        ]);

        $this->assertCount(
            2,
            $actor->reportStatusEvents
        );
    }

    public function test_deleting_report_cascades_status_events(): void
    {
        $actor = $this->makeUser();
        $report = $this->makeValidReport();

        $event = ReportStatusEvent::query()->create([
            'id' => (string) Str::uuid(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'comment' => null,
        ]);

        $eventId = $event->id;

        $report->delete();

        $this->assertDatabaseMissing(
            'report_status_events',
            [
                'id' => $eventId,
            ]
        );
    }
}
