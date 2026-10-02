<?php

use App\Ai\Agents\StoryWriter;
use App\Enums\ArticleStatus;
use App\Enums\StoryCategory;
use App\Jobs\GenerateStoryDraftJob;
use App\Models\AiRun;
use App\Models\Article;
use App\Models\Source;
use App\Models\Story;
use App\Models\StoryDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('news.ai.story_writer_provider', 'openai');
    config()->set('news.ai.story_writer_model', 'gpt-4o-mini');
});

function draftStoryWithArticle(): array
{
    $source = Source::where('slug', 'agencia-brasil')->first()
        ?? Source::factory()->create(['slug' => 'agencia-brasil', 'enabled' => true]);
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

it('rejects long passages copied from a source article', function () {
    [$story, $article] = draftStoryWithArticle();
    $article->update(['content' => 'A agência informou que o Senado aprovou uma medida nesta terça-feira para ampliar o horário de funcionamento das bibliotecas públicas em cidades brasileiras.']);
    config()->set('ai.providers.openai.key', 'test-key');
    StoryWriter::fake([[
        'title' => 'Senado aprova medida para bibliotecas',
        'category' => 'politica',
        'paragraphs' => [[
            'text' => 'O Senado aprovou uma medida nesta terça-feira para ampliar o horário de funcionamento das bibliotecas públicas em cidades brasileiras.',
            'article_ids' => [$article->id],
        ]],
    ]]);

    $this->artisan('news:write-draft', ['story' => $story->id])->assertFailed();

    expect(StoryDraft::count())->toBe(0)
        ->and(AiRun::sole()->error)->toContain('copia um trecho longo');
});

it('detects copied passages combined from different articles', function () {
    [$story, $first] = draftStoryWithArticle();
    $first->update(['content' => 'O relatório confirmou nesta terça a abertura de vinte bibliotecas públicas em várias cidades brasileiras.']);
    $second = Article::factory()->create([
        'source_id' => $first->source_id,
        'content' => 'A secretaria informou que todas as unidades terão horário ampliado durante o mês de outubro.',
    ]);
    $story->articles()->attach($second);
    config()->set('ai.providers.openai.key', 'test-key');
    StoryWriter::fake([[
        'title' => 'Bibliotecas terão horário ampliado',
        'category' => 'politica',
        'paragraphs' => [[
            'text' => 'O relatório confirmou nesta terça a abertura de vinte bibliotecas públicas; além disso, a secretaria informou que todas as unidades terão horário ampliado, conforme os dados divulgados.',
            'article_ids' => [$first->id, $second->id],
        ]],
    ]]);

    $this->artisan('news:write-draft', ['story' => $story->id])->assertFailed();

    expect(StoryDraft::count())->toBe(0)
        ->and(AiRun::sole()->error)->toContain('copia um trecho longo');
});

it('keeps internal article IDs out of the summary text', function () {
    [$story, $article] = draftStoryWithArticle();
    config()->set('ai.providers.openai.key', 'test-key');
    StoryWriter::fake([[
        'title' => 'Senado aprova medida para bibliotecas',
        'category' => 'politica',
        'paragraphs' => [[
            'text' => "O Senado aprovou a medida (id: {$article->id}).",
            'article_ids' => [$article->id],
        ]],
    ]]);

    $this->artisan('news:write-draft', ['story' => $story->id])->assertFailed();

    expect(StoryDraft::count())->toBe(0)
        ->and(AiRun::sole()->error)->toContain('apenas em article_ids');
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

it('generates through the local provider without an OpenAI key', function () {
    [$story, $article] = draftStoryWithArticle();
    config()->set('news.ai.story_writer_provider', 'ollama');
    config()->set('news.ai.story_writer_model', 'qwen3:1.7b');
    config()->set('ai.providers.openai.key', null);
    config()->set('ai.providers.ollama.url', 'http://ollama:11434');
    StoryWriter::fake([[
        'title' => 'Senado aprova medida para bibliotecas',
        'category' => 'politica',
        'paragraphs' => [[
            'text' => 'O Senado aprovou uma medida sobre bibliotecas.',
            'article_ids' => [$article->id],
        ]],
    ]]);

    $this->artisan('news:write-draft', ['story' => $story->id])->assertSuccessful();

    expect(AiRun::sole()->provider)->toBe('ollama')
        ->and(AiRun::sole()->model)->toBe('qwen3:1.7b')
        ->and(StoryDraft::sole()->story_id)->toBe($story->id);
});

it('queues only eligible unpublished Stories on the dedicated AI queue', function () {
    config()->set('news.ai.story_summaries_enabled', true);
    [$eligible] = draftStoryWithArticle();
    [$alreadyDrafted] = draftStoryWithArticle();
    StoryDraft::factory()->create(['story_id' => $alreadyDrafted->id]);
    [$headlineOnly, $article] = draftStoryWithArticle();
    $article->update(['content' => null]);
    config()->set('news.ai.story_writer_provider', 'ollama');
    Queue::fake();

    $this->artisan('news:queue-drafts', ['--limit' => '5'])->assertSuccessful();

    Queue::assertPushedOn('ai', GenerateStoryDraftJob::class);
    Queue::assertPushed(GenerateStoryDraftJob::class, 1);
    Queue::assertPushed(GenerateStoryDraftJob::class, fn (GenerateStoryDraftJob $job): bool => $job->storyId === $eligible->id);

    $this->artisan('news:write-draft', ['story' => $headlineOnly->id, '--queue' => true])->assertSuccessful();
    Queue::assertPushed(GenerateStoryDraftJob::class, 2);
});
