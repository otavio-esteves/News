<?php

namespace App\News\Deduplication;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Source;
use App\News\Categorization\ArticleCategoryClassifier;
use App\News\Matching\ArticleStoryMatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ArticleIngestor
{
    public function __construct(
        private readonly ContentFingerprint $fingerprint,
        private readonly ArticleCategoryClassifier $categories,
        private readonly ArticleStoryMatcher $matcher,
    ) {}

    public function store(
        Source $source,
        string $originalUrl,
        string $canonicalUrl,
        string $title,
        string $content,
        Carbon $publishedAt,
        ?string $author = null,
    ): Article {
        $contentHash = $this->fingerprint->hash($content);

        return DB::transaction(function () use ($source, $originalUrl, $canonicalUrl, $title, $content, $publishedAt, $author, $contentHash): Article {
            // Use the same lock order as ArticleStoryMatcher across source workers.
            Source::whereIn('slug', array_keys(config('news.source_adapters', [])))
                ->orderBy('id')->lockForUpdate()->get();
            Source::whereKey($source->id)->lockForUpdate()->firstOrFail();

            $sameUrl = Article::where('canonical_url', $canonicalUrl)->first();

            if ($sameUrl !== null) {
                return $sameUrl;
            }

            $sameContent = Article::where('source_id', $source->id)
                ->where('content_hash', $contentHash)
                ->first();

            if ($sameContent !== null) {
                return $sameContent;
            }

            $article = Article::firstOrCreate(
                ['canonical_url' => $canonicalUrl],
                [
                    'source_id' => $source->id,
                    'original_url' => $originalUrl,
                    'title' => $title,
                    'author' => $author,
                    'category' => $this->categories->classify($source->slug, $canonicalUrl),
                    'published_at' => $publishedAt,
                    'discovered_at' => now(),
                    'fetched_at' => now(),
                    'content' => $content,
                    'content_hash' => $contentHash,
                    'status' => ArticleStatus::Processed,
                ],
            );

            if ($article->wasRecentlyCreated) {
                $this->matcher->match($article);
            }

            return $article->refresh();
        });
    }

    public function storeHeadline(
        Source $source,
        string $url,
        string $title,
        Carbon $publishedAt,
        ?string $author = null,
    ): Article {
        return DB::transaction(function () use ($source, $url, $title, $publishedAt, $author): Article {
            Source::whereIn('slug', array_keys(config('news.source_adapters', [])))
                ->orderBy('id')->lockForUpdate()->get();
            Source::whereKey($source->id)->lockForUpdate()->firstOrFail();

            return Article::firstOrCreate(
                ['canonical_url' => $url],
                [
                    'source_id' => $source->id,
                    'original_url' => $url,
                    'title' => $title,
                    'author' => $author,
                    'category' => $this->categories->classify($source->slug, $url),
                    'published_at' => $publishedAt,
                    'discovered_at' => now(),
                    'fetched_at' => now(),
                    'content' => null,
                    'content_hash' => null,
                    'status' => ArticleStatus::Headline,
                ],
            );
        });
    }
}
