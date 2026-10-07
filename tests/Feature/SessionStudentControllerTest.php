<?php

namespace Tests\Feature;

use App\Http\Requests\SessionStudentStoreRequest;
use App\Models\Branch;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\SessionStudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionStudentControllerTest extends TestCase
{
    use RefreshDatabase;

    private SessionStudentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SessionStudentService::class);
    }

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

    private function makeEvent(): SessionStudent
    {
        $session = $this->makeLessonSession();
        $student = $this->makeStudent();

        return $this->service->create([
            'session_id' => $session->id,
            'student_id' => $student->id,
            'attendance_status' => 'present',
            'individual_learning_note' => 'Good progress.',
            'recorded_at' => now(),
        ]);
    }

    private function makeRequest(
        array $data,
        string $method = 'POST'
    ): SessionStudentStoreRequest {
        $request = SessionStudentStoreRequest::create(
            '/session-students',
            $method,
            $data
        );

        $request->setContainer(app());
        $request->merge($data);

        $validator = \Illuminate\Support\Facades\Validator::make(
            $data,
            $request->rules()
        );

        $request->setValidator($validator);

        return $request;
    }

    public function test_store_returns_created_session_student(): void
    {
        $controller = app(\App\Http\Controllers\SessionStudentController::class);

        $session = $this->makeLessonSession();
        $student = $this->makeStudent();

        $request = $this->makeRequest([
            'session_id' => $session->id,
            'student_id' => $student->id,
            'attendance_status' => 'present',
            'individual_learning_note' => 'Good progress.',
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $response = $controller->store($request);

        $this->assertSame(201, $response->getStatusCode());

        $data = $response->getData(true);

        $this->assertSame(
            $session->id,
            $data['session_id']
        );

        $this->assertSame(
            $student->id,
            $data['student_id']
        );

        $this->assertSame(
            'present',
            $data['attendance_status']
        );
    }

    public function test_show_returns_session_student(): void
    {
        $controller = app(\App\Http\Controllers\SessionStudentController::class);

        $sessionStudent = $this->makeEvent();

        $response = $controller->show(
            $sessionStudent->session_id,
            $sessionStudent->student_id
        );

        $this->assertSame(200, $response->getStatusCode());

        $data = $response->getData(true);

        $this->assertSame(
            $sessionStudent->session_id,
            $data['session_id']
        );

        $this->assertSame(
            $sessionStudent->student_id,
            $data['student_id']
        );
    }

    public function test_by_session_returns_session_students(): void
    {
        $controller = app(\App\Http\Controllers\SessionStudentController::class);

        $session = $this->makeLessonSession();
        $studentOne = $this->makeStudent();
        $studentTwo = $this->makeStudent();

        $this->service->create([
            'session_id' => $session->id,
            'student_id' => $studentOne->id,
            'attendance_status' => 'present',
            'individual_learning_note' => null,
            'recorded_at' => now(),
        ]);

        $this->service->create([
            'session_id' => $session->id,
            'student_id' => $studentTwo->id,
            'attendance_status' => 'absent',
            'individual_learning_note' => null,
            'recorded_at' => now(),
        ]);

        $response = $controller->bySession($session->id);

        $this->assertSame(200, $response->getStatusCode());

        $data = $response->getData(true);

        $this->assertCount(2, $data);
    }

    public function test_by_student_returns_session_students(): void
    {
        $controller = app(\App\Http\Controllers\SessionStudentController::class);

        $sessionOne = $this->makeLessonSession();
        $sessionTwo = $this->makeLessonSession();
        $student = $this->makeStudent();

        $this->service->create([
            'session_id' => $sessionOne->id,
            'student_id' => $student->id,
            'attendance_status' => 'present',
            'individual_learning_note' => null,
            'recorded_at' => now(),
        ]);

        $this->service->create([
            'session_id' => $sessionTwo->id,
            'student_id' => $student->id,
            'attendance_status' => 'present',
            'individual_learning_note' => null,
            'recorded_at' => now(),
        ]);

        $response = $controller->byStudent($student->id);

        $this->assertSame(200, $response->getStatusCode());

        $data = $response->getData(true);

        $this->assertCount(2, $data);
    }

    public function test_update_returns_updated_session_student(): void
    {
        $controller = app(\App\Http\Controllers\SessionStudentController::class);

        $sessionStudent = $this->makeEvent();

        $request = $this->makeRequest([
            'session_id' => $sessionStudent->session_id,
            'student_id' => $sessionStudent->student_id,
            'attendance_status' => 'absent',
            'individual_learning_note' => 'Updated note.',
            'recorded_at' => now()->toDateTimeString(),
        ], 'PUT');

        $response = $controller->update(
            $request,
            $sessionStudent->session_id,
            $sessionStudent->student_id
        );

        $this->assertSame(200, $response->getStatusCode());

        $data = $response->getData(true);

        $this->assertSame(
            'absent',
            $data['attendance_status']
        );

        $this->assertSame(
            'Updated note.',
            $data['individual_learning_note']
        );
    }

    public function test_destroy_deletes_session_student(): void
    {
        $controller = app(\App\Http\Controllers\SessionStudentController::class);

        $sessionStudent = $this->makeEvent();

        $response = $controller->destroy(
            $sessionStudent->session_id,
            $sessionStudent->student_id
        );

        $this->assertSame(200, $response->getStatusCode());

        $data = $response->getData(true);

        $this->assertSame(
            'Session student deleted successfully.',
            $data['message']
        );

        $this->assertDatabaseMissing('session_students', [
            'session_id' => $sessionStudent->session_id,
            'student_id' => $sessionStudent->student_id,
        ]);
    }

    public function test_show_throws_when_session_student_does_not_exist(): void
    {
        $controller = app(\App\Http\Controllers\SessionStudentController::class);

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $controller->show(
            (string) Str::uuid(),
            (string) Str::uuid()
        );
    }
}
