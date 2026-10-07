<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Controllers\SuperAdmin\TeacherAvailabilityController;
use App\Http\Requests\SuperAdmin\StoreTeacherAvailabilityRequest;
use App\Http\Requests\SuperAdmin\UpdateTeacherAvailabilityRequest;
use App\Models\Branch;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\User;
use App\Services\TeacherAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherAvailabilityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_controller_class_exists(): void
    {
        $this->assertTrue(
            class_exists(TeacherAvailabilityController::class)
        );
    }

    public function test_controller_has_service_dependency(): void
    {
        $controller = app(
            TeacherAvailabilityController::class
        );

        $reflection = new \ReflectionClass($controller);

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);

        $parameters = $constructor->getParameters();

        $this->assertCount(1, $parameters);

        $this->assertSame(
            TeacherAvailabilityService::class,
            $parameters[0]->getType()?->getName()
        );
    }

    public function test_store_method_uses_store_request(): void
    {
        $reflection = new \ReflectionMethod(
            TeacherAvailabilityController::class,
            'store'
        );

        $parameters = $reflection->getParameters();

        $this->assertCount(1, $parameters);

        $this->assertSame(
            StoreTeacherAvailabilityRequest::class,
            $parameters[0]->getType()?->getName()
        );
    }

    public function test_update_method_uses_update_request(): void
    {
        $reflection = new \ReflectionMethod(
            TeacherAvailabilityController::class,
            'update'
        );

        $parameters = $reflection->getParameters();

        $this->assertCount(2, $parameters);

        $this->assertSame(
            UpdateTeacherAvailabilityRequest::class,
            $parameters[0]->getType()?->getName()
        );

        $this->assertSame(
            TeacherAvailability::class,
            $parameters[1]->getType()?->getName()
        );
    }

    public function test_store_calls_service(): void
    {
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $availability = TeacherAvailability::query()->create([
            'id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-20',
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'class_type' => 'regular',
            'availability_status' => 'available',
            'notes' => null,
        ]);

        $request = $this->makeStoreRequest([
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-20',
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'class_type' => 'regular',
            'availability_status' => 'available',
            'notes' => null,
        ]);

        $service = $this->createMock(
            TeacherAvailabilityService::class
        );

        $service
            ->expects($this->once())
            ->method('create')
            ->with($request->validated())
            ->willReturn($availability);

        $controller = new TeacherAvailabilityController(
            $service
        );

        $response = $controller->store($request);

        $this->assertSame(
            302,
            $response->getStatusCode()
        );

        $this->assertSame(
            route(
                'superadmin.teacher-availability.show',
                $availability
            ),
            $response->getTargetUrl()
        );
    }

    public function test_update_calls_service(): void
    {
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $availability = TeacherAvailability::query()->create([
            'id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-21',
            'starts_at' => '10:00:00',
            'ends_at' => '13:00:00',
            'class_type' => 'private',
            'availability_status' => 'available',
            'notes' => 'Original note',
        ]);

        $request = $this->makeUpdateRequest([
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-22',
            'starts_at' => '14:00:00',
            'ends_at' => '17:00:00',
            'class_type' => 'private',
            'availability_status' => 'booked',
            'notes' => 'Updated note',
        ]);

        $service = $this->createMock(
            TeacherAvailabilityService::class
        );

        $service
            ->expects($this->once())
            ->method('update')
            ->with(
                $availability,
                $request->validated()
            )
            ->willReturn($availability);

        $controller = new TeacherAvailabilityController(
            $service
        );

        $response = $controller->update(
            $request,
            $availability
        );

        $this->assertSame(
            302,
            $response->getStatusCode()
        );

        $this->assertSame(
            route(
                'superadmin.teacher-availability.show',
                $availability
            ),
            $response->getTargetUrl()
        );
    }

    private function makeStoreRequest(
        array $data
    ): StoreTeacherAvailabilityRequest {
        return new class($data)
        extends StoreTeacherAvailabilityRequest
        {
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

                return $this->testValidatedData[$key]
                    ?? $default;
            }
        };
    }

    private function makeUpdateRequest(
        array $data
    ): UpdateTeacherAvailabilityRequest {
        return new class($data)
        extends UpdateTeacherAvailabilityRequest
        {
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

                return $this->testValidatedData[$key]
                    ?? $default;
            }
        };
    }

    private function createTeacher(): Teacher
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'teacher-' . Str::random(8),
            'email' => Str::uuid() . '@example.com',
            'full_name' => 'Teacher Test',
            'password_hash' => bcrypt('password'),
            'role_code' => 'teacher',
            'is_active' => true,
        ]);

        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'full_name' => 'Test Teacher',
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ]);
    }

    private function createBranch(): Branch
    {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . strtoupper(Str::random(6)),
            'name' => 'Test Branch',
            'address' => null,
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 1,
            'is_active' => true,
        ]);
    }
}
