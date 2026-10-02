<?php

namespace App\Jobs;

use App\Ai\GenerateDailySummary;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDailySummaryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 420;

    public int $uniqueFor = 7200;

    public function __construct(public readonly string $slotAt)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return $this->slotAt;
    }

    public function handle(GenerateDailySummary $generator): void
    {
        $generator->handle(CarbonImmutable::parse($this->slotAt));
    }
}
