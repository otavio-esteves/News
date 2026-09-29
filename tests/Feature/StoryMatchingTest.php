<?php

use App\Enums\ArticleStatus;
use App\Enums\StoryCategory;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\Source;
use App\Models\Story;
use App\News\Deduplication\ArticleIngestor;
use App\News\Matching\ArticleStoryMatcher;
use App\News\Matching\StoryMatchOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function matchingSources(): array
{
    return [
        Source::factory()->create(['slug' => 'agencia-brasil', 'enabled' => true]),
        Source::factory()->create(['slug' => 'agencia-senado', 'enabled' => true]),
    ];
}

it('groups unmistakably matching headlines from two real sources without publishing the story', function () {
    [$brasil, $senado] = matchingSources();
    $ingestor = app(ArticleIngestor::class);
    $publishedAt = now();

    $first = $ingestor->store(
        $brasil,
        'https://agenciabrasil.ebc.com.br/politica/noticia/2026-09/projeto-bibliotecas',
        'https://agenciabrasil.ebc.com.br/politica/noticia/2026-09/projeto-bibliotecas',
        'Senado aprova projeto de proteção das bibliotecas públicas',
        'A proposta cria novas regras para bibliotecas.',
        $publishedAt,
    );
    $second = $ingestor->store(
        $senado,
        'https://www12.senado.leg.br/noticias/materias/2026/09/27/projeto-bibliotecas',
        'https://www12.senado.leg.br/noticias/materias/2026/09/27/projeto-bibliotecas',
        'Projeto de proteção das bibliotecas públicas: Senado aprova',
        'Os senadores aprovaram o texto em votação.',
        $publishedAt->copy()->addHour(),
    );

    $story = Story::sole();

    expect($story->status)->toBe(StoryStatus::Draft)
        ->and($story->category)->toBe(StoryCategory::Politica)
        ->and($story->articles()->count())->toBe(2)
        ->and($first->status)->toBe(ArticleStatus::Matched)
        ->and($second->status)->toBe(ArticleStatus::Matched)
        ->and(app(ArticleStoryMatcher::class)->match($second))->toBe(StoryMatchOutcome::AlreadyMatched);

    $this->get('/')->assertOk()->assertSee($first->title)->assertSee($second->title);
});

it('leaves a similar but different event pending instead of merging it', function () {
    [$brasil, $senado] = matchingSources();
    $ingestor = app(ArticleIngestor::class);
    $publishedAt = now();
    $ingestor->store(
        $brasil,
        'https://agenciabrasil.ebc.com.br/politica/noticia/2026-09/bibliotecas',
        'https://agenciabrasil.ebc.com.br/politica/noticia/2026-09/bibliotecas',
        'Senado aprova projeto de proteção das bibliotecas públicas',
        'Texto sobre bibliotecas.',
        $publishedAt,
    );
    $different = $ingestor->store(
        $senado,
        'https://www12.senado.leg.br/noticias/materias/2026/09/27/escolas',
        'https://www12.senado.leg.br/noticias/materias/2026/09/27/escolas',
        'Senado aprova projeto de proteção das escolas públicas',
        'Texto sobre escolas.',
        $publishedAt,
    );

    expect(Story::count())->toBe(1)
        ->and($different->status)->toBe(ArticleStatus::Processed)
        ->and($different->stories()->count())->toBe(0)
        ->and(app(ArticleStoryMatcher::class)->match($different))->toBe(StoryMatchOutcome::Pending);
});

