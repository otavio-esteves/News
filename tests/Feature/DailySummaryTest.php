<?php

use App\Ai\Agents\DailySummaryWriter;
use App\Ai\GenerateDailySummary;
use App\Enums\ArticleStatus;
use App\Jobs\GenerateStoryDraftJob;
use App\Models\Article;
use App\Models\DailySummary;
use App\Models\Source;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('accumulates two-hour updates and publishes only editor-approved revisions', function () {
    config()->set('news.ai.story_writer_provider', 'openai');
    config()->set('news.ai.story_writer_model', 'gpt-4o-mini');
    config()->set('ai.providers.openai.key', 'test-key');
    $source = Source::factory()->create(['slug' => 'agencia-brasil', 'enabled' => true]);
    $first = Article::factory()->create([
        'source_id' => $source->id, 'status' => ArticleStatus::Processed,
        'content' => 'O relatório oficial apresentou novas medidas para bibliotecas.',
        'discovered_at' => CarbonImmutable::parse('2026-10-01 09:00 America/Sao_Paulo')->utc(),
        'fetched_at' => now(),
    ]);
    $second = Article::factory()->create([
        'source_id' => $source->id, 'status' => ArticleStatus::Processed,
        'content' => 'A secretaria anunciou mais recursos para escolas públicas.',
        'discovered_at' => CarbonImmutable::parse('2026-10-01 11:00 America/Sao_Paulo')->utc(),
        'fetched_at' => now(),
    ]);
    DailySummaryWriter::fake([
        ['paragraphs' => [['text' => 'O relatório trouxe medidas para bibliotecas.', 'article_ids' => [$first->id]]]],
        ['paragraphs' => [['text' => 'Também foram anunciados recursos para escolas.', 'article_ids' => [$second->id]]]],
    ]);
    $generator = app(GenerateDailySummary::class);
    $generator->handle(CarbonImmutable::parse('2026-10-01 10:00 America/Sao_Paulo'));
    $daily = DailySummary::sole();
    expect(DB::table('daily_summary_articles')->pluck('article_id')->all())->toBe([$first->id]);

    $this->get('/')->assertOk()->assertDontSee('O relatório trouxe medidas');

    $editor = User::factory()->create(['is_editor' => true]);
    $this->actingAs($editor)->post(route('admin.daily-summaries.approve', $daily))->assertRedirect();
    $this->get('/')->assertOk()->assertSee('Resumo diário')->assertSee('O relatório trouxe medidas');

    $generator->handle(CarbonImmutable::parse('2026-10-01 12:00 America/Sao_Paulo'));
    $daily->refresh();
    expect($daily->draft_blocks)->toHaveCount(2)
        ->and($daily->draft_blocks[0]['text'])->toBe('O relatório trouxe medidas para bibliotecas.')
        ->and($daily->published_blocks)->toHaveCount(1);
    $this->get('/')->assertOk()->assertDontSee('Também foram anunciados');

    $this->post(route('admin.daily-summaries.approve', $daily))->assertRedirect();
    expect($daily->fresh()->revisions()->count())->toBe(2)
        ->and($daily->revisions()->orderBy('version')->first()->blocks)->toHaveCount(1)
        ->and($daily->revisions()->orderBy('version')->first()->reference_snapshots[$first->id]['url'])->toBe($first->canonical_url);
    $this->get('/')->assertOk()->assertSee('O relatório trouxe medidas')
        ->assertSee('Também foram anunciados');
    DailySummaryWriter::assertPromptedTimes(2);
});

it('keeps the final midnight update with the previous day', function () {
    config()->set('news.ai.story_writer_provider', 'openai');
    config()->set('news.ai.story_writer_model', 'gpt-4o-mini');
    config()->set('ai.providers.openai.key', 'test-key');
    $source = Source::factory()->create(['slug' => 'agencia-brasil', 'enabled' => true]);
    $article = Article::factory()->create([
        'source_id' => $source->id, 'status' => ArticleStatus::Processed,
        'content' => 'O órgão divulgou dados à noite.',
        'discovered_at' => CarbonImmutable::parse('2026-10-01 23:00 America/Sao_Paulo')->utc(),
        'fetched_at' => now(),
    ]);
    DailySummaryWriter::fake([['paragraphs' => [[
        'text' => 'O órgão apresentou dados no fim do dia.', 'article_ids' => [$article->id],
    ]]]]);

    app(GenerateDailySummary::class)->handle(CarbonImmutable::parse('2026-10-02 00:00 America/Sao_Paulo'));

    expect(DailySummary::sole()->date->toDateString())->toBe('2026-10-01');
});

