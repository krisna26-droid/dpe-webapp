<?php

namespace Tests\Unit\Services;

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
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportShareAttemptServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportShareAttemptService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ReportShareAttemptService();
    }

    public function test_can_create_report_share_attempt(): void
    {
        $quarterlyReportFileId = (string) Str::uuid();
        $teacherId = (string) Str::uuid();
        $guardianId = (string) Str::uuid();
        $cycleId = (string) Str::uuid();
        $fileId = (string) Str::uuid();
        $generatedByUserId = (string) Str::uuid();

        $this->createQuarterlyReportFileDependencies(
            $cycleId,
            $fileId,
            $generatedByUserId,
            $quarterlyReportFileId
        );

        $this->createTeacher($teacherId);
        $this->createGuardian($guardianId);

        $openedAt = now()->subMinutes(10);

        $attempt = $this->service->create([
            'quarterly_report_file_id' => $quarterlyReportFileId,
            'teacher_id' => $teacherId,
            'guardian_id' => $guardianId,
            'recipient_phone_snapshot' => '081234567890',
            'status' => 'opened',
            'opened_at' => $openedAt,
            'teacher_note' => 'Laporan telah dibagikan.',
        ]);

        $this->assertInstanceOf(
            ReportShareAttempt::class,
            $attempt
        );

        $this->assertNotEmpty($attempt->id);

        $this->assertDatabaseHas('report_share_attempts', [
            'id' => $attempt->id,
            'quarterly_report_file_id' => $quarterlyReportFileId,
            'teacher_id' => $teacherId,
            'guardian_id' => $guardianId,
            'recipient_phone_snapshot' => '081234567890',
            'status' => 'opened',
            'teacher_note' => 'Laporan telah dibagikan.',
        ]);
    }

    public function test_can_create_attempt_with_explicit_id(): void
    {
        $quarterlyReportFileId = (string) Str::uuid();
        $teacherId = (string) Str::uuid();
        $guardianId = (string) Str::uuid();
        $cycleId = (string) Str::uuid();
        $fileId = (string) Str::uuid();
        $generatedByUserId = (string) Str::uuid();
        $attemptId = (string) Str::uuid();

        $this->createQuarterlyReportFileDependencies(
            $cycleId,
            $fileId,
            $generatedByUserId,
            $quarterlyReportFileId
        );

        $this->createTeacher($teacherId);
        $this->createGuardian($guardianId);

        $attempt = $this->service->create([
            'id' => $attemptId,
            'quarterly_report_file_id' => $quarterlyReportFileId,
            'teacher_id' => $teacherId,
            'guardian_id' => $guardianId,
            'recipient_phone_snapshot' => '081234567890',
            'status' => 'opened',
            'opened_at' => now(),
        ]);

        $this->assertSame($attemptId, $attempt->id);
    }

    public function test_confirmed_at_and_teacher_note_can_be_null(): void
    {
        $quarterlyReportFileId = (string) Str::uuid();
        $teacherId = (string) Str::uuid();
        $guardianId = (string) Str::uuid();
        $cycleId = (string) Str::uuid();
        $fileId = (string) Str::uuid();
        $generatedByUserId = (string) Str::uuid();

        $this->createQuarterlyReportFileDependencies(
            $cycleId,
            $fileId,
            $generatedByUserId,
            $quarterlyReportFileId
        );

        $this->createTeacher($teacherId);
        $this->createGuardian($guardianId);

        $attempt = $this->service->create([
            'quarterly_report_file_id' => $quarterlyReportFileId,
            'teacher_id' => $teacherId,
            'guardian_id' => $guardianId,
            'recipient_phone_snapshot' => '081234567890',
            'status' => 'opened',
            'opened_at' => now(),
            'confirmed_at' => null,
            'teacher_note' => null,
        ]);

        $this->assertNull($attempt->confirmed_at);
        $this->assertNull($attempt->teacher_note);
    }

    public function test_find_by_id_returns_attempt(): void
    {
        $attempt = $this->createAttempt();

        $result = $this->service->findById($attempt->id);

        $this->assertInstanceOf(
            ReportShareAttempt::class,
            $result
        );

        $this->assertSame(
            $attempt->id,
            $result->id
        );
    }

    public function test_find_by_id_throws_exception_when_not_found(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->findById(
            (string) Str::uuid()
        );
    }

    public function test_get_by_quarterly_report_file_returns_attempts(): void
    {
        $quarterlyReportFileId = (string) Str::uuid();

        $attempt1 = $this->createAttempt(
            quarterlyReportFileId: $quarterlyReportFileId,
            openedAt: now()->subMinutes(20)
        );

        $attempt2 = $this->createAttempt(
            quarterlyReportFileId: $quarterlyReportFileId,
            openedAt: now()->subMinutes(5)
        );

        $otherAttempt = $this->createAttempt();

        $quarterlyReportFile = QuarterlyReportFile::query()
            ->findOrFail($quarterlyReportFileId);

        $results = $this->service
            ->getByQuarterlyReportFile($quarterlyReportFile);

        $this->assertCount(2, $results);

        $this->assertSame(
            $attempt2->id,
            $results->first()->id
        );

        $this->assertNotContains(
            $otherAttempt->id,
            $results->pluck('id')->all()
        );

        $this->assertContains(
            $attempt1->id,
            $results->pluck('id')->all()
        );
    }

    public function test_get_by_teacher_returns_attempts(): void
    {
        $teacherId = (string) Str::uuid();

        $attempt1 = $this->createAttempt(
            teacherId: $teacherId,
            openedAt: now()->subMinutes(20)
        );

        $attempt2 = $this->createAttempt(
            teacherId: $teacherId,
            openedAt: now()->subMinutes(5)
        );

        $otherAttempt = $this->createAttempt();

        $teacher = Teacher::query()->findOrFail($teacherId);

        $results = $this->service->getByTeacher($teacher);

        $this->assertCount(2, $results);

        $this->assertSame(
            $attempt2->id,
            $results->first()->id
        );

        $this->assertContains(
            $attempt1->id,
            $results->pluck('id')->all()
        );

        $this->assertNotContains(
            $otherAttempt->id,
            $results->pluck('id')->all()
        );
    }

    public function test_get_by_guardian_returns_attempts(): void
    {
        $guardianId = (string) Str::uuid();

        $attempt1 = $this->createAttempt(
            guardianId: $guardianId,
            openedAt: now()->subMinutes(20)
        );

        $attempt2 = $this->createAttempt(
            guardianId: $guardianId,
            openedAt: now()->subMinutes(5)
        );

        $otherAttempt = $this->createAttempt();

        $guardian = Guardian::query()->findOrFail($guardianId);

        $results = $this->service->getByGuardian($guardian);

        $this->assertCount(2, $results);

        $this->assertSame(
            $attempt2->id,
            $results->first()->id
        );

        $this->assertContains(
            $attempt1->id,
            $results->pluck('id')->all()
        );

        $this->assertNotContains(
            $otherAttempt->id,
            $results->pluck('id')->all()
        );
    }

    private function createAttempt(
        ?string $quarterlyReportFileId = null,
        ?string $teacherId = null,
        ?string $guardianId = null,
        ?\DateTimeInterface $openedAt = null
    ): ReportShareAttempt {
        $quarterlyReportFileId ??= (string) Str::uuid();
        $teacherId ??= (string) Str::uuid();
        $guardianId ??= (string) Str::uuid();

        $cycleId = (string) Str::uuid();
        $fileId = (string) Str::uuid();
        $generatedByUserId = (string) Str::uuid();

        $this->createQuarterlyReportFileDependencies(
            $cycleId,
            $fileId,
            $generatedByUserId,
            $quarterlyReportFileId
        );

        $this->createTeacher($teacherId);
        $this->createGuardian($guardianId);

        return $this->service->create([
            'quarterly_report_file_id' => $quarterlyReportFileId,
            'teacher_id' => $teacherId,
            'guardian_id' => $guardianId,
            'recipient_phone_snapshot' => '081234567890',
            'status' => 'opened',
            'opened_at' => $openedAt ?? now(),
        ]);
    }

    private function createQuarterlyReportFileDependencies(
        string $cycleId,
        string $fileId,
        string $generatedByUserId,
        string $quarterlyReportFileId
    ): void {
        if (QuarterlyReportFile::query()->whereKey($quarterlyReportFileId)->exists()) {
            return;
        }

        $this->createReportCycle($cycleId);

        $this->createFileAsset($fileId);

        $this->createUser($generatedByUserId);

        QuarterlyReportFile::query()->create([
            'id' => $quarterlyReportFileId,
            'cycle_id' => $cycleId,
            'file_id' => $fileId,
            'version_number' => 1,
            'generated_by_user_id' => $generatedByUserId,
            'generated_at' => now(),
            'source_snapshot_hash' => null,
        ]);
    }

    private function createReportCycle(string $cycleId): void
    {
        $studentUser = $this->createUser(roleCode: 'student');
        $student = Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $studentUser->id,
            'full_name' => 'Test Student',
            'school_name' => 'Test School',
            'grade_name' => 'Grade 5',
            'began_on' => '2026-09-01',
            'status' => 'active',
        ]);

        ReportCycle::query()->forceCreate([
            'id' => $cycleId,
            'student_id' => $student->id,
            'cycle_number' => 1,
            'start_month' => '2026-01-01',
            'end_month' => '2026-03-01',
        ]);
    }

    private function createFileAsset(string $fileId): void
    {
        FileAsset::query()->create([
            'id' => $fileId,
            'storage_key' => 'quarterly-reports/' . Str::uuid() . '.pdf',
            'original_name' => 'quarterly-report.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
        ]);
    }

    private function createTeacher(string $teacherId): void
    {
        if (Teacher::query()->whereKey($teacherId)->exists()) {
            return;
        }

        $user = $this->createUser(roleCode: 'teacher');

        Teacher::query()->create([
            'id' => $teacherId,
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ]);
    }

    private function createGuardian(string $guardianId): void
    {
        if (Guardian::query()->whereKey($guardianId)->exists()) {
            return;
        }

        $studentUser = $this->createUser(roleCode: 'student');
        $student = Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $studentUser->id,
            'full_name' => 'Guardian Test Student',
            'school_name' => 'Test School',
            'grade_name' => 'Grade 5',
            'began_on' => '2026-09-01',
            'status' => 'active',
        ]);

        Guardian::query()->create([
            'id' => $guardianId,
            'student_id' => $student->id,
            'full_name' => 'Test Guardian',
            'relationship_name' => 'Parent',
            'whatsapp_number' => '081234567891',
            'is_primary' => true,
        ]);
    }

    private function createUser(
        ?string $userId = null,
        string $roleCode = 'superadmin'
    ): User {
        return User::query()->create([
            'id' => $userId ?? (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => true,
        ]);
    }
}
