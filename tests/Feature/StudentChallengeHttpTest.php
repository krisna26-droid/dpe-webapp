<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentChallenge;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentChallengeHttpTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        string $roleCode = 'superadmin',
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

    private function makeStudentChallenge(
        ?Student $student = null,
        ?Teacher $teacher = null
    ): StudentChallenge {
        $student ??= $this->makeStudent();
        $teacher ??= $this->makeTeacher();

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

    private function validPayload(
        Student $student,
        Teacher $teacher
    ): array {
        return [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'category_name' => 'Pronunciation',
            'internal_note' => 'Needs extra pronunciation practice.',
            'logged_on' => '2026-10-06',
        ];
    }

    public function test_guest_cannot_access_student_challenge_index(): void
    {
        $this->get(route('superadmin.student-challenges.index'))
            ->assertRedirect(route('login'));
    }

    public function test_non_superadmin_cannot_access_student_challenge_index(): void
    {
        $this->actingAs($this->makeUser('teacher'))
            ->get(route('superadmin.student-challenges.index'))
            ->assertForbidden();
    }

    public function test_inactive_superadmin_cannot_access_student_challenges(): void
    {
        $this->actingAs($this->makeUser('superadmin', false))
            ->get(route('superadmin.student-challenges.index'))
            ->assertForbidden();
    }

    public function test_superadmin_can_access_index_and_create_pages(): void
    {
        $superadmin = $this->makeUser();

        $this->actingAs($superadmin)
            ->get(route('superadmin.student-challenges.index'))
            ->assertOk()
            ->assertViewIs('student-challenges.index')
            ->assertViewHas('studentChallenges');

        $this->actingAs($superadmin)
            ->get(route('superadmin.student-challenges.create'))
            ->assertOk()
            ->assertViewIs('student-challenges.create');
    }

    public function test_superadmin_can_store_student_challenge_through_http(): void
    {
        $superadmin = $this->makeUser();
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $response = $this->actingAs($superadmin)->post(
            route('superadmin.student-challenges.store'),
            $this->validPayload($student, $teacher)
        );

        $response->assertSessionHasNoErrors();

        $challenge = StudentChallenge::query()
            ->where('student_id', $student->id)
            ->first();

        $this->assertNotNull($challenge);

        $response->assertRedirect(
            route('superadmin.student-challenges.show', $challenge)
        )->assertSessionHas(
            'success',
            'Student challenge berhasil dibuat.'
        );

        $this->assertDatabaseHas('student_challenges', [
            'id' => $challenge->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'category_name' => 'Pronunciation',
            'internal_note' => 'Needs extra pronunciation practice.',
            'logged_on' => '2026-10-06 00:00:00',
        ]);
    }

    public function test_superadmin_can_view_student_challenge_and_edit_form(): void
    {
        $superadmin = $this->makeUser();
        $challenge = $this->makeStudentChallenge();

        $this->actingAs($superadmin)
            ->get(route('superadmin.student-challenges.show', $challenge))
            ->assertOk()
            ->assertViewIs('student-challenges.show')
            ->assertViewHas('studentChallenge');

        $this->actingAs($superadmin)
            ->get(route('superadmin.student-challenges.edit', $challenge))
            ->assertOk()
            ->assertViewIs('student-challenges.edit')
            ->assertViewHas('studentChallenge');
    }

    public function test_superadmin_can_update_student_challenge_through_http(): void
    {
        $superadmin = $this->makeUser();
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $challenge = $this->makeStudentChallenge($student, $teacher);

        $response = $this->actingAs($superadmin)->put(
            route('superadmin.student-challenges.update', $challenge),
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => null,
                'category_name' => 'Vocabulary',
                'internal_note' => 'Updated challenge note.',
                'logged_on' => '2026-10-07',
            ]
        );

        $response->assertRedirect(
            route('superadmin.student-challenges.show', $challenge)
        )->assertSessionHas(
            'success',
            'Student challenge berhasil diperbarui.'
        );

        $this->assertDatabaseHas('student_challenges', [
            'id' => $challenge->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'category_name' => 'Vocabulary',
            'internal_note' => 'Updated challenge note.',
            'logged_on' => '2026-10-07 00:00:00',
        ]);
    }

    public function test_superadmin_can_destroy_student_challenge_through_http(): void
    {
        $superadmin = $this->makeUser();
        $challenge = $this->makeStudentChallenge();

        $this->actingAs($superadmin)
            ->delete(
                route('superadmin.student-challenges.destroy', $challenge)
            )
            ->assertRedirect(route('superadmin.student-challenges.index'))
            ->assertSessionHas(
                'success',
                'Student challenge berhasil dihapus.'
            );

        $this->assertDatabaseMissing('student_challenges', [
            'id' => $challenge->id,
        ]);
    }

    public function test_non_superadmin_cannot_create_student_challenge(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $this->actingAs($this->makeUser('teacher'))
            ->post(
                route('superadmin.student-challenges.store'),
                $this->validPayload($student, $teacher)
            )
            ->assertForbidden();

        $this->assertDatabaseCount('student_challenges', 0);
    }
}