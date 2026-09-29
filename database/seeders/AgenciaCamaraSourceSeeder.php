<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class AgenciaCamaraSourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::firstOrCreate(
            ['slug' => 'agencia-camara'],
            [
                'name' => 'Agência Câmara',
                'homepage_url' => 'https://www.camara.leg.br/noticias/',
                'feed_url' => 'https://www.camara.leg.br/noticias/rss/ultimas-noticias',
                'enabled' => true,
                'fetch_interval_minutes' => 30,
                'config' => [
                    'access' => 'official_rss',
                    'attribution' => 'Agência Câmara',
                    'policy_url' => 'https://www.camara.leg.br/noticias/rss',
                    'robots_url' => 'https://www.camara.leg.br/robots.txt',
                    'verified_at' => '2026-09-28',
                ],
            ],
        );
    }
}
