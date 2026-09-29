<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class AgenciaCnjSourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::firstOrCreate(
            ['slug' => 'agencia-cnj'],
            [
                'name' => 'Agência CNJ',
                'homepage_url' => 'https://www.cnj.jus.br/agencia-cnj/',
                'feed_url' => 'https://www.cnj.jus.br/wp-json/wp/v2/posts?categories=1415&per_page=10&_fields=id,date_gmt,link,title,content,categories',
                'enabled' => true,
                'fetch_interval_minutes' => 30,
                'config' => [
                    'access' => 'official_public_api',
                    'attribution' => 'Agência CNJ de Notícias',
                    'policy_url' => 'https://www.cnj.jus.br/agencia-cnj/',
                    'robots_url' => 'https://www.cnj.jus.br/robots.txt',
                    'verified_at' => '2026-09-28',
                ],
            ],
        );
    }
}
