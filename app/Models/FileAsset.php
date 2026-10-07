<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FileAsset extends Model
{
    use HasFactory;

    protected $table = 'file_assets';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'storage_key',
        'original_name',
        'mime_type',
        'size_bytes',
        'sha256_hex',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function teacherMessages(): HasMany
    {
        return $this->hasMany(
            TeacherMessage::class,
            'attachment_file_id',
            'id'
        );
    }

    public function paymentProofs(): HasMany
    {
        return $this->hasMany(
            PaymentProof::class,
            'image_file_id',
            'id'
        );
    }

    public function paymentRecapRuns(): HasMany
    {
        return $this->hasMany(
            PaymentRecapRun::class,
            'export_file_id',
            'id'
        );
    }

    public function quarterlyReportFiles(): HasMany
    {
        return $this->hasMany(
            QuarterlyReportFile::class,
            'file_id',
            'id'
        );
    }

    public function publicContentSections(): HasMany
    {
        return $this->hasMany(
            PublicContentSection::class,
            'image_file_id',
            'id'
        );
    }
    public function systemSetting(): HasOne
    {
        return $this->hasOne(
            SystemSetting::class,
            'logo_file_id',
            'id'
        );
    }
}