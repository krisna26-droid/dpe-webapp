<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportShareAttempt extends Model
{
    use HasFactory;

    protected $table = 'report_share_attempts';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'quarterly_report_file_id',
        'teacher_id',
        'guardian_id',
        'recipient_phone_snapshot',
        'status',
        'opened_at',
        'confirmed_at',
        'teacher_note',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function quarterlyReportFile(): BelongsTo
    {
        return $this->belongsTo(
            QuarterlyReportFile::class,
            'quarterly_report_file_id'
        );
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}