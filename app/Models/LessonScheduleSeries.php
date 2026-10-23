<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonScheduleSeries extends Model
{
    protected $table = 'lesson_schedule_series';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected function casts(): array { return ['definition' => 'array']; }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function classGroup(): BelongsTo { return $this->belongsTo(ClassGroup::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function sessions(): HasMany { return $this->hasMany(LessonSession::class, 'schedule_series_id'); }
    public function changes(): HasMany { return $this->hasMany(LessonScheduleSeriesChange::class, 'series_id'); }
}
