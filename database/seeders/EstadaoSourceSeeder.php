<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class EstadaoSourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::firstOrCreate(['slug' => 'estadao'], [
            'name' => 'Estadão',
            'homepage_url' => 'https://www.estadao.com.br/',
            'feed_url' => 'https://www.estadao.com.br/arc/outboundfeeds/rss/?outputType=xml',
            'enabled' => true,
            'fetch_interval_minutes' => 60,
            'config' => [
                'access' => 'public_rss_headlines_only',
                'attribution' => 'Estadão',
                'policy_url' => 'https://acervo.estadao.com.br/faq/',
                'robots_url' => 'https://www.estadao.com.br/robots.txt',
                'verified_at' => '2026-09-29',
            ],
        ]);
    }
}
