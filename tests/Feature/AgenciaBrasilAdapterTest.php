<?php

use App\Enums\ArticleStatus;
use App\Enums\StoryCategory;
use App\Models\Article;
use App\Models\Source;
use App\News\Deduplication\ContentFingerprint;
use App\News\Discovery\PublicDnsResolver;
use App\News\Sources\AgenciaBrasilAdapter;
use Database\Seeders\AgenciaBrasilSourceSeeder;
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

it('imports original Agência Brasil items from the official feed once', function () {
    $this->seed(AgenciaBrasilSourceSeeder::class);
    $source = Source::where('slug', 'agencia-brasil')->firstOrFail();
    $url = $source->feed_url;

    Http::fake([$url => Http::response(
        file_get_contents(base_path('tests/Fixtures/Sources/AgenciaBrasil/feed.xml')),
        200,
        ['Content-Type' => 'application/rss+xml; charset=utf-8'],
    )]);

    $adapter = app(AgenciaBrasilAdapter::class);
    $adapter->discover($source);
    $adapter->discover($source);

    $article = Article::sole();

    expect($article->canonical_url)->toBe('https://agenciabrasil.ebc.com.br/educacao/noticia/2026-09/bibliotecas-ampliam-horario')
        ->and($article->status)->toBe(ArticleStatus::Matched)
        ->and($article->category)->toBe(StoryCategory::Brasil)
        ->and($article->content)->toBe("As bibliotecas abrirão por mais tempo.\n\nO novo horário começa na próxima semana.")
        ->and($article->content_hash)->toBe(app(ContentFingerprint::class)->hash($article->content))
        ->and($article->published_at->toIso8601String())->toBe('2026-09-27T19:30:00+00:00');

    Http::assertSentCount(2);
});

it('rejects unofficial feed URLs before making a request', function () {
    Http::fake();
    $source = Source::factory()->create([
        'feed_url' => 'http://127.0.0.1/private.xml',
        'enabled' => true,
    ]);

    expect(fn () => app(AgenciaBrasilAdapter::class)->discover($source))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('rejects invalid XML and unexpected content types', function (string $body, string $contentType) {
    $this->seed(AgenciaBrasilSourceSeeder::class);
    $source = Source::where('slug', 'agencia-brasil')->firstOrFail();
    Http::fake([$source->feed_url => Http::response($body, 200, ['Content-Type' => $contentType])]);

    expect(fn () => app(AgenciaBrasilAdapter::class)->discover($source))
        ->toThrow(RuntimeException::class);

    expect(Article::count())->toBe(0);
})->with([
    ['not xml', 'application/rss+xml'],
    ['<html>not an RSS document</html>', 'application/rss+xml'],
    ['<html>not a feed</html>', 'text/html'],
]);
