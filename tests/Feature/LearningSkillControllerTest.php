<?php

namespace Tests\Feature;

use App\Http\Controllers\LearningSkillController;
use App\Http\Requests\LearningSkill\StoreLearningSkillRequest;
use App\Http\Requests\LearningSkill\UpdateLearningSkillRequest;
use App\Models\LearningSkill;
use App\Services\LearningSkillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningSkillControllerTest extends TestCase
{
    use RefreshDatabase;

    private LearningSkillController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = app(LearningSkillController::class);
    }

    public function test_index_returns_learning_skill_index_view(): void
    {
        LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LISTENING',
            'name' => 'Listening',
            'display_order' => 2,
            'is_active' => true,
        ]);

        LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'READING',
            'name' => 'Reading',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->controller->index();

        $this->assertSame(
            'learning-skills.index',
            $response->name()
        );

        $this->assertArrayHasKey(
            'learningSkills',
            $response->getData()
        );

        $learningSkills = $response->getData()['learningSkills'];

        $this->assertCount(2, $learningSkills->items());

        $this->assertSame(
            'READING',
            $learningSkills->items()[0]->code
        );

        $this->assertSame(
            'LISTENING',
            $learningSkills->items()[1]->code
        );
    }

    public function test_create_returns_learning_skill_create_view(): void
    {
        $response = $this->controller->create();

        $this->assertSame(
            'learning-skills.create',
            $response->name()
        );
    }

    public function test_store_uses_service_and_redirects_to_show(): void
    {
        $request = $this->makeStoreRequest([
            'code' => 'SPEAKING',
            'name' => 'Speaking',
            'display_order' => 3,
            'is_active' => true,
        ]);

        $response = $this->controller->store($request);

        $learningSkill = LearningSkill::query()
            ->where('code', 'SPEAKING')
            ->first();

        $this->assertNotNull($learningSkill);

        $this->assertSame(
            'SPEAKING',
            $learningSkill->code
        );

        $this->assertSame(
            'learning-skills.show',
            $response->getTargetUrl()
                ? 'learning-skills.show'
                : null
        );
    }

    public function test_show_returns_learning_skill_show_view(): void
    {
        $learningSkill = LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'WRITING',
            'name' => 'Writing',
            'display_order' => 4,
            'is_active' => true,
        ]);

        $response = $this->controller->show($learningSkill);

        $this->assertSame(
            'learning-skills.show',
            $response->name()
        );

        $this->assertSame(
            $learningSkill->id,
            $response->getData()['learningSkill']->id
        );
    }

    public function test_edit_returns_learning_skill_edit_view(): void
    {
        $learningSkill = LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'GRAMMAR',
            'name' => 'Grammar',
            'display_order' => 5,
            'is_active' => true,
        ]);

        $response = $this->controller->edit($learningSkill);

        $this->assertSame(
            'learning-skills.edit',
            $response->name()
        );

        $this->assertSame(
            $learningSkill->id,
            $response->getData()['learningSkill']->id
        );
    }

    public function test_update_uses_service_and_redirects_to_show(): void
    {
        $learningSkill = LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'OLD_CODE',
            'name' => 'Old Name',
            'display_order' => 10,
            'is_active' => true,
        ]);

        $request = $this->makeUpdateRequest([
            'code' => 'NEW_CODE',
            'name' => 'New Name',
            'display_order' => 1,
            'is_active' => false,
        ]);

        $response = $this->controller->update(
            $request,
            $learningSkill
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
            1,
            $learningSkill->display_order
        );

        $this->assertFalse(
            $learningSkill->is_active
        );

        $this->assertStringContainsString(
            '/learning-skills/' . $learningSkill->id,
            $response->getTargetUrl()
        );
    }

    public function test_destroy_uses_service_and_redirects_to_index(): void
    {
        $learningSkill = LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'DELETE_ME',
            'name' => 'Delete Me',
            'display_order' => 99,
            'is_active' => true,
        ]);

        $response = $this->controller->destroy(
            $learningSkill
        );

        $this->assertDatabaseMissing(
            'learning_skills',
            [
                'id' => $learningSkill->id,
            ]
        );

        $this->assertStringEndsWith(
            '/learning-skills',
            $response->getTargetUrl()
        );
    }

    private function makeStoreRequest(
        array $data
    ): StoreLearningSkillRequest {
        return new class($data) extends StoreLearningSkillRequest {
            public function __construct(
                private array $testValidatedData
            ) {}

            public function validated(
                $key = null,
                $default = null
            ) {
                if ($key === null) {
                    return $this->testValidatedData;
                }

                return $this->testValidatedData[$key] ?? $default;
            }
        };
    }

    private function makeUpdateRequest(
        array $data
    ): UpdateLearningSkillRequest {
        return new class($data) extends UpdateLearningSkillRequest {
            public function __construct(
                private array $testValidatedData
            ) {}

            public function validated(
                $key = null,
                $default = null
            ) {
                if ($key === null) {
                    return $this->testValidatedData;
                }

                return $this->testValidatedData[$key] ?? $default;
            }
        };
    }
}
