<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Guardian;
use App\Models\FileAsset;
use App\Models\QuarterlyReportFile;
use App\Models\ReportCycle;
use App\Models\ReportShareAttempt;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReportShareAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportShareAttemptHttpTest extends TestCase
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

    private function authenticateAsSuperadmin(): User
    {
        $user = $this->makeUser('superadmin');

        $this->actingAs($user);

        return $user;
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

    private function makeGuardian(): Guardian
    {
        $portalUser = $this->makeUser('student');
        $student = Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Guardian Test Student',
            'school_name' => 'Test School',
            'grade_name' => 'Grade 5',
            'began_on' => '2026-09-01',
            'status' => 'active',
        ]);

        return Guardian::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'full_name' => 'Test Guardian',
            'relationship_name' => 'Parent',
            'whatsapp_number' => '081234567891',
            'is_primary' => true,
        ]);
    }

    private function makeQuarterlyReportFile(): QuarterlyReportFile
    {
        $portalUser = $this->makeUser('student');

        $studentId = (string) Str::uuid();

        \DB::table('students')->insert([
            'id' => $studentId,
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Quarterly Report Test Student',
            'began_on' => '2026-09-01',
            'status' => 'active',
        ]);

        $cycle = ReportCycle::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'student_id' => $studentId,
            'cycle_number' => 1,
            'start_month' => '2026-01-01',
            'end_month' => '2026-03-31',
        ]);

        $fileId = (string) Str::uuid();

        FileAsset::query()->create([
            'id' => $fileId,
            'storage_key' => 'quarterly-reports/' . Str::uuid() . '.pdf',
            'original_name' => 'quarterly-report.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
        ]);

        return QuarterlyReportFile::query()->create([
            'id' => (string) Str::uuid(),
            'cycle_id' => $cycle->id,
            'file_id' => $fileId,
            'version_number' => 1,
            'generated_by_user_id' => $this->makeUser()->id,
            'generated_at' => Carbon::parse('2026-10-06 10:00:00'),
            'source_snapshot_hash' => null,
        ]);
    }

    public function test_superadmin_can_create_report_share_attempt(): void
    {
        $this->authenticateAsSuperadmin();

        $quarterlyReportFile = $this->makeQuarterlyReportFile();
        $teacher = $this->makeTeacher();
        $guardian = $this->makeGuardian();

        $response = $this->postJson(
            '/superadmin/report-share-attempts',
            [
                'quarterly_report_file_id' =>
                $quarterlyReportFile->id,
                'teacher_id' => $teacher->id,
                'guardian_id' => $guardian->id,
                'recipient_phone_snapshot' => '081234567890',
                'status' => 'pending',
                'opened_at' => '2026-10-06 10:00:00',
                'confirmed_at' => null,
                'teacher_note' => null,
            ]
        );

        $response
            ->assertStatus(201)
            ->assertJsonPath(
                'quarterly_report_file_id',
                $quarterlyReportFile->id
            )
            ->assertJsonPath(
                'teacher_id',
                $teacher->id
            )
            ->assertJsonPath(
                'guardian_id',
                $guardian->id
            )
            ->assertJsonPath(
                'recipient_phone_snapshot',
                '081234567890'
            )
            ->assertJsonPath(
                'status',
                'pending'
            );

        $this->assertDatabaseHas(
            'report_share_attempts',
            [
                'quarterly_report_file_id' =>
                $quarterlyReportFile->id,
                'teacher_id' => $teacher->id,
                'guardian_id' => $guardian->id,
                'recipient_phone_snapshot' => '081234567890',
                'status' => 'pending',
            ]
        );
    }

    public function test_superadmin_can_show_report_share_attempt(): void
    {
        $this->authenticateAsSuperadmin();

        $quarterlyReportFile = $this->makeQuarterlyReportFile();
        $teacher = $this->makeTeacher();
        $guardian = $this->makeGuardian();

        $shareAttempt = app(
            ReportShareAttemptService::class
        )->create([
            'quarterly_report_file_id' =>
            $quarterlyReportFile->id,
            'teacher_id' => $teacher->id,
            'guardian_id' => $guardian->id,
            'recipient_phone_snapshot' => '081234567890',
            'status' => 'pending',
            'opened_at' =>
            Carbon::parse('2026-10-06 10:00:00'),
        ]);

        $response = $this->getJson(
            '/superadmin/report-share-attempts/' .
                $shareAttempt->id
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'id',
                $shareAttempt->id
            )
            ->assertJsonPath(
                'quarterly_report_file_id',
                $quarterlyReportFile->id
            )
            ->assertJsonPath(
                'teacher_id',
                $teacher->id
            )
            ->assertJsonPath(
                'guardian_id',
                $guardian->id
            );
    }

    public function test_superadmin_can_get_share_attempts_by_quarterly_report_file(): void
    {
        $this->authenticateAsSuperadmin();

        $quarterlyReportFile = $this->makeQuarterlyReportFile();
        $teacher = $this->makeTeacher();
        $guardian = $this->makeGuardian();

        app(ReportShareAttemptService::class)->create([
            'quarterly_report_file_id' =>
            $quarterlyReportFile->id,
            'teacher_id' => $teacher->id,
            'guardian_id' => $guardian->id,
            'recipient_phone_snapshot' => '081234567890',
            'status' => 'pending',
            'opened_at' =>
            Carbon::parse('2026-10-06 10:00:00'),
        ]);

        $response = $this->getJson(
            '/superadmin/quarterly-report-files/' .
                $quarterlyReportFile->id .
                '/share-attempts'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath(
                '0.quarterly_report_file_id',
                $quarterlyReportFile->id
            );
    }

    public function test_superadmin_can_get_share_attempts_by_teacher(): void
    {
        $this->authenticateAsSuperadmin();

        $quarterlyReportFile = $this->makeQuarterlyReportFile();
        $teacher = $this->makeTeacher();
        $guardian = $this->makeGuardian();

        app(ReportShareAttemptService::class)->create([
            'quarterly_report_file_id' =>
            $quarterlyReportFile->id,
            'teacher_id' => $teacher->id,
            'guardian_id' => $guardian->id,
            'recipient_phone_snapshot' => '081234567890',
            'status' => 'pending',
            'opened_at' =>
            Carbon::parse('2026-10-06 10:00:00'),
        ]);

        $response = $this->getJson(
            '/superadmin/teachers/' .
                $teacher->id .
                '/report-share-attempts'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath(
                '0.teacher_id',
                $teacher->id
            );
    }

    public function test_superadmin_can_get_share_attempts_by_guardian(): void
    {
        $this->authenticateAsSuperadmin();

        $quarterlyReportFile = $this->makeQuarterlyReportFile();
        $teacher = $this->makeTeacher();
        $guardian = $this->makeGuardian();

        app(ReportShareAttemptService::class)->create([
            'quarterly_report_file_id' =>
            $quarterlyReportFile->id,
            'teacher_id' => $teacher->id,
            'guardian_id' => $guardian->id,
            'recipient_phone_snapshot' => '081234567890',
            'status' => 'pending',
            'opened_at' =>
            Carbon::parse('2026-10-06 10:00:00'),
        ]);

        $response = $this->getJson(
            '/superadmin/guardians/' .
                $guardian->id .
                '/report-share-attempts'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath(
                '0.guardian_id',
                $guardian->id
            );
    }

    public function test_non_superadmin_cannot_access_report_share_attempt_routes(): void
    {
        $user = $this->makeUser('teacher');

        $this->actingAs($user);

        $response = $this->getJson(
            '/superadmin/report-share-attempts/' .
                Str::uuid()
        );

        $response->assertForbidden();
    }
}
