<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'id',
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

    public function studentProfile(): HasOne
    {
        return $this->hasOne(Student::class, 'portal_user_id');
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(Teacher::class, 'user_id');
    }

    public function uploadedPaymentProofs(): HasMany
    {
        return $this->hasMany(
            PaymentProof::class,
            'uploaded_by_user_id',
            'id'
        );
    }

    public function reviewedPaymentProofs(): HasMany
    {
        return $this->hasMany(
            PaymentProof::class,
            'reviewed_by_user_id',
            'id'
        );
    }

    public function generatedPaymentRecapRuns(): HasMany
    {
        return $this->hasMany(
            PaymentRecapRun::class,
            'generated_by_user_id',
            'id'
        );
    }

    public function generatedQuarterlyReportFiles(): HasMany
    {
        return $this->hasMany(
            QuarterlyReportFile::class,
            'generated_by_user_id',
            'id'
        );
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(
            Notification::class,
            'user_id',
            'id'
        );
    }

    public function reportStatusEvents(): HasMany
    {
        return $this->hasMany(
            ReportStatusEvent::class,
            'actor_user_id',
            'id'
        );
    }
}