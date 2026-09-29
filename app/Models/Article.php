<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Enums\StoryCategory;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    protected $fillable = [
        'source_id', 'original_url', 'canonical_url', 'title', 'description',
        'author', 'category', 'published_at', 'discovered_at', 'fetched_at', 'content',
        'content_hash', 'status', 'error',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'discovered_at' => 'datetime',
            'fetched_at' => 'datetime',
            'status' => ArticleStatus::class,
            'category' => StoryCategory::class,
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class)->withPivot('created_at');
    }
}
