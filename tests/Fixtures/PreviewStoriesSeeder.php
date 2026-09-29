<?php

namespace Tests\Fixtures;

use App\Enums\ArticleStatus;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\Source;
use App\Models\Story;
use App\Models\StoryRevision;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PreviewStoriesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (PreviewStories::all() as $preview) {
                $publishedAt = Carbon::parse($preview['published_at']);
                $articles = [];

                foreach ($preview['sources'] as $key => $details) {
                    $source = Source::firstOrCreate(
                        ['slug' => "preview-{$key}"],
                        [
                            'name' => $details['name'],
                            'homepage_url' => 'https://example.org/',
                            'enabled' => false,
                            'config' => ['preview' => true],
                        ],
                    );

                    $url = "https://example.org/news/{$preview['slug']}/{$key}";
                    $content = implode("\n\n", array_column(array_filter(
                        $preview['paragraphs'],
                        fn (array $paragraph): bool => in_array($key, $paragraph['sources'], true),
                    ), 'text'));

                    $articles[$key] = Article::firstOrCreate(
                        ['canonical_url' => $url],
                        [
                            'source_id' => $source->id,
                            'original_url' => $url,
                            'title' => $details['title'],
                            'published_at' => $publishedAt,
                            'discovered_at' => $publishedAt,
                            'fetched_at' => $publishedAt,
                            'content' => $content,
                            'content_hash' => hash('sha256', $content),
                            'status' => ArticleStatus::Processed,
                        ],
                    );
                }

                $blocks = array_map(fn (array $paragraph): array => [
                    'text' => $paragraph['text'],
                    'article_ids' => array_map(
                        fn (string $key): int => $articles[$key]->id,
                        $paragraph['sources'],
                    ),
                ], $preview['paragraphs']);

                $story = Story::firstOrCreate(
                    ['slug' => $preview['slug']],
                    [
                        'title' => $preview['title'],
                        'category' => $preview['category'],
                        'status' => StoryStatus::Published,
                        'summary_blocks' => $blocks,
                        'first_seen_at' => $publishedAt,
                        'last_updated_at' => $publishedAt,
                        'published_at' => $publishedAt,
                    ],
                );

                $story->articles()->syncWithoutDetaching(array_map(
                    fn (Article $article): int => $article->id,
                    array_values($articles),
                ));

                $snapshots = [];

                foreach ($articles as $key => $article) {
                    $snapshots[$article->id] = [
                        'source' => $preview['sources'][$key]['name'],
                        'title' => $article->title,
                        'url' => $article->canonical_url,
                        'published_at' => $publishedAt->toIso8601String(),
                    ];
                }

                StoryRevision::firstOrCreate(
                    ['story_id' => $story->id, 'version' => 1],
                    [
                        'title' => $preview['title'],
                        'category' => $preview['category'],
                        'summary_blocks' => $blocks,
                        'reference_snapshots' => $snapshots,
                        'published_at' => $publishedAt,
                    ],
                );
            }
        });
    }
}
