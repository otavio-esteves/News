<?php

namespace App\Models;

use App\Enums\StoryCategory;
use App\Enums\StoryStatus;
use Database\Factories\StoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Story extends Model
{
    /** @use HasFactory<StoryFactory> */
    use HasFactory;

    protected $fillable = [
        'slug', 'title', 'category', 'status', 'summary_blocks',
        'first_seen_at', 'last_updated_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => StoryCategory::class,
            'status' => StoryStatus::class,
            'summary_blocks' => 'array',
            'first_seen_at' => 'datetime',
            'last_updated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', StoryStatus::Published)
            ->whereNotNull('published_at')
            ->whereNotNull('slug')
            ->whereNotNull('title')
            ->whereNotNull('category')
            ->whereNotNull('summary_blocks')
            ->whereHas('revisions', fn (Builder $revision): Builder => $revision->whereNotNull('published_by'));
    }

    public function scopeFromRealSources(Builder $query): Builder
    {
        $configuredSources = array_keys(config('news.source_adapters', []));

        return $query->whereHas('articles.source', fn (Builder $source): Builder => $source->whereIn('slug', $configuredSources))
            ->whereDoesntHave('articles.source', fn (Builder $source): Builder => $source->whereNotIn('slug', $configuredSources));
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class)->withPivot('created_at');
    }

    public function draft(): HasOne
    {
        return $this->hasOne(StoryDraft::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(StoryRevision::class);
    }

    public function aiRuns(): HasMany
    {
        return $this->hasMany(AiRun::class);
    }
}
