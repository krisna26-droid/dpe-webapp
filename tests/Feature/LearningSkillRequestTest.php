<?php

namespace Tests\Feature;

use App\Http\Requests\LearningSkill\StoreLearningSkillRequest;
use App\Http\Requests\LearningSkill\UpdateLearningSkillRequest;
use App\Models\LearningSkill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningSkillRequestTest extends TestCase
{
    use RefreshDatabase;

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

    private function validate(
        $request,
        array $data
    ) {
        $validator = Validator::make(
            $data,
            $request->rules()
        );

        return $validator;
    }

    public function test_store_request_accepts_valid_data(): void
    {
        $user = $this->makeUser('superadmin');

        $this->actingAs($user);

        $request = StoreLearningSkillRequest::create(
            '/learning-skills',
            'POST'
        );

        $validator = $this->validate($request, [
            'code' => 'LISTENING',
            'name' => 'Listening',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_store_request_requires_code(): void
    {
        $user = $this->makeUser('superadmin');

        $this->actingAs($user);

        $request = StoreLearningSkillRequest::create(
            '/learning-skills',
            'POST'
        );

        $validator = $this->validate($request, [
            'name' => 'Listening',
            'display_order' => 1,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'code',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_rejects_duplicate_code(): void
    {
        $user = $this->makeUser('superadmin');

        LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LISTENING',
            'name' => 'Existing Listening',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $request = StoreLearningSkillRequest::create(
            '/learning-skills',
            'POST'
        );

        $validator = $this->validate($request, [
            'code' => 'LISTENING',
            'name' => 'Listening',
            'display_order' => 2,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'code',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_rejects_negative_display_order(): void
    {
        $user = $this->makeUser('superadmin');

        $this->actingAs($user);

        $request = StoreLearningSkillRequest::create(
            '/learning-skills',
            'POST'
        );

        $validator = $this->validate($request, [
            'code' => 'LISTENING',
            'name' => 'Listening',
            'display_order' => -1,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'display_order',
            $validator->errors()->toArray()
        );
    }

    public function test_update_request_allows_same_code(): void
    {
        $user = $this->makeUser('superadmin');

        $skill = LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LISTENING',
            'name' => 'Listening',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $request = UpdateLearningSkillRequest::create(
            '/learning-skills/' . $skill->id,
            'PUT'
        );

        $request->setRouteResolver(function () use ($skill) {
            return new class($skill)
            {
                public function __construct(
                    private LearningSkill $skill
                ) {}

                public function parameter($key, $default = null)
                {
                    if ($key === 'learning_skill') {
                        return $this->skill;
                    }

                    return $default;
                }
            };
        });

        $validator = $this->validate($request, [
            'code' => 'LISTENING',
            'name' => 'Updated Listening',
            'display_order' => 2,
            'is_active' => true,
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_update_request_rejects_code_used_by_another_skill(): void
    {
        $user = $this->makeUser('superadmin');

        $skill = LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LISTENING',
            'name' => 'Listening',
            'display_order' => 1,
            'is_active' => true,
        ]);

        LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'SPEAKING',
            'name' => 'Speaking',
            'display_order' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $request = UpdateLearningSkillRequest::create(
            '/learning-skills/' . $skill->id,
            'PUT'
        );

        $request->setRouteResolver(function () use ($skill) {
            return new class($skill)
            {
                public function __construct(
                    private LearningSkill $skill
                ) {}

                public function parameter($key, $default = null)
                {
                    if ($key === 'learning_skill') {
                        return $this->skill;
                    }

                    return $default;
                }
            };
        });

        $validator = $this->validate($request, [
            'code' => 'SPEAKING',
            'name' => 'Updated Listening',
            'display_order' => 2,
            'is_active' => true,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'code',
            $validator->errors()->toArray()
        );
    }

    public function test_non_superadmin_cannot_authorize_request(): void
    {
        $user = $this->makeUser('admin');

        $this->actingAs($user);

        $request = StoreLearningSkillRequest::create(
            '/learning-skills',
            'POST'
        );

        $this->assertFalse($request->authorize());
    }

    public function test_inactive_superadmin_cannot_authorize_request(): void
    {
        $user = $this->makeUser(
            'superadmin',
            false
        );

        $this->actingAs($user);

        $request = StoreLearningSkillRequest::create(
            '/learning-skills',
            'POST'
        );

        $this->assertFalse($request->authorize());
    }
}
