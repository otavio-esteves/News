<?php

namespace App\Ai;

use App\Ai\Agents\StoryWriter;
use App\Enums\StoryStatus;
use App\Models\AiRun;
use App\Models\Story;
use App\Models\StoryDraft;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

final class GenerateStoryDraft
{
    public function __construct(private readonly StoryDraftOutputValidator $validator) {}

    public function handle(Story $story): StoryDraft
    {
        $story->load('articles.source');

        if ($story->status !== StoryStatus::Draft || $story->category === null || $story->draft()->exists()) {
            throw new DomainException('A Story precisa estar em rascunho e ainda não ter uma síntese.');
        }

        $articles = $story->articles;

        if ($articles->isEmpty() || $articles->count() > 10) {
            throw new DomainException('A Story precisa ter de um a dez artigos.');
        }

        foreach ($articles as $article) {
            if (! $article->source->enabled || ! array_key_exists($article->source->slug, config('news.source_adapters', []))
                || blank($article->content)) {
                throw new DomainException('Todos os artigos precisam ter texto e uma fonte real habilitada.');
            }
        }

        if (blank(config('ai.providers.openai.key'))) {
            throw new DomainException('Configure OPENAI_API_KEY antes de gerar um rascunho.');
        }

        $model = config('news.ai.story_writer_model');
        $context = $articles->map(fn ($article): array => [
            'id' => $article->id,
            'source' => $article->source->name,
            'title' => $article->title,
            'published_at' => $article->published_at?->toIso8601String(),
            'content' => Str::limit($article->content, 8000, ''),
        ])->all();
        $prompt = json_encode([
            'category' => $story->category->value,
            'articles' => $context,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $run = AiRun::create([
            'type' => 'story_writer',
            'story_id' => $story->id,
            'provider' => 'openai',
            'model' => $model,
            'prompt_version' => 'v1',
            'schema_version' => 'v1',
            'input_hash' => hash('sha256', $prompt),
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $response = StoryWriter::make()->prompt($prompt, provider: 'openai', model: $model);

            if (! $response instanceof StructuredAgentResponse) {
                throw new DomainException('O modelo não retornou uma resposta estruturada.');
            }

            $validated = $this->validator->validate($story, $response->toArray(), $articles->modelKeys());

            return DB::transaction(function () use ($story, $run, $response, $validated): StoryDraft {
                $lockedStory = Story::whereKey($story->id)->lockForUpdate()->firstOrFail();

                if ($lockedStory->status !== StoryStatus::Draft || $lockedStory->category !== $story->category
                    || $lockedStory->draft()->exists()
                    || $lockedStory->articles()->pluck('articles.id')->sort()->values()->all()
                        !== $story->articles->pluck('id')->sort()->values()->all()) {
                    throw new DomainException('A Story mudou durante a geração; revise e tente novamente.');
                }

                $draft = $lockedStory->draft()->create([
                    ...$validated,
                    'ai_run_id' => $run->id,
                    'generated_at' => now(),
                ]);

                $run->update([
                    'status' => 'completed',
                    'input_tokens' => $response->usage->inputTokens,
                    'output_tokens' => $response->usage->outputTokens,
                    'finished_at' => now(),
                ]);

                return $draft;
            });
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error' => $exception instanceof DomainException ? $exception->getMessage() : $exception::class,
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }
}
