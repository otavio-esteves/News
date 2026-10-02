<?php

use App\Enums\ArticleStatus;
use App\Enums\StoryCategory;
use App\Models\Article;
use App\Models\Source;
use App\Models\Story;
use App\Models\StoryDraft;
use App\Models\StoryRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\PreviewStoriesSeeder;

uses(RefreshDatabase::class);

function publishedStory(string $slug, string $title, StoryCategory $category): array
{
    $source = Source::firstOrCreate(
        ['slug' => 'agencia-brasil'],
        ['name' => 'Agência Brasil', 'homepage_url' => 'https://agenciabrasil.ebc.com.br/', 'enabled' => true],
    );
    $article = Article::factory()->create(['source_id' => $source->id, 'status' => ArticleStatus::Processed]);
    $story = Story::factory()->published()->create([
        'slug' => $slug,
        'title' => $title,
        'category' => $category,
        'summary_blocks' => [['text' => 'Síntese baseada na matéria original.', 'article_ids' => [$article->id]]],
    ]);
    $story->articles()->attach($article->id);
    StoryRevision::factory()->create([
        'story_id' => $story->id,
        'title' => $title,
        'category' => $category,
        'summary_blocks' => $story->summary_blocks,
        'published_by' => User::factory()->create()->id,
        'published_at' => $story->published_at,
    ]);

    return [$story, $article];
}

