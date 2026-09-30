<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends Model
{
    use HasFactory;

    protected $table = 'payment_proofs';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $fillable = [
        'charge_id',
        'image_file_id',
        'uploaded_by_user_id',
        'submitted_at',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(
            MonthlyCharge::class,
            'charge_id'
        );
    }

    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(
            FileAsset::class,
            'image_file_id'
        );
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by_user_id'
        );
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by_user_id'
        );
    }
}