<?php

use App\Models\Article;
use App\Models\Source;
use App\News\Discovery\PublicDnsResolver;
use App\News\Sources\AgenciaIbgeAdapter;
use Database\Seeders\AgenciaIbgeSourceSeeder;
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

it('imports only official IBGE releases through RSS once', function () {
    $this->seed(AgenciaIbgeSourceSeeder::class);
    $source = Source::where('slug', 'agencia-ibge')->firstOrFail();
    Http::fake([$source->feed_url => Http::response(
        file_get_contents(base_path('tests/Fixtures/Sources/AgenciaIbge/feed.xml')),
        200,
        ['Content-Type' => 'application/rss+xml; charset=utf-8'],
    )]);

    $adapter = app(AgenciaIbgeAdapter::class);
    $adapter->discover($source);
    $adapter->discover($source);

    $article = Article::sole();

    expect($article->canonical_url)->toBe('https://agenciadenoticias.ibge.gov.br/agencia-sala-de-imprensa/48134-o-ipca-15-e-de-0-70-em-setembro.html')
        ->and($article->title)->toBe('IPCA-15 é de 0,70% em setembro')
        ->and($article->content)->toBe("O índice foi divulgado hoje.\n\nConfira os dados completos.")
        ->and($article->author)->toBe('Equipe IBGE')
        ->and($article->category)->toBeNull()
        ->and($article->published_at->toIso8601String())->toBe('2026-09-28T15:00:00+00:00');

    Http::assertSentCount(2);
});

it('rejects a feed URL outside the official channel', function () {
    Http::fake();
    $source = Source::factory()->create(['feed_url' => 'https://example.com/feed.xml']);

    expect(fn () => app(AgenciaIbgeAdapter::class)->discover($source))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('rejects invalid RSS responses', function (string $body, string $contentType) {
    $this->seed(AgenciaIbgeSourceSeeder::class);
    $source = Source::where('slug', 'agencia-ibge')->firstOrFail();
    Http::fake([$source->feed_url => Http::response($body, 200, ['Content-Type' => $contentType])]);

    expect(fn () => app(AgenciaIbgeAdapter::class)->discover($source))
        ->toThrow(RuntimeException::class);

    expect(Article::count())->toBe(0);
})->with([
    ['not xml', 'application/rss+xml'],
    ['<rss/>', 'application/rss+xml'],
    ['<html>not a feed</html>', 'text/html'],
]);
