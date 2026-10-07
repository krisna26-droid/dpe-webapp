<?php

namespace Tests\Unit\Models;

use App\Models\Guardian;
use App\Models\FileAsset;
use App\Models\QuarterlyReportFile;
use App\Models\ReportCycle;
use App\Models\ReportShareAttempt;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportShareAttemptModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_correct_table(): void
    {
        $model = new ReportShareAttempt();

        $this->assertSame(
            'report_share_attempts',
            $model->getTable()
        );
    }

    public function test_uses_string_non_incrementing_primary_key(): void
    {
        $model = new ReportShareAttempt();

        $this->assertSame('id', $model->getKeyName());
        $this->assertFalse($model->getIncrementing());
        $this->assertSame('string', $model->getKeyType());
    }

    public function test_does_not_use_timestamps(): void
    {
        $model = new ReportShareAttempt();

        $this->assertFalse($model->usesTimestamps());
    }

    public function test_has_expected_fillable_columns(): void
    {
        $model = new ReportShareAttempt();

        $this->assertSame([
            'id',
            'quarterly_report_file_id',
            'teacher_id',
            'guardian_id',
            'recipient_phone_snapshot',
            'status',
            'opened_at',
            'confirmed_at',
            'teacher_note',
        ], $model->getFillable());
    }

    public function test_casts_opened_at_to_datetime(): void
    {
        $model = new ReportShareAttempt();

        $casts = $model->getCasts();

        $this->assertSame(
            'datetime',
            $casts['opened_at']
        );
    }

    public function test_casts_confirmed_at_to_datetime(): void
    {
        $model = new ReportShareAttempt();

        $casts = $model->getCasts();

        $this->assertSame(
            'datetime',
            $casts['confirmed_at']
        );
    }

    public function test_quarterly_report_file_relation(): void
    {
        $model = new ReportShareAttempt();

        $relation = $model->quarterlyReportFile();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $relation
        );

        $this->assertSame(
            'quarterly_report_file_id',
            $relation->getForeignKeyName()
        );

        $this->assertSame(
            'id',
            $relation->getOwnerKeyName()
        );

        $this->assertInstanceOf(
            QuarterlyReportFile::class,
            $relation->getRelated()
        );
    }

    public function test_teacher_relation(): void
    {
        $model = new ReportShareAttempt();

        $relation = $model->teacher();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $relation
        );

        $this->assertSame(
            'teacher_id',
            $relation->getForeignKeyName()
        );

        $this->assertSame(
            'id',
            $relation->getOwnerKeyName()
        );

        $this->assertInstanceOf(
            Teacher::class,
            $relation->getRelated()
        );
    }

    public function test_guardian_relation(): void
    {
        $model = new ReportShareAttempt();

        $relation = $model->guardian();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $relation
        );

        $this->assertSame(
            'guardian_id',
            $relation->getForeignKeyName()
        );

        $this->assertSame(
            'id',
            $relation->getOwnerKeyName()
        );

        $this->assertInstanceOf(
            Guardian::class,
            $relation->getRelated()
        );
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

        $openedAt = now()->subMinutes(5);

        $attempt = ReportShareAttempt::query()->create([
            'id' => (string) Str::uuid(),
            'quarterly_report_file_id' => $quarterlyReportFileId,
            'teacher_id' => $teacherId,
            'guardian_id' => $guardianId,
            'recipient_phone_snapshot' => '081234567890',
            'status' => 'opened',
            'opened_at' => $openedAt,
            'confirmed_at' => null,
            'teacher_note' => 'Report dibagikan kepada guardian.',
        ]);

        $this->assertDatabaseHas('report_share_attempts', [
            'id' => $attempt->id,
            'quarterly_report_file_id' => $quarterlyReportFileId,
            'teacher_id' => $teacherId,
            'guardian_id' => $guardianId,
            'recipient_phone_snapshot' => '081234567890',
            'status' => 'opened',
        ]);
    }

    public function test_confirmed_at_and_teacher_note_are_nullable(): void
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

        $attempt = ReportShareAttempt::query()->create([
            'id' => (string) Str::uuid(),
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

    private function createQuarterlyReportFileDependencies(
        string $cycleId,
        string $fileId,
        string $generatedByUserId,
        string $quarterlyReportFileId
    ): void {
        $studentUser = $this->createUser('student');
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

        FileAsset::query()->create([
            'id' => $fileId,
            'storage_key' => 'quarterly-reports/' . Str::uuid() . '.pdf',
            'original_name' => 'quarterly-report.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
        ]);

        $generator = $this->createUser('superadmin', $generatedByUserId);

        QuarterlyReportFile::query()->create([
            'id' => $quarterlyReportFileId,
            'cycle_id' => $cycleId,
            'file_id' => $fileId,
            'version_number' => 1,
            'generated_by_user_id' => $generator->id,
        ]);
    }

    private function createTeacher(string $id): Teacher
    {
        $user = $this->createUser('teacher');

        return Teacher::query()->create([
            'id' => $id,
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ]);
    }

    private function createGuardian(string $id): Guardian
    {
        $studentUser = $this->createUser('student');
        $student = Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $studentUser->id,
            'full_name' => 'Guardian Test Student',
            'school_name' => 'Test School',
            'grade_name' => 'Grade 5',
            'began_on' => '2026-09-01',
            'status' => 'active',
        ]);

        return Guardian::query()->create([
            'id' => $id,
            'student_id' => $student->id,
            'full_name' => 'Test Guardian',
            'relationship_name' => 'Parent',
            'whatsapp_number' => '081234567891',
            'is_primary' => true,
        ]);
    }

    private function createUser(
        string $roleCode,
        ?string $id = null
    ): User {
        return User::query()->create([
            'id' => $id ?? (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => true,
        ]);
    }
}
