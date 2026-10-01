<x-layouts.app title="Revisão editorial | NEWS">
    <main id="conteudo" class="mx-auto max-w-5xl pt-10">
        <div class="flex flex-wrap items-start justify-between gap-5">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-muted-foreground">Área editorial</p>
                <h1 class="mt-2 text-4xl font-semibold tracking-[-0.05em]">Resumos para revisão</h1>
            </div>
            <form method="post" action="{{ route('admin.logout') }}">@csrf<button type="submit" class="rounded-lg border border-border px-4 py-2 text-sm">Sair</button></form>
        </div>

        @if (session('status')) <p role="status" class="mt-7 rounded-lg border border-border bg-card p-4 text-sm">{{ session('status') }}</p> @endif

        <nav aria-label="Estado dos resumos" class="mt-8 flex gap-4 border-b border-border pb-4 text-sm font-semibold">
            <a href="{{ route('admin.stories.index') }}" @if($status === 'pending') aria-current="page" @endif class="underline-offset-4 hover:underline">Pendentes</a>
            <a href="{{ route('admin.stories.index', ['status' => 'rejected']) }}" @if($status === 'rejected') aria-current="page" @endif class="underline-offset-4 hover:underline">Rejeitados</a>
        </nav>

        <div class="mt-5 space-y-3">
            @forelse ($drafts as $draft)
                <a href="{{ route('admin.stories.show', $draft) }}" class="block rounded-xl border border-border bg-card p-5 hover:bg-muted">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted-foreground">{{ $draft->category?->label() }} · {{ $draft->generated_at?->format('d/m/Y H:i') }} · {{ $draft->story->articles->count() }} matérias</p>
                    <h2 class="mt-2 text-xl font-semibold">{{ $draft->title }}</h2>
                    <p class="mt-2 text-sm text-muted-foreground">{{ \Illuminate\Support\Str::limit($draft->summary_blocks[0]['text'] ?? '', 180) }}</p>
                </a>
            @empty
                <p class="py-12 text-center text-sm text-muted-foreground">Nenhum resumo {{ $status === 'pending' ? 'pendente' : 'rejeitado' }}.</p>
            @endforelse
        </div>
        <div class="mt-8">{{ $drafts->links() }}</div>
    </main>
</x-layouts.app>
