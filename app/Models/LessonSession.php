<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonSession extends Model
{
    use HasFactory;

    protected $table = 'lesson_sessions';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'branch_id',
        'teacher_id',
        'program_id',
        'class_group_id',
        'planned_start_at',
        'planned_end_at',
        'actual_start_at',
        'actual_end_at',
        'status',
        'rescheduled_from_session_id',
        'topic',
        'material',
        'activity',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(
            LessonSession::class,
            'rescheduled_from_session_id'
        );
    }

    public function sessionStudents(): HasMany
    {
        return $this->hasMany(SessionStudent::class, 'session_id', 'id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(
            Student::class,
            'session_students',
            'session_id',
            'student_id'
        )->using(SessionStudent::class)
            ->withPivot([
                'attendance_status',
                'individual_learning_note',
                'recorded_at',
            ]);
    }

    public function studentChallenges(): HasMany
    {
        return $this->hasMany(StudentChallenge::class, 'session_id', 'id');
    }

    public function teacherMessages(): HasMany
    {
        return $this->hasMany(
            TeacherMessage::class,
            'session_id',
            'id'
        );
    }
}
