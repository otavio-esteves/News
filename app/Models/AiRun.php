<?php

namespace App\Models;

use Database\Factories\AiRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRun extends Model
{
    /** @use HasFactory<AiRunFactory> */
    use HasFactory;

    protected $fillable = [
        'type', 'story_id', 'article_id', 'provider', 'model',
        'prompt_version', 'schema_version', 'input_hash', 'status',
        'input_tokens', 'output_tokens', 'cost', 'cost_currency',
        'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:6',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
