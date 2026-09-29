@props(['article'])

<article>
    <x-ui.card class="p-5 transition-colors hover:border-foreground/40 sm:p-6">
        <div class="mb-4 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <span class="rounded-full bg-muted px-2.5 py-1 font-semibold text-foreground">{{ $article->source->name }}</span>
            <span aria-hidden="true">·</span>
            <time datetime="{{ $article->published_at?->toIso8601String() }}">{{ $article->published_at?->copy()->timezone('America/Sao_Paulo')->format('d/m/Y · H\hi') }}</time>
        </div>
        <h3 class="text-xl font-semibold leading-snug tracking-tight text-foreground sm:text-2xl">
            <a href="{{ $article->canonical_url }}" target="_blank" rel="noopener noreferrer" class="card-link rounded-sm hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring">{{ $article->title }} <span class="sr-only">(abre no site da fonte)</span></a>
        </h3>
        <div class="mt-5 flex items-center justify-between gap-4 text-xs text-muted-foreground">
            <span class="truncate">{{ $article->author ?: 'Redação' }}</span>
            <a href="{{ $article->canonical_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex shrink-0 items-center gap-1 rounded-sm font-semibold text-foreground hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                Ler na fonte
                <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" class="size-3.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"><path d="M4 12 12 4M5 4h7v7" /></svg>
                <span class="sr-only">: {{ $article->title }} (abre no site da fonte)</span>
            </a>
        </div>
    </x-ui.card>
</article>
