<?php

namespace Tests\Feature;

use App\Http\Controllers\StudentChallengeController;
use App\Http\Requests\StudentChallenge\StoreStudentChallengeRequest;
use App\Http\Requests\StudentChallenge\UpdateStudentChallengeRequest;
use App\Models\Branch;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentChallenge;
use App\Models\Teacher;
use App\Models\User;
use App\Services\StudentChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Tests\TestCase;

class StudentChallengeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_student_challenge_index_view(): void
    {
        $controller = app(StudentChallengeController::class);

        $response = $controller->index();

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame(
            'student-challenges.index',
            $response->name()
        );
        $this->assertArrayHasKey(
            'studentChallenges',
            $response->getData()
        );
    }

    public function test_create_returns_student_challenge_create_view(): void
    {
        $controller = app(StudentChallengeController::class);

        $response = $controller->create();

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame(
            'student-challenges.create',
            $response->name()
        );
    }

    public function test_store_uses_service_and_redirects_to_show(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $request = $this->makeStoreRequest([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'category_name' => 'Pronunciation',
            'internal_note' => 'Needs practice.',
            'logged_on' => '2026-10-06',
        ]);

        $controller = app(StudentChallengeController::class);

        $response = $controller->store($request);

        $this->assertInstanceOf(
            RedirectResponse::class,
            $response
        );

        $challenge = StudentChallenge::query()
            ->where('student_id', $student->id)
            ->first();

        $this->assertNotNull($challenge);

        $this->assertSame(
            route(
                'superadmin.student-challenges.show',
                $challenge
            ),
            $response->getTargetUrl()
        );
    }

    public function test_show_returns_student_challenge_show_view(): void
    {
        $challenge = $this->makeStudentChallenge();

        $controller = app(StudentChallengeController::class);

        $response = $controller->show($challenge);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame(
            'student-challenges.show',
            $response->name()
        );

        $this->assertSame(
            $challenge->id,
            $response->getData()['studentChallenge']->id
        );
    }

    public function test_edit_returns_student_challenge_edit_view(): void
    {
        $challenge = $this->makeStudentChallenge();

        $controller = app(StudentChallengeController::class);

        $response = $controller->edit($challenge);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame(
            'student-challenges.edit',
            $response->name()
        );

        $this->assertSame(
            $challenge->id,
            $response->getData()['studentChallenge']->id
        );
    }

    public function test_update_uses_service_and_redirects_to_show(): void
    {
        $challenge = $this->makeStudentChallenge();

        $student = $challenge->student;
        $teacher = $challenge->teacher;

        $request = $this->makeUpdateRequest([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'category_name' => 'Vocabulary',
            'internal_note' => 'Updated note.',
            'logged_on' => '2026-10-07',
        ]);

        $controller = app(StudentChallengeController::class);

        $response = $controller->update(
            $request,
            $challenge
        );

        $this->assertInstanceOf(
            RedirectResponse::class,
            $response
        );

        $this->assertSame(
            route(
                'superadmin.student-challenges.show',
                $challenge
            ),
            $response->getTargetUrl()
        );

        $this->assertDatabaseHas('student_challenges', [
            'id' => $challenge->id,
            'category_name' => 'Vocabulary',
            'internal_note' => 'Updated note.',
        ]);
    }

    public function test_destroy_uses_service_and_redirects_to_index(): void
    {
        $challenge = $this->makeStudentChallenge();

        $controller = app(StudentChallengeController::class);

        $response = $controller->destroy($challenge);

        $this->assertInstanceOf(
            RedirectResponse::class,
            $response
        );

        $this->assertSame(
            route(
                'superadmin.student-challenges.index'
            ),
            $response->getTargetUrl()
        );

        $this->assertDatabaseMissing('student_challenges', [
            'id' => $challenge->id,
        ]);
    }

    private function makeStoreRequest(
        array $data
    ): StoreStudentChallengeRequest {
        return new class($data) extends StoreStudentChallengeRequest {
            public function __construct(
                private array $testValidatedData
            ) {}

            public function validated(
                $key = null,
                $default = null
            ) {
                if ($key === null) {
                    return $this->testValidatedData;
                }

                return $this->testValidatedData[$key] ?? $default;
            }
        };
    }

    private function makeUpdateRequest(
        array $data
    ): UpdateStudentChallengeRequest {
        return new class($data) extends UpdateStudentChallengeRequest {
            public function __construct(
                private array $testValidatedData
            ) {}

            public function validated(
                $key = null,
                $default = null
            ) {
                if ($key === null) {
                    return $this->testValidatedData;
                }

                return $this->testValidatedData[$key] ?? $default;
            }
        };
    }

    private function makeStudentChallenge(): StudentChallenge
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        return StudentChallenge::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'category_name' => 'Pronunciation',
            'internal_note' => 'Initial note.',
            'logged_on' => '2026-10-06',
        ]);
    }

    private function makeStudent(): Student
    {
        $portalUser = $this->makeUser('student');

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Test Student',
            'began_on' => '2026-01-01',
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

    private function makeUser(string $roleCode): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => strtolower($roleCode) . '_' . Str::random(8),
            'email' => strtolower($roleCode)
                . '-'
                . Str::random(8)
                . '@example.com',
            'password_hash' => password_hash(
                'password',
                PASSWORD_BCRYPT
            ),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => true,
        ]);
    }
}
