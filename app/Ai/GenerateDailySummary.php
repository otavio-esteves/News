<?php

namespace App\Ai;

use App\Ai\Agents\DailySummaryWriter;
use App\Enums\ArticleStatus;
use App\Models\AiRun;
use App\Models\Article;
use App\Models\DailySummary;
use App\Models\Source;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

final class GenerateDailySummary
{
    public function __construct(private readonly DailySummaryOutputValidator $validator) {}

    public function handle(CarbonImmutable $at): ?DailySummary
    {
        $local = $at->setTimezone('America/Sao_Paulo');
        $slot = $local->setTime((int) floor($local->hour / 2) * 2, 0);
        $slotAt = $slot->utc()->toIso8601String();
        $day = $slot->hour === 0 ? $slot->subDay() : $slot;
        $date = $day->toDateString();
        DB::table('daily_summaries')->insertOrIgnore([
            'date' => $date, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $daily = DailySummary::query()->whereDate('date', $date)->firstOrFail();

        if ($daily->draft_slot_at?->gte($slot)) {
            return $daily;
        }

        $previous = $daily->draft_blocks ?? $daily->published_blocks ?? [];
        $firstDate = DailySummary::min('date');
        $sourceIds = Source::where('enabled', true)
            ->whereIn('slug', array_keys(config('news.source_adapters', [])))->pluck('id');
        $articles = Article::query()->whereIn('source_id', $sourceIds)
            ->whereIn('status', [ArticleStatus::Processed, ArticleStatus::Matched])
            ->whereNotNull('content')->whereNotNull('fetched_at')
            ->where('discovered_at', '>=', CarbonImmutable::parse($firstDate, 'America/Sao_Paulo')->startOfDay()->utc())
            ->where('discovered_at', '<', $slot->utc())
            ->whereNotIn('id', DB::table('daily_summary_articles')->select('article_id'))
            ->orderBy('discovered_at')->orderBy('id')->limit(8)->get();

        if ($articles->isEmpty()) {
            return $daily;
        }

        $provider = config('news.ai.story_writer_provider');
        $model = config('news.ai.daily_summary_model');

        if (! in_array($provider, ['ollama', 'openai'], true) || blank($model)
            || ($provider === 'openai' && blank(config('ai.providers.openai.key')))
            || ($provider === 'ollama' && blank(config('ai.providers.ollama.url')))) {
            throw new DomainException('Configure um provedor e modelo de IA suportados para o resumo diário.');
        }

        $prompt = json_encode([
            'date' => $date,
            'previous_summary' => collect($previous)->pluck('text')->take(-2)->all(),
            'new_articles' => $articles->map(fn (Article $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'content' => Str::limit($article->content, 500, ''),
            ])->all(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $run = AiRun::create([
            'type' => 'daily_summary', 'daily_summary_id' => $daily->id,
            'provider' => $provider, 'model' => $model, 'prompt_version' => 'v1',
            'schema_version' => 'v1', 'input_hash' => hash('sha256', $prompt),
            'status' => 'running', 'started_at' => now(),
        ]);

        try {
            $response = DailySummaryWriter::make()->prompt($prompt, provider: $provider, model: $model, timeout: $provider === 'ollama' ? 360 : 60);

            if (! $response instanceof StructuredAgentResponse) {
                throw new DomainException('O modelo não retornou uma resposta estruturada.');
            }

            $blocks = $this->validator->validate($response->toArray(), $articles, $slotAt);

            return DB::transaction(function () use ($daily, $previous, $slot, $blocks, $articles, $run, $response): DailySummary {
                $locked = DailySummary::whereKey($daily->id)->lockForUpdate()->firstOrFail();

                if ($locked->draft_slot_at?->gte($slot)
                    || ($locked->draft_blocks ?? $locked->published_blocks ?? []) !== $previous) {
                    throw new DomainException('O resumo diário mudou durante a geração; tente novamente.');
                }

                DB::table('daily_summary_articles')->insert($articles->map(fn (Article $article): array => [
                    'article_id' => $article->id,
                    'daily_summary_id' => $locked->id,
                ])->all());
                $locked->update(['draft_blocks' => [...$previous, ...$blocks], 'draft_slot_at' => $slot->utc()]);
                $run->update([
                    'status' => 'completed', 'input_tokens' => $response->usage->inputTokens,
                    'output_tokens' => $response->usage->outputTokens, 'finished_at' => now(),
                ]);

                return $locked;
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
