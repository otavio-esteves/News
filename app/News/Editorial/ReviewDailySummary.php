<?php

namespace App\News\Editorial;

use App\Models\Article;
use App\Models\DailySummary;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReviewDailySummary
{
    public function approve(DailySummary $daily, User $editor): void
    {
        DB::transaction(function () use ($daily, $editor): void {
            $daily = DailySummary::whereKey($daily->id)->lockForUpdate()->firstOrFail();
            $blocks = $daily->draft_blocks;

            if ($blocks === null || $blocks === [] || $daily->draft_slot_at === null) {
                throw new DomainException('Não há atualização pendente para aprovar.');
            }

            $ids = collect($blocks)->flatMap(fn (array $block): array => $block['article_ids'])->unique()->all();
            $articles = Article::with('source')->whereIn('id', $ids)->get()->keyBy('id');

            if ($articles->count() !== count($ids) || $articles->contains(fn (Article $article): bool => blank($article->content)
                || ! $article->source->enabled
                || ! array_key_exists($article->source->slug, config('news.source_adapters', [])))) {
                throw new DomainException('As fontes do resumo diário precisam ser revistas.');
            }

            foreach ($blocks as $block) {
                if (! is_string($block['text'] ?? null) || blank($block['text'])
                    || ! is_array($block['article_ids'] ?? null) || $block['article_ids'] === []
                    || array_diff($block['article_ids'], $ids) !== []) {
                    throw new DomainException('O resumo diário contém um bloco inválido.');
                }
            }

            $publishedAt = now();
            $daily->revisions()->create([
                'version' => ((int) $daily->revisions()->max('version')) + 1,
                'blocks' => $blocks,
                'reference_snapshots' => $articles->mapWithKeys(fn (Article $article): array => [
                    $article->id => [
                        'source' => $article->source->name,
                        'title' => $article->title,
                        'url' => $article->canonical_url,
                    ],
                ])->all(),
                'published_by' => $editor->id,
                'published_at' => $publishedAt,
            ]);
            $daily->update([
                'published_blocks' => $blocks,
                'published_at' => $publishedAt,
                'draft_blocks' => null,
            ]);
        });
    }

    public function reject(DailySummary $daily): void
    {
        DB::transaction(function () use ($daily): void {
            DailySummary::whereKey($daily->id)->lockForUpdate()->firstOrFail()
                ->update(['draft_blocks' => null]);
        });
    }
}
