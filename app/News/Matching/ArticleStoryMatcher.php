<?php

namespace App\News\Matching;

use App\Enums\ArticleStatus;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\Source;
use App\Models\Story;
use Illuminate\Support\Facades\DB;

final class ArticleStoryMatcher
{
    private const WINDOW_HOURS = 36;

    private const MIN_CANDIDATE_SCORE = 0.45;

    private const AUTO_MATCH_SCORE = 0.95;

    public function __construct(private readonly TitleSimilarity $titles) {}

    public function match(Article $article): StoryMatchOutcome
    {
        return DB::transaction(function () use ($article): StoryMatchOutcome {
            Source::whereIn('slug', array_keys(config('news.source_adapters', [])))
                ->orderBy('id')->lockForUpdate()->get();
            $article = Article::with('source')->lockForUpdate()->findOrFail($article->id);

            if ($article->stories()->exists()) {
                return StoryMatchOutcome::AlreadyMatched;
            }

            if ($article->status !== ArticleStatus::Processed || $article->category === null || $article->published_at === null
                || ! $article->source->enabled || ! array_key_exists($article->source->slug, config('news.source_adapters', []))) {
                return StoryMatchOutcome::Skipped;
            }

            $candidates = $this->candidates($article);

            if ($candidates !== []) {
                $best = $candidates[0];
                $runnerUp = $candidates[1] ?? null;

                if ($best->score < self::AUTO_MATCH_SCORE
                    || ($runnerUp !== null && $best->score - $runnerUp->score < 0.15)) {
                    return StoryMatchOutcome::Pending;
                }

                $story = $best->story;
                $story->update([
                    'first_seen_at' => $story->first_seen_at->min($article->published_at),
                    'last_updated_at' => $story->last_updated_at?->max($article->published_at) ?? $article->published_at,
                ]);
                $story->articles()->syncWithoutDetaching([$article->id]);
                $article->update(['status' => ArticleStatus::Matched]);

                return StoryMatchOutcome::Matched;
            }

            $story = Story::create([
                'category' => $article->category,
                'status' => StoryStatus::Draft,
                'first_seen_at' => $article->published_at,
                'last_updated_at' => $article->published_at,
            ]);
            $story->articles()->attach($article->id);
            $article->update(['status' => ArticleStatus::Matched]);

            return StoryMatchOutcome::Created;
        });
    }

    /** @return list<StoryCandidate> */
    public function candidates(Article $article): array
    {
        if ($article->category === null || $article->published_at === null) {
            return [];
        }

        $from = $article->published_at->copy()->subHours(self::WINDOW_HOURS);
        $until = $article->published_at->copy()->addHours(self::WINDOW_HOURS);
        $configuredSources = array_keys(config('news.source_adapters', []));
        $candidates = [];

        $stories = Story::where('status', StoryStatus::Draft)
            ->where('category', $article->category)
            ->whereBetween('first_seen_at', [$from, $until])
            ->whereHas('articles.source', fn ($source) => $source->whereIn('slug', $configuredSources)->where('enabled', true))
            ->whereDoesntHave('articles.source', fn ($source) => $source->whereNotIn('slug', $configuredSources))
            ->with('articles:id,title,source_id')
            ->orderBy('id');

        $stories->chunkById(100, function ($batch) use ($article, &$candidates): void {
            foreach ($batch as $story) {
                $score = $story->articles->max(fn (Article $existing): float => $this->titles->score($article->title, $existing->title)) ?? 0.0;

                if ($score >= self::MIN_CANDIDATE_SCORE) {
                    $candidates[] = new StoryCandidate($story, $score);
                }
            }
        });

        usort($candidates, fn (StoryCandidate $a, StoryCandidate $b): int => $b->score <=> $a->score);

        return $candidates;
    }
}
