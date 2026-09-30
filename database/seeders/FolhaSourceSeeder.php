<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class FolhaSourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::firstOrCreate(['slug' => 'folha'], [
            'name' => 'Folha de S.Paulo',
            'homepage_url' => 'https://www1.folha.uol.com.br/',
            'feed_url' => 'https://feeds.folha.uol.com.br/emcimadahora/rss091.xml',
            'enabled' => true,
            'fetch_interval_minutes' => 30,
            'config' => [
                'access' => 'official_rss_headlines_only',
                'attribution' => 'Folha de S.Paulo',
                'policy_url' => 'https://www1.folha.uol.com.br/feed/',
                'robots_url' => 'https://feeds.folha.uol.com.br/robots.txt',
                'verified_at' => '2026-09-29',
            ],
        ]);
    }
}
