<?php

namespace App\Models;

use App\Enums\StoryCategory;
use Database\Factories\StoryDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryDraft extends Model
{
    /** @use HasFactory<StoryDraftFactory> */
    use HasFactory;

    protected $fillable = [
        'story_id', 'title', 'category', 'summary_blocks', 'ai_run_id',
        'validation_error', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => StoryCategory::class,
            'summary_blocks' => 'array',
            'generated_at' => 'datetime',
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
}
