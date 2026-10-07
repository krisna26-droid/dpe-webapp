<?php

namespace Tests\Feature;

use App\Http\Requests\SuperAdmin\StoreTeacherAvailabilityRequest;
use App\Http\Requests\SuperAdmin\UpdateTeacherAvailabilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherAvailabilityRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_request_authorizes_active_superadmin(): void
    {
        $user = $this->createUser('superadmin', true);

        $request = StoreTeacherAvailabilityRequest::create(
            '/superadmin/teacher-availability',
            'POST'
        );

        $request->setUserResolver(
            fn() => $user
        );

        $this->assertTrue($request->authorize());
    }

    public function test_store_request_rejects_inactive_superadmin(): void
    {
        $user = $this->createUser('superadmin', false);

        $request = StoreTeacherAvailabilityRequest::create(
            '/superadmin/teacher-availability',
            'POST'
        );

        $request->setUserResolver(
            fn() => $user
        );

        $this->assertFalse($request->authorize());
    }

    public function test_store_request_rejects_non_superadmin(): void
    {
        $user = $this->createUser('teacher', true);

        $request = StoreTeacherAvailabilityRequest::create(
            '/superadmin/teacher-availability',
            'POST'
        );

        $request->setUserResolver(
            fn() => $user
        );

        $this->assertFalse($request->authorize());
    }

    public function test_store_request_has_expected_rules(): void
    {
        $request = new StoreTeacherAvailabilityRequest();

        $rules = $request->rules();

        $this->assertArrayHasKey('teacher_id', $rules);
        $this->assertArrayHasKey('branch_id', $rules);
        $this->assertArrayHasKey('available_on', $rules);
        $this->assertArrayHasKey('starts_at', $rules);
        $this->assertArrayHasKey('ends_at', $rules);
        $this->assertArrayHasKey('class_type', $rules);
        $this->assertArrayHasKey('availability_status', $rules);
        $this->assertArrayHasKey('notes', $rules);

        $this->assertContains(
            'required',
            $rules['teacher_id']
        );

        $this->assertContains(
            'required',
            $rules['branch_id']
        );

        $this->assertContains(
            'required',
            $rules['available_on']
        );

        $this->assertContains(
            'required',
            $rules['starts_at']
        );

        $this->assertContains(
            'required',
            $rules['ends_at']
        );

        $this->assertContains(
            'required',
            $rules['class_type']
        );

        $this->assertContains(
            'required',
            $rules['availability_status']
        );

        $this->assertContains(
            'nullable',
            $rules['notes']
        );
    }

    public function test_store_request_accepts_valid_data(): void
    {
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $request = new StoreTeacherAvailabilityRequest();

        $validator = Validator::make(
            [
                'teacher_id' => $teacher->id,
                'branch_id' => $branch->id,
                'available_on' => '2026-10-10',
                'starts_at' => '09:00:00',
                'ends_at' => '12:00:00',
                'class_type' => 'regular',
                'availability_status' => 'available',
                'notes' => null,
            ],
            $request->rules()
        );

        $this->assertTrue(
            $validator->passes(),
            $validator->errors()->toJson()
        );
    }

    public function test_store_request_rejects_end_time_before_start_time(): void
    {
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $validator = Validator::make(
            [
                'teacher_id' => $teacher->id,
                'branch_id' => $branch->id,
                'available_on' => '2026-10-10',
                'starts_at' => '12:00:00',
                'ends_at' => '09:00:00',
                'class_type' => 'regular',
                'availability_status' => 'available',
                'notes' => null,
            ],
            (new StoreTeacherAvailabilityRequest())->rules()
        );

        $this->assertFalse(
            $validator->passes()
        );

        $this->assertArrayHasKey(
            'ends_at',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_rejects_invalid_time_format(): void
    {
        $request = new StoreTeacherAvailabilityRequest();

        $validator = Validator::make(
            [
                'starts_at' => '09:00',
                'ends_at' => '12:00',
            ],
            [
                'starts_at' => [
                    'required',
                    'date_format:H:i:s',
                ],
                'ends_at' => [
                    'required',
                    'date_format:H:i:s',
                ],
            ]
        );

        $this->assertFalse(
            $validator->passes()
        );
    }

    public function test_update_request_authorizes_active_superadmin(): void
    {
        $user = $this->createUser('superadmin', true);

        $request = UpdateTeacherAvailabilityRequest::create(
            '/superadmin/teacher-availability/test-id',
            'PUT'
        );

        $request->setUserResolver(
            fn() => $user
        );

        $this->assertTrue($request->authorize());
    }

    public function test_update_request_rejects_inactive_superadmin(): void
    {
        $user = $this->createUser('superadmin', false);

        $request = UpdateTeacherAvailabilityRequest::create(
            '/superadmin/teacher-availability/test-id',
            'PUT'
        );

        $request->setUserResolver(
            fn() => $user
        );

        $this->assertFalse($request->authorize());
    }

    public function test_update_request_has_expected_rules(): void
    {
        $request = new UpdateTeacherAvailabilityRequest();

        $rules = $request->rules();

        $this->assertArrayHasKey('teacher_id', $rules);
        $this->assertArrayHasKey('branch_id', $rules);
        $this->assertArrayHasKey('available_on', $rules);
        $this->assertArrayHasKey('starts_at', $rules);
        $this->assertArrayHasKey('ends_at', $rules);
        $this->assertArrayHasKey('class_type', $rules);
        $this->assertArrayHasKey('availability_status', $rules);
        $this->assertArrayHasKey('notes', $rules);

        $this->assertContains(
            'required',
            $rules['teacher_id']
        );

        $this->assertContains(
            'required',
            $rules['branch_id']
        );

        $this->assertContains(
            'required',
            $rules['available_on']
        );

        $this->assertContains(
            'required',
            $rules['starts_at']
        );

        $this->assertContains(
            'required',
            $rules['ends_at']
        );

        $this->assertContains(
            'required',
            $rules['class_type']
        );

        $this->assertContains(
            'required',
            $rules['availability_status']
        );

        $this->assertContains(
            'nullable',
            $rules['notes']
        );
    }

    public function test_update_request_rejects_end_time_before_start_time(): void
    {
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $validator = Validator::make(
            [
                'teacher_id' => $teacher->id,
                'branch_id' => $branch->id,
                'available_on' => '2026-10-10',
                'starts_at' => '16:00:00',
                'ends_at' => '14:00:00',
                'class_type' => 'regular',
                'availability_status' => 'available',
                'notes' => null,
            ],
            (new UpdateTeacherAvailabilityRequest())->rules()
        );

        $this->assertFalse(
            $validator->passes()
        );

        $this->assertArrayHasKey(
            'ends_at',
            $validator->errors()->toArray()
        );
    }

    private function createUser(
        string $roleCode,
        bool $isActive
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(
                Str::random(8)
            ),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => $isActive,
        ]);
    }

    private function createTeacher(): \App\Models\Teacher
    {
        $user = $this->createUser('teacher', true);

        return \App\Models\Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'full_name' => 'Test Teacher',
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ]);
    }

    private function createBranch(): \App\Models\Branch
    {
        return \App\Models\Branch::query()->create([
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
