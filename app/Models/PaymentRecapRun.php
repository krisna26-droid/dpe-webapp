<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRecapRun extends Model
{
    use HasFactory;

    protected $table = 'payment_recap_runs';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'recap_month',
        'scheduled_on',
        'status',
        'generated_at',
        'generated_by_user_id',
        'export_file_id',
    ];

    protected function casts(): array
    {
        return [
            'recap_month' => 'date',
            'scheduled_on' => 'date',
            'generated_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'generated_by_user_id'
        );
    }

    public function exportFile(): BelongsTo
    {
        return $this->belongsTo(
            FileAsset::class,
            'export_file_id'
        );
    }
}