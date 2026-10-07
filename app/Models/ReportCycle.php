<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCycle extends Model
{
    use HasFactory;

    protected $table = 'report_cycles';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'student_id',
        'cycle_number',
        'start_month',
        'end_month',
        'share_due_on',
    ];

    protected function casts(): array
    {
        return [
            'cycle_number' => 'integer',
            'start_month' => 'date',
            'end_month' => 'date',
            'share_due_on' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function monthlyReports(): HasMany
    {
        return $this->hasMany(
            MonthlyReport::class,
            'cycle_id',
            'id'
        );
    }

    public function quarterlyReportFiles(): HasMany
    {
        return $this->hasMany(
            QuarterlyReportFile::class,
            'cycle_id',
            'id'
        );
    }
}