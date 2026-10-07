<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Student extends Model
{
    use HasFactory;

    protected $table = 'students';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    // Tabel students hanya memiliki created_at
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'portal_user_id',
        'full_name',
        'photo_file_id',
        'school_name',
        'grade_name',
        'began_on',
        'status',
        'special_notes_internal',
    ];

    protected function casts(): array
    {
        return [
            'began_on' => 'date',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Student memiliki akun portal/user.
     */
    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'portal_user_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'student_id');
    }

    public function photoFile(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'photo_file_id');
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class, 'student_id');
    }

    public function classGroupMemberships(): HasMany
    {
        return $this->hasMany(ClassGroupMembership::class, 'student_id');
    }

    public function studentChallenges(): HasMany
    {
        return $this->hasMany(StudentChallenge::class, 'student_id', 'id');
    }

    public function teacherMessages(): HasMany
    {
        return $this->hasMany(TeacherMessage::class, 'student_id', 'id');
    }

    public function monthlyCharges(): HasMany
    {
        return $this->hasMany(
            MonthlyCharge::class,
            'student_id',
            'id'
        );
    }

    public function lessonSessions(): BelongsToMany
    {
        return $this->belongsToMany(
            LessonSession::class,
            'session_students',
            'student_id',
            'session_id'
        )
            ->using(SessionStudent::class)
            ->withPivot([
                'attendance_status',
                'individual_learning_note',
                'recorded_at',
            ]);
    }

    public function learningVideos(): HasMany
    {
        return $this->hasMany(
            LearningVideo::class,
            'student_id',
            'id'
        );
    }

    public function monthlyReports(): HasMany
    {
        return $this->hasMany(
            MonthlyReport::class,
            'student_id',
            'id'
        );
    }

    public function reportCycles(): HasMany
    {
        return $this->hasMany(
            ReportCycle::class,
            'student_id',
            'id'
        );
    }

    public function studentTeacherAssignments(): HasMany
    {
        return $this->hasMany(
            StudentTeacherAssignment::class,
            'student_id',
            'id'
        );
    }
}
