<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StudentRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        string $roleCode = 'student',
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

    private function makeStudent(User $portalUser): Student
    {
        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Existing Student',
            'school_name' => 'Existing School',
            'grade_name' => 'Grade 5',
            'began_on' => '2026-01-01',
            'status' => 'active',
            'special_notes_internal' => null,
            'photo_file_id' => null,
        ]);
    }

    private function validateStore(array $data, ?User $user = null): \Illuminate\Contracts\Validation\Validator
    {
        $request = StoreStudentRequest::create(
            '/admin/students',
            'POST',
            $data
        );

        $request->setUserResolver(
            fn() => $user ?? $this->makeUser('admin')
        );

        return Validator::make(
            $request->all(),
            $request->rules()
        );
    }

    public function test_store_request_accepts_valid_data(): void
    {
        $portalUser = $this->makeUser('student');

        $validator = $this->validateStore([
            'portal_user_id' => $portalUser->id,
            'full_name' => 'John Student',
            'school_name' => 'Test School',
            'grade_name' => 'Grade 6',
            'began_on' => '2026-02-01',
            'special_notes_internal' => 'Test note',
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_store_request_rejects_non_student_user(): void
    {
        $teacherUser = $this->makeUser('teacher');

        $validator = $this->validateStore([
            'portal_user_id' => $teacherUser->id,
            'full_name' => 'John Student',
            'began_on' => '2026-02-01',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'portal_user_id',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_rejects_inactive_student_user(): void
    {
        $portalUser = $this->makeUser('student', false);

        $validator = $this->validateStore([
            'portal_user_id' => $portalUser->id,
            'full_name' => 'John Student',
            'began_on' => '2026-02-01',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'portal_user_id',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_rejects_duplicate_portal_user_id(): void
    {
        $portalUser = $this->makeUser('student');

        $this->makeStudent($portalUser);

        $validator = $this->validateStore([
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Another Student',
            'began_on' => '2026-02-01',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'portal_user_id',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_rejects_missing_required_fields(): void
    {
        $validator = $this->validateStore([]);

        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey('portal_user_id', $errors);
        $this->assertArrayHasKey('full_name', $errors);
        $this->assertArrayHasKey('began_on', $errors);
    }

    public function test_store_request_accepts_photo(): void
    {
        $portalUser = $this->makeUser('student');

        $photo = UploadedFile::fake()->image(
            'student.jpg',
            100,
            100
        );

        $validator = $this->validateStore([
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Student Photo',
            'began_on' => '2026-02-01',
            'photo' => $photo,
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_update_request_allows_current_student_portal_user(): void
    {
        $portalUser = $this->makeUser('student');

        $student = $this->makeStudent($portalUser);

        $request = UpdateStudentRequest::create(
            '/admin/students/' . $student->id,
            'PUT',
            [
                'portal_user_id' => $portalUser->id,
                'full_name' => 'Updated Student',
                'began_on' => '2026-03-01',
            ]
        );

        $route = new \Illuminate\Routing\Route(
            'PUT',
            '/admin/students/{student}',
            []
        );
        $route->bind($request);
        $route->setParameter('student', $student);
        $request->setRouteResolver(fn() => $route);

        $validator = Validator::make(
            $request->all(),
            $request->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_update_request_rejects_portal_user_already_assigned_to_another_student(): void
    {
        $currentPortalUser = $this->makeUser('student');
        $otherPortalUser = $this->makeUser('student');

        $student = $this->makeStudent($currentPortalUser);

        $this->makeStudent($otherPortalUser);

        $request = UpdateStudentRequest::create(
            '/admin/students/' . $student->id,
            'PUT',
            [
                'portal_user_id' => $otherPortalUser->id,
                'full_name' => 'Updated Student',
                'began_on' => '2026-03-01',
            ]
        );

        $route = new \Illuminate\Routing\Route(
            'PUT',
            '/admin/students/{student}',
            []
        );
        $route->bind($request);
        $route->setParameter('student', $student);
        $request->setRouteResolver(fn() => $route);

        $validator = Validator::make(
            $request->all(),
            $request->rules()
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'portal_user_id',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_authorizes_admin_only(): void
    {
        $request = StoreStudentRequest::create(
            '/admin/students',
            'POST'
        );

        $admin = $this->makeUser('admin');
        $teacher = $this->makeUser('teacher');

        $request->setUserResolver(
            fn() => $admin
        );

        $this->assertTrue(
            $request->authorize()
        );

        $request->setUserResolver(
            fn() => $teacher
        );

        $this->assertFalse(
            $request->authorize()
        );
    }

    public function test_update_request_authorizes_admin_only(): void
    {
        $request = UpdateStudentRequest::create(
            '/admin/students/test',
            'PUT'
        );

        $admin = $this->makeUser('admin');
        $teacher = $this->makeUser('teacher');

        $request->setUserResolver(
            fn() => $admin
        );

        $this->assertTrue(
            $request->authorize()
        );

        $request->setUserResolver(
            fn() => $teacher
        );

        $this->assertFalse(
            $request->authorize()
        );
    }
}
