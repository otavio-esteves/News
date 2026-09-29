<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('news:discover')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command('news:match-articles')
    ->everyThirtyMinutes()
    ->withoutOverlapping(10);
