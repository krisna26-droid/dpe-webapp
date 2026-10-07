<?php

namespace Tests\Feature;

use App\Http\Requests\SessionStudentStoreRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionStudentRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $data): array
    {
        $request = new SessionStudentStoreRequest();

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        return [
            'passes' => $validator->passes(),
            'errors' => $validator->errors()->toArray(),
        ];
    }

    public function test_session_id_is_required(): void
    {
        $result = $this->validate([
            'student_id' => (string) Str::uuid(),
        ]);

        $this->assertFalse($result['passes']);
        $this->assertArrayHasKey(
            'session_id',
            $result['errors']
        );
    }

    public function test_session_id_must_be_uuid(): void
    {
        $result = $this->validate([
            'session_id' => 'invalid',
            'student_id' => (string) Str::uuid(),
        ]);

        $this->assertFalse($result['passes']);
        $this->assertArrayHasKey(
            'session_id',
            $result['errors']
        );
    }

    public function test_student_id_is_required(): void
    {
        $result = $this->validate([
            'session_id' => (string) Str::uuid(),
        ]);

        $this->assertFalse($result['passes']);
        $this->assertArrayHasKey(
            'student_id',
            $result['errors']
        );
    }

    public function test_student_id_must_be_uuid(): void
    {
        $result = $this->validate([
            'session_id' => (string) Str::uuid(),
            'student_id' => 'invalid',
        ]);

        $this->assertFalse($result['passes']);
        $this->assertArrayHasKey(
            'student_id',
            $result['errors']
        );
    }

    public function test_attendance_status_is_nullable(): void
    {
        $rules = (new SessionStudentStoreRequest())->rules();

        $this->assertContains(
            'nullable',
            $rules['attendance_status']
        );
    }

    public function test_individual_learning_note_is_nullable(): void
    {
        $rules = (new SessionStudentStoreRequest())->rules();

        $this->assertContains(
            'nullable',
            $rules['individual_learning_note']
        );
    }

    public function test_recorded_at_is_nullable(): void
    {
        $rules = (new SessionStudentStoreRequest())->rules();

        $this->assertContains(
            'nullable',
            $rules['recorded_at']
        );
    }

    public function test_attendance_status_must_be_string(): void
    {
        $result = $this->validate([
            'session_id' => (string) Str::uuid(),
            'student_id' => (string) Str::uuid(),
            'attendance_status' => ['invalid'],
        ]);

        $this->assertFalse($result['passes']);
        $this->assertArrayHasKey(
            'attendance_status',
            $result['errors']
        );
    }

    public function test_individual_learning_note_must_be_string(): void
    {
        $result = $this->validate([
            'session_id' => (string) Str::uuid(),
            'student_id' => (string) Str::uuid(),
            'individual_learning_note' => ['invalid'],
        ]);

        $this->assertFalse($result['passes']);
        $this->assertArrayHasKey(
            'individual_learning_note',
            $result['errors']
        );
    }

    public function test_recorded_at_must_be_date(): void
    {
        $result = $this->validate([
            'session_id' => (string) Str::uuid(),
            'student_id' => (string) Str::uuid(),
            'recorded_at' => 'not-a-date',
        ]);

        $this->assertFalse($result['passes']);
        $this->assertArrayHasKey(
            'recorded_at',
            $result['errors']
        );
    }
}
