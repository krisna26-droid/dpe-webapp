<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'system_settings';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    const CREATED_AT = null;
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'organization_name',
        'organization_description',
        'contact_email',
        'contact_phone',
        'logo_file_id',
        'default_report_due_day',
        'default_monthly_video_target',
        'in_app_reminders_enabled',
    ];

    protected function casts(): array
    {
        return [
            'default_report_due_day' => 'integer',
            'default_monthly_video_target' => 'integer',
            'in_app_reminders_enabled' => 'boolean',
            'updated_at' => 'datetime',
        ];
    }

    public function logoFile(): BelongsTo
    {
        return $this->belongsTo(
            FileAsset::class,
            'logo_file_id'
        );
    }
}