@props(['daily'])

@php
    $blocks = $daily->published_blocks ?? [];
    $articles = \App\Models\Article::with('source')->whereIn('id', collect($blocks)
        ->flatMap(fn (array $block): array => $block['article_ids'])->unique())->get()->keyBy('id');
@endphp

<section aria-label="Resumo diário" class="mb-12">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 px-1">
        <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Resumo diário</h2>
        <time class="text-xs text-muted-foreground" datetime="{{ $daily->date->toDateString() }}">{{ $daily->date->format('d/m/Y') }} · atualizado {{ $daily->published_at?->timezone('America/Sao_Paulo')->format('H\hi') }}</time>
    </div>
    <x-ui.card class="space-y-6 p-5 sm:p-6">
        @foreach ($blocks as $block)
            <div>
                <p class="text-sm leading-7 text-foreground">{{ $block['text'] }}</p>
                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                    <span>Fontes:</span>
                    @foreach ($block['article_ids'] as $id)
                        @php($article = $articles->get($id))
                        @if ($article)
                            <a href="{{ $article->canonical_url }}" target="_blank" rel="noopener noreferrer" class="underline underline-offset-2">{{ $article->source->name }}: {{ $article->title }} ↗</a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </x-ui.card>
</section>
