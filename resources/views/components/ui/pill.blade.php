@props(['href', 'active' => false])

<a href="{{ $href }}" {{ $attributes->class([
    'inline-flex min-h-9 shrink-0 items-center justify-center rounded-full border px-4 py-2 text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
    'border-primary bg-primary text-primary-foreground' => $active,
    'border-border bg-card text-muted-foreground hover:border-foreground/40 hover:bg-muted hover:text-foreground' => ! $active,
]) }} @if($active) aria-current="page" @endif>{{ $slot }}</a>
