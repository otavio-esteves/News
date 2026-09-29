<x-layouts.app :title="$story['title'] . ' | NEWS'" :active-category="$story['category']">
    <main id="conteudo" class="mx-auto max-w-[740px] pt-10 sm:pt-14">
        <a href="{{ route('stories.category', $story['category']) }}" class="text-xs font-semibold text-muted-foreground underline underline-offset-4 hover:text-foreground">← Voltar para {{ \App\Enums\StoryCategory::from($story['category'])->label() }}</a>

        <article class="mt-10">
            <div class="mb-4 flex flex-wrap items-center gap-3 text-xs font-bold uppercase tracking-[0.16em]">
                <span class="text-muted-foreground">{{ \App\Enums\StoryCategory::from($story['category'])->label() }}</span>
                <span class="text-faint" aria-hidden="true">•</span>
                <x-timestamp :value="$story['updated_at']" />
            </div>
            <h1 class="text-[clamp(2.4rem,6vw,4rem)] font-semibold leading-[1.06] tracking-[-0.06em]">{{ $story['title'] }}</h1>
            <p class="mt-6 max-w-[680px] text-lg leading-relaxed text-muted-foreground sm:text-xl">{{ $story['summary'] }}</p>

            <div class="mt-8 flex flex-wrap items-center gap-3 border-y border-border py-4 text-xs text-muted-foreground">
                <span class="font-semibold text-foreground">{{ $story['source_count'] }} fontes consultadas</span>
                <span aria-hidden="true">·</span>
                <span>{{ $story['reading_time'] }}</span>
                <span aria-hidden="true">·</span>
                <span>Síntese com referências</span>
            </div>

            <section aria-label="Resumo com referências" class="space-y-9 py-10 sm:space-y-11 sm:py-12">
                @foreach ($story['paragraphs'] as $paragraph)
                    <x-story-paragraph :story="$story" :paragraph="$paragraph" />
                @endforeach
            </section>

            <section id="fontes" class="border-t border-border pt-8">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-muted-foreground">Transparência</p>
                <h2 class="mt-2 text-2xl font-semibold tracking-[-0.04em]">Fontes desta história</h2>
                <p class="mt-3 text-sm leading-relaxed text-muted-foreground">Abra as matérias originais para conferir as informações.</p>
                <ol class="mt-6 space-y-3">
                    @foreach ($story['sources'] as $id => $source)
                        <li id="source-{{ $id }}" class="scroll-mt-8">
                            <x-ui.card class="p-5">
                                <p class="text-xs font-bold uppercase tracking-[0.13em] text-muted-foreground">{{ $source['name'] }}</p>
                                <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="mt-2 block text-sm font-medium leading-relaxed underline decoration-border underline-offset-4 hover:text-foreground">{{ $source['title'] }} ↗</a>
                            </x-ui.card>
                        </li>
                    @endforeach
                </ol>
            </section>
        </article>
    </main>
</x-layouts.app>
