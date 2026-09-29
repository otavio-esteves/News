<?php

namespace App\Console\Commands;

use App\Jobs\DiscoverSourceArticles;
use App\Models\Source;
use App\News\Discovery\SourceAdapterRegistry;
use App\News\Discovery\SourceIsDue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiscoverNews extends Command
{
    protected $signature = 'news:discover';

    protected $description = 'Dispatch discovery jobs for enabled sources that are due';

    public function handle(SourceAdapterRegistry $adapters, SourceIsDue $due): int
    {
        $eligible = 0;
        $missingAdapter = 0;
        $dispatchFailures = 0;

        foreach (Source::where('enabled', true)->cursor() as $source) {
            if (! $due->check($source)) {
                continue;
            }

            if (! $adapters->hasAdapter($source)) {
                $missingAdapter++;
                Log::warning('news.source.adapter_missing', [
                    'source_id' => $source->id,
                    'source_slug' => $source->slug,
                ]);

                continue;
            }

            try {
                DiscoverSourceArticles::dispatch($source->id);
                $eligible++;
            } catch (Throwable $exception) {
                $dispatchFailures++;
                Log::error('news.source.dispatch_failed', [
                    'source_id' => $source->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->components->info("Eligible: {$eligible}; missing adapter: {$missingAdapter}; dispatch errors: {$dispatchFailures}.");

        return $dispatchFailures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
