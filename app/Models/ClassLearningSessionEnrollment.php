<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassLearningSessionEnrollment extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'weekday' => 'integer'];
    }

    public function learningSession(): BelongsTo
    {
        return $this->belongsTo(ClassLearningSession::class, 'class_learning_session_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(ClassGroupMembership::class, 'class_group_membership_id');
    }
}
