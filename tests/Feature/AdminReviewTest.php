<?php

use App\Enums\ArticleStatus;
use App\Enums\StoryCategory;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\Source;
use App\Models\Story;
use App\Models\StoryDraft;
use App\Models\StoryRevision;
use App\Models\User;
use App\News\Editorial\ReviewStoryDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reviewableDraft(): array
{
    $source = Source::factory()->create(['slug' => 'agencia-brasil', 'name' => 'Agência Brasil', 'enabled' => true]);
    $story = Story::factory()->create(['category' => StoryCategory::Brasil]);
    $article = Article::factory()->create([
        'source_id' => $source->id,
        'status' => ArticleStatus::Matched,
        'title' => 'Matéria verificável',
        'canonical_url' => 'https://agenciabrasil.ebc.com.br/geral/noticia/2026-09/materia-verificavel',
        'content' => 'A agência publicou os dados oficiais nesta quarta-feira.',
    ]);
    $story->articles()->attach($article);
    $draft = StoryDraft::factory()->create([
        'story_id' => $story->id,
        'title' => 'Resumo para revisão',
        'category' => StoryCategory::Brasil,
        'summary_blocks' => [['text' => 'Dados oficiais foram divulgados nesta quarta-feira.', 'article_ids' => [$article->id]]],
    ]);

    return [$draft, $story, $article];
}

it('requires editor authentication for the review queue and decisions', function () {
    [$draft] = reviewableDraft();

    $this->get('/admin/stories')->assertRedirect('/admin/login');
    $this->post(route('admin.stories.approve', $draft))->assertRedirect('/admin/login');

    $user = User::factory()->create();
    $this->actingAs($user)->get('/admin/stories')->assertForbidden();
    $this->actingAs($user)->post(route('admin.stories.approve', $draft))->assertForbidden();
    expect(StoryRevision::count())->toBe(0);
});

it('logs in an editor and shows pending summaries with their source references', function () {
    [$draft] = reviewableDraft();
    $editor = User::factory()->create(['email' => 'editor@news.test', 'password' => 'senha-segura']);
    $editor->forceFill(['is_editor' => true])->save();

    $this->post('/admin/login', ['email' => $editor->email, 'password' => 'senha-segura'])
        ->assertRedirect(route('admin.stories.index'));
    $this->get(route('admin.stories.index'))->assertOk()->assertSee($draft->title);
    $this->get(route('admin.stories.show', $draft))->assertOk()
        ->assertSee('Matéria verificável')->assertSee('Aprovar e publicar');
});

it('does not allow a regular user to log in as an editor', function () {
    $user = User::factory()->create(['email' => 'leitor@news.test', 'password' => 'senha-segura']);

    $this->post('/admin/login', ['email' => $user->email, 'password' => 'senha-segura'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('publishes an approved summary and preserves a revision with its references', function () {
    [$draft, $story, $article] = reviewableDraft();
    $editor = User::factory()->create();
    $editor->forceFill(['is_editor' => true])->save();

    $this->get('/')->assertDontSee($draft->title);
    $this->actingAs($editor)->post(route('admin.stories.approve', $draft))
        ->assertRedirect(route('admin.stories.index'));

    $story->refresh();
    $revision = StoryRevision::sole();
    expect($story->status)->toBe(StoryStatus::Published)
        ->and($revision->version)->toBe(1)
        ->and($revision->published_by)->toBe($editor->id)
        ->and($revision->summary_blocks)->toBe($story->summary_blocks)
        ->and($revision->reference_snapshots[$article->id]['url'])->toBe($article->canonical_url)
        ->and(StoryDraft::count())->toBe(0);

    $this->get(route('stories.show', $story->slug))->assertOk()->assertSee($draft->title);
    $this->actingAs($editor)->post(route('admin.stories.approve', $draft))->assertNotFound();
});

it('records rejection without publishing and keeps the draft available to editors', function () {
    [$draft, $story] = reviewableDraft();
    $editor = User::factory()->create();
    $editor->forceFill(['is_editor' => true])->save();

    $this->actingAs($editor)->post(route('admin.stories.reject', $draft), ['reason' => 'Afirmação sem apoio na fonte.'])
        ->assertRedirect(route('admin.stories.index'));

    expect($draft->fresh()->review_status)->toBe('rejected')
        ->and($draft->fresh()->reviewed_by)->toBe($editor->id)
        ->and($story->fresh()->status)->toBe(StoryStatus::Draft)
        ->and(StoryRevision::count())->toBe(0);
    $this->get(route('admin.stories.index', ['status' => 'rejected']))->assertSee($draft->title);
    $this->post(route('admin.stories.approve', $draft))->assertSessionHasErrors('review');
});

it('revalidates a draft and blocks stale references before publication', function () {
    [$draft, $story, $article] = reviewableDraft();
    $editor = User::factory()->create();
    $editor->forceFill(['is_editor' => true])->save();
    $story->articles()->detach($article);

    $this->actingAs($editor)->post(route('admin.stories.approve', $draft))
        ->assertSessionHasErrors('review');
    expect($story->fresh()->status)->toBe(StoryStatus::Draft)
        ->and(StoryRevision::count())->toBe(0)
        ->and(StoryDraft::count())->toBe(1);
});

it('publishes a new revision without changing the previous published revision', function () {
    [$draft, $story] = reviewableDraft();
    $editor = User::factory()->create();
    $editor->forceFill(['is_editor' => true])->save();
    $review = app(ReviewStoryDraft::class);

    $review->approve($draft, $editor);
    $original = StoryRevision::sole();
    $oldTitle = $original->title;

    $updatedDraft = StoryDraft::factory()->create([
        'story_id' => $story->id,
        'title' => 'Resumo corrigido',
        'category' => StoryCategory::Brasil,
        'summary_blocks' => [['text' => 'Os dados oficiais foram corrigidos.', 'article_ids' => [$story->articles()->first()->id]]],
    ]);

    $this->actingAs($editor)->post(route('admin.stories.approve', $updatedDraft))
        ->assertRedirect(route('admin.stories.index'));

    expect($original->fresh()->title)->toBe($oldTitle)
        ->and(StoryRevision::orderByDesc('version')->first()->version)->toBe(2)
        ->and($story->fresh()->title)->toBe('Resumo corrigido');
});
