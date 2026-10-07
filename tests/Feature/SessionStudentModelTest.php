<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionStudentModelTest extends TestCase
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

    private function makeStudent(): Student
    {
        $portalUser = $this->makeUser('student');

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Test Student',
            'photo_file_id' => null,
            'school_name' => null,
            'grade_name' => null,
            'began_on' => now()->subMonth()->toDateString(),
            'status' => 'active',
            'special_notes_internal' => null,
        ]);
    }

    private function makeTeacher(): Teacher
    {
        $teacherUser = $this->makeUser('teacher');

        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $teacherUser->id,
            'whatsapp_number' => '081234567890',
            'photo_file_id' => null,
            'is_active' => true,
        ]);
    }

    private function makeBranch(): Branch
    {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . Str::upper(Str::random(6)),
            'name' => 'Test Branch',
            'address' => null,
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => true,
        ]);
    }

    private function makeProgram(): Program
    {
        return Program::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PRG-' . Str::upper(Str::random(6)),
            'name' => 'Test Program',
            'class_type' => 'private',
            'monthly_video_target_override' => null,
            'is_active' => true,
        ]);
    }

    private function makeLessonSession(): LessonSession
    {
        $branch = $this->makeBranch();
        $teacher = $this->makeTeacher();
        $program = $this->makeProgram();

        $session = new LessonSession();

        $session->forceFill([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => now()->startOfHour(),
            'planned_end_at' => now()->startOfHour()->addHour(),
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'planned',
            'rescheduled_from_session_id' => null,
            'topic' => 'Test Topic',
            'material' => 'Test Material',
            'activity' => 'Test Activity',
        ]);

        $session->save();

        return $session;
    }

    private function makeSessionStudent(): SessionStudent
    {
        $session = $this->makeLessonSession();
        $student = $this->makeStudent();

        return SessionStudent::query()->create([
            'session_id' => $session->id,
            'student_id' => $student->id,
            'attendance_status' => 'present',
            'individual_learning_note' => 'Good progress.',
            'recorded_at' => now(),
        ]);
    }

    public function test_uses_correct_table(): void
    {
        $model = new SessionStudent();

        $this->assertSame(
            'session_students',
            $model->getTable()
        );
    }

    public function test_does_not_use_auto_increment(): void
    {
        $model = new SessionStudent();

        $this->assertFalse($model->incrementing);
    }

    public function test_does_not_use_timestamps(): void
    {
        $model = new SessionStudent();

        $this->assertFalse($model->usesTimestamps());
    }

    public function test_has_expected_fillable_columns(): void
    {
        $model = new SessionStudent();

        $this->assertSame([
            'session_id',
            'student_id',
            'attendance_status',
            'individual_learning_note',
            'recorded_at',
        ], $model->getFillable());
    }

    public function test_casts_recorded_at_to_datetime(): void
    {
        $sessionStudent = $this->makeSessionStudent();

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $sessionStudent->recorded_at
        );
    }

    public function test_can_create_session_student(): void
    {
        $sessionStudent = $this->makeSessionStudent();

        $this->assertDatabaseHas('session_students', [
            'session_id' => $sessionStudent->session_id,
            'student_id' => $sessionStudent->student_id,
            'attendance_status' => 'present',
            'individual_learning_note' => 'Good progress.',
        ]);
    }

    public function test_session_relation_returns_lesson_session(): void
    {
        $sessionStudent = $this->makeSessionStudent();

        $this->assertInstanceOf(
            LessonSession::class,
            $sessionStudent->session
        );

        $this->assertSame(
            $sessionStudent->session_id,
            $sessionStudent->session->id
        );
    }

    public function test_student_relation_returns_student(): void
    {
        $sessionStudent = $this->makeSessionStudent();

        $this->assertInstanceOf(
            Student::class,
            $sessionStudent->student
        );

        $this->assertSame(
            $sessionStudent->student_id,
            $sessionStudent->student->id
        );
    }

    public function test_lesson_session_students_relation_uses_session_student_pivot(): void
    {
        $sessionStudent = $this->makeSessionStudent();

        $student = $sessionStudent->session->students->first();

        $this->assertNotNull($student);
        $this->assertSame(
            $sessionStudent->student_id,
            $student->id
        );

        $this->assertSame(
            'present',
            $student->pivot->attendance_status
        );

        $this->assertSame(
            'Good progress.',
            $student->pivot->individual_learning_note
        );
    }

    public function test_student_lesson_sessions_relation_uses_session_student_pivot(): void
    {
        $sessionStudent = $this->makeSessionStudent();

        $session = $sessionStudent->student->lessonSessions->first();

        $this->assertNotNull($session);
        $this->assertSame(
            $sessionStudent->session_id,
            $session->id
        );

        $this->assertSame(
            'present',
            $session->pivot->attendance_status
        );

        $this->assertSame(
            'Good progress.',
            $session->pivot->individual_learning_note
        );
    }

    public function test_composite_key_prevents_duplicate_session_student_pair(): void
    {
        $sessionStudent = $this->makeSessionStudent();

        $this->expectException(\Illuminate\Database\QueryException::class);

        SessionStudent::query()->create([
            'session_id' => $sessionStudent->session_id,
            'student_id' => $sessionStudent->student_id,
            'attendance_status' => 'absent',
            'individual_learning_note' => null,
            'recorded_at' => now(),
        ]);
    }
}
