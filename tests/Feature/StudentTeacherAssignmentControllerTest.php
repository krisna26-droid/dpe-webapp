<?php

namespace Tests\Feature;

use App\Http\Controllers\SuperAdmin\StudentTeacherAssignmentController;
use App\Http\Requests\SuperAdmin\StoreStudentTeacherAssignmentRequest;
use App\Http\Requests\SuperAdmin\UpdateStudentTeacherAssignmentRequest;
use App\Models\StudentTeacherAssignment;
use App\Services\StudentTeacherAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentTeacherAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function validData(): array
    {
        return [
            'student_id' => (string) Str::uuid(),
            'teacher_id' => (string) Str::uuid(),
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-31',
            'is_report_owner' => false,
        ];
    }

    private function makeAssignment(): StudentTeacherAssignment
    {
        return new StudentTeacherAssignment([
            'id' => (string) Str::uuid(),
            'student_id' => (string) Str::uuid(),
            'teacher_id' => (string) Str::uuid(),
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-31',
            'is_report_owner' => false,
        ]);
    }

    private function makeStoreRequest(
        array $data
    ): StoreStudentTeacherAssignmentRequest {
        return new class($data) extends StoreStudentTeacherAssignmentRequest {
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
    ): UpdateStudentTeacherAssignmentRequest {
        return new class($data) extends UpdateStudentTeacherAssignmentRequest {
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

    public function test_controller_class_exists(): void
    {
        $this->assertTrue(
            class_exists(
                StudentTeacherAssignmentController::class
            )
        );
    }

    public function test_controller_has_service_dependency(): void
    {
        $reflection = new \ReflectionClass(
            StudentTeacherAssignmentController::class
        );

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);

        $parameters = $constructor->getParameters();

        $this->assertCount(1, $parameters);

        $this->assertSame(
            StudentTeacherAssignmentService::class,
            $parameters[0]->getType()?->getName()
        );
    }

    public function test_store_method_uses_store_request(): void
    {
        $reflection = new \ReflectionClass(
            StudentTeacherAssignmentController::class
        );

        $method = $reflection->getMethod('store');

        $parameters = $method->getParameters();

        $this->assertCount(1, $parameters);

        $this->assertSame(
            StoreStudentTeacherAssignmentRequest::class,
            $parameters[0]->getType()?->getName()
        );
    }

    public function test_update_method_uses_update_request_and_assignment(): void
    {
        $reflection = new \ReflectionClass(
            StudentTeacherAssignmentController::class
        );

        $method = $reflection->getMethod('update');

        $parameters = $method->getParameters();

        $this->assertCount(2, $parameters);

        $this->assertSame(
            UpdateStudentTeacherAssignmentRequest::class,
            $parameters[0]->getType()?->getName()
        );

        $this->assertSame(
            StudentTeacherAssignment::class,
            $parameters[1]->getType()?->getName()
        );
    }

    public function test_store_calls_service(): void
    {
        $data = $this->validData();

        $assignment = $this->makeAssignment();

        $request = $this->makeStoreRequest($data);

        $service = $this->mock(
            StudentTeacherAssignmentService::class
        );

        $service
            ->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($assignment);

        $controller = app(
            StudentTeacherAssignmentController::class
        );

        $response = $controller->store($request);

        $this->assertSame(
            route(
                'superadmin.student-teacher-assignments.show',
                $assignment
            ),
            $response->getTargetUrl()
        );

        $this->assertSame(
            'Student teacher assignment berhasil dibuat.',
            session('success')
        );
    }

    public function test_update_calls_service(): void
    {
        $data = $this->validData();

        $assignment = $this->makeAssignment();

        $request = $this->makeUpdateRequest($data);

        $service = $this->mock(
            StudentTeacherAssignmentService::class
        );

        $service
            ->shouldReceive('update')
            ->once()
            ->with(
                $assignment,
                $data
            )
            ->andReturn($assignment);

        $controller = app(
            StudentTeacherAssignmentController::class
        );

        $response = $controller->update(
            $request,
            $assignment
        );

        $this->assertSame(
            route(
                'superadmin.student-teacher-assignments.show',
                $assignment
            ),
            $response->getTargetUrl()
        );

        $this->assertSame(
            'Student teacher assignment berhasil diperbarui.',
            session('success')
        );
    }
}
