<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ClassGroupMembership extends Model
{
    public function learningSessionEnrollments(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ClassLearningSessionEnrollment::class); }

    use HasFactory;

    protected $table = 'class_group_memberships';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'class_group_id',
        'student_id',
        'starts_on',
        'ends_on',
        'session_selection_from', 'session_selection_revision',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date', 'session_selection_from' => 'date', 'session_selection_revision' => 'integer',
        ];
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(
            ClassGroup::class,
            'class_group_id',
            'id'
        )->withTrashed();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(
            Student::class,
            'student_id',
            'id'
        )->withTrashed();
    }

    public function isActiveOn(?Carbon $date = null): bool
    {
        $date ??= Carbon::today();

        $day = $date->toDateString();

        return $this->starts_on->toDateString() <= $day
            && ($this->ends_on === null || $this->ends_on->toDateString() >= $day);
    }
}
