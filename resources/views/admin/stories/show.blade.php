<x-layouts.app :title="$draft->title . ' | Revisão editorial'">
    <main id="conteudo" class="mx-auto max-w-3xl pt-10">
        <a href="{{ route('admin.stories.index') }}" class="text-sm font-semibold underline underline-offset-4">← Voltar aos resumos</a>
        <p class="mt-8 text-xs font-bold uppercase tracking-[0.18em] text-muted-foreground">{{ $draft->category?->label() }} · {{ $draft->review_status === 'pending' ? 'Pendente' : 'Rejeitado' }}</p>
        <h1 class="mt-3 text-4xl font-semibold leading-tight tracking-[-0.05em]">{{ $draft->title }}</h1>
        <p class="mt-3 text-sm text-muted-foreground">Gerado em {{ $draft->generated_at?->format('d/m/Y H:i') }}. Confira cada afirmação nas matérias originais antes de aprovar.</p>

        @if ($errors->any()) <div role="alert" class="mt-6 rounded-lg border border-red-500 p-4 text-sm text-red-500">{{ $errors->first() }}</div> @endif

        <section aria-label="Resumo gerado" class="mt-9 space-y-6">
            @foreach ($draft->summary_blocks ?? [] as $block)
                <div class="rounded-xl border border-border bg-card p-5">
                    <p class="text-lg leading-relaxed">{{ $block['text'] ?? '' }}</p>
                    <p class="mt-4 text-xs font-bold uppercase tracking-[0.12em] text-muted-foreground">Referências deste parágrafo</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($block['article_ids'] ?? [] as $articleId)
                            @php($article = $draft->story->articles->firstWhere('id', $articleId))
                            <li>@if ($article)<a href="{{ $article->canonical_url }}" target="_blank" rel="noopener noreferrer" class="underline underline-offset-4">{{ $article->source->name }} — {{ $article->title }} ↗</a>@else <span class="text-red-500">Matéria {{ $articleId }} indisponível</span>@endif</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </section>

        <section aria-labelledby="fontes-titulo" class="mt-10 border-t border-border pt-8">
            <h2 id="fontes-titulo" class="text-2xl font-semibold">Matérias associadas</h2>
            <ul class="mt-4 space-y-3 text-sm">
                @foreach ($draft->story->articles as $article)
                    <li><a href="{{ $article->canonical_url }}" target="_blank" rel="noopener noreferrer" class="underline underline-offset-4">{{ $article->source->name }} — {{ $article->title }} ↗</a></li>
                @endforeach
            </ul>
        </section>

        @if ($draft->review_status === 'pending')
            <section aria-label="Decisão editorial" class="mt-10 border-t border-border pt-8">
                <form method="post" action="{{ route('admin.stories.approve', $draft) }}">@csrf<button type="submit" class="rounded-lg bg-foreground px-5 py-3 font-semibold text-background">Aprovar e publicar</button></form>
                <form method="post" action="{{ route('admin.stories.reject', $draft) }}" class="mt-8 space-y-3">
                    @csrf
                    <label for="reason" class="block text-sm font-semibold">Motivo da rejeição</label>
                    <textarea id="reason" name="reason" rows="3" maxlength="2000" required class="w-full rounded-lg border border-border bg-card p-3 text-foreground">{{ old('reason') }}</textarea>
                    <button type="submit" class="rounded-lg border border-border px-5 py-3 font-semibold">Rejeitar resumo</button>
                </form>
            </section>
        @else
            <section class="mt-10 rounded-xl border border-border bg-card p-5 text-sm">
                <p class="font-semibold">Rejeitado por {{ $draft->reviewer?->name }} em {{ $draft->reviewed_at?->format('d/m/Y H:i') }}</p>
                <p class="mt-2">{{ $draft->rejection_reason }}</p>
            </section>
        @endif
    </main>
</x-layouts.app>
