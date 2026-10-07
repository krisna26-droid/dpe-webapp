<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassGroup extends Model
{
    use HasFactory;

    protected $table = 'class_groups';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'branch_id',
        'program_id',
        'default_teacher_id',
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(
            Branch::class,
            'branch_id'
        );
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(
            Program::class,
            'program_id'
        );
    }

    public function defaultTeacher(): BelongsTo
    {
        return $this->belongsTo(
            Teacher::class,
            'default_teacher_id'
        );
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(
            ClassGroupMembership::class,
            'class_group_id',
            'id'
        );
    }

    public function lessonSessions(): HasMany
    {
        return $this->hasMany(
            LessonSession::class,
            'class_group_id',
            'id'
        );
    }
}