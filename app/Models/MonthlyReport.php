<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MonthlyReport extends Model
{
    use HasFactory;

    protected $table = 'monthly_reports';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'cycle_id',
        'student_id',
        'branch_id',
        'teacher_id',
        'report_month',
        'due_on',
        'video_target',
        'status',
        'development_summary',
        'parent_challenges_summary',
        'parent_message',
        'internal_teacher_note',
        'submitted_at',
        'approved_at',
        'approved_by_user_id',
        'approved_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'report_month' => 'date',
            'due_on' => 'date',
            'video_target' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'approved_snapshot' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(
            ReportCycle::class,
            'cycle_id'
        );
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by_user_id'
        );
    }

    public function reportSkills(): HasMany
    {
        return $this->hasMany(
            MonthlyReportSkill::class,
            'report_id',
            'id'
        );
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(
            LearningSkill::class,
            'monthly_report_skills',
            'report_id',
            'skill_id'
        )
        ->using(MonthlyReportSkill::class)
        ->withPivot([
            'trend',
            'description',
        ]);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(
            ReportStatusEvent::class,
            'report_id',
            'id'
        );
    }
}