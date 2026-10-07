<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyCharge extends Model
{
    use HasFactory;

    protected $table = 'monthly_charges';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'student_id',
        'branch_id',
        'charge_month',
        'amount_idr',
        'due_on',
    ];

    protected function casts(): array
    {
        return [
            'charge_month' => 'date',
            'amount_idr' => 'decimal:2',
            'due_on' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(
            Student::class,
            'student_id',
            'id'
        );
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(
            Branch::class,
            'branch_id',
            'id'
        );
    }

    public function paymentProofs(): HasMany
    {
        return $this->hasMany(
            PaymentProof::class,
            'charge_id',
            'id'
        );
    }
}
