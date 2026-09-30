<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}