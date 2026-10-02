<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class DailySummaryRevision extends Model
{
    public $timestamps = false;

    protected $fillable = ['version', 'blocks', 'reference_snapshots', 'published_by', 'published_at'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Published daily summary revisions cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Published daily summary revisions cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['blocks' => 'array', 'reference_snapshots' => 'array', 'published_at' => 'datetime'];
    }
}
