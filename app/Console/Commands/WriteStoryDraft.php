<?php

namespace App\Console\Commands;

use App\Ai\GenerateStoryDraft;
use App\Models\Story;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class WriteStoryDraft extends Command
{
    protected $signature = 'news:write-draft {story : Story ID}';

    protected $description = 'Generate an unpublished, validated AI draft for one Story';

    public function handle(GenerateStoryDraft $writer): int
    {
        $story = Story::findOrFail($this->argument('story'));

        try {
            $draft = $writer->handle($story);
        } catch (DomainException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            Log::error('news.ai.story_writer_failed', [
                'story_id' => $story->id,
                'error_type' => $exception::class,
            ]);
            $this->components->error('A geração falhou. Consulte o registro em ai_runs.');

            return self::FAILURE;
        }

        $this->components->info("Rascunho {$draft->id} gerado para a Story {$story->id}; aguarda revisão editorial.");

        return self::SUCCESS;
    }
}
