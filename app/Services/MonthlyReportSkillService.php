<?php

namespace App\Services;

use App\Models\LearningSkill;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportSkill;
use Illuminate\Support\Facades\DB;

class MonthlyReportSkillService
{
    public function create(array $data): MonthlyReportSkill
    {
        return DB::transaction(function () use ($data) {
            return MonthlyReportSkill::query()->create([
                'report_id' => $data['report_id'],
                'skill_id' => $data['skill_id'],
                'trend' => $data['trend'] ?? null,
                'description' => $data['description'],
            ]);
        });
    }

    public function find(
        string $reportId,
        string $skillId
    ): MonthlyReportSkill {
        return MonthlyReportSkill::query()
            ->where('report_id', $reportId)
            ->where('skill_id', $skillId)
            ->firstOrFail();
    }

    public function getByReport(MonthlyReport $report)
    {
        return MonthlyReportSkill::query()
            ->where('report_id', $report->id)
            ->with('skill')
            ->get();
    }

    public function getBySkill(LearningSkill $skill)
    {
        return MonthlyReportSkill::query()
            ->where('skill_id', $skill->id)
            ->with('report')
            ->get();
    }

    public function update(
        string $reportId,
        string $skillId,
        array $data
    ): MonthlyReportSkill {
        return DB::transaction(function () use (
            $reportId,
            $skillId,
            $data
        ) {
            $this->find(
                $reportId,
                $skillId
            );

            MonthlyReportSkill::query()
                ->where('report_id', $reportId)
                ->where('skill_id', $skillId)
                ->update([
                    'trend' => $data['trend'] ?? null,
                    'description' => $data['description'],
                ]);

            return $this->find($reportId, $skillId);
        });
    }

    public function delete(
        string $reportId,
        string $skillId
    ): void {
        DB::transaction(function () use (
            $reportId,
            $skillId
        ) {
            $this->find(
                $reportId,
                $skillId
            );

            MonthlyReportSkill::query()
                ->where('report_id', $reportId)
                ->where('skill_id', $skillId)
                ->delete();
        });
    }
}
