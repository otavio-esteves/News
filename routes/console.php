<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('news:discover')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command('news:match-articles')
    ->everyThirtyMinutes()
    ->withoutOverlapping(10);

Schedule::command('news:queue-drafts --limit=1')
    ->everyFiveMinutes()
    ->when(fn (): bool => (bool) config('news.ai.auto_queue'))
    ->withoutOverlapping(10);
