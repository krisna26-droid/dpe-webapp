<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuarterlyReportFile extends Model
{
    use HasFactory;

    protected $table = 'quarterly_report_files';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'cycle_id',
        'file_id',
        'version_number',
        'generated_by_user_id',
        'generated_at',
        'source_snapshot_hash',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(
            ReportCycle::class,
            'cycle_id',
            'id'
        );
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(
            FileAsset::class,
            'file_id',
            'id'
        );
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'generated_by_user_id',
            'id'
        );
    }

    public function shareAttempts(): HasMany
    {
        return $this->hasMany(
            ReportShareAttempt::class,
            'quarterly_report_file_id',
            'id'
        );
    }

}
