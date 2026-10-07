<?php

namespace Tests\Feature;

use App\Http\Controllers\SuperAdmin\GuardianController;
use App\Http\Requests\SuperAdmin\StoreGuardianRequest;
use App\Http\Requests\SuperAdmin\UpdateGuardianRequest;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\GuardianService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuardianControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('superadmin.guardians.show')) {
            Route::get(
                '/_test/guardians/{guardian}',
                static fn () => null
            )->name('superadmin.guardians.show');
        }

        if (! Route::has('superadmin.guardians.index')) {
            Route::get(
                '/_test/guardians',
                static fn () => null
            )->name('superadmin.guardians.index');
        }

        View::addLocation(
            base_path('tests/Fixtures/views')
        );
    }

    private function makeStudent(): Student
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'student_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test Student',
            'role_code' => 'student',
            'is_active' => true,
        ]);

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $user->id,
            'full_name' => 'Test Student',
            'photo_file_id' => null,
            'school_name' => 'Test School',
            'grade_name' => 'Grade 5',
            'began_on' => '2026-09-01',
            'status' => 'active',
            'special_notes_internal' => null,
        ]);
    }

    private function makeGuardian(?Student $student = null): Guardian
    {
        $student ??= $this->makeStudent();

        return Guardian::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'full_name' => 'Test Parent',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081111111111',
            'is_primary' => true,
        ]);
    }

    private function validData(Student $student): array
    {
        return [
            'student_id' => $student->id,
            'full_name' => 'Test Parent',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081111111111',
            'is_primary' => true,
        ];
    }

    private function makeStoreRequest(array $data): StoreGuardianRequest
    {
        return new class($data) extends StoreGuardianRequest {
            public function __construct(
                private array $testValidatedData
            ) {}

            public function validated($key = null, $default = null)
            {
                if ($key === null) {
                    return $this->testValidatedData;
                }

                return $this->testValidatedData[$key] ?? $default;
            }
        };
    }

    private function makeUpdateRequest(array $data): UpdateGuardianRequest
    {
        return new class($data) extends UpdateGuardianRequest {
            public function __construct(
                private array $testValidatedData
            ) {}

            public function validated($key = null, $default = null)
            {
                if ($key === null) {
                    return $this->testValidatedData;
                }

                return $this->testValidatedData[$key] ?? $default;
            }
        };
    }

    private function mockView(
        string $name,
        callable $assertData
    ): \Illuminate\Contracts\View\View
    {
        $view = $this->createMock(\Illuminate\Contracts\View\View::class);
        $viewFactory = $this->createMock(Factory::class);
        $viewFactory->expects($this->once())
            ->method('make')
            ->with(
                $name,
                $this->callback(function (array $data) use ($assertData): bool {
                    $assertData($data);

                    return true;
                }),
                $this->anything()
            )
            ->willReturn($view);

        $this->app->instance('view', $viewFactory);

        return $view;
    }

    private function mockRedirect(string $routeName, mixed $routeParameter): RedirectResponse
    {
        $response = new RedirectResponse(
            '/test-redirect',
            302,
            []
        );
        $response->setSession($this->app['session.store']);
        $redirector = $this->createMock(Redirector::class);
        $redirector->expects($this->once())
            ->method('route')
            ->with($routeName, $routeParameter)
            ->willReturn($response);

        $this->app->instance('redirect', $redirector);

        return $response;
    }

    public function test_controller_class_exists(): void
    {
        $this->assertTrue(class_exists(GuardianController::class));
    }

    public function test_controller_has_guardian_service_dependency(): void
    {
        $reflection = new \ReflectionClass(GuardianController::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertCount(1, $constructor->getParameters());
        $this->assertSame(
            GuardianService::class,
            $constructor->getParameters()[0]->getType()?->getName()
        );
    }

    public function test_store_method_uses_store_guardian_request(): void
    {
        $method = new \ReflectionMethod(GuardianController::class, 'store');

        $this->assertSame(
            StoreGuardianRequest::class,
            $method->getParameters()[0]->getType()?->getName()
        );
    }

    public function test_update_method_uses_update_request_and_guardian(): void
    {
        $method = new \ReflectionMethod(GuardianController::class, 'update');
        $parameters = $method->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertSame(
            UpdateGuardianRequest::class,
            $parameters[0]->getType()?->getName()
        );
        $this->assertSame(
            Guardian::class,
            $parameters[1]->getType()?->getName()
        );
    }

    public function test_index_returns_paginated_guardians_view(): void
    {
        $guardian = $this->makeGuardian();
        $view = $this->mockView(
            'superadmin.guardians.index',
            function (array $data) use ($guardian): void {
                $this->assertArrayHasKey('guardians', $data);
                $this->assertSame(
                    $guardian->id,
                    $data['guardians']->first()->id
                );
                $this->assertSame(
                    $guardian->student_id,
                    $data['guardians']->first()->student->id
                );
            }
        );
        $controller = new GuardianController(
            $this->createMock(GuardianService::class)
        );

        $response = $controller->index();

        $this->assertSame($view, $response);
    }

    public function test_create_returns_guardian_create_view(): void
    {
        $view = $this->mockView(
            'superadmin.guardians.create',
            fn (array $data) => $this->assertSame([], $data)
        );
        $controller = new GuardianController(
            $this->createMock(GuardianService::class)
        );

        $this->assertSame($view, $controller->create());
    }

    public function test_show_and_edit_load_guardian_student_relation(): void
    {
        $guardian = $this->makeGuardian();
        $showView = $this->mockView(
            'superadmin.guardians.show',
            function (array $data) use ($guardian): void {
                $this->assertSame($guardian, $data['guardian']);
                $this->assertSame(
                    $guardian->student_id,
                    $data['guardian']->student->id
                );
            }
        );
        $controller = new GuardianController(
            $this->createMock(GuardianService::class)
        );

        $showResponse = $controller->show($guardian);

        $editView = $this->mockView(
            'superadmin.guardians.edit',
            function (array $data) use ($guardian): void {
                $this->assertSame($guardian, $data['guardian']);
                $this->assertSame(
                    $guardian->student_id,
                    $data['guardian']->student->id
                );
            }
        );
        $editResponse = $controller->edit($guardian);

        $this->assertSame($showView, $showResponse);
        $this->assertSame($editView, $editResponse);
    }

    public function test_store_calls_service_and_redirects_to_created_guardian(): void
    {
        $student = $this->makeStudent();
        $data = $this->validData($student);
        $guardian = $this->makeGuardian($student);
        $redirectResponse = $this->mockRedirect(
            'superadmin.guardians.show',
            $guardian
        );

        $service = $this->createMock(GuardianService::class);
        $service->expects($this->once())
            ->method('create')
            ->with($data)
            ->willReturn($guardian);

        $controller = new GuardianController($service);
        $response = $controller->store($this->makeStoreRequest($data));

        $this->assertSame($redirectResponse, $response);
        $this->assertSame('/test-redirect', $response->getTargetUrl());
        $this->assertSame('Guardian berhasil dibuat.', session('success'));
    }

    public function test_update_calls_service_and_redirects_to_updated_guardian(): void
    {
        $student = $this->makeStudent();
        $guardian = $this->makeGuardian($student);
        $data = $this->validData($student);
        $data['full_name'] = 'Updated Parent';
        $redirectResponse = $this->mockRedirect(
            'superadmin.guardians.show',
            $guardian
        );

        $service = $this->createMock(GuardianService::class);
        $service->expects($this->once())
            ->method('update')
            ->with($guardian, $data)
            ->willReturn($guardian);

        $controller = new GuardianController($service);
        $response = $controller->update(
            $this->makeUpdateRequest($data),
            $guardian
        );

        $this->assertSame($redirectResponse, $response);
        $this->assertSame('/test-redirect', $response->getTargetUrl());
        $this->assertSame(
            'Guardian berhasil diperbarui.',
            session('success')
        );
    }

    public function test_destroy_calls_service_and_redirects_to_guardian_index(): void
    {
        $guardian = $this->makeGuardian();
        $redirectResponse = $this->mockRedirect(
            'superadmin.guardians.index',
            []
        );

        $service = $this->createMock(GuardianService::class);
        $service->expects($this->once())
            ->method('delete')
            ->with($guardian);

        $controller = new GuardianController($service);
        $response = $controller->destroy($guardian);

        $this->assertSame($redirectResponse, $response);
        $this->assertSame('/test-redirect', $response->getTargetUrl());
        $this->assertSame(
            'Guardian berhasil dihapus.',
            session('success')
        );
    }
}
