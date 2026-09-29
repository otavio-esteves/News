@props(['story', 'paragraph'])

<div class="story-paragraph">
    <p class="text-[17px] leading-[1.85] text-foreground sm:text-[19px]">{{ $paragraph['text'] }}</p>
    <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
        <span class="font-semibold uppercase tracking-[0.1em]">Fontes</span>
        <span aria-hidden="true">·</span>
        @foreach ($paragraph['sources'] as $id)
            <x-source-link :story="$story" :source-id="$id" :source="$story['sources'][$id]" />
        @endforeach
    </div>
</div>
