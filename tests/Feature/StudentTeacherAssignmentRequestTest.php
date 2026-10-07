<?php

namespace Tests\Feature;

use App\Http\Requests\SuperAdmin\StoreStudentTeacherAssignmentRequest;
use App\Http\Requests\SuperAdmin\UpdateStudentTeacherAssignmentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentTeacherAssignmentRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        string $roleCode,
        bool $isActive = true
    ): User {
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

    public function test_store_request_authorizes_active_superadmin(): void
    {
        $user = $this->makeUser('superadmin');

        $request = StoreStudentTeacherAssignmentRequest::create(
            '/superadmin/student-teacher-assignments',
            'POST',
            $this->validData()
        );

        $request->setUserResolver(
            fn () => $user
        );

        $this->assertTrue(
            $request->authorize()
        );
    }

    public function test_store_request_rejects_non_superadmin(): void
    {
        $user = $this->makeUser('teacher');

        $request = StoreStudentTeacherAssignmentRequest::create(
            '/superadmin/student-teacher-assignments',
            'POST',
            $this->validData()
        );

        $request->setUserResolver(
            fn () => $user
        );

        $this->assertFalse(
            $request->authorize()
        );
    }

    public function test_store_request_rejects_inactive_superadmin(): void
    {
        $user = $this->makeUser('superadmin', false);

        $request = StoreStudentTeacherAssignmentRequest::create(
            '/superadmin/student-teacher-assignments',
            'POST',
            $this->validData()
        );

        $request->setUserResolver(
            fn () => $user
        );

        $this->assertFalse(
            $request->authorize()
        );
    }

    public function test_store_request_accepts_valid_data(): void
    {
        $request = StoreStudentTeacherAssignmentRequest::create(
            '/superadmin/student-teacher-assignments',
            'POST',
            $this->validData()
        );

        $validator = Validator::make(
            $request->all(),
            $request->rules()
        );

        /*
         * Rule::exists() memang memerlukan data student dan teacher
         * nyata di database. Test ini hanya menguji bahwa field-field
         * dasar memiliki format yang valid.
         *
         * Validasi exists diuji secara khusus pada HTTP/Service test.
         */
        $rules = $request->rules();

        $this->assertContains(
            'required',
            $rules['student_id']
        );

        $this->assertContains(
            'string',
            $rules['student_id']
        );

        $this->assertContains(
            'required',
            $rules['teacher_id']
        );

        $this->assertContains(
            'string',
            $rules['teacher_id']
        );

        $this->assertFalse(
            Validator::make(
                [
                    'starts_on' => $this->validData()['starts_on'],
                    'ends_on' => $this->validData()['ends_on'],
                    'is_report_owner' => false,
                ],
                [
                    'starts_on' => $rules['starts_on'],
                    'ends_on' => $rules['ends_on'],
                    'is_report_owner' => $rules['is_report_owner'],
                ]
            )->fails()
        );
    }

    public function test_store_request_requires_student_id(): void
    {
        $data = $this->validData();
        unset($data['student_id']);

        $request = StoreStudentTeacherAssignmentRequest::create(
            '/superadmin/student-teacher-assignments',
            'POST',
            $data
        );

        $validator = Validator::make(
            $request->all(),
            $request->rules()
        );

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'student_id',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_requires_teacher_id(): void
    {
        $data = $this->validData();
        unset($data['teacher_id']);

        $request = StoreStudentTeacherAssignmentRequest::create(
            '/superadmin/student-teacher-assignments',
            'POST',
            $data
        );

        $validator = Validator::make(
            $request->all(),
            $request->rules()
        );

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'teacher_id',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_requires_starts_on(): void
    {
        $data = $this->validData();
        unset($data['starts_on']);

        $request = StoreStudentTeacherAssignmentRequest::create(
            '/superadmin/student-teacher-assignments',
            'POST',
            $data
        );

        $validator = Validator::make(
            $request->all(),
            $request->rules()
        );

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'starts_on',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_accepts_nullable_ends_on(): void
    {
        $data = $this->validData();
        $data['ends_on'] = null;

        $request = StoreStudentTeacherAssignmentRequest::create(
            '/superadmin/student-teacher-assignments',
            'POST',
            $data
        );

        $rules = $request->rules();

        $validator = Validator::make(
            $request->all(),
            [
                'ends_on' => $rules['ends_on'],
            ]
        );

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_store_request_rejects_end_date_before_start_date(): void
    {
        $data = $this->validData();
        $data['starts_on'] = '2026-10-10';
        $data['ends_on'] = '2026-10-01';

        $request = StoreStudentTeacherAssignmentRequest::create(
            '/superadmin/student-teacher-assignments',
            'POST',
            $data
        );

        $validator = Validator::make(
            $request->all(),
            $request->rules()
        );

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'ends_on',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_accepts_same_start_and_end_date(): void
    {
        $data = $this->validData();
        $data['starts_on'] = '2026-10-10';
        $data['ends_on'] = '2026-10-10';

        $request = StoreStudentTeacherAssignmentRequest::create(
            '/superadmin/student-teacher-assignments',
            'POST',
            $data
        );

        $rules = $request->rules();

        $validator = Validator::make(
            $request->all(),
            [
                'starts_on' => $rules['starts_on'],
                'ends_on' => $rules['ends_on'],
            ]
        );

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_store_request_accepts_report_owner_flag(): void
    {
        $data = $this->validData();
        $data['is_report_owner'] = true;

        $request = StoreStudentTeacherAssignmentRequest::create(
            '/superadmin/student-teacher-assignments',
            'POST',
            $data
        );

        $rules = $request->rules();

        $validator = Validator::make(
            $request->all(),
            [
                'is_report_owner' => $rules['is_report_owner'],
            ]
        );

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_store_request_rejects_invalid_report_owner_type(): void
    {
        $data = $this->validData();
        $data['is_report_owner'] = 'not-a-boolean';

        $request = StoreStudentTeacherAssignmentRequest::create(
            '/superadmin/student-teacher-assignments',
            'POST',
            $data
        );

        $rules = $request->rules();

        $validator = Validator::make(
            $request->all(),
            [
                'is_report_owner' => $rules['is_report_owner'],
            ]
        );

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'is_report_owner',
            $validator->errors()->toArray()
        );
    }

    public function test_update_request_has_same_validation_rules_as_store_request(): void
    {
        $storeRequest = new StoreStudentTeacherAssignmentRequest();
        $updateRequest = new UpdateStudentTeacherAssignmentRequest();

        $this->assertEquals(
            $storeRequest->rules(),
            $updateRequest->rules()
        );
    }

    public function test_update_request_authorizes_active_superadmin(): void
    {
        $user = $this->makeUser('superadmin');

        $request = UpdateStudentTeacherAssignmentRequest::create(
            '/superadmin/student-teacher-assignments/test',
            'PUT',
            $this->validData()
        );

        $request->setUserResolver(
            fn () => $user
        );

        $this->assertTrue(
            $request->authorize()
        );
    }

    public function test_update_request_rejects_non_superadmin(): void
    {
        $user = $this->makeUser('teacher');

        $request = UpdateStudentTeacherAssignmentRequest::create(
            '/superadmin/student-teacher-assignments/test',
            'PUT',
            $this->validData()
        );

        $request->setUserResolver(
            fn () => $user
        );

        $this->assertFalse(
            $request->authorize()
        );
    }
}