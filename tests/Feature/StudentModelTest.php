<?php

namespace Tests\Feature;

use App\Models\ClassGroupMembership;
use App\Models\FileAsset;
use App\Models\Guardian;
use App\Models\LearningVideo;
use App\Models\LessonSession;
use App\Models\MonthlyCharge;
use App\Models\MonthlyReport;
use App\Models\ReportCycle;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\StudentChallenge;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use App\Models\TeacherMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        string $roleCode = 'student'
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => true,
        ]);
    }

    private function makeStudent(): Student
    {
        $portalUser = $this->makeUser('student');

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Test Student',
            'school_name' => 'Test School',
            'grade_name' => 'Grade 6',
            'began_on' => '2026-01-01',
            'status' => 'active',
            'special_notes_internal' => null,
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

    private function makeFileAsset(): FileAsset
    {
        return FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => 'student-photos/' . Str::uuid() . '.jpg',
            'original_name' => 'student.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'sha256_hex' => hash('sha256', 'student-photo'),
        ]);
    }

    public function test_uses_correct_table(): void
    {
        $student = new Student();

        $this->assertSame(
            'students',
            $student->getTable()
        );
    }

    public function test_uses_string_primary_key_without_auto_increment(): void
    {
        $student = new Student();

        $this->assertSame(
            'id',
            $student->getKeyName()
        );

        $this->assertFalse(
            $student->getIncrementing()
        );

        $this->assertSame(
            'string',
            $student->getKeyType()
        );
    }

    public function test_uses_created_at_without_updated_at(): void
    {
        $student = new Student();

        $this->assertSame(
            'created_at',
            $student->getCreatedAtColumn()
        );

        $this->assertNull(
            $student->getUpdatedAtColumn()
        );
    }

    public function test_has_expected_fillable_columns(): void
    {
        $student = new Student();

        $this->assertSame(
            [
                'id',
                'portal_user_id',
                'full_name',
                'photo_file_id',
                'school_name',
                'grade_name',
                'began_on',
                'status',
                'special_notes_internal',
            ],
            $student->getFillable()
        );
    }

    public function test_casts_expected_attributes(): void
    {
        $student = new Student();

        $casts = $student->getCasts();

        $this->assertSame('date', $casts['began_on']);
        $this->assertSame('datetime', $casts['created_at']);
    }

    public function test_can_create_student(): void
    {
        $student = $this->makeStudent();

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'portal_user_id' => $student->portal_user_id,
            'full_name' => 'Test Student',
            'school_name' => 'Test School',
            'grade_name' => 'Grade 6',
            'status' => 'active',
        ]);

        $this->assertSame(
            '2026-01-01',
            $student->fresh()->began_on->format('Y-m-d')
        );
    }

    public function test_portal_user_relation_returns_user(): void
    {
        $student = $this->makeStudent();

        $this->assertInstanceOf(
            User::class,
            $student->portalUser
        );

        $this->assertSame(
            $student->portal_user_id,
            $student->portalUser->id
        );
    }

    public function test_photo_file_relation_returns_file_asset(): void
    {
        $file = $this->makeFileAsset();

        $student = $this->makeStudent();

        $student->update([
            'photo_file_id' => $file->id,
        ]);

        $student->refresh();

        $this->assertInstanceOf(
            FileAsset::class,
            $student->photoFile
        );

        $this->assertSame(
            $file->id,
            $student->photoFile->id
        );
    }

    public function test_enrollments_relation_is_has_many(): void
    {
        $student = new Student();

        $relation = $student->enrollments();

        $this->assertSame(
            StudentEnrollment::class,
            $relation->getRelated()::class
        );

        $this->assertSame(
            'student_id',
            $relation->getForeignKeyName()
        );
    }

    public function test_guardians_relation_is_has_many(): void
    {
        $student = new Student();

        $relation = $student->guardians();

        $this->assertSame(
            Guardian::class,
            $relation->getRelated()::class
        );

        $this->assertSame(
            'student_id',
            $relation->getForeignKeyName()
        );
    }

    public function test_class_group_memberships_relation_is_has_many(): void
    {
        $student = new Student();

        $relation = $student->classGroupMemberships();

        $this->assertSame(
            ClassGroupMembership::class,
            $relation->getRelated()::class
        );

        $this->assertSame(
            'student_id',
            $relation->getForeignKeyName()
        );
    }

    public function test_student_challenges_relation_is_has_many(): void
    {
        $student = new Student();

        $relation = $student->studentChallenges();

        $this->assertSame(
            StudentChallenge::class,
            $relation->getRelated()::class
        );

        $this->assertSame(
            'student_id',
            $relation->getForeignKeyName()
        );
    }

    public function test_teacher_messages_relation_is_has_many(): void
    {
        $student = new Student();

        $relation = $student->teacherMessages();

        $this->assertSame(
            TeacherMessage::class,
            $relation->getRelated()::class
        );

        $this->assertSame(
            'student_id',
            $relation->getForeignKeyName()
        );
    }

    public function test_monthly_charges_relation_is_has_many(): void
    {
        $student = new Student();

        $relation = $student->monthlyCharges();

        $this->assertSame(
            MonthlyCharge::class,
            $relation->getRelated()::class
        );

        $this->assertSame(
            'student_id',
            $relation->getForeignKeyName()
        );
    }

    public function test_learning_videos_relation_is_has_many(): void
    {
        $student = new Student();

        $relation = $student->learningVideos();

        $this->assertSame(
            LearningVideo::class,
            $relation->getRelated()::class
        );

        $this->assertSame(
            'student_id',
            $relation->getForeignKeyName()
        );
    }

    public function test_monthly_reports_relation_is_has_many(): void
    {
        $student = new Student();

        $relation = $student->monthlyReports();

        $this->assertSame(
            MonthlyReport::class,
            $relation->getRelated()::class
        );

        $this->assertSame(
            'student_id',
            $relation->getForeignKeyName()
        );
    }

    public function test_report_cycles_relation_is_has_many(): void
    {
        $student = new Student();

        $relation = $student->reportCycles();

        $this->assertSame(
            ReportCycle::class,
            $relation->getRelated()::class
        );

        $this->assertSame(
            'student_id',
            $relation->getForeignKeyName()
        );
    }

    public function test_student_teacher_assignments_relation_is_has_many(): void
    {
        $student = new Student();

        $relation = $student->studentTeacherAssignments();

        $this->assertSame(
            StudentTeacherAssignment::class,
            $relation->getRelated()::class
        );

        $this->assertSame(
            'student_id',
            $relation->getForeignKeyName()
        );
    }

    public function test_lesson_sessions_relation_uses_session_student_pivot(): void
    {
        $student = new Student();

        $relation = $student->lessonSessions();

        $this->assertSame(
            'session_students',
            $relation->getTable()
        );

        $this->assertSame(
            'student_id',
            $relation->getForeignPivotKeyName()
        );

        $this->assertSame(
            'session_id',
            $relation->getRelatedPivotKeyName()
        );

        $this->assertSame(
            SessionStudent::class,
            $relation->getPivotClass()
        );

        $this->assertSame(
            [
                'attendance_status',
                'individual_learning_note',
                'recorded_at',
            ],
            $relation->getPivotColumns()
        );
    }
}
