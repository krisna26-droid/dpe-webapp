<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyReportFile extends Model
{
    protected $table = 'monthly_report_files';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'generated_at' => 'datetime', 'public_snapshot' => 'array'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class, 'report_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'file_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id')->withTrashed();
    }
}
