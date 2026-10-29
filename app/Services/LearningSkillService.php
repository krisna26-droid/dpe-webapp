<?php

namespace App\Services;

use App\Models\LearningSkill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
                'is_active' => true,
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
            $learningSkill = LearningSkill::whereKey($learningSkill->id)->lockForUpdate()->firstOrFail();
            if ($this->requiredCategory($learningSkill) && ($data['code'] !== $learningSkill->code || $data['name'] !== $learningSkill->name)) {
                throw ValidationException::withMessages(['code' => 'Identitas kategori progres wajib tidak dapat diganti. Urutan dan status tetap dapat diperbarui.']);
            }
            $learningSkill->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'display_order' => $data['display_order'],
                'is_active' => $learningSkill->is_active,
            ]);

            return $learningSkill->refresh();
        });
    }

    public function delete(
        LearningSkill $learningSkill
    ): void {
        DB::transaction(function () use ($learningSkill) {
            $learningSkill = LearningSkill::whereKey($learningSkill->id)->lockForUpdate()->firstOrFail();
            if ($this->requiredCategory($learningSkill) || $learningSkill->reportSkills()->exists()) {
                throw ValidationException::withMessages(['skill' => 'Kategori progres wajib atau skill yang sudah dipakai laporan tidak dapat dihapus.']);
            }
            $learningSkill->delete();
        });
    }

    private function requiredCategory(LearningSkill $skill): bool
    {
        return array_key_exists(strtolower($skill->code), StudentProgressService::CATEGORIES)
            || in_array(StudentProgressService::categoryKey($skill->name), array_map([StudentProgressService::class, 'categoryKey'], array_values(StudentProgressService::CATEGORIES)), true);
    }
}
