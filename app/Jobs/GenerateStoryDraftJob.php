<?php

namespace App\Jobs;

use App\Ai\GenerateStoryDraft;
use App\Models\Story;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateStoryDraftJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public int $uniqueFor = 7200;

    public function __construct(public readonly int $storyId)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->storyId;
    }

    public function backoff(): array
    {
        return [30];
    }

    public function handle(GenerateStoryDraft $writer): void
    {
        if (! config('news.ai.story_summaries_enabled')) {
            return;
        }

        $story = Story::find($this->storyId);

        if ($story === null || $story->draft()->exists()) {
            return;
        }

        try {
            $writer->handle($story);
        } catch (DomainException $exception) {
            Log::warning('news.ai.story_writer_skipped', [
                'story_id' => $this->storyId,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('news.ai.story_writer_failed', [
            'story_id' => $this->storyId,
            'error_type' => $exception::class,
        ]);
    }
}
