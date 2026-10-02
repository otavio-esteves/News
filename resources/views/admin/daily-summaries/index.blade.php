<x-layouts.app title="Resumo diário | Revisão editorial">
    <main id="conteudo" class="mx-auto max-w-3xl pt-10">
        <a href="{{ route('admin.stories.index') }}" class="text-sm font-semibold underline underline-offset-4">← Área editorial</a>
        <h1 class="mt-8 text-4xl font-semibold">Resumo diário</h1>
        @if (session('status')) <p role="status" class="mt-5 text-sm">{{ session('status') }}</p> @endif
        <div class="mt-8 space-y-3">
            @forelse ($summaries as $daily)
                <a href="{{ route('admin.daily-summaries.show', $daily) }}" class="block rounded-xl border border-border bg-card p-5 hover:bg-muted">
                    <span class="font-semibold">{{ $daily->date->format('d/m/Y') }}</span>
                    <span class="ml-2 text-sm text-muted-foreground">{{ $daily->draft_blocks ? 'Aguardando revisão' : 'Publicado' }}</span>
                </a>
            @empty
                <p class="text-sm text-muted-foreground">Ainda não há resumo diário para revisão.</p>
            @endforelse
        </div>
        <div class="mt-8">{{ $summaries->links() }}</div>
    </main>
</x-layouts.app>
