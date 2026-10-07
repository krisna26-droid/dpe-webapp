<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportStatusEventController;
use App\Http\Requests\ReportStatusEventStoreRequest;
use App\Models\Branch;
use App\Models\MonthlyReport;
use App\Models\ReportCycle;
use App\Models\ReportStatusEvent;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReportStatusEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportStatusEventControllerTest extends TestCase
{
    use RefreshDatabase;

    private ReportStatusEventController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller =
            new ReportStatusEventController(
                new ReportStatusEventService()
            );
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
            'code' =>
            'BR-' . Str::upper(Str::random(6)),
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
            'began_on' =>
            now()->subMonth()->toDateString(),
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
            'start_month' =>
            now()->startOfMonth()->toDateString(),
            'end_month' =>
            now()->endOfMonth()->toDateString(),
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

    private function makeEvent(): ReportStatusEvent
    {
        $report = $this->makeReport();
        $actor = $this->makeUser();

        return ReportStatusEvent::query()->create([
            'id' => (string) Str::uuid(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'comment' => 'Submitted.',
        ]);
    }

    private function makeRequest(
        array $data
    ): ReportStatusEventStoreRequest {
        $request = ReportStatusEventStoreRequest::create(
            '/report-status-events',
            'POST',
            $data
        );

        $request->setContainer(app());

        $request->merge($data);
        $request->setValidator(
            Validator::make(
                $data,
                array_fill_keys(array_keys($data), 'sometimes')
            )
        );

        return $request;
    }

    public function test_store_returns_created_event(): void
    {
        $report = $this->makeReport();
        $actor = $this->makeUser();

        $id = (string) Str::uuid();

        $request = $this->makeRequest([
            'id' => $id,
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => null,
            'to_status' => 'draft',
            'comment' => null,
        ]);

        $response = $this->controller->store($request);

        $this->assertSame(
            201,
            $response->getStatusCode()
        );

        $this->assertSame(
            $id,
            $response->getData()->id
        );

        $this->assertDatabaseHas(
            'report_status_events',
            [
                'id' => $id,
                'to_status' => 'draft',
            ]
        );
    }

    public function test_show_returns_event(): void
    {
        $event = $this->makeEvent();

        $response = $this->controller->show(
            $event->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            $event->id,
            $response->getData()->id
        );
    }

    public function test_by_report_returns_events(): void
    {
        $report = $this->makeReport();
        $actor = $this->makeUser();

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

        $response = $this->controller->byReport(
            $report->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertCount(
            2,
            $response->getData()
        );
    }

    public function test_by_actor_returns_events(): void
    {
        $report = $this->makeReport();
        $actor = $this->makeUser();

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

        $response = $this->controller->byActor(
            $actor->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertCount(
            2,
            $response->getData()
        );
    }

    public function test_update_returns_updated_event(): void
    {
        $event = $this->makeEvent();

        $request = $this->makeRequest([
            'id' => $event->id,
            'report_id' => $event->report_id,
            'actor_user_id' => $event->actor_user_id,
            'from_status' => 'submitted',
            'to_status' => 'approved',
            'comment' => 'Approved.',
        ]);

        $response = $this->controller->update(
            $request,
            $event->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            'approved',
            $response->getData()->to_status
        );

        $this->assertSame(
            'Approved.',
            $response->getData()->comment
        );
    }

    public function test_destroy_deletes_event(): void
    {
        $event = $this->makeEvent();

        $response = $this->controller->destroy(
            $event->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            'Report status event deleted successfully.',
            $response->getData()->message
        );

        $this->assertDatabaseMissing(
            'report_status_events',
            [
                'id' => $event->id,
            ]
        );
    }

    public function test_show_throws_when_event_does_not_exist(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->controller->show(
            (string) Str::uuid()
        );
    }
}
