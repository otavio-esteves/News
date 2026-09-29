<?php

namespace App\Models;

use App\Enums\StoryCategory;
use Database\Factories\StoryRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StoryRevision extends Model
{
    /** @use HasFactory<StoryRevisionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'story_id', 'version', 'title', 'category', 'summary_blocks',
        'reference_snapshots', 'ai_run_id', 'published_by', 'published_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Published story revisions cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Published story revisions cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'category' => StoryCategory::class,
            'summary_blocks' => 'array',
            'reference_snapshots' => 'array',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
