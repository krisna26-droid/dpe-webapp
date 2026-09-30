<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningVideo extends Model
{
    use HasFactory;

    protected $table = 'learning_videos';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'student_id',
        'teacher_id',
        'session_id',
        'video_on',
        'topic',
        'description',
        'video_url',
    ];

    protected function casts(): array
    {
        return [
            'video_on' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LessonSession::class, 'session_id');
    }
}