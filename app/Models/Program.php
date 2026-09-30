<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasFactory;

    protected $table = 'programs';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $fillable = [
        'code',
        'name',
        'class_type',
        'monthly_video_target_override',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monthly_video_target_override' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function studentEnrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
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