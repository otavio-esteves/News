<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class AgenciaBrasilSourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::firstOrCreate(
            ['slug' => 'agencia-brasil'],
            [
                'name' => 'Agência Brasil',
                'homepage_url' => 'https://agenciabrasil.ebc.com.br/',
                'feed_url' => 'https://agenciabrasil.ebc.com.br/rss/ultimasnoticias/feed.xml',
                'enabled' => true,
                'fetch_interval_minutes' => 30,
                'config' => [
                    'access' => 'official_rss',
                    'attribution' => 'Agência Brasil',
                    'policy_url' => 'https://agenciabrasil.ebc.com.br/sobre',
                    'robots_url' => 'https://agenciabrasil.ebc.com.br/robots.txt',
                    'verified_at' => '2026-09-27',
                ],
            ],
        );
    }
}
