<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteContentChange extends Model
{
    protected $table = 'website_content_changes';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['before_payload' => 'array', 'after_payload' => 'array', 'created_at' => 'datetime'];
    }
}
