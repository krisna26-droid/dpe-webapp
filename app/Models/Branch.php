<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $table = 'branches';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'code',
        'name',
        'address',
        'timezone_name',
        'payment_recap_day',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'payment_recap_day' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function studentEnrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function teacherBranchAssignments(): HasMany
    {
        return $this->hasMany(TeacherBranchAssignment::class);
    }

    public function classGroups(): HasMany
    {
        return $this->hasMany(ClassGroup::class);
    }

    public function lessonSessions(): HasMany
    {
        return $this->hasMany(LessonSession::class);
    }
}