it('carries articles beyond the eight-article limit into later days', function () {
    config()->set('news.ai.story_writer_provider', 'openai');
    config()->set('news.ai.story_writer_model', 'gpt-4o-mini');
    config()->set('ai.providers.openai.key', 'test-key');
    $source = Source::factory()->create(['slug' => 'agencia-brasil', 'enabled' => true]);
    $articles = collect(range(1, 17))->map(fn (int $number): Article => Article::factory()->create([
        'source_id' => $source->id,
        'status' => ArticleStatus::Processed,
        'content' => "Notícia original número {$number}.",
        'discovered_at' => CarbonImmutable::parse('2026-10-01 09:00 America/Sao_Paulo')->addMinutes($number)->utc(),
        'fetched_at' => now(),
    ]));
    DailySummaryWriter::fake(collect([8, 16, 17])->map(fn (int $last): array => [
        'paragraphs' => [[
            'text' => "Resumo do artigo {$last}.",
            'article_ids' => [$articles[$last - 1]->id],
        ]],
    ])->all());

    $generator = app(GenerateDailySummary::class);
    $generator->handle(CarbonImmutable::parse('2026-10-01 22:00 America/Sao_Paulo'));
    $generator->handle(CarbonImmutable::parse('2026-10-02 00:00 America/Sao_Paulo'));
    $generator->handle(CarbonImmutable::parse('2026-10-02 02:00 America/Sao_Paulo'));

    expect(DailySummary::count())->toBe(2)
        ->and(DailySummary::whereDate('date', '2026-10-01')->firstOrFail()->draft_blocks)->toHaveCount(2)
        ->and(DailySummary::whereDate('date', '2026-10-02')->firstOrFail()->draft_blocks)->toHaveCount(1)
        ->and(DB::table('daily_summary_articles')->count())->toBe(17);
    DailySummaryWriter::assertPromptedTimes(3);
});

it('does not run queued summaries for individual stories while paused', function () {
    Queue::fake();
    $this->artisan('news:queue-drafts')->assertSuccessful();
    Queue::assertNotPushed(GenerateStoryDraftJob::class);
});

it('requires editorial approval and preserves the published version when an update is discarded', function () {
    $source = Source::factory()->create(['slug' => 'agencia-brasil', 'enabled' => true]);
    $article = Article::factory()->create(['source_id' => $source->id, 'status' => ArticleStatus::Processed]);
    $oldBlock = ['text' => 'Texto aprovado anteriormente.', 'article_ids' => [$article->id], 'slot_at' => now()->toIso8601String()];
    $newBlock = ['text' => 'Atualização ainda pendente.', 'article_ids' => [$article->id], 'slot_at' => now()->toIso8601String()];
    $daily = DailySummary::create([
        'date' => now()->toDateString(),
        'draft_blocks' => [$oldBlock, $newBlock],
        'draft_slot_at' => now(),
        'published_blocks' => [$oldBlock],
        'published_at' => now(),
    ]);

    $this->get('/')->assertOk()->assertDontSee('Texto aprovado anteriormente.');
    $this->post(route('admin.daily-summaries.approve', $daily))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['is_editor' => false]))
        ->post(route('admin.daily-summaries.approve', $daily))->assertForbidden();

    $editor = User::factory()->create(['is_editor' => true]);
    $daily->revisions()->create([
        'version' => 1, 'blocks' => [$oldBlock], 'published_by' => $editor->id, 'published_at' => now(),
    ]);
    $this->actingAs($editor)->post(route('admin.daily-summaries.reject', $daily))->assertRedirect();

    expect($daily->fresh()->draft_blocks)->toBeNull()
        ->and($daily->fresh()->published_blocks)->toBe([$oldBlock])
        ->and($daily->revisions()->count())->toBe(1);
    $this->get('/')->assertOk()->assertSee('Texto aprovado anteriormente.')
        ->assertDontSee('Atualização ainda pendente.');
});
