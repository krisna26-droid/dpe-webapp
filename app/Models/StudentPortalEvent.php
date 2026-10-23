<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPortalEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['id', 'actor_user_id', 'student_id', 'branch_id', 'charge_id', 'payment_proof_id', 'event_type', 'before_snapshot', 'after_snapshot', 'reason', 'occurred_at'];

    protected function casts(): array
    {
        return ['before_snapshot' => 'array', 'after_snapshot' => 'array', 'occurred_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(MonthlyCharge::class, 'charge_id');
    }

    public function paymentProof(): BelongsTo
    {
        return $this->belongsTo(PaymentProof::class);
    }
}
