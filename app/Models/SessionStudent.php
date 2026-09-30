<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionStudent extends Pivot
{
    protected $table = 'session_students';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'student_id',
        'attendance_status',
        'individual_learning_note',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(
            LessonSession::class,
            'session_id',
            'id'
        );
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(
            Student::class,
            'student_id',
            'id'
        );
    }
}