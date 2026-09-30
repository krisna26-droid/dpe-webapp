<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    // Database DPE hanya memiliki created_at, tidak memiliki updated_at
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'full_name',
        'role_code',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Laravel authentication akan mengambil password
     * dari kolom password_hash milik database DPE.
     */
    public function getAuthPasswordName()
    {
        return 'password_hash';
    }
}