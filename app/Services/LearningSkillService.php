<?php

namespace App\Services;

use App\Models\LearningSkill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LearningSkillService
{
    public function create(array $data): LearningSkill
    {
        return DB::transaction(function () use ($data) {
            return LearningSkill::query()->create([
                'id' => (string) Str::uuid(),
                'code' => $data['code'],
                'name' => $data['name'],
                'display_order' => $data['display_order'],
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function update(
        LearningSkill $learningSkill,
        array $data
    ): LearningSkill {
        return DB::transaction(function () use (
            $learningSkill,
            $data
        ) {
            $learningSkill->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'display_order' => $data['display_order'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            return $learningSkill->refresh();
        });
    }

    public function delete(
        LearningSkill $learningSkill
    ): void {
        DB::transaction(function () use ($learningSkill) {
            $learningSkill->delete();
        });
    }
}
