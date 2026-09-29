<?php

use App\Enums\StoryCategory;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\Story;
use App\Models\StoryRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\PreviewStoriesSeeder;

uses(RefreshDatabase::class);

it('stores published summaries and their article references as structured data', function () {
    $this->seed(PreviewStoriesSeeder::class);

    $story = Story::where('slug', 'aurora-amplia-horario-das-bibliotecas')->firstOrFail();
    $revision = $story->revisions()->firstOrFail();
    $firstArticleId = $story->summary_blocks[0]['article_ids'][0];

    expect($story->status)->toBe(StoryStatus::Published)
        ->and($story->category)->toBe(StoryCategory::Brasil)
        ->and($story->articles->modelKeys())->toContain($firstArticleId)
        ->and($revision->summary_blocks)->toBe($story->summary_blocks)
        ->and($revision->reference_snapshots)->toHaveKey((string) $firstArticleId)
        ->and(Article::findOrFail($firstArticleId)->source->name)->toBe('Boletim de Aurora');
});

it('seeds the local preview without duplicating stories or revisions', function () {
    $this->seed(PreviewStoriesSeeder::class);
    $this->seed(PreviewStoriesSeeder::class);

    expect(Story::count())->toBe(4)
        ->and(StoryRevision::count())->toBe(4)
        ->and(Article::count())->toBe(8);
});

it('keeps published revisions immutable', function () {
    $revision = StoryRevision::factory()->create();

    expect(fn () => $revision->update(['title' => 'Changed']))
        ->toThrow(LogicException::class);

    expect(fn () => $revision->delete())
        ->toThrow(LogicException::class);
});
