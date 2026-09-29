<?php

use App\Jobs\DiscoverSourceArticles;
use App\Models\Source;
use App\News\Contracts\SourceAdapter;
use App\News\Discovery\SourceAdapterRegistry;
use App\News\Discovery\SourceIsDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches only enabled sources whose discovery interval has elapsed', function () {
    Queue::fake();
    config()->set('news.source_adapters', [
        'ready' => RecordingSourceAdapter::class,
        'recent' => RecordingSourceAdapter::class,
        'disabled' => RecordingSourceAdapter::class,
    ]);

    $ready = Source::factory()->create(['slug' => 'ready', 'enabled' => true]);
    Source::factory()->create([
        'slug' => 'recent', 'enabled' => true,
        'last_fetched_at' => now()->subMinutes(5),
        'fetch_interval_minutes' => 15,
    ]);
    Source::factory()->create(['slug' => 'disabled', 'enabled' => false]);
    Source::factory()->create(['slug' => 'unconfigured', 'enabled' => true]);

    expect(Artisan::call('news:discover'))->toBe(0);

    Queue::assertPushed(DiscoverSourceArticles::class, 1);
    Queue::assertPushed(DiscoverSourceArticles::class, fn (DiscoverSourceArticles $job) => $job->sourceId === $ready->id);
});

it('runs an adapter once per interval and updates the source only after success', function () {
    $source = Source::factory()->create(['enabled' => true]);
    $adapter = new RecordingSourceAdapter;
    app()->instance(RecordingSourceAdapter::class, $adapter);
    config()->set('news.source_adapters.'.$source->slug, RecordingSourceAdapter::class);

    $job = new DiscoverSourceArticles($source->id);
    $job->handle(app(SourceAdapterRegistry::class), app(SourceIsDue::class));
    $job->handle(app(SourceAdapterRegistry::class), app(SourceIsDue::class));

    expect($adapter->sourceIds)->toBe([$source->id])
        ->and($source->fresh()->last_fetched_at)->not->toBeNull();
});

it('persists one unique job in the database queue and processes it', function () {
    config()->set('queue.default', 'database');

    $source = Source::factory()->create(['enabled' => true]);
    $adapter = new RecordingSourceAdapter;
    app()->instance(RecordingSourceAdapter::class, $adapter);
    config()->set('news.source_adapters.'.$source->slug, RecordingSourceAdapter::class);

    DiscoverSourceArticles::dispatch($source->id);
    DiscoverSourceArticles::dispatch($source->id);

    expect(Queue::size())->toBe(1);

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true]);

    expect(Queue::size())->toBe(0)
        ->and($adapter->sourceIds)->toBe([$source->id])
        ->and($source->fresh()->last_fetched_at)->not->toBeNull();
});

it('leaves a failed discovery due so the queue can retry it', function () {
    $source = Source::factory()->create(['enabled' => true]);
    config()->set('news.source_adapters.'.$source->slug, FailingSourceAdapter::class);

    try {
        (new DiscoverSourceArticles($source->id))->handle(app(SourceAdapterRegistry::class), app(SourceIsDue::class));
        test()->fail('Expected adapter failure.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Discovery failed.');
    }

    expect($source->fresh()->last_fetched_at)->toBeNull();
});

class RecordingSourceAdapter implements SourceAdapter
{
    public array $sourceIds = [];

    public function discover(Source $source): void
    {
        $this->sourceIds[] = $source->id;
    }
}

class FailingSourceAdapter implements SourceAdapter
{
    public function discover(Source $source): void
    {
        throw new RuntimeException('Discovery failed.');
    }
}