it('creates separate drafts outside the time window and leaves tied candidates pending', function () {
    [$brasil, $senado] = matchingSources();
    $ingestor = app(ArticleIngestor::class);
    $old = $ingestor->store(
        $brasil,
        'https://agenciabrasil.ebc.com.br/politica/noticia/2026-09/votacao-antiga',
        'https://agenciabrasil.ebc.com.br/politica/noticia/2026-09/votacao-antiga',
        'Câmara aprova nova lei de proteção ambiental nacional',
        'Votação anterior.',
        now()->subDays(4),
    );
    $recent = $ingestor->store(
        $senado,
        'https://www12.senado.leg.br/noticias/materias/2026/09/27/votacao-recente',
        'https://www12.senado.leg.br/noticias/materias/2026/09/27/votacao-recente',
        'Câmara aprova nova lei de proteção ambiental nacional',
        'Outra votação.',
        now(),
    );

    expect(Story::count())->toBe(2)
        ->and($old->stories()->first()->id)->not->toBe($recent->stories()->first()->id);

    $candidate = Article::factory()->create([
        'source_id' => $brasil->id,
        'title' => 'Nova lei de proteção ambiental nacional é aprovada pela Câmara',
        'category' => StoryCategory::Politica,
        'published_at' => now(),
        'status' => ArticleStatus::Processed,
    ]);
    $duplicate = Story::factory()->create([
        'category' => StoryCategory::Politica,
        'first_seen_at' => now(),
        'last_updated_at' => now(),
    ]);
    $duplicate->articles()->attach(Article::factory()->create([
        'source_id' => $brasil->id,
        'title' => 'Câmara aprova nova lei de proteção ambiental nacional',
        'category' => StoryCategory::Politica,
        'status' => ArticleStatus::Matched,
    ]));

    expect(app(ArticleStoryMatcher::class)->match($candidate))->toBe(StoryMatchOutcome::Pending)
        ->and($candidate->stories()->count())->toBe(0);
});

it('backfills unmatched articles through the matching command', function () {
    [$brasil] = matchingSources();
    $article = Article::factory()->create([
        'source_id' => $brasil->id,
        'category' => StoryCategory::Cultura,
        'status' => ArticleStatus::Processed,
    ]);

    $this->artisan('news:match-articles')->assertSuccessful();

    expect($article->fresh()->status)->toBe(ArticleStatus::Matched)
        ->and(Story::count())->toBe(1);

    $this->artisan('news:match-articles')->assertSuccessful();
    expect(Story::count())->toBe(1);
});

it('never changes a published story when a similar new article arrives', function () {
    [$brasil, $senado] = matchingSources();
    $original = Article::factory()->create([
        'source_id' => $brasil->id,
        'title' => 'Senado aprova projeto de proteção das bibliotecas públicas',
        'category' => StoryCategory::Politica,
        'status' => ArticleStatus::Matched,
    ]);
    $published = Story::factory()->published()->create([
        'category' => StoryCategory::Politica,
        'first_seen_at' => now(),
    ]);
    $published->articles()->attach($original);

    $incoming = app(ArticleIngestor::class)->store(
        $senado,
        'https://www12.senado.leg.br/noticias/materias/2026/09/27/bibliotecas',
        'https://www12.senado.leg.br/noticias/materias/2026/09/27/bibliotecas',
        'Projeto de proteção das bibliotecas públicas: Senado aprova',
        'Nova cobertura da votação.',
        now(),
    );

    expect($published->articles()->count())->toBe(1)
        ->and($incoming->stories()->first()->id)->not->toBe($published->id)
        ->and(Story::where('status', StoryStatus::Draft)->count())->toBe(1);
});

it('considers a matching draft beyond the hundred most recent drafts', function () {
    [$source] = matchingSources();
    $article = Article::factory()->create([
        'source_id' => $source->id,
        'title' => 'Senado aprova projeto de proteção das bibliotecas públicas',
        'category' => StoryCategory::Politica,
        'published_at' => now(),
        'status' => ArticleStatus::Processed,
    ]);
    $matching = Story::factory()->create([
        'category' => StoryCategory::Politica,
        'first_seen_at' => now()->subHours(12),
        'last_updated_at' => now()->subHours(12),
    ]);
    $matching->articles()->attach(Article::factory()->create([
        'source_id' => $source->id,
        'title' => $article->title,
        'status' => ArticleStatus::Matched,
    ]));

    for ($index = 0; $index < 101; $index++) {
        $other = Story::factory()->create([
            'category' => StoryCategory::Politica,
            'first_seen_at' => now()->subMinutes($index),
            'last_updated_at' => now()->subMinutes($index),
        ]);
        $other->articles()->attach(Article::factory()->create([
            'source_id' => $source->id,
            'title' => "Assunto sem relação {$index} na cobertura diária do Senado",
            'status' => ArticleStatus::Matched,
        ]));
    }

    expect(app(ArticleStoryMatcher::class)->match($article))->toBe(StoryMatchOutcome::Matched)
        ->and($article->stories()->firstOrFail()->id)->toBe($matching->id);
});
