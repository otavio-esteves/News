@props(['activeCategory' => null])

<nav aria-label="Editorias" class="col-span-2 row-start-2 -mx-1 flex items-center gap-2 overflow-x-auto px-1 pb-1 lg:col-span-1 lg:col-start-2 lg:row-start-1 lg:justify-center lg:pb-0">
    <x-ui.pill :href="route('home')" :active="$activeCategory === null">Todas</x-ui.pill>
    @foreach (\App\Enums\StoryCategory::cases() as $category)
        <x-ui.pill :href="route('stories.category', $category->value)" :active="$activeCategory === $category->value">{{ $category->label() }}</x-ui.pill>
    @endforeach
</nav>
