<?php

namespace App\Services;

use App\Models\BranchAdminAssignment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BranchAdminAssignmentService
{
    public function create(array $data): BranchAdminAssignment
    {
        try {
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
        } catch (QueryException $exception) {
            $this->throwFriendlyConstraintError($exception);

            throw $exception;
        }
    }

    public function update(
        BranchAdminAssignment $assignment,
        array $data
    ): BranchAdminAssignment {
        try {
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
        } catch (QueryException $exception) {
            $this->throwFriendlyConstraintError($exception);

            throw $exception;
        }
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

        $sameAdminAndBranch = BranchAdminAssignment::query()
            ->where('branch_id', $data['branch_id'])
            ->where('admin_user_id', $data['admin_user_id']);

        $this->wherePeriodOverlaps($sameAdminAndBranch, $startsOn, $endsOn);

        if ($ignore) {
            $sameAdminAndBranch->whereKeyNot($ignore->getKey());
        }

        if ($sameAdminAndBranch->exists()) {
            throw ValidationException::withMessages([
                'admin_user_id' => 'Admin sudah memiliki assignment pada branch tersebut untuk periode yang bertumpang tindih.',
            ]);
        }

        $branchAlreadyHasAnotherAdmin = BranchAdminAssignment::query()
            ->where('branch_id', $data['branch_id'])
            ->where('admin_user_id', '!=', $data['admin_user_id']);

        $this->wherePeriodOverlaps($branchAlreadyHasAnotherAdmin, $startsOn, $endsOn);

        if ($ignore) {
            $branchAlreadyHasAnotherAdmin->whereKeyNot($ignore->getKey());
        }

        if ($branchAlreadyHasAnotherAdmin->exists()) {
            throw ValidationException::withMessages([
                'branch_id' => 'Cabang ini sudah memiliki Admin Cabang untuk periode yang bertumpang tindih. Akhiri assignment admin lama terlebih dahulu.',
            ]);
        }

        $adminAlreadyHasAnotherBranch = BranchAdminAssignment::query()
            ->where('admin_user_id', $data['admin_user_id'])
            ->where('branch_id', '!=', $data['branch_id']);

        $this->wherePeriodOverlaps($adminAlreadyHasAnotherBranch, $startsOn, $endsOn);

        if ($ignore) {
            $adminAlreadyHasAnotherBranch->whereKeyNot($ignore->getKey());
        }

        if ($adminAlreadyHasAnotherBranch->exists()) {
            throw ValidationException::withMessages([
                'admin_user_id' => 'Admin ini sudah ditugaskan pada cabang lain untuk periode yang bertumpang tindih.',
            ]);
        }
    }

    private function wherePeriodOverlaps(
        $query,
        string $startsOn,
        ?string $endsOn
    ): void {
        $query
            ->whereDate('starts_on', '<=', $endsOn ?? '9999-12-31')
            ->where(function ($query) use ($startsOn) {
                $query
                    ->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $startsOn);
            });
    }

    private function throwFriendlyConstraintError(QueryException $exception): void
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'uq_branch_active_admin')) {
            throw ValidationException::withMessages([
                'branch_id' => 'Cabang ini sudah memiliki Admin Cabang aktif. Akhiri assignment admin lama terlebih dahulu.',
            ]);
        }

        if (str_contains($message, 'uq_admin_active_branch')) {
            throw ValidationException::withMessages([
                'admin_user_id' => 'Admin ini sudah memiliki penugasan cabang aktif. Akhiri assignment lama terlebih dahulu.',
            ]);
        }
    }
}
