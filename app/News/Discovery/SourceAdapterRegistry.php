<?php

namespace App\News\Discovery;

use App\Models\Source;
use App\News\Contracts\SourceAdapter;
use LogicException;

final class SourceAdapterRegistry
{
    public function hasAdapter(Source $source): bool
    {
        return array_key_exists($source->slug, config('news.source_adapters', []));
    }

    public function for(Source $source): SourceAdapter
    {
        $class = config('news.source_adapters.'.$source->slug);

        if (! is_string($class) || ! is_a($class, SourceAdapter::class, true)) {
            throw new LogicException("Invalid source adapter for [{$source->slug}].");
        }

        return app($class);
    }
}
