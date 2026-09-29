<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Source;
use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemovePreviewData extends Command
{
    protected $signature = 'news:remove-preview';

    protected $description = 'Remove the fictional local preview while preserving real source articles';

    public function handle(): int
    {
        $sources = Source::where('slug', 'like', 'preview-%')->get()
            ->filter(fn (Source $source): bool => ($source->config['preview'] ?? false) === true);
        $sourceIds = $sources->modelKeys();

        if ($sourceIds === []) {
            $this->components->info('No preview data found.');

            return self::SUCCESS;
        }

        $articleIds = Article::whereIn('source_id', $sourceIds)->pluck('id')->all();
        $storyIds = Story::whereHas('articles', fn ($query) => $query->whereIn('articles.id', $articleIds))
            ->pluck('id')->all();

        $mixedStories = Story::whereIn('id', $storyIds)
            ->whereHas('articles', fn ($query) => $query->whereNotIn('source_id', $sourceIds))
            ->exists();

        if ($mixedStories) {
            $this->components->error('Preview articles are linked to stories with real sources. Cleanup stopped.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($sourceIds, $articleIds, $storyIds): void {
            DB::table('story_revisions')->whereIn('story_id', $storyIds)->delete();
            DB::table('story_drafts')->whereIn('story_id', $storyIds)->delete();
            DB::table('ai_runs')->whereIn('story_id', $storyIds)->orWhereIn('article_id', $articleIds)->delete();
            DB::table('article_story')->whereIn('story_id', $storyIds)->delete();
            DB::table('stories')->whereIn('id', $storyIds)->delete();
            DB::table('articles')->whereIn('id', $articleIds)->delete();
            DB::table('sources')->whereIn('id', $sourceIds)->delete();
        });

        $this->components->info('Removed '.$sources->count().' preview sources, '.count($articleIds).' articles, and '.count($storyIds).' stories.');

        return self::SUCCESS;
    }
}
