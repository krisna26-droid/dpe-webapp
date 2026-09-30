<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyReportSkill extends Pivot
{
    protected $table = 'monthly_report_skills';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'report_id',
        'skill_id',
        'trend',
        'description',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(
            MonthlyReport::class,
            'report_id',
            'id'
        );
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(
            LearningSkill::class,
            'skill_id',
            'id'
        );
    }
}