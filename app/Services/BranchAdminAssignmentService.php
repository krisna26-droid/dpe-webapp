<?php

namespace App\Services;

use App\Models\BranchAdminAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BranchAdminAssignmentService
{
    public function create(array $data): BranchAdminAssignment
    {
        return DB::transaction(function () use ($data) {
            $this->ensureNoOverlappingAssignment($data);

            return BranchAdminAssignment::query()->create([
                'id' => (string) Str::uuid(),
                'branch_id' => $data['branch_id'],
                'admin_user_id' => $data['admin_user_id'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
            ]);
        });
    }

    public function update(
        BranchAdminAssignment $assignment,
        array $data
    ): BranchAdminAssignment {
        return DB::transaction(function () use ($assignment, $data) {
            $this->ensureNoOverlappingAssignment($data, $assignment);

            $assignment->update([
                'branch_id' => $data['branch_id'],
                'admin_user_id' => $data['admin_user_id'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
            ]);

            return $assignment->refresh();
        });
    }

    public function delete(BranchAdminAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment) {
            $assignment->delete();
        });
    }

    private function ensureNoOverlappingAssignment(
        array $data,
        ?BranchAdminAssignment $ignore = null
    ): void {
        $startsOn = $data['starts_on'];
        $endsOn = $data['ends_on'] ?? null;

        $query = BranchAdminAssignment::query()
            ->where('branch_id', $data['branch_id'])
            ->where('admin_user_id', $data['admin_user_id'])
            ->where(function ($query) use ($startsOn, $endsOn) {
                $query
                    ->where(function ($query) use ($startsOn, $endsOn) {
                        $query
                            ->whereDate('starts_on', '<=', $startsOn)
                            ->where(function ($query) use ($startsOn) {
                                $query
                                    ->whereNull('ends_on')
                                    ->orWhereDate('ends_on', '>=', $startsOn);
                            });
                    })
                    ->orWhere(function ($query) use ($startsOn, $endsOn) {
                        $query
                            ->whereDate('starts_on', '<=', $endsOn ?? '9999-12-31')
                            ->where(function ($query) use ($endsOn) {
                                $query
                                    ->whereNull('ends_on')
                                    ->orWhereDate('ends_on', '>=', $endsOn ?? '9999-12-31');
                            });
                    })
                    ->orWhere(function ($query) use ($startsOn) {
                        $query
                            ->whereDate('starts_on', '>=', $startsOn)
                            ->whereNull('ends_on');
                    });
            });

        if ($ignore) {
            $query->whereKeyNot($ignore->getKey());
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'admin_user_id' => 'Admin sudah memiliki assignment pada branch tersebut untuk periode yang bertumpang tindih.',
            ]);
        }
    }
}