<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningSkill extends Model
{
    use HasFactory;

    protected $table = 'learning_skills';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function reportSkills(): HasMany
    {
        return $this->hasMany(
            MonthlyReportSkill::class,
            'skill_id',
            'id'
        );
    }
}