<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicContentSection extends Model
{
    use HasFactory;

    protected $table = 'public_content_sections';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'section_key',
        'title',
        'body',
        'image_file_id',
        'display_order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(
            FileAsset::class,
            'image_file_id'
        );
    }
}