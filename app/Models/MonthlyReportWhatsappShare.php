<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyReportWhatsappShare extends Model
{
    protected $table = 'monthly_report_whatsapp_shares';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['contact_snapshot' => 'array', 'prepared_at' => 'datetime', 'opened_at' => 'datetime',
            'downloaded_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class, 'report_id');
    }

    public function pdf(): BelongsTo
    {
        return $this->belongsTo(MonthlyReportFile::class, 'file_id');
    }
}
