<?php

namespace App\Console\Commands;

use App\Ai\GenerateDailySummary;
use App\Jobs\GenerateDailySummaryJob;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class QueueDailySummary extends Command
{
    protected $signature = 'news:daily-summary {--sync : Generate immediately instead of using the AI queue}';

    protected $description = 'Generate the current two-hour update of the daily news summary';

    public function handle(GenerateDailySummary $generator): int
    {
        $now = CarbonImmutable::now('America/Sao_Paulo');
        $slot = $now->setTime((int) floor($now->hour / 2) * 2, 0);

        if ($this->option('sync')) {
            $generator->handle($slot);
        } else {
            GenerateDailySummaryJob::dispatch($slot->utc()->toIso8601String());
        }

        $this->components->info('Atualização do resumo diário iniciada.');

        return self::SUCCESS;
    }
}
