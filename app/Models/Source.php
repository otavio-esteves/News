<?php

namespace App\Models;

use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'homepage_url', 'feed_url', 'enabled',
        'fetch_interval_minutes', 'last_fetched_at', 'config',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_fetched_at' => 'datetime',
            'config' => 'array',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
