<?php

use App\Models\Article;
use App\Models\Source;
use App\News\Discovery\PublicDnsResolver;
use App\News\Sources\AgenciaCnjAdapter;
use Database\Seeders\AgenciaCnjSourceSeeder;
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

it('imports only CNJ-authored news through the official API once', function () {
    $this->seed(AgenciaCnjSourceSeeder::class);
    $source = Source::where('slug', 'agencia-cnj')->firstOrFail();
    Http::fake([$source->feed_url => Http::response(
        file_get_contents(base_path('tests/Fixtures/Sources/AgenciaCnj/posts.json')),
        200,
        ['Content-Type' => 'application/json; charset=UTF-8'],
    )]);

    $adapter = app(AgenciaCnjAdapter::class);
    $adapter->discover($source);
    $adapter->discover($source);

    $article = Article::sole();

    expect($article->canonical_url)->toBe('https://www.cnj.jus.br/conciliar-e-legal-premiacao-sera-entregue-pelo-cnj')
        ->and($article->title)->toBe('Premiação & conciliação no CNJ')
        ->and($article->content)->toBe("O CNJ anunciou a premiação.\n\nA cerimônia será realizada nesta semana.")
        ->and($article->author)->toBe('Agência CNJ de Notícias')
        ->and($article->category)->toBeNull()
        ->and($article->published_at->toIso8601String())->toBe('2026-09-28T18:59:04+00:00');

    Http::assertSentCount(2);
});

it('rejects an API URL outside the exact official query', function () {
    Http::fake();
    $source = Source::factory()->create(['feed_url' => 'https://www.cnj.jus.br/wp-json/wp/v2/users']);

    expect(fn () => app(AgenciaCnjAdapter::class)->discover($source))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('rejects malformed API responses', function (string $body, string $contentType) {
    $this->seed(AgenciaCnjSourceSeeder::class);
    $source = Source::where('slug', 'agencia-cnj')->firstOrFail();
    Http::fake([$source->feed_url => Http::response($body, 200, ['Content-Type' => $contentType])]);

    expect(fn () => app(AgenciaCnjAdapter::class)->discover($source))
        ->toThrow(RuntimeException::class);

    expect(Article::count())->toBe(0);
})->with([
    ['not json', 'application/json'],
    ['{"error":"not a list"}', 'application/json'],
    ['<html>not JSON</html>', 'text/html'],
]);
