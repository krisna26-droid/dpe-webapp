<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guardian extends Model
{
    use HasFactory;

    protected $table = 'guardians';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    // Tabel guardians hanya memiliki created_at
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'student_id',
        'full_name',
        'relationship_name',
        'whatsapp_number',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Guardian terkait dengan satu student.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}