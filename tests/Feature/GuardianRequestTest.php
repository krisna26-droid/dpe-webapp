<?php

namespace Tests\Feature;

use App\Http\Requests\SuperAdmin\StoreGuardianRequest;
use App\Http\Requests\SuperAdmin\UpdateGuardianRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuardianRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $roleCode = 'teacher', bool $isActive = true): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => $isActive,
        ]);
    }

    private function makeStudent(): Student
    {
        $user = $this->makeUser('student');

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

    private function validData(): array
    {
        return [
            'student_id' => $this->makeStudent()->id,
            'full_name' => 'Test Parent',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081111111111',
            'is_primary' => true,
        ];
    }

    public function test_store_request_authorizes_active_superadmin(): void
    {
        $user = $this->makeUser('superadmin', true);

        $request = StoreGuardianRequest::create(
            '/superadmin/guardians',
            'POST'
        );

        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    public function test_store_request_rejects_non_superadmin(): void
    {
        $user = $this->makeUser('teacher', true);

        $request = StoreGuardianRequest::create(
            '/superadmin/guardians',
            'POST'
        );

        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_store_request_rejects_inactive_superadmin(): void
    {
        $user = $this->makeUser('superadmin', false);

        $request = StoreGuardianRequest::create(
            '/superadmin/guardians',
            'POST'
        );

        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_update_request_authorizes_active_superadmin(): void
    {
        $user = $this->makeUser('superadmin', true);

        $request = UpdateGuardianRequest::create(
            '/superadmin/guardians/123',
            'PUT'
        );

        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    public function test_update_request_rejects_non_superadmin_and_inactive_superadmin(): void
    {
        foreach ([
            ['teacher', true],
            ['superadmin', false],
        ] as [$roleCode, $isActive]) {
            $user = $this->makeUser($roleCode, $isActive);

            $request = UpdateGuardianRequest::create(
                '/superadmin/guardians/123',
                'PUT'
            );

            $request->setUserResolver(fn () => $user);

            $this->assertFalse($request->authorize());
        }
    }

    public function test_store_request_has_expected_rules(): void
    {
        $rules = (new StoreGuardianRequest())->rules();

        $this->assertArrayHasKey('student_id', $rules);
        $this->assertArrayHasKey('full_name', $rules);
        $this->assertArrayHasKey('relationship_name', $rules);
        $this->assertArrayHasKey('whatsapp_number', $rules);
        $this->assertArrayHasKey('is_primary', $rules);

        $this->assertContains('required', $rules['student_id']);
        $this->assertContains('string', $rules['student_id']);
        $this->assertContains('required', $rules['full_name']);
        $this->assertContains('required', $rules['whatsapp_number']);
        $this->assertContains('nullable', $rules['relationship_name']);
        $this->assertContains('boolean', $rules['is_primary']);
    }

    public function test_update_request_has_expected_rules(): void
    {
        $rules = (new UpdateGuardianRequest())->rules();

        $this->assertArrayHasKey('student_id', $rules);
        $this->assertArrayHasKey('full_name', $rules);
        $this->assertArrayHasKey('relationship_name', $rules);
        $this->assertArrayHasKey('whatsapp_number', $rules);
        $this->assertArrayHasKey('is_primary', $rules);
    }

    public function test_store_request_accepts_valid_data(): void
    {
        $student = $this->makeStudent();

        $validator = Validator::make([
            'student_id' => $student->id,
            'full_name' => 'Test Parent',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081111111111',
            'is_primary' => true,
        ], (new StoreGuardianRequest())->rules());

        $this->assertFalse($validator->fails(), $validator->errors()->toJson());
    }

    public function test_store_request_requires_required_fields(): void
    {
        $validator = Validator::make([], (new StoreGuardianRequest())->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('student_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('full_name', $validator->errors()->toArray());
        $this->assertArrayHasKey('whatsapp_number', $validator->errors()->toArray());
    }

    public function test_store_request_rejects_invalid_boolean_flag(): void
    {
        $student = $this->makeStudent();

        $validator = Validator::make([
            'student_id' => $student->id,
            'full_name' => 'Test Parent',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081111111111',
            'is_primary' => 'yes',
        ], (new StoreGuardianRequest())->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('is_primary', $validator->errors()->toArray());
    }

    public function test_update_request_accepts_valid_data(): void
    {
        $student = $this->makeStudent();

        $validator = Validator::make([
            'student_id' => $student->id,
            'full_name' => 'Updated Parent',
            'relationship_name' => 'Mother',
            'whatsapp_number' => '082222222222',
            'is_primary' => false,
        ], (new UpdateGuardianRequest())->rules());

        $this->assertFalse($validator->fails(), $validator->errors()->toJson());
    }
}
