<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuarterlyReport extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['skills' => 'array', 'approved_snapshot' => 'array', 'revision_number' => 'integer', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ReportCycle::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id')->withTrashed();
    }

    public function events(): HasMany
    {
        return $this->hasMany(QuarterlyReportEvent::class, 'report_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(QuarterlyReportFile::class, 'quarterly_report_id');
    }
}
