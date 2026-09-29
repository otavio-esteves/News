<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class AgenciaSenadoSourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::firstOrCreate(
            ['slug' => 'agencia-senado'],
            [
                'name' => 'Agência Senado',
                'homepage_url' => 'https://www12.senado.leg.br/noticias/',
                'feed_url' => 'https://www12.senado.leg.br/noticias/rss.xml',
                'enabled' => true,
                'fetch_interval_minutes' => 30,
                'config' => [
                    'access' => 'official_rss_and_article_pages',
                    'attribution' => 'Agência Senado e autor',
                    'policy_url' => 'https://www12.senado.leg.br/assessoria-de-imprensa/noticias/politica-de-uso',
                    'robots_url' => 'https://www12.senado.leg.br/robots.txt',
                    'verified_at' => '2026-09-27',
                ],
            ],
        );
    }
}
