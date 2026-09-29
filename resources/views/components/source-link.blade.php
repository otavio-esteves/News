@props(['story', 'sourceId', 'source'])

<a href="{{ route('stories.show', $story['slug']) }}#source-{{ $sourceId }}" class="source-link underline decoration-border underline-offset-4 hover:text-accent hover:decoration-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">{{ $source['name'] }}</a>
