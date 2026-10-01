<?php

namespace App\Console\Commands;

use App\Enums\StoryStatus;
use App\Jobs\GenerateStoryDraftJob;
use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class QueueStoryDrafts extends Command
{
    protected $signature = 'news:queue-drafts {--limit=1 : Maximum number of Stories to enqueue}';

    protected $description = 'Queue unpublished Story summaries for editorial review';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1 || $limit > 20) {
            $this->components->error('O limite deve ser um número entre 1 e 20.');

            return self::FAILURE;
        }

        $provider = config('news.ai.story_writer_provider');
        $configuredSources = array_keys(config('news.source_adapters', []));

        $stories = Story::query()
            ->where('status', StoryStatus::Draft)
            ->whereNotNull('category')
            ->whereDoesntHave('draft')
            ->whereHas('articles')
            ->whereHas('articles', fn (Builder $query): Builder => $query, '<=', 10)
            ->whereDoesntHave('articles', fn (Builder $query): Builder => $query->whereNull('content'))
            ->whereDoesntHave('articles.source', fn (Builder $query): Builder => $query
                ->where('enabled', false)->orWhereNotIn('slug', $configuredSources))
            ->whereDoesntHave('aiRuns', fn (Builder $query): Builder => $query
                ->where('type', 'story_writer')->where('provider', $provider))
            ->orderByDesc('last_updated_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($stories as $storyId) {
            GenerateStoryDraftJob::dispatch($storyId);
        }

        $this->components->info("Enfileiradas: {$stories->count()} Stories para revisão editorial.");

        return self::SUCCESS;
    }
}
