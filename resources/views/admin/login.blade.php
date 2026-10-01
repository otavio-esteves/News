<x-layouts.app title="Acesso editorial | NEWS">
    <main id="conteudo" class="mx-auto max-w-md pt-12 sm:pt-20">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-muted-foreground">Área editorial</p>
        <h1 class="mt-3 text-4xl font-semibold tracking-[-0.05em]">Entrar</h1>
        <p class="mt-3 text-sm text-muted-foreground">Acesso reservado a editores cadastrados.</p>

        <form method="post" action="{{ route('admin.login') }}" class="mt-8 space-y-5 rounded-2xl border border-border bg-card p-6">
            @csrf
            @error('email') <p role="alert" class="text-sm text-red-500">{{ $message }}</p> @enderror
            <label class="block text-sm font-medium" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus class="w-full rounded-lg border border-border bg-background px-4 py-3 text-foreground">
            <label class="block text-sm font-medium" for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required class="w-full rounded-lg border border-border bg-background px-4 py-3 text-foreground">
            <button type="submit" class="w-full rounded-lg bg-foreground px-4 py-3 font-semibold text-background">Entrar</button>
        </form>
    </main>
</x-layouts.app>
