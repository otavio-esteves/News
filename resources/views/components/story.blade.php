@props(['story', 'featured' => false])

<article>
    <x-ui.card class="p-5 sm:p-6">
        <div class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
            <span class="rounded-full bg-muted px-2.5 py-1 font-semibold text-foreground">{{ \App\Enums\StoryCategory::from($story['category'])->label() }}</span>
            <span aria-hidden="true">·</span>
            <x-timestamp :value="$story['updated_at']" />
        </div>
        <h3 class="text-xl font-semibold leading-snug tracking-tight text-foreground sm:text-2xl">
            <a href="{{ route('stories.show', $story['slug']) }}" class="story-title-link">{{ $story['title'] }}</a>
        </h3>
        <p class="mt-3 text-sm leading-6 text-muted-foreground">{{ $story['summary'] }}</p>
        <div class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-muted-foreground">
            <span class="font-semibold text-foreground">{{ $story['source_count'] }} fontes</span>
            <span aria-hidden="true">·</span>
            <span>{{ $story['reading_time'] }}</span>
        </div>
    </x-ui.card>
</article>
