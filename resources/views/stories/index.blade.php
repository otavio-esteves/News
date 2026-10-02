@php
    $categoryName = $activeCategory ? \App\Enums\StoryCategory::from($activeCategory)->label() : null;
    $sourceName = $sourceName ?? null;
    $activeSource = $activeSource ?? null;
    $heading = $sourceName ?? $categoryName ?? 'Últimas notícias';
@endphp

<x-layouts.app :title="$heading . ' | NEWS'" :active-category="$activeCategory" :active-source="$activeSource">
    <main id="conteudo" class="mx-auto max-w-[740px] pb-4">
        <div class="mx-auto max-w-[620px] pb-9 pt-14 text-center sm:pb-11 sm:pt-20">
            <p class="mb-4 text-[11px] font-bold uppercase tracking-[0.2em] text-muted-foreground">{{ $sourceName ? 'Fontes' : ($categoryName ? 'Temas' : 'News / Agora') }}</p>
            <h1 class="text-[clamp(2.4rem,6vw,3.8rem)] font-bold leading-[1.06] tracking-[-0.06em] text-foreground">{{ $heading }}</h1>
            <p class="mx-auto mt-4 max-w-[470px] text-sm leading-6 text-muted-foreground sm:text-base">O que você precisa saber, direto de fontes identificadas.</p>
        </div>

        @if ($dailySummary)
            <x-daily-summary :daily="$dailySummary" />
        @elseif (! $activeCategory && ! $activeSource)
            <section aria-label="Resumo diário" class="mb-12">
                <h2 class="px-1 text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Resumo diário</h2>
                <p class="mt-4 rounded-xl border border-border bg-card p-5 text-sm text-muted-foreground">O resumo de hoje aparecerá aqui após a revisão editorial.</p>
            </section>
        @endif

        <section id="noticias-das-fontes" aria-label="Notícias das fontes" class="scroll-mt-6 space-y-4">
                <div class="flex items-center justify-between gap-4 px-1 pb-1">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Direto das fontes</h2>
                    <span class="text-xs text-muted-foreground">{{ count($articles) }} {{ count($articles) === 1 ? 'notícia' : 'notícias' }}</span>
                </div>
                @forelse ($articles as $article)
                    <x-article-card :article="$article" />
                @empty
                    <x-ui.card class="p-8 text-center">
                        <p class="font-semibold">{{ $activeSource ? 'Ainda não há notícias desta fonte.' : ($activeCategory ? 'Ainda não há notícias nesta editoria.' : 'Nenhuma notícia disponível agora.') }}</p>
                        <p class="mt-2 text-sm text-muted-foreground">{{ $activeSource ? 'As matérias desta fonte aparecerão aqui quando forem coletadas.' : ($activeCategory ? 'As matérias desta editoria aparecerão aqui quando forem coletadas.' : 'A próxima coleta aparecerá aqui.') }}</p>
                        @if ($activeCategory || $activeSource)
                            <a href="{{ route('home') }}" class="mt-5 inline-block text-sm font-semibold underline underline-offset-4 hover:text-muted-foreground">Ver todas as notícias</a>
                        @endif
                    </x-ui.card>
                @endforelse
                @if ($articles->hasMorePages())
                    <div class="pt-4 text-center">
                        <a href="{{ $articles->appends(request()->except('articles_page'))->nextPageUrl() }}#noticias-das-fontes" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-border bg-card px-6 py-3 text-sm font-semibold text-foreground transition-colors hover:border-foreground/40 hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">Ler mais notícias ↓</a>
                    </div>
                @endif
        </section>
    </main>
</x-layouts.app>
