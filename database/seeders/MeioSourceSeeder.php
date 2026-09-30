<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class MeioSourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::firstOrCreate(['slug' => 'meio'], [
            'name' => 'Meio',
            'homepage_url' => 'https://www.canalmeio.com.br/',
            'feed_url' => 'https://www.canalmeio.com.br/feed/',
            'enabled' => true,
            'fetch_interval_minutes' => 60,
            'config' => [
                'access' => 'public_rss_headlines_only',
                'attribution' => 'Meio',
                'policy_url' => 'https://www.canalmeio.com.br/termos-de-uso-e-politica-de-privacidade-do-meio/',
                'robots_url' => 'https://www.canalmeio.com.br/robots.txt',
                'verified_at' => '2026-09-29',
            ],
        ]);
    }
}
