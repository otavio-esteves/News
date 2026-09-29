<?php

namespace App\Console\Commands;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\News\Matching\ArticleStoryMatcher;
use App\News\Matching\StoryMatchOutcome;
use Illuminate\Console\Command;

class MatchArticles extends Command
{
    protected $signature = 'news:match-articles';

    protected $description = 'Group processed articles into unpublished draft stories';

    public function handle(ArticleStoryMatcher $matcher): int
    {
        $counts = array_fill_keys(array_column(StoryMatchOutcome::cases(), 'value'), 0);
        $inspected = 0;

        Article::where('status', ArticleStatus::Processed)
            ->whereNotNull('category')
            ->whereDoesntHave('stories')
            ->whereHas('source', fn ($source) => $source->where('enabled', true)
                ->whereIn('slug', array_keys(config('news.source_adapters', []))))
            ->orderBy('id')
            ->chunkById(100, function ($articles) use ($matcher, &$counts, &$inspected): void {
                foreach ($articles as $article) {
                    $outcome = $matcher->match($article);
                    $counts[$outcome->value]++;
                    $inspected++;
                }
            });

        $this->components->info("Inspected: {$inspected}; created: {$counts['created']}; matched: {$counts['matched']}; pending: {$counts['pending']}; skipped: {$counts['skipped']}.");

        return self::SUCCESS;
    }
}
