<?php

namespace App\Jobs;

use App\Models\Source;
use App\News\Discovery\SourceAdapterRegistry;
use App\News\Discovery\SourceIsDue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiscoverSourceArticles implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $sourceId) {}

    public function uniqueId(): string
    {
        return (string) $this->sourceId;
    }

    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(SourceAdapterRegistry $adapters, SourceIsDue $due): void
    {
        $source = Source::find($this->sourceId);

        if ($source === null || ! $due->check($source)) {
            return;
        }

        if (parse_url($source->feed_url ?? '', PHP_URL_HOST) === 'agenciabrasil.ebc.com.br') {
            Cache::lock('news:ebc:feed-request', 45)->block(45, function () use ($adapters, $source): void {
                $lastRequest = Cache::get('news:ebc:last-feed-request');

                if (is_numeric($lastRequest)) {
                    $wait = max(0, 10 - (microtime(true) - (float) $lastRequest));

                    if ($wait > 0) {
                        usleep((int) ceil($wait * 1_000_000));
                    }
                }

                Cache::put('news:ebc:last-feed-request', microtime(true), 60);
                $adapters->for($source)->discover($source);
            });
        } else {
            $adapters->for($source)->discover($source);
        }

        $source->update(['last_fetched_at' => now()]);

        Log::info('news.source.discovered', ['source_id' => $source->id, 'source_slug' => $source->slug]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('news.source.discovery_failed', [
            'source_id' => $this->sourceId,
            'error' => $exception->getMessage(),
        ]);
    }
}
