<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassLearningSession extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class)->withTrashed();
    }

    public function lessonSessions(): HasMany
    {
        return $this->hasMany(LessonSession::class);
    }
}
