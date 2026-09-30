<?php

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Source;
use App\Models\Story;
use App\News\Contracts\SourceAdapter;
use App\News\Discovery\PublicDnsResolver;
use App\News\Sources\FolhaAdapter;
use App\News\Sources\G1Adapter;
use App\News\Sources\MeioAdapter;
use Database\Seeders\FolhaSourceSeeder;
use Database\Seeders\G1SourceSeeder;
use Database\Seeders\MeioSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(PublicDnsResolver::class, new class extends PublicDnsResolver
    {
        protected function lookup(string $host): array
        {
            return [['ip' => '93.184.215.14']];
        }
    });
});

dataset('headline feeds', [
    'Folha' => [FolhaSourceSeeder::class, FolhaAdapter::class, 'folha', 'Folha', 'https://www1.folha.uol.com.br/cotidiano/2026/09/bibliotecas-ampliam-horario-em-sao-paulo.shtml'],
    'G1' => [G1SourceSeeder::class, G1Adapter::class, 'g1', 'G1', 'https://g1.globo.com/sp/sao-paulo/noticia/2026/09/29/bibliotecas-ampliam-horario-em-sao-paulo.ghtml'],
    'Meio' => [MeioSourceSeeder::class, MeioAdapter::class, 'meio', 'Meio', 'https://www.canalmeio.com.br/2026/09/29/o-futuro-das-bibliotecas-brasileiras'],
]);

it('imports only own article headlines with direct links and no article text', function (string $seeder, string $adapter, string $slug, string $fixture, string $expectedUrl) {
    $this->seed($seeder);
    $source = Source::where('slug', $slug)->firstOrFail();
    Http::fake([$source->feed_url => Http::response(
        file_get_contents(base_path("tests/Fixtures/Sources/{$fixture}/feed.xml")),
        200,
        ['Content-Type' => 'application/rss+xml; charset=utf-8'],
    )]);

    $collector = app($adapter);
    expect($collector)->toBeInstanceOf(SourceAdapter::class);
    $collector->discover($source);
    $collector->discover($source);

    $article = Article::sole();

    expect($article->canonical_url)->toBe($expectedUrl)
        ->and($article->original_url)->toBe($expectedUrl)
        ->and($article->status)->toBe(ArticleStatus::Headline)
        ->and($article->content)->toBeNull()
        ->and($article->content_hash)->toBeNull()
        ->and(Story::count())->toBe(0);

    $this->artisan('news:match-articles')->assertSuccessful();
    expect(Story::count())->toBe(0);

    $this->get(route('stories.source', $slug))
        ->assertOk()
        ->assertSee($article->title)
        ->assertSee($expectedUrl);
    Http::assertSentCount(2);
})->with('headline feeds');

it('rejects a different feed URL before making a request', function (string $seeder, string $adapter, string $slug) {
    $this->seed($seeder);
    $source = Source::where('slug', $slug)->firstOrFail();
    $source->update(['feed_url' => 'https://127.0.0.1/private.xml']);
    Http::fake();

    expect(fn () => app($adapter)->discover($source))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
})->with('headline feeds');

it('rejects invalid XML and unexpected response types', function (string $seeder, string $adapter, string $slug) {
    $this->seed($seeder);
    $source = Source::where('slug', $slug)->firstOrFail();
    Http::fake([$source->feed_url => Http::response('<html>not a feed</html>', 200, ['Content-Type' => 'text/html'])]);

    expect(fn () => app($adapter)->discover($source))->toThrow(RuntimeException::class);
    expect(Article::count())->toBe(0);
})->with('headline feeds');
