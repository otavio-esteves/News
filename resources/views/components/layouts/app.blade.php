@props(['title' => 'NEWS', 'activeCategory' => null, 'activeSource' => null])

@php
    $menuSources = \App\Models\Source::query()
        ->whereIn('slug', array_keys(config('news.source_adapters', [])))
        ->where('enabled', true)
        ->orderBy('name')
        ->get();
@endphp

<!doctype html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="dark light">
        <title>{{ $title }}</title>
        <meta name="description" content="News: notícias de fontes identificadas, com acesso direto às matérias originais.">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <script>
            let savedTheme = null;
            try {
                savedTheme = localStorage.getItem('news-theme');
            } catch (_) {}
            document.documentElement.dataset.theme = savedTheme === 'light' || savedTheme === 'dark'
                ? savedTheme
                : (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-background text-foreground antialiased">
        <a href="#conteudo" class="skip-link">Ir para o conteúdo</a>
        <div class="mx-auto min-h-screen max-w-[1440px] px-5 sm:px-8 lg:px-12">
            <header class="grid grid-cols-[1fr_auto] items-center gap-x-4 gap-y-5 py-6 lg:grid-cols-[1fr_auto_1fr] lg:py-7">
                <div class="-ml-2 flex items-center gap-1.5 justify-self-start sm:-ml-4 lg:-ml-8">
                    <button id="site-menu-toggle" type="button" aria-label="Abrir menu de temas e fontes" aria-controls="site-menu" aria-expanded="false" class="flex h-10 w-10 items-center justify-center rounded-full border border-border bg-card text-foreground transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                            <path d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    </button>
                    <button id="theme-toggle" type="button" aria-label="Ativar tema claro" aria-pressed="true" class="flex h-10 w-10 items-center justify-center rounded-full border border-border bg-card text-foreground transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                        <svg class="theme-icon-moon h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z" />
                        </svg>
                        <svg class="theme-icon-sun h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                            <circle cx="12" cy="12" r="4" />
                            <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" />
                        </svg>
                    </button>
                    <a href="{{ route('home') }}" class="brand-mark ml-2 text-2xl font-extrabold tracking-[-0.075em] text-foreground sm:text-[27px]" aria-label="News, voltar ao início">NEWS<span class="text-muted-foreground">.</span></a>
                </div>

                <x-category-nav :active-category="$activeCategory" />

            </header>

            <dialog id="site-menu" aria-label="Menu de temas e fontes" class="site-menu-dialog bg-card text-foreground">
                <div class="flex h-full flex-col">
                    <div class="flex items-center justify-between border-b border-border px-5 py-6 sm:px-7">
                        <span class="text-2xl font-extrabold tracking-[-0.075em]">NEWS<span class="text-muted-foreground">.</span></span>
                        <button id="site-menu-close" type="button" aria-label="Fechar menu" class="flex h-10 w-10 items-center justify-center rounded-full border border-border text-foreground transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path d="M5 5l14 14M19 5L5 19" />
                            </svg>
                        </button>
                    </div>
                    <nav aria-label="Temas e fontes" class="min-h-0 flex-1 overflow-y-auto px-5 py-6 sm:px-7">
                        <div class="border-b border-border pb-6">
                            <h2 class="px-2 text-[11px] font-bold uppercase tracking-[0.18em] text-muted-foreground">Temas</h2>
                            <div class="mt-3 grid gap-1">
                                <a href="{{ route('home') }}" class="menu-link" @if($activeCategory === null && $activeSource === null) aria-current="page" @endif>Todos</a>
                                @foreach (\App\Enums\StoryCategory::cases() as $category)
                                    <a href="{{ route('stories.category', $category->value) }}" class="menu-link" @if($activeCategory === $category->value) aria-current="page" @endif>{{ $category->label() }}</a>
                                @endforeach
                            </div>
                        </div>
                        <div class="pt-6">
                            <h2 class="px-2 text-[11px] font-bold uppercase tracking-[0.18em] text-muted-foreground">Fontes</h2>
                            <div class="mt-3 grid gap-1">
                                @foreach ($menuSources as $source)
                                    <a href="{{ route('stories.source', $source->slug) }}" class="menu-link" @if($activeSource === $source->slug) aria-current="page" @endif>{{ $source->name }}</a>
                                @endforeach
                            </div>
                        </div>
                    </nav>
                </div>
            </dialog>

            {{ $slot }}

            <footer class="mx-auto mt-20 flex max-w-[740px] flex-col gap-3 border-t border-border py-8 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <span class="font-bold tracking-[-0.06em] text-foreground">NEWS.</span>
                <span>Notícias reais. Fontes à vista.</span>
            </footer>
        </div>
    </body>
</html>
