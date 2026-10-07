<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherMessage extends Model
{
    use HasFactory;

    protected $table = 'teacher_messages';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'student_id',
        'teacher_id',
        'session_id',
        'message_body',
        'attachment_file_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(
            Student::class,
            'student_id',
            'id'
        );
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(
            Teacher::class,
            'teacher_id',
            'id'
        );
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(
            LessonSession::class,
            'session_id',
            'id'
        );
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(
            FileAsset::class,
            'attachment_file_id',
            'id'
        );
    }
}
