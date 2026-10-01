<?php

namespace App\News\Editorial;

use App\Ai\StoryDraftOutputValidator;
use App\Enums\StoryStatus;
use App\Models\Story;
use App\Models\StoryDraft;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReviewStoryDraft
{
    public function __construct(private readonly StoryDraftOutputValidator $validator) {}

    public function approve(StoryDraft $draft, User $editor): Story
    {
        return DB::transaction(function () use ($draft, $editor): Story {
            $story = Story::query()->lockForUpdate()->findOrFail($draft->story_id);
            $draft = StoryDraft::query()->lockForUpdate()->findOrFail($draft->id);

            if ($draft->story_id !== $story->id || $draft->review_status !== 'pending'
                || ! in_array($story->status, [StoryStatus::Draft, StoryStatus::Published], true)) {
                throw new DomainException('Este rascunho já foi revisado ou não pode ser publicado.');
            }

            $articles = $story->articles()->with('source')->get();
            $configuredSources = array_keys(config('news.source_adapters', []));

            if ($articles->isEmpty() || $articles->count() > 10
                || $articles->contains(fn ($article): bool => $article->content === null
                    || ! $article->source->enabled
                    || ! in_array($article->source->slug, $configuredSources, true))) {
                throw new DomainException('As matérias ou fontes desta Story não estão aptas à publicação.');
            }

            $validated = $this->validator->validate($story, [
                'title' => $draft->title,
                'category' => $draft->category?->value,
                'paragraphs' => $draft->summary_blocks,
            ], $articles);

            $referencedIds = collect($validated['summary_blocks'])
                ->flatMap(fn (array $block): array => $block['article_ids'])->unique()->all();
            $snapshots = $articles->whereIn('id', $referencedIds)->mapWithKeys(fn ($article): array => [
                $article->id => [
                    'source' => $article->source->name,
                    'title' => $article->title,
                    'url' => $article->canonical_url,
                    'published_at' => $article->published_at?->toIso8601String(),
                ],
            ])->all();

            $publishedAt = now();
            $story->fill([
                'slug' => $story->slug ?? (Str::slug($validated['title']) ?: 'noticia').'-'.$story->id,
                'title' => $validated['title'],
                'category' => $validated['category'],
                'status' => StoryStatus::Published,
                'summary_blocks' => $validated['summary_blocks'],
                'last_updated_at' => $publishedAt,
                'published_at' => $publishedAt,
            ])->save();

            $story->revisions()->create([
                'version' => ((int) $story->revisions()->max('version')) + 1,
                'title' => $validated['title'],
                'category' => $validated['category'],
                'summary_blocks' => $validated['summary_blocks'],
                'reference_snapshots' => $snapshots,
                'ai_run_id' => $draft->ai_run_id,
                'published_by' => $editor->id,
                'published_at' => $publishedAt,
            ]);

            $draft->delete();

            return $story;
        });
    }

    public function reject(StoryDraft $draft, User $editor, string $reason): void
    {
        DB::transaction(function () use ($draft, $editor, $reason): void {
            $story = Story::query()->lockForUpdate()->findOrFail($draft->story_id);
            $draft = StoryDraft::query()->lockForUpdate()->findOrFail($draft->id);

            if ($draft->story_id !== $story->id || $draft->review_status !== 'pending') {
                throw new DomainException('Este rascunho já foi revisado.');
            }

            $draft->update([
                'review_status' => 'rejected',
                'reviewed_by' => $editor->id,
                'reviewed_at' => now(),
                'rejection_reason' => trim($reason),
            ]);
        });
    }
}
