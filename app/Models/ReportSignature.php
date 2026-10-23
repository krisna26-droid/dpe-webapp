<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSignature extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['revision' => 'integer', 'created_at' => 'datetime'];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'file_id');
    }
}
