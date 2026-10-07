<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BranchService
{
    public function create(array $data): Branch
    {
        return DB::transaction(function () use ($data) {
            return Branch::query()->create([
                'id' => (string) Str::uuid(),
                'code' => $data['code'],
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'timezone_name' => $data['timezone_name'],
                'payment_recap_day' => (int) $data['payment_recap_day'],
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : true,
            ]);
        });
    }

    public function update(Branch $branch, array $data): Branch
    {
        return DB::transaction(function () use ($branch, $data) {
            $branch->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'timezone_name' => $data['timezone_name'],
                'payment_recap_day' => (int) $data['payment_recap_day'],
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $branch->is_active,
            ]);

            return $branch->refresh();
        });
    }

    public function toggleStatus(Branch $branch): Branch
    {
        return DB::transaction(function () use ($branch) {
            $branch->update([
                'is_active' => ! $branch->is_active,
            ]);

            return $branch->refresh();
        });
    }
}