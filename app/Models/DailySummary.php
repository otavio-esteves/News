<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailySummary extends Model
{
    protected $fillable = ['date', 'draft_blocks', 'draft_slot_at', 'published_blocks', 'published_at'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'draft_blocks' => 'array',
            'draft_slot_at' => 'datetime',
            'published_blocks' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DailySummaryRevision::class);
    }
}
