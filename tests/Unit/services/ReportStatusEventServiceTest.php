<?php

namespace Tests\Unit\Services;

use App\Models\Branch;
use App\Models\MonthlyReport;
use App\Models\ReportCycle;
use App\Models\ReportStatusEvent;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReportStatusEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportStatusEventServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportStatusEventService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ReportStatusEventService();
    }

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

    private function makeReport(): MonthlyReport
    {
        $branch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . Str::upper(Str::random(6)),
            'name' => 'Test Branch',
            'address' => null,
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => true,
        ]);

        $studentUser = $this->makeUser('student');

        $student = Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $studentUser->id,
            'full_name' => 'Test Student',
            'photo_file_id' => null,
            'school_name' => null,
            'grade_name' => null,
            'began_on' => now()->subMonth()->toDateString(),
            'status' => 'active',
            'special_notes_internal' => null,
        ]);

        $teacherUser = $this->makeUser('teacher');

        $teacher = Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $teacherUser->id,
            'whatsapp_number' => '081234567890',
            'photo_file_id' => null,
            'is_active' => true,
        ]);

        $cycle = new ReportCycle();

        $cycle->forceFill([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'cycle_number' => 1,
            'start_month' => now()
                ->startOfMonth()
                ->toDateString(),
            'end_month' => now()
                ->endOfMonth()
                ->toDateString(),
            'share_due_on' => null,
        ]);

        $cycle->save();

        $report = new MonthlyReport();

        $report->forceFill([
            'id' => (string) Str::uuid(),
            'cycle_id' => $cycle->id,
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'report_month' => now()
                ->startOfMonth()
                ->toDateString(),
            'due_on' => now()
                ->startOfMonth()
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

    private function makeEvent(
        ?string $reportId = null,
        ?string $actorUserId = null
    ): ReportStatusEvent {
        $report = $reportId
            ? MonthlyReport::query()->findOrFail($reportId)
            : $this->makeReport();

        $actor = $actorUserId
            ? User::query()->findOrFail($actorUserId)
            : $this->makeUser();

        return $this->service->create([
            'id' => (string) Str::uuid(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'comment' => 'Submitted.',
        ]);
    }

    public function test_create_creates_event(): void
    {
        $report = $this->makeReport();
        $actor = $this->makeUser();

        $id = (string) Str::uuid();

        $event = $this->service->create([
            'id' => $id,
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => null,
            'to_status' => 'draft',
            'comment' => null,
        ]);

        $this->assertInstanceOf(
            ReportStatusEvent::class,
            $event
        );

        $this->assertSame($id, $event->id);

        $this->assertDatabaseHas(
            'report_status_events',
            [
                'id' => $id,
                'report_id' => $report->id,
                'actor_user_id' => $actor->id,
                'to_status' => 'draft',
            ]
        );
    }

    public function test_create_allows_nullable_fields(): void
    {
        $report = $this->makeReport();
        $actor = $this->makeUser();

        $event = $this->service->create([
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

    public function test_find_returns_event(): void
    {
        $event = $this->makeEvent();

        $result = $this->service->find($event->id);

        $this->assertSame(
            $event->id,
            $result->id
        );
    }

    public function test_find_throws_when_event_does_not_exist(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->find(
            (string) Str::uuid()
        );
    }

    public function test_get_by_report_returns_events(): void
    {
        $report = $this->makeReport();

        $this->makeEvent($report->id);
        $this->makeEvent($report->id);

        $otherReport = $this->makeReport();

        $this->makeEvent($otherReport->id);

        $result = $this->service->getByReport($report);

        $this->assertCount(2, $result);

        $this->assertTrue(
            $result->every(
                fn(ReportStatusEvent $event) =>
                $event->report_id === $report->id
            )
        );
    }

    public function test_get_by_actor_returns_events(): void
    {
        $actor = $this->makeUser();

        $this->makeEvent(
            actorUserId: $actor->id
        );

        $this->makeEvent(
            actorUserId: $actor->id
        );

        $otherActor = $this->makeUser();

        $this->makeEvent(
            actorUserId: $otherActor->id
        );

        $result = $this->service->getByActor($actor);

        $this->assertCount(2, $result);

        $this->assertTrue(
            $result->every(
                fn(ReportStatusEvent $event) =>
                $event->actor_user_id === $actor->id
            )
        );
    }

    public function test_update_updates_event(): void
    {
        $event = $this->makeEvent();

        $report = MonthlyReport::query()->findOrFail(
            $event->report_id
        );

        $actor = User::query()->findOrFail(
            $event->actor_user_id
        );

        $updated = $this->service->update(
            $event->id,
            [
                'report_id' => $report->id,
                'actor_user_id' => $actor->id,
                'from_status' => 'submitted',
                'to_status' => 'approved',
                'comment' => 'Approved.',
            ]
        );

        $this->assertSame(
            'submitted',
            $updated->from_status
        );

        $this->assertSame(
            'approved',
            $updated->to_status
        );

        $this->assertSame(
            'Approved.',
            $updated->comment
        );

        $this->assertDatabaseHas(
            'report_status_events',
            [
                'id' => $event->id,
                'from_status' => 'submitted',
                'to_status' => 'approved',
                'comment' => 'Approved.',
            ]
        );
    }

    public function test_update_allows_nullable_fields(): void
    {
        $event = $this->makeEvent();

        $updated = $this->service->update(
            $event->id,
            [
                'report_id' => $event->report_id,
                'actor_user_id' => $event->actor_user_id,
                'from_status' => null,
                'to_status' => 'draft',
                'comment' => null,
            ]
        );

        $this->assertNull($updated->from_status);
        $this->assertNull($updated->comment);
    }

    public function test_delete_deletes_event(): void
    {
        $event = $this->makeEvent();

        $this->service->delete($event->id);

        $this->assertDatabaseMissing(
            'report_status_events',
            [
                'id' => $event->id,
            ]
        );
    }

    public function test_delete_throws_when_event_does_not_exist(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->delete(
            (string) Str::uuid()
        );
    }
}
