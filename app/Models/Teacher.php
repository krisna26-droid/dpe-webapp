<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    use HasFactory;

    protected $table = 'teachers';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'user_id',
        'whatsapp_number',
        'photo_file_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function photoFile(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'photo_file_id');
    }

    public function branchAssignments(): HasMany
    {
        return $this->hasMany(
            TeacherBranchAssignment::class,
            'teacher_id'
        );
    }

    public function studentAssignments(): HasMany
    {
        return $this->hasMany(
            StudentTeacherAssignment::class,
            'teacher_id'
        );
    }

    public function classGroups(): HasMany
    {
        return $this->hasMany(
            ClassGroup::class,
            'default_teacher_id',
            'id'
        );
    }

    public function teacherMessages(): HasMany
    {
        return $this->hasMany(
            TeacherMessage::class,
            'teacher_id',
            'id'
        );
    }

    public function reportShareAttempts(): HasMany
    {
        return $this->hasMany(
            ReportShareAttempt::class,
            'teacher_id',
            'id'
        );
    }
}
