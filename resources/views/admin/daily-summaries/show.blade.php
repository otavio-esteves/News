<x-layouts.app title="Resumo diário | Revisão editorial">
    <main id="conteudo" class="mx-auto max-w-3xl pt-10">
        <a href="{{ route('admin.daily-summaries.index') }}" class="text-sm font-semibold underline underline-offset-4">← Resumos diários</a>
        <h1 class="mt-8 text-4xl font-semibold">Resumo diário · {{ $daily->date->format('d/m/Y') }}</h1>
        <p class="mt-3 text-sm text-muted-foreground">{{ $daily->draft_blocks ? 'Atualização pendente de aprovação.' : 'Versão publicada.' }} Confira as fontes antes de publicar.</p>
        @if ($errors->any()) <p role="alert" class="mt-5 text-sm text-red-500">{{ $errors->first() }}</p> @endif
        <div class="mt-8 space-y-6">
            @foreach ($blocks as $block)
                <section class="rounded-xl border border-border bg-card p-5">
                    <p class="text-sm text-muted-foreground">{{ \Illuminate\Support\Carbon::parse($block['slot_at'])->timezone('America/Sao_Paulo')->format('H\hi') }}</p>
                    <p class="mt-2 leading-relaxed">{{ $block['text'] }}</p>
                    <ul class="mt-4 space-y-1 text-sm">
                        @foreach ($block['article_ids'] as $id)
                            @php($article = $articles->get($id))
                            <li>@if ($article)<a href="{{ $article->canonical_url }}" target="_blank" rel="noopener noreferrer" class="underline underline-offset-4">{{ $article->source->name }} — {{ $article->title }} ↗</a>@else Matéria {{ $id }} indisponível @endif</li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
        @if ($daily->draft_blocks)
            <div class="mt-8 flex gap-4">
                <form method="post" action="{{ route('admin.daily-summaries.approve', $daily) }}">@csrf<button type="submit" class="rounded-lg bg-foreground px-5 py-3 font-semibold text-background">Aprovar e publicar</button></form>
                <form method="post" action="{{ route('admin.daily-summaries.reject', $daily) }}">@csrf<button type="submit" class="rounded-lg border border-border px-5 py-3 font-semibold">Descartar atualização</button></form>
            </div>
        @endif
    </main>
</x-layouts.app>
