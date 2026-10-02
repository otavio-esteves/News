<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('news:discover')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command('news:daily-summary')
    ->cron('0 */2 * * *')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping(10);