it('shows extracted real headlines and their original links on the homepage', function () {
    $source = Source::factory()->create(['slug' => 'agencia-brasil', 'name' => 'Agência Brasil', 'enabled' => true]);
    $article = Article::factory()->create([
        'source_id' => $source->id,
        'title' => 'Notícia extraída de fonte real',
        'canonical_url' => 'https://agenciabrasil.ebc.com.br/geral/noticia/2026-09/noticia-real',
        'status' => ArticleStatus::Processed,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Últimas notícias')
        ->assertSee('Notícia extraída de fonte real')
        ->assertSee('Agência Brasil')
        ->assertSee($article->canonical_url)
        ->assertDontSee('fictícios');
});

it('shows only the central daily summary in the news feed', function () {
    [$story, $article] = publishedStory('resumo-aprovado', 'Título da síntese', StoryCategory::Brasil);
    $story->update(['summary_blocks' => [
        ['text' => 'Primeiro parágrafo aprovado.', 'article_ids' => [$article->id]],
        ['text' => 'Segundo parágrafo aprovado.', 'article_ids' => [$article->id]],
    ]]);
    StoryDraft::factory()->create([
        'story_id' => $story->id,
        'title' => 'Título pendente',
        'summary_blocks' => [['text' => 'Resumo pendente e privado.', 'article_ids' => [$article->id]]],
    ]);

    $this->get('/')->assertOk()
        ->assertSee('Resumo diário')
        ->assertSee($article->canonical_url)
        ->assertDontSee('Primeiro parágrafo aprovado.')
        ->assertDontSee('Segundo parágrafo aprovado.')
        ->assertDontSee('Resumo pendente e privado.');
});

it('keeps articles without approved summaries as source headlines only', function () {
    $source = Source::factory()->create(['slug' => 'agencia-brasil', 'enabled' => true]);
    $article = Article::factory()->create(['source_id' => $source->id, 'status' => ArticleStatus::Processed]);
    $story = Story::factory()->create();
    $story->articles()->attach($article);
    StoryDraft::factory()->create([
        'story_id' => $story->id,
        'summary_blocks' => [['text' => 'Rascunho não aprovado.', 'article_ids' => [$article->id]]],
    ]);

    $this->get('/')->assertOk()
        ->assertSee($article->title)
        ->assertSee($article->canonical_url)
        ->assertSee('Resumo diário')
        ->assertDontSee('Rascunho não aprovado.');
});

it('offers more source headlines after the first 30 in each news section', function () {
    $source = Source::factory()->create(['slug' => 'agencia-brasil', 'enabled' => true]);

    for ($index = 1; $index <= 31; $index++) {
        Article::factory()->create([
            'source_id' => $source->id,
            'title' => "Manchete de paginação {$index}",
            'category' => StoryCategory::Brasil,
            'status' => ArticleStatus::Processed,
            'published_at' => now()->subMinutes($index),
        ]);
    }

    foreach (['/', '/brasil', route('stories.source', $source->slug)] as $url) {
        $this->get($url)->assertOk()
            ->assertSee('Manchete de paginação 1')
            ->assertDontSee('Manchete de paginação 31')
            ->assertSee('Ler mais notícias')
            ->assertSee('articles_page=2')
            ->assertSee('#noticias-das-fontes');

        $separator = str_contains($url, '?') ? '&' : '?';
        $this->get($url.$separator.'articles_page=2')->assertOk()
            ->assertDontSee('Manchete de paginação 1')
            ->assertSee('Manchete de paginação 31')
            ->assertDontSee('Ler mais notícias');
    }
});

it('keeps the top category bar and provides a left drawer beside the brand', function () {
    Source::factory()->create(['slug' => 'agencia-camara', 'name' => 'Agência Câmara', 'enabled' => true]);
    Source::factory()->create(['slug' => 'agencia-senado', 'name' => 'Agência Senado', 'enabled' => false]);

    $this->get('/')
        ->assertOk()
        ->assertSee('id="site-menu"', false)
        ->assertSee('id="site-menu-toggle"', false)
        ->assertSee('id="theme-toggle"', false)
        ->assertSee('class="site-menu-dialog', false)
        ->assertSee('nav aria-label="Editorias"', false)
        ->assertSee('nav aria-label="Temas e fontes"', false)
        ->assertDontSee('grid-cols-2')
        ->assertSeeInOrder(['id="site-menu-toggle"', 'id="theme-toggle"', 'NEWS', 'nav aria-label="Editorias"', 'id="site-menu"', 'Temas', 'Fontes', 'Agência Câmara'])
        ->assertDontSee('theme-toggle-label')
        ->assertSee(route('stories.source', 'agencia-camara'))
        ->assertDontSee('Agência Senado');
});

it('filters original articles by source without individual summaries', function () {
    $brasil = Source::factory()->create(['slug' => 'agencia-brasil', 'name' => 'Agência Brasil', 'enabled' => true]);
    $camara = Source::factory()->create(['slug' => 'agencia-camara', 'name' => 'Agência Câmara', 'enabled' => true]);
    $brasilArticle = Article::factory()->create(['source_id' => $brasil->id, 'title' => 'Manchete da Agência Brasil', 'status' => ArticleStatus::Processed]);
    Article::factory()->create(['source_id' => $camara->id, 'title' => 'Manchete da Agência Câmara', 'status' => ArticleStatus::Processed]);
    $story = Story::factory()->published()->create(['title' => 'Síntese da Agência Brasil']);
    $story->articles()->attach($brasilArticle);
    StoryRevision::factory()->create([
        'story_id' => $story->id,
        'title' => $story->title,
        'category' => $story->category,
        'summary_blocks' => $story->summary_blocks,
        'published_by' => User::factory()->create()->id,
        'published_at' => $story->published_at,
    ]);

    $this->get(route('stories.source', 'agencia-brasil'))
        ->assertOk()
        ->assertSee('Manchete da Agência Brasil')
        ->assertDontSee('Síntese da Agência Brasil')
        ->assertDontSee('Manchete da Agência Câmara')
        ->assertSee('aria-current="page"', false);

    $this->get(route('stories.source', 'agencia-camara'))
        ->assertOk()
        ->assertSee('Manchete da Agência Câmara')
        ->assertDontSee('Manchete da Agência Brasil')
        ->assertDontSee('Síntese da Agência Brasil');
});

it('does not expose disabled or unconfigured sources through the source filter', function () {
    Source::factory()->create(['slug' => 'agencia-brasil', 'enabled' => false]);
    Source::factory()->create(['slug' => 'unconfigured-source', 'enabled' => true]);

    $this->get(route('stories.source', 'agencia-brasil'))->assertNotFound();
    $this->get(route('stories.source', 'unconfigured-source'))->assertNotFound();
});

it('uses https for built assets when a trusted proxy forwards the original scheme', function () {
    config()->set('trustedproxy.proxies', ['127.0.0.1']);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders(['Host' => 'news.test', 'X-Forwarded-Proto' => 'https'])
        ->get('/');
    $response->assertOk();
    expect(preg_match('/href="(https?:\/\/[^\"]+\/build\/assets\/[^\"]+\.css)"/', $response->getContent(), $matches))->toBe(1);
    expect(parse_url($matches[1], PHP_URL_SCHEME))->toBe('https');
});

it('filters original articles by editorial category', function () {
    $brasil = Source::factory()->create(['slug' => 'agencia-brasil', 'name' => 'Agência Brasil', 'enabled' => true]);
    $senado = Source::factory()->create(['slug' => 'agencia-senado', 'name' => 'Agência Senado', 'enabled' => true]);
    Article::factory()->create(['source_id' => $brasil->id, 'title' => 'Manchete sobre cultura', 'category' => StoryCategory::Cultura, 'status' => ArticleStatus::Processed]);
    Article::factory()->create(['source_id' => $senado->id, 'title' => 'Manchete sobre política', 'category' => StoryCategory::Politica, 'status' => ArticleStatus::Processed]);
    publishedStory('historia-da-agencia-brasil', 'Síntese da Agência Brasil', StoryCategory::Brasil);

    $this->get('/cultura')
        ->assertOk()
        ->assertSee('Manchete sobre cultura')
        ->assertDontSee('Manchete sobre política')
        ->assertDontSee('Síntese da Agência Brasil')
        ->assertSee('aria-current="page"', false);

    $this->get('/politica')
        ->assertOk()
        ->assertSee('Manchete sobre política')
        ->assertDontSee('Manchete sobre cultura')
        ->assertDontSee('Síntese da Agência Brasil');

    $this->get('/')
        ->assertOk()
        ->assertSee('Manchete sobre cultura')
        ->assertSee('Manchete sobre política');
});

it('keeps individual story summaries out of category feeds', function () {
    publishedStory('historia-economia', 'Economia em destaque', StoryCategory::Economia);
    publishedStory('historia-brasil', 'Brasil em destaque', StoryCategory::Brasil);

    $this->get('/economia')
        ->assertOk()
        ->assertDontSee('Economia em destaque')
        ->assertDontSee('Brasil em destaque');
});

it('shows an empty category state', function () {
    $this->get('/politica')
        ->assertOk()
        ->assertSee('Ainda não há notícias nesta editoria.')
        ->assertSee('Ver todas as notícias');
});

it('shows references and links on a published story page', function () {
    [$story, $article] = publishedStory('historia-real', 'História real', StoryCategory::Brasil);

    $this->get('/n/'.$story->slug)
        ->assertOk()
        ->assertSee('Fontes desta história')
        ->assertSee('Agência Brasil')
        ->assertSee('id="source-'.$article->id.'"', false)
        ->assertSee($article->canonical_url);
});

it('does not expose a draft or replace a published version with its pending draft', function () {
    [$story] = publishedStory('historia-publicada', 'Título aprovado', StoryCategory::Brasil);
    Story::factory()->create();
    StoryDraft::factory()->create(['story_id' => $story->id, 'title' => 'Título ainda não aprovado']);

    $this->get('/n/'.$story->slug)->assertOk()->assertSee('Título aprovado');
    $this->get('/')
        ->assertOk()
        ->assertDontSee('Título aprovado')
        ->assertDontSee('Título ainda não aprovado');
});

it('does not expose a published flag without an editor-approved revision', function () {
    $source = Source::factory()->create(['slug' => 'agencia-brasil', 'enabled' => true]);
    $article = Article::factory()->create(['source_id' => $source->id]);
    $story = Story::factory()->published()->create(['title' => 'Síntese sem aprovação']);
    $story->articles()->attach($article);

    $this->get('/')->assertOk()->assertDontSee($story->title);
    $this->get('/n/'.$story->slug)->assertNotFound();
});

it('does not show fictional preview data even if seeded for tests', function () {
    $this->seed(PreviewStoriesSeeder::class);

    $this->get('/')->assertOk()->assertDontSee('Aurora amplia o horário');
    $this->get('/n/aurora-amplia-horario-das-bibliotecas')->assertNotFound();
});

it('returns 404 for an unknown story', function () {
    $this->get('/n/historia-inexistente')->assertNotFound();
});

it('responds to the health check', function () {
    $this->get('/up')->assertOk();
});
