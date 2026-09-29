<?php

use App\Enums\StoryCategory;
use App\Models\Article;
use App\Models\Source;
use App\News\Discovery\PublicDnsResolver;
use App\News\Sources\RadioagenciaNacionalAdapter;
use Database\Seeders\RadioagenciaNacionalSourceSeeder;
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

it('imports only Radioagência Nacional text with the reporter credit once', function () {
    $this->seed(RadioagenciaNacionalSourceSeeder::class);
    $source = Source::where('slug', 'radioagencia-nacional')->firstOrFail();
    Http::fake([$source->feed_url => Http::response(
        file_get_contents(base_path('tests/Fixtures/Sources/RadioagenciaNacional/feed.xml')),
        200,
        ['Content-Type' => 'application/rss+xml; charset=utf-8'],
    )]);

    $adapter = app(RadioagenciaNacionalAdapter::class);
    $adapter->discover($source);
    $adapter->discover($source);

    $article = Article::sole();

    expect($article->canonical_url)->toBe('https://agenciabrasil.ebc.com.br/radioagencia-nacional/saude/audio/2026-09/pesquisa-amplia-atendimento-em-saude')
        ->and($article->category)->toBe(StoryCategory::Brasil)
        ->and($article->author)->toBe('Ana Silva - Repórter da Rádio Nacional')
        ->and($article->content)->toBe("A pesquisa ampliou o atendimento.\n\nOs resultados serão publicados amanhã.")
        ->and($article->published_at->toIso8601String())->toBe('2026-09-28T19:30:00+00:00');

    Http::assertSentCount(2);
});

it('rejects a feed URL outside the official channel', function () {
    Http::fake();
    $source = Source::factory()->create(['feed_url' => 'https://example.com/feed.xml']);

    expect(fn () => app(RadioagenciaNacionalAdapter::class)->discover($source))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('rejects invalid RSS responses', function (string $body, string $contentType) {
    $this->seed(RadioagenciaNacionalSourceSeeder::class);
    $source = Source::where('slug', 'radioagencia-nacional')->firstOrFail();
    Http::fake([$source->feed_url => Http::response($body, 200, ['Content-Type' => $contentType])]);

    expect(fn () => app(RadioagenciaNacionalAdapter::class)->discover($source))
        ->toThrow(RuntimeException::class);

    expect(Article::count())->toBe(0);
})->with([
    ['not xml', 'application/rss+xml'],
    ['<rss/>', 'application/rss+xml'],
    ['<html>not a feed</html>', 'text/html'],
]);
