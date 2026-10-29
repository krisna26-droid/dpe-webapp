<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonScheduleSeriesChange extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['before_snapshot' => 'array', 'after_snapshot' => 'array', 'created_at' => 'datetime'];
    }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id')->withTrashed(); }
}
