<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class AgenciaIbgeSourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::firstOrCreate(
            ['slug' => 'agencia-ibge'],
            [
                'name' => 'Agência de Notícias do IBGE',
                'homepage_url' => 'https://agenciadenoticias.ibge.gov.br/',
                'feed_url' => 'https://agenciadenoticias.ibge.gov.br/agencia-rss',
                'enabled' => true,
                'fetch_interval_minutes' => 60,
                'config' => [
                    'access' => 'official_rss',
                    'attribution' => 'Agência de Notícias do IBGE e autor',
                    'policy_url' => 'https://agenciadenoticias.ibge.gov.br/agencia-sala-de-imprensa/2080-agencia-de-noticias/an-artigos-diversos/9277-licenca.html',
                    'robots_url' => 'https://agenciadenoticias.ibge.gov.br/robots.txt',
                    'verified_at' => '2026-09-28',
                ],
            ],
        );
    }
}
