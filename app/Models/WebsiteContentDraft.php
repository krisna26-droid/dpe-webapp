<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteContentDraft extends Model
{
    protected $table = 'website_content_drafts';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'revision' => 'integer', 'saved_at' => 'datetime'];
    }
}
