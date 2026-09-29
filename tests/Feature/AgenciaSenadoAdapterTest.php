<?php

use App\Enums\StoryCategory;
use App\Models\Article;
use App\Models\Source;
use App\News\Deduplication\ArticleIngestor;
use App\News\Discovery\PublicDnsResolver;
use App\News\Sources\AgenciaSenadoAdapter;
use Database\Seeders\AgenciaBrasilSourceSeeder;
use Database\Seeders\AgenciaSenadoSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
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

it('imports distinct Senado articles and preserves coverage from another source', function () {
    $this->seed(AgenciaBrasilSourceSeeder::class);
    $this->seed(AgenciaSenadoSourceSeeder::class);

    $brasil = Source::where('slug', 'agencia-brasil')->firstOrFail();
    $senado = Source::where('slug', 'agencia-senado')->firstOrFail();

    app(ArticleIngestor::class)->store(
        $brasil,
        'https://agenciabrasil.ebc.com.br/educacao/noticia/2026-09/bibliotecas-ampliam-horario',
        'https://agenciabrasil.ebc.com.br/educacao/noticia/2026-09/bibliotecas-ampliam-horario',
        'Bibliotecas ampliam horário',
        'A comissão debateu o horário de bibliotecas públicas.',
        now(),
    );

    $feed = $senado->feed_url;
    $libraries = 'https://www12.senado.leg.br/noticias/materias/2026/09/27/senado-debate-ampliacao-de-bibliotecas';
    $schools = 'https://www12.senado.leg.br/noticias/materias/2026/09/27/senado-debate-ampliacao-de-escolas';

    Http::fake([
        $feed => Http::response(file_get_contents(base_path('tests/Fixtures/Sources/AgenciaSenado/feed.xml')), 200, ['Content-Type' => 'application/rss+xml']),
        $libraries => Http::response(file_get_contents(base_path('tests/Fixtures/Sources/AgenciaSenado/article-bibliotecas.html')), 200, ['Content-Type' => 'text/html']),
        $schools => Http::response(file_get_contents(base_path('tests/Fixtures/Sources/AgenciaSenado/article-escolas.html')), 200, ['Content-Type' => 'text/html']),
    ]);

    $adapter = app(AgenciaSenadoAdapter::class);
    $adapter->discover($senado);
    $adapter->discover($senado);

    expect(Article::count())->toBe(3)
        ->and(Article::where('source_id', $senado->id)->count())->toBe(2)
        ->and(Article::where('source_id', $senado->id)->firstOrFail()->category)->toBe(StoryCategory::Politica)
        ->and(Article::where('source_id', $senado->id)->where('canonical_url', $libraries)->value('content'))
        ->toBe("A comissão debateu o horário de bibliotecas públicas.\n\nO projeto seguirá para outra votação.")
        ->and(Article::where('source_id', $senado->id)->where('canonical_url', $schools)->value('author'))
        ->toBe('Marina Silva');

    Http::assertSentCount(4);
});

it('rejects an unapproved Senate feed URL', function () {
    Http::fake();
    $source = Source::factory()->create(['feed_url' => 'https://example.org/rss.xml']);

    expect(fn () => app(AgenciaSenadoAdapter::class)->discover($source))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('limits article page requests in one run', function () {
    $this->seed(AgenciaSenadoSourceSeeder::class);
    $source = Source::where('slug', 'agencia-senado')->firstOrFail();
    $feed = file_get_contents(base_path('tests/Fixtures/Sources/AgenciaSenado/feed.xml'));
    $extra = '';

    foreach ([3, 4] as $number) {
        $extra .= "<item><title>Matéria {$number}</title><pubDate>Sun, 27 Sep 2026 16:00:00 -0300</pubDate><guid>https://www12.senado.leg.br/noticias/materias/2026/09/27/materia-{$number}</guid></item>";
    }

    Http::fake([
        $source->feed_url => Http::response(str_replace('</channel>', $extra.'</channel>', $feed), 200, ['Content-Type' => 'application/rss+xml']),
        'https://www12.senado.leg.br/noticias/materias/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/Sources/AgenciaSenado/article-bibliotecas.html')),
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    app(AgenciaSenadoAdapter::class)->discover($source);

    Http::assertSentCount(4);
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/materia-4'));
});

it('continues after one article fails and leaves discovery due for a retry', function () {
    $this->seed(AgenciaSenadoSourceSeeder::class);
    $source = Source::where('slug', 'agencia-senado')->firstOrFail();
    $libraries = 'https://www12.senado.leg.br/noticias/materias/2026/09/27/senado-debate-ampliacao-de-bibliotecas';
    $schools = 'https://www12.senado.leg.br/noticias/materias/2026/09/27/senado-debate-ampliacao-de-escolas';

    Http::fake([
        $source->feed_url => Http::response(file_get_contents(base_path('tests/Fixtures/Sources/AgenciaSenado/feed.xml')), 200, ['Content-Type' => 'application/rss+xml']),
        $libraries => Http::response('Temporarily unavailable', 503, ['Content-Type' => 'text/html']),
        $schools => Http::response(file_get_contents(base_path('tests/Fixtures/Sources/AgenciaSenado/article-escolas.html')), 200, ['Content-Type' => 'text/html']),
    ]);

    expect(fn () => app(AgenciaSenadoAdapter::class)->discover($source))
        ->toThrow(RequestException::class);

    expect(Article::where('canonical_url', $schools)->exists())->toBeTrue()
        ->and(Article::where('canonical_url', $libraries)->exists())->toBeFalse()
        ->and($source->fresh()->last_fetched_at)->toBeNull();

    Http::assertSentCount(3);
});
