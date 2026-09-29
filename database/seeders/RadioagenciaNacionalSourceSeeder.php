<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class RadioagenciaNacionalSourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::firstOrCreate(
            ['slug' => 'radioagencia-nacional'],
            [
                'name' => 'Radioagência Nacional',
                'homepage_url' => 'https://agenciabrasil.ebc.com.br/radioagencia-nacional',
                'feed_url' => 'https://agenciabrasil.ebc.com.br/radioagencia-nacional/rss/ultimasnoticias/feed.xml',
                'enabled' => true,
                'fetch_interval_minutes' => 30,
                'config' => [
                    'access' => 'official_rss',
                    'attribution' => 'Radioagência Nacional e repórter',
                    'policy_url' => 'https://acessoainformacao.ebc.com.br/participacao-social/ouvidoria/relatorios/relatorios-da-ouvidoria/2023-04.pdf',
                    'robots_url' => 'https://agenciabrasil.ebc.com.br/robots.txt',
                    'verified_at' => '2026-09-28',
                ],
            ],
        );
    }
}
