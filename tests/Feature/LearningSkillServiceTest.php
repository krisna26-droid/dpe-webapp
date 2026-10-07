<?php

namespace Tests\Feature;

use App\Models\LearningSkill;
use App\Services\LearningSkillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningSkillServiceTest extends TestCase
{
    use RefreshDatabase;

    private LearningSkillService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LearningSkillService::class);
    }

    public function test_can_create_a_learning_skill(): void
    {
        $skill = $this->service->create([
            'code' => 'LISTENING',
            'name' => 'Listening',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(
            LearningSkill::class,
            $skill
        );

        $this->assertNotNull($skill->id);
        $this->assertSame('LISTENING', $skill->code);
        $this->assertSame('Listening', $skill->name);
        $this->assertSame(1, $skill->display_order);
        $this->assertTrue($skill->is_active);

        $this->assertDatabaseHas('learning_skills', [
            'id' => $skill->id,
            'code' => 'LISTENING',
            'name' => 'Listening',
            'display_order' => 1,
            'is_active' => 1,
        ]);
    }

    public function test_defaults_learning_skill_to_active_when_is_active_is_omitted(): void
    {
        $skill = $this->service->create([
            'code' => 'SPEAKING',
            'name' => 'Speaking',
            'display_order' => 2,
        ]);

        $this->assertTrue($skill->is_active);

        $this->assertDatabaseHas('learning_skills', [
            'id' => $skill->id,
            'is_active' => 1,
        ]);
    }

    public function test_can_create_an_inactive_learning_skill(): void
    {
        $skill = $this->service->create([
            'code' => 'WRITING',
            'name' => 'Writing',
            'display_order' => 3,
            'is_active' => false,
        ]);

        $this->assertFalse($skill->is_active);

        $this->assertDatabaseHas('learning_skills', [
            'id' => $skill->id,
            'is_active' => 0,
        ]);
    }

    public function test_can_update_a_learning_skill(): void
    {
        $skill = LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'OLD-CODE',
            'name' => 'Old Name',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $updated = $this->service->update($skill, [
            'code' => 'NEW-CODE',
            'name' => 'New Name',
            'display_order' => 5,
            'is_active' => false,
        ]);

        $this->assertSame(
            $skill->id,
            $updated->id
        );

        $this->assertSame(
            'NEW-CODE',
            $updated->code
        );

        $this->assertSame(
            'New Name',
            $updated->name
        );

        $this->assertSame(
            5,
            $updated->display_order
        );

        $this->assertFalse(
            $updated->is_active
        );

        $this->assertDatabaseHas('learning_skills', [
            'id' => $skill->id,
            'code' => 'NEW-CODE',
            'name' => 'New Name',
            'display_order' => 5,
            'is_active' => 0,
        ]);
    }

    public function test_can_delete_a_learning_skill(): void
    {
        $skill = LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'DELETE-ME',
            'name' => 'Delete Me',
            'display_order' => 10,
            'is_active' => true,
        ]);

        $this->service->delete($skill);

        $this->assertDatabaseMissing('learning_skills', [
            'id' => $skill->id,
        ]);
    }
}
