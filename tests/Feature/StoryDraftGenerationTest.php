<?php

use App\Ai\Agents\StoryWriter;
use App\Enums\ArticleStatus;
use App\Enums\StoryCategory;
use App\Models\AiRun;
use App\Models\Article;
use App\Models\Source;
use App\Models\Story;
use App\Models\StoryDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function draftStoryWithArticle(): array
{
    $source = Source::factory()->create(['slug' => 'agencia-brasil', 'enabled' => true]);
    $story = Story::factory()->create(['category' => StoryCategory::Politica]);
    $article = Article::factory()->create([
        'source_id' => $source->id,
        'status' => ArticleStatus::Matched,
        'title' => 'Senado aprova medida sobre bibliotecas',
        'content' => 'O Senado aprovou a medida em votação nesta terça-feira.',
    ]);
    $story->articles()->attach($article);

    return [$story, $article];
}

it('generates a referenced draft and records the run without publishing', function () {
    [$story, $article] = draftStoryWithArticle();
    config()->set('ai.providers.openai.key', 'test-key');
    StoryWriter::fake([[
        'title' => 'Senado aprova medida para bibliotecas',
        'category' => 'politica',
        'paragraphs' => [[
            'text' => 'O Senado aprovou uma medida sobre bibliotecas.',
            'article_ids' => [$article->id],
        ]],
    ]]);

    $this->artisan('news:write-draft', ['story' => $story->id])->assertSuccessful();

    $draft = StoryDraft::sole();
    $run = AiRun::sole();

    expect($draft->story_id)->toBe($story->id)
        ->and($draft->summary_blocks)->toBe([[
            'text' => 'O Senado aprovou uma medida sobre bibliotecas.',
            'article_ids' => [$article->id],
        ]])
        ->and($draft->ai_run_id)->toBe($run->id)
        ->and($run->status)->toBe('completed')
        ->and($run->input_hash)->toHaveLength(64)
        ->and($story->fresh()->title)->toBeNull();

    $this->get('/')->assertOk()->assertDontSee($draft->title);
    StoryWriter::assertPromptedTimes(1);
});

it('rejects references outside the supplied Story and records validation failure', function () {
    [$story] = draftStoryWithArticle();
    $foreign = Article::factory()->create();
    config()->set('ai.providers.openai.key', 'test-key');
    StoryWriter::fake([[
        'title' => 'Título gerado',
        'category' => 'politica',
        'paragraphs' => [[
            'text' => 'Texto sem uma referência permitida.',
            'article_ids' => [$foreign->id],
        ]],
    ]]);

    $this->artisan('news:write-draft', ['story' => $story->id])->assertFailed();

    expect(StoryDraft::count())->toBe(0)
        ->and(AiRun::sole()->status)->toBe('failed')
        ->and(AiRun::sole()->error)->toContain('fora do contexto');
});

it('rejects a category change proposed by the model', function () {
    [$story, $article] = draftStoryWithArticle();
    config()->set('ai.providers.openai.key', 'test-key');
    StoryWriter::fake([[
        'title' => 'Título gerado',
        'category' => 'economia',
        'paragraphs' => [[
            'text' => 'O Senado aprovou uma medida.',
            'article_ids' => [$article->id],
        ]],
    ]]);

    $this->artisan('news:write-draft', ['story' => $story->id])->assertFailed();

    expect(StoryDraft::count())->toBe(0)
        ->and(AiRun::sole()->status)->toBe('failed');
});

it('requires a configured key and never overwrites an existing draft', function () {
    [$story] = draftStoryWithArticle();
    config()->set('ai.providers.openai.key', null);
    StoryWriter::fake();

    $this->artisan('news:write-draft', ['story' => $story->id])->assertFailed();
    expect(AiRun::count())->toBe(0);

    StoryDraft::factory()->create(['story_id' => $story->id, 'title' => 'Texto revisado pelo editor']);
    config()->set('ai.providers.openai.key', 'test-key');

    $this->artisan('news:write-draft', ['story' => $story->id])->assertFailed();

    expect(StoryDraft::sole()->title)->toBe('Texto revisado pelo editor')
        ->and(AiRun::count())->toBe(0);
    StoryWriter::assertNeverPrompted();
});
