<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ClassGroupMembership extends Model
{
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
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(
            ClassGroup::class,
            'class_group_id',
            'id'
        );
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(
            Student::class,
            'student_id',
            'id'
        );
    }

    public function isActiveOn(?Carbon $date = null): bool
    {
        $date ??= Carbon::today();

        return $this->starts_on->lte($date)
            && (
                $this->ends_on === null
                || $this->ends_on->gte($date)
            );
    }
}