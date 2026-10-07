<?php

namespace Tests\Feature;

use App\Models\LearningSkill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningSkillHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_learning_skill_index(): void
    {
        $response = $this->get(
            route('superadmin.learning-skills.index')
        );

        $response->assertRedirect();
    }

    public function test_non_superadmin_cannot_access_learning_skill_index(): void
    {
        $user = $this->makeUser('teacher');

        $response = $this
            ->actingAs($user)
            ->get(route('superadmin.learning-skills.index'));

        $response->assertForbidden();
    }

    public function test_inactive_superadmin_cannot_access_learning_skills(): void
    {
        $user = $this->makeUser(
            'superadmin',
            false
        );

        $response = $this
            ->actingAs($user)
            ->get(route('superadmin.learning-skills.index'));

        $response->assertForbidden();
    }

    public function test_superadmin_can_access_index_and_create_pages(): void
    {
        $user = $this->makeUser('superadmin');

        $indexResponse = $this
            ->actingAs($user)
            ->get(route('superadmin.learning-skills.index'));

        $indexResponse
            ->assertOk()
            ->assertViewIs('learning-skills.index');

        $createResponse = $this
            ->actingAs($user)
            ->get(route('superadmin.learning-skills.create'));

        $createResponse
            ->assertOk()
            ->assertViewIs('learning-skills.create');
    }

    public function test_superadmin_can_store_learning_skill_through_http(): void
    {
        $user = $this->makeUser('superadmin');

        $response = $this
            ->actingAs($user)
            ->post(
                route('superadmin.learning-skills.store'),
                [
                    'code' => 'LISTENING',
                    'name' => 'Listening',
                    'display_order' => 1,
                    'is_active' => true,
                ]
            );

        $learningSkill = LearningSkill::query()
            ->where('code', 'LISTENING')
            ->first();

        $this->assertNotNull($learningSkill);

        $response->assertRedirect(
            route(
                'superadmin.learning-skills.show',
                $learningSkill
            )
        );

        $response->assertSessionHas(
            'success',
            'Learning skill berhasil dibuat.'
        );
    }

    public function test_superadmin_can_view_learning_skill_and_edit_form(): void
    {
        $user = $this->makeUser('superadmin');

        $learningSkill = LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'READING',
            'name' => 'Reading',
            'display_order' => 2,
            'is_active' => true,
        ]);

        $showResponse = $this
            ->actingAs($user)
            ->get(
                route(
                    'superadmin.learning-skills.show',
                    $learningSkill
                )
            );

        $showResponse
            ->assertOk()
            ->assertViewIs('learning-skills.show')
            ->assertViewHas(
                'learningSkill',
                $learningSkill
            );

        $editResponse = $this
            ->actingAs($user)
            ->get(
                route(
                    'superadmin.learning-skills.edit',
                    $learningSkill
                )
            );

        $editResponse
            ->assertOk()
            ->assertViewIs('learning-skills.edit')
            ->assertViewHas(
                'learningSkill',
                $learningSkill
            );
    }

    public function test_superadmin_can_update_learning_skill_through_http(): void
    {
        $user = $this->makeUser('superadmin');

        $learningSkill = LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'OLD_CODE',
            'name' => 'Old Name',
            'display_order' => 5,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->put(
                route(
                    'superadmin.learning-skills.update',
                    $learningSkill
                ),
                [
                    'code' => 'NEW_CODE',
                    'name' => 'New Name',
                    'display_order' => 3,
                    'is_active' => false,
                ]
            );

        $learningSkill->refresh();

        $this->assertSame(
            'NEW_CODE',
            $learningSkill->code
        );

        $this->assertSame(
            'New Name',
            $learningSkill->name
        );

        $this->assertSame(
            3,
            $learningSkill->display_order
        );

        $this->assertFalse(
            $learningSkill->is_active
        );

        $response->assertRedirect(
            route(
                'superadmin.learning-skills.show',
                $learningSkill
            )
        );

        $response->assertSessionHas(
            'success',
            'Learning skill berhasil diperbarui.'
        );
    }

    public function test_superadmin_can_destroy_learning_skill_through_http(): void
    {
        $user = $this->makeUser('superadmin');

        $learningSkill = LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'DELETE_ME',
            'name' => 'Delete Me',
            'display_order' => 10,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(
                route(
                    'superadmin.learning-skills.destroy',
                    $learningSkill
                )
            );

        $this->assertDatabaseMissing(
            'learning_skills',
            [
                'id' => $learningSkill->id,
            ]
        );

        $response->assertRedirect(
            route('superadmin.learning-skills.index')
        );

        $response->assertSessionHas(
            'success',
            'Learning skill berhasil dihapus.'
        );
    }

    public function test_non_superadmin_cannot_create_learning_skill(): void
    {
        $user = $this->makeUser('teacher');

        $response = $this
            ->actingAs($user)
            ->post(
                route('superadmin.learning-skills.store'),
                [
                    'code' => 'LISTENING',
                    'name' => 'Listening',
                    'display_order' => 1,
                    'is_active' => true,
                ]
            );

        $response->assertForbidden();

        $this->assertDatabaseMissing(
            'learning_skills',
            [
                'code' => 'LISTENING',
            ]
        );
    }

    private function makeUser(
        string $roleCode,
        bool $isActive = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => strtolower($roleCode)
                . '_'
                . Str::random(8),
            'email' => strtolower($roleCode)
                . '-'
                . Str::random(8)
                . '@example.com',
            'password_hash' => password_hash(
                'password',
                PASSWORD_BCRYPT
            ),
            'full_name' => 'Test User',
            'role_code' => $roleCode,
            'is_active' => $isActive,
        ]);
    }
}
