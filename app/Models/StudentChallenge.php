<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentChallenge extends Model
{
    use HasFactory;

    protected $table = 'student_challenges';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'student_id',
        'teacher_id',
        'session_id',
        'category_name',
        'internal_note',
        'logged_on',
    ];

    protected function casts(): array
    {
        return [
            'logged_on' => 'date',
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