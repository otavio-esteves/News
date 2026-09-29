<?php

use App\Enums\StoryCategory;
use App\Models\Article;
use App\Models\Source;
use App\News\Discovery\PublicDnsResolver;
use App\News\Sources\AgenciaCamaraAdapter;
use Database\Seeders\AgenciaCamaraSourceSeeder;
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

it('imports and deduplicates only Agência Câmara articles from its official RSS', function () {
    $this->seed(AgenciaCamaraSourceSeeder::class);
    $source = Source::where('slug', 'agencia-camara')->firstOrFail();

    Http::fake([$source->feed_url => Http::response(
        file_get_contents(base_path('tests/Fixtures/Sources/AgenciaCamara/feed.xml')),
        200,
        ['Content-Type' => 'text/xml; charset=utf-8'],
    )]);

    $adapter = app(AgenciaCamaraAdapter::class);
    $adapter->discover($source);
    $adapter->discover($source);

    $article = Article::where('title', 'Câmara debate proteção das bibliotecas públicas')->firstOrFail();

    expect(Article::count())->toBe(2)
        ->and($article->canonical_url)->toBe('https://www.camara.leg.br/noticias/1306355-camara-debate-protecao-das-bibliotecas-publicas')
        ->and($article->author)->toBe('Agência Câmara')
        ->and($article->category)->toBe(StoryCategory::Politica)
        ->and($article->content)->toBe("A comissão discutiu uma proposta para as bibliotecas.\n\nO texto seguirá para votação.")
        ->and($article->published_at->toIso8601String())->toBe('2026-09-28T21:04:00+00:00');

    Http::assertSentCount(2);
});

it('rejects an unapproved feed URL before making a request', function () {
    Http::fake();
    $source = Source::factory()->create(['feed_url' => 'https://example.org/rss.xml']);

    expect(fn () => app(AgenciaCamaraAdapter::class)->discover($source))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('rejects invalid XML and unexpected response types', function (string $body, string $contentType) {
    $this->seed(AgenciaCamaraSourceSeeder::class);
    $source = Source::where('slug', 'agencia-camara')->firstOrFail();
    Http::fake([$source->feed_url => Http::response($body, 200, ['Content-Type' => $contentType])]);

    expect(fn () => app(AgenciaCamaraAdapter::class)->discover($source))
        ->toThrow(RuntimeException::class);
})->with([
    ['not xml', 'text/xml'],
    ['<html>not an RSS document</html>', 'text/xml'],
    ['<rss/>', 'text/html'],
]);
