<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MonthlyReport;
use App\Models\ReportCycle;
use App\Models\ReportStatusEvent;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportStatusEventHttpTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        string $roleCode = 'superadmin',
        bool $isActive = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => $isActive,
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
            'start_month' => now()->startOfMonth()->toDateString(),
            'end_month' => now()->endOfMonth()->toDateString(),
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
            'report_month' => now()->startOfMonth()->toDateString(),
            'due_on' => now()->startOfMonth()->addDays(10)->toDateString(),
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
        ?MonthlyReport $report = null,
        ?User $actor = null
    ): ReportStatusEvent {
        $report ??= $this->makeReport();
        $actor ??= $this->makeUser('superadmin');

        return ReportStatusEvent::query()->create([
            'id' => (string) Str::uuid(),
            'report_id' => $report->id,
            'actor_user_id' => $actor->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'comment' => 'Submitted.',
        ]);
    }

    public function test_guest_cannot_access_report_status_events(): void
    {
        $response = $this->getJson('/superadmin/report-status-events/' . Str::uuid());

        $response->assertUnauthorized();
    }

    public function test_non_superadmin_cannot_access_report_status_events(): void
    {
        $user = $this->makeUser('teacher');

        $response = $this->actingAs($user)->getJson(
            '/superadmin/report-status-events/' . Str::uuid()
        );

        $response->assertForbidden();
    }

    public function test_can_store_report_status_event(): void
    {
        $user = $this->makeUser('superadmin');
        $report = $this->makeReport();

        $eventId = (string) Str::uuid();

        $response = $this->actingAs($user)->postJson(
            '/superadmin/report-status-events',
            [
                'id' => $eventId,
                'report_id' => $report->id,
                'actor_user_id' => $user->id,
                'from_status' => 'draft',
                'to_status' => 'submitted',
                'comment' => 'Report submitted.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('id', $eventId)
            ->assertJsonPath('report_id', $report->id)
            ->assertJsonPath('actor_user_id', $user->id)
            ->assertJsonPath('from_status', 'draft')
            ->assertJsonPath('to_status', 'submitted')
            ->assertJsonPath('comment', 'Report submitted.');

        $this->assertDatabaseHas('report_status_events', [
            'id' => $eventId,
            'report_id' => $report->id,
            'actor_user_id' => $user->id,
            'to_status' => 'submitted',
        ]);
    }

    public function test_can_show_report_status_event(): void
    {
        $user = $this->makeUser('superadmin');
        $event = $this->makeEvent();

        $response = $this->actingAs($user)->getJson(
            '/superadmin/report-status-events/' . $event->id
        );

        $response
            ->assertOk()
            ->assertJsonPath('id', $event->id)
            ->assertJsonPath('report_id', $event->report_id)
            ->assertJsonPath('actor_user_id', $event->actor_user_id)
            ->assertJsonPath('to_status', 'submitted');
    }

    public function test_can_get_events_by_report(): void
    {
        $user = $this->makeUser('superadmin');
        $report = $this->makeReport();

        $this->makeEvent($report, $user);
        $this->makeEvent($report, $user);

        $response = $this->actingAs($user)->getJson(
            '/superadmin/monthly-reports/' . $report->id . '/status-events'
        );

        $response
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_can_get_events_by_actor(): void
    {
        $user = $this->makeUser('superadmin');

        $this->makeEvent(null, $user);
        $this->makeEvent(null, $user);

        $response = $this->actingAs($user)->getJson(
            '/superadmin/users/' . $user->id . '/report-status-events'
        );

        $response
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_can_update_report_status_event(): void
    {
        $user = $this->makeUser('superadmin');
        $event = $this->makeEvent(null, $user);

        $response = $this->actingAs($user)->putJson(
            '/superadmin/report-status-events/' . $event->id,
            [
                'id' => $event->id,
                'report_id' => $event->report_id,
                'actor_user_id' => $user->id,
                'from_status' => 'submitted',
                'to_status' => 'approved',
                'comment' => 'Report approved.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('id', $event->id)
            ->assertJsonPath('from_status', 'submitted')
            ->assertJsonPath('to_status', 'approved')
            ->assertJsonPath('comment', 'Report approved.');

        $this->assertDatabaseHas('report_status_events', [
            'id' => $event->id,
            'to_status' => 'approved',
            'comment' => 'Report approved.',
        ]);
    }

    public function test_can_destroy_report_status_event(): void
    {
        $user = $this->makeUser('superadmin');
        $event = $this->makeEvent(null, $user);

        $response = $this->actingAs($user)->deleteJson(
            '/superadmin/report-status-events/' . $event->id
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Report status event deleted successfully.'
            );

        $this->assertDatabaseMissing('report_status_events', [
            'id' => $event->id,
        ]);
    }
}
