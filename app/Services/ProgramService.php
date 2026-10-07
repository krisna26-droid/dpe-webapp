<?php

namespace App\Services;

use App\Models\Program;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProgramService
{
    public function create(array $data): Program
    {
        return DB::transaction(function () use ($data) {
            return Program::query()->create([
                'id' => (string) Str::uuid(),
                'code' => $data['code'],
                'name' => $data['name'],
                'class_type' => $data['class_type'],
                'monthly_video_target_override' =>
                    $data['monthly_video_target_override'] ?? null,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : true,
            ]);
        });
    }

    public function update(
        Program $program,
        array $data
    ): Program {
        return DB::transaction(function () use ($program, $data) {
            $program->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'class_type' => $data['class_type'],
                'monthly_video_target_override' =>
                    $data['monthly_video_target_override'] ?? null,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $program->is_active,
            ]);

            return $program->refresh();
        });
    }

    public function toggleStatus(Program $program): Program
    {
        return DB::transaction(function () use ($program) {
            $program->update([
                'is_active' => ! $program->is_active,
            ]);

            return $program->refresh();
        });
    }
}