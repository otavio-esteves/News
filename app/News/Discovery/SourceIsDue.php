<?php

namespace App\News\Discovery;

use App\Models\Source;

final class SourceIsDue
{
    public function check(Source $source): bool
    {
        if (! $source->enabled) {
            return false;
        }

        if ($source->last_fetched_at === null) {
            return true;
        }

        return $source->last_fetched_at->copy()
            ->addMinutes(max(1, $source->fetch_interval_minutes))
            ->lte(now());
    }
}
