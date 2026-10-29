<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarterlyReportEvent extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['before_snapshot' => 'array', 'after_snapshot' => 'array', 'occurred_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(QuarterlyReport::class, 'report_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }
}
