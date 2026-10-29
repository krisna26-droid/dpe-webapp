<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            $branch = Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $before = $branch->getAttributes();
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
            $this->event($branch, $before);

            return $branch->refresh();
        });
    }



    private function event(Branch $branch, array $before): void
    {
        if (Schema::hasTable('student_portal_events') && ($actor = User::find(auth()->id()))) {
            app(PortalPaymentService::class)->event($actor, 'branch_settings_changed', before: $before,
                after: $branch->getAttributes(), branch: $branch);
        }
    }
}
