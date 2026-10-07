<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\ReportShareAttemptController;
use App\Http\Requests\ReportShareAttemptStoreRequest;
use App\Models\FileAsset;
use App\Models\Guardian;
use App\Models\QuarterlyReportFile;
use App\Models\ReportCycle;
use App\Models\ReportShareAttempt;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReportShareAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ReportShareAttemptControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_store_returns_created_response(): void
    {
        $service = Mockery::mock(ReportShareAttemptService::class);

        $this->app->instance(
            ReportShareAttemptService::class,
            $service
        );

        $shareAttempt = new ReportShareAttempt([
            'id' => (string) Str::uuid(),
            'quarterly_report_file_id' => (string) Str::uuid(),
            'teacher_id' => (string) Str::uuid(),
            'guardian_id' => (string) Str::uuid(),
            'recipient_phone_snapshot' => '081234567890',
            'status' => 'pending',
            'opened_at' => Carbon::parse('2026-10-06 10:00:00'),
            'confirmed_at' => null,
            'teacher_note' => null,
        ]);

        $request = Mockery::mock(
            ReportShareAttemptStoreRequest::class
        );

        $request->shouldReceive('validated')
            ->once()
            ->andReturn([
                'quarterly_report_file_id' =>
                $shareAttempt->quarterly_report_file_id,
                'teacher_id' =>
                $shareAttempt->teacher_id,
                'guardian_id' =>
                $shareAttempt->guardian_id,
                'recipient_phone_snapshot' =>
                $shareAttempt->recipient_phone_snapshot,
                'status' =>
                $shareAttempt->status,
                'opened_at' =>
                '2026-10-06 10:00:00',
                'confirmed_at' => null,
                'teacher_note' => null,
            ]);

        $service->shouldReceive('create')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn($shareAttempt);

        $controller = app(
            ReportShareAttemptController::class
        );

        $response = $controller->store($request);

        $this->assertInstanceOf(
            JsonResponse::class,
            $response
        );

        $this->assertSame(
            201,
            $response->getStatusCode()
        );

        $this->assertSame(
            $shareAttempt->id,
            $response->getData(true)['id']
        );
    }

    public function test_show_returns_share_attempt(): void
    {
        $service = Mockery::mock(ReportShareAttemptService::class);

        $this->app->instance(
            ReportShareAttemptService::class,
            $service
        );

        $shareAttempt = new ReportShareAttempt([
            'id' => (string) Str::uuid(),
            'quarterly_report_file_id' => (string) Str::uuid(),
            'teacher_id' => (string) Str::uuid(),
            'guardian_id' => (string) Str::uuid(),
            'recipient_phone_snapshot' => '081234567890',
            'status' => 'opened',
            'opened_at' => Carbon::parse('2026-10-06 10:00:00'),
            'confirmed_at' => null,
            'teacher_note' => null,
        ]);

        $service->shouldReceive('findById')
            ->once()
            ->with($shareAttempt->id)
            ->andReturn($shareAttempt);

        $controller = app(
            ReportShareAttemptController::class
        );

        $response = $controller->show(
            $shareAttempt->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            $shareAttempt->id,
            $response->getData(true)['id']
        );
    }

    public function test_by_quarterly_report_file_returns_share_attempts(): void
    {
        $service = Mockery::mock(ReportShareAttemptService::class);

        $this->app->instance(
            ReportShareAttemptService::class,
            $service
        );

        $quarterlyReportFile = $this->createQuarterlyReportFile();

        $shareAttempts = collect([
            new ReportShareAttempt([
                'id' => (string) Str::uuid(),
                'quarterly_report_file_id' =>
                $quarterlyReportFile->id,
                'teacher_id' => (string) Str::uuid(),
                'guardian_id' => (string) Str::uuid(),
                'recipient_phone_snapshot' => '081234567890',
                'status' => 'pending',
                'opened_at' =>
                Carbon::parse('2026-10-06 10:00:00'),
            ]),
        ]);

        $service->shouldReceive('getByQuarterlyReportFile')
            ->once()
            ->with(Mockery::on(
                fn($model) =>
                $model instanceof QuarterlyReportFile
                    && $model->id === $quarterlyReportFile->id
            ))
            ->andReturn($shareAttempts);

        $controller = app(
            ReportShareAttemptController::class
        );

        $response = $controller->byQuarterlyReportFile(
            $quarterlyReportFile->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertCount(
            1,
            $response->getData(true)
        );
    }

    public function test_by_teacher_returns_share_attempts(): void
    {
        $service = Mockery::mock(ReportShareAttemptService::class);

        $this->app->instance(
            ReportShareAttemptService::class,
            $service
        );

        $teacher = $this->createTeacher();

        $shareAttempts = collect([
            new ReportShareAttempt([
                'id' => (string) Str::uuid(),
                'quarterly_report_file_id' =>
                (string) Str::uuid(),
                'teacher_id' => $teacher->id,
                'guardian_id' => (string) Str::uuid(),
                'recipient_phone_snapshot' => '081234567890',
                'status' => 'pending',
                'opened_at' =>
                Carbon::parse('2026-10-06 10:00:00'),
            ]),
        ]);

        $service->shouldReceive('getByTeacher')
            ->once()
            ->with(Mockery::on(
                fn($model) =>
                $model instanceof Teacher
                    && $model->id === $teacher->id
            ))
            ->andReturn($shareAttempts);

        $controller = app(
            ReportShareAttemptController::class
        );

        $response = $controller->byTeacher(
            $teacher->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertCount(
            1,
            $response->getData(true)
        );
    }

    public function test_by_guardian_returns_share_attempts(): void
    {
        $service = Mockery::mock(ReportShareAttemptService::class);

        $this->app->instance(
            ReportShareAttemptService::class,
            $service
        );

        $guardian = $this->createGuardian();

        $shareAttempts = collect([
            new ReportShareAttempt([
                'id' => (string) Str::uuid(),
                'quarterly_report_file_id' =>
                (string) Str::uuid(),
                'teacher_id' => (string) Str::uuid(),
                'guardian_id' => $guardian->id,
                'recipient_phone_snapshot' => '081234567890',
                'status' => 'pending',
                'opened_at' =>
                Carbon::parse('2026-10-06 10:00:00'),
            ]),
        ]);

        $service->shouldReceive('getByGuardian')
            ->once()
            ->with(Mockery::on(
                fn($model) =>
                $model instanceof Guardian
                    && $model->id === $guardian->id
            ))
            ->andReturn($shareAttempts);

        $controller = app(
            ReportShareAttemptController::class
        );

        $response = $controller->byGuardian(
            $guardian->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertCount(
            1,
            $response->getData(true)
        );
    }

    private function createQuarterlyReportFile(): QuarterlyReportFile
    {
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

        $cycle = ReportCycle::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'cycle_number' => 1,
            'start_month' => '2026-01-01',
            'end_month' => '2026-03-01',
        ]);

        $file = FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => 'quarterly-reports/' . Str::uuid() . '.pdf',
            'original_name' => 'quarterly-report.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
        ]);

        $generator = $this->createUser('superadmin');

        return QuarterlyReportFile::query()->create([
            'id' => (string) Str::uuid(),
            'cycle_id' => $cycle->id,
            'file_id' => $file->id,
            'version_number' => 1,
            'generated_by_user_id' => $generator->id,
        ]);
    }

    private function createTeacher(): Teacher
    {
        $user = $this->createUser('teacher');

        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ]);
    }

    private function createGuardian(): Guardian
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
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'full_name' => 'Test Guardian',
            'relationship_name' => 'Parent',
            'whatsapp_number' => '081234567891',
            'is_primary' => true,
        ]);
    }

    private function createUser(string $roleCode): User
    {
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
}
