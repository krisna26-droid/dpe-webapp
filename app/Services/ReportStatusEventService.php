<?php

namespace App\Services;

use App\Models\ReportStatusEvent;
use App\Models\MonthlyReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportStatusEventService
{
    public function create(array $data): ReportStatusEvent
    {
        return DB::transaction(function () use ($data) {
            return ReportStatusEvent::query()->create([
                'id' => $data['id'],
                'report_id' => $data['report_id'],
                'actor_user_id' => $data['actor_user_id'],
                'from_status' => $data['from_status'] ?? null,
                'to_status' => $data['to_status'],
                'comment' => $data['comment'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);
        });
    }

    public function find(string $id): ReportStatusEvent
    {
        return ReportStatusEvent::query()->findOrFail($id);
    }

    public function getByReport(MonthlyReport $report)
    {
        return ReportStatusEvent::query()
            ->where('report_id', $report->id)
            ->orderBy('occurred_at')
            ->get();
    }

    public function getByActor(User $user)
    {
        return ReportStatusEvent::query()
            ->where('actor_user_id', $user->id)
            ->orderBy('occurred_at')
            ->get();
    }

    public function update(
        string $id,
        array $data
    ): ReportStatusEvent {
        return DB::transaction(function () use ($id, $data) {
            $event = $this->find($id);

            $event->update([
                'report_id' => $data['report_id'],
                'actor_user_id' => $data['actor_user_id'],
                'from_status' => $data['from_status'] ?? null,
                'to_status' => $data['to_status'],
                'comment' => $data['comment'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? $event->occurred_at ?? now(),
            ]);

            return $event->refresh();
        });
    }

    public function delete(string $id): void
    {
        DB::transaction(function () use ($id) {
            $event = $this->find($id);

            $event->delete();
        });
    }
}
