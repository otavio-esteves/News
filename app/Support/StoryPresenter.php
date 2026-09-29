<?php

namespace App\Support;

use App\Models\Story;
use Illuminate\Support\Str;

final class StoryPresenter
{
    public static function make(Story $story): array
    {
        $story->loadMissing('articles.source');
        $articles = $story->articles->keyBy('id');
        $blocks = $story->summary_blocks ?? [];
        $text = implode(' ', array_column($blocks, 'text'));
        $wordCount = count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return [
            'slug' => $story->slug,
            'category' => $story->category->value,
            'title' => $story->title,
            'updated_at' => ($story->last_updated_at ?? $story->published_at)
                ->copy()->timezone('America/Sao_Paulo')->locale('pt_BR')
                ->translatedFormat('d M Y · H\hi'),
            'reading_time' => max(1, (int) ceil($wordCount / 180)).' min de leitura',
            'summary' => Str::limit($blocks[0]['text'] ?? '', 175),
            'paragraphs' => array_map(fn (array $block): array => [
                'text' => $block['text'],
                'sources' => array_values(array_filter(
                    $block['article_ids'],
                    fn (int $id): bool => $articles->has($id),
                )),
            ], $blocks),
            'sources' => $articles->mapWithKeys(fn ($article): array => [
                $article->id => [
                    'name' => $article->source->name,
                    'title' => $article->title,
                    'url' => $article->canonical_url,
                ],
            ])->all(),
            'source_count' => $articles->pluck('source_id')->unique()->count(),
        ];
    }
}
