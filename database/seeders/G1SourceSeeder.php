<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class G1SourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::firstOrCreate(['slug' => 'g1'], [
            'name' => 'G1',
            'homepage_url' => 'https://g1.globo.com/',
            'feed_url' => 'https://g1.globo.com/rss/g1/',
            'enabled' => true,
            'fetch_interval_minutes' => 30,
            'config' => [
                'access' => 'public_rss_headlines_only',
                'attribution' => 'G1',
                'policy_url' => 'https://g1.globo.com/institucional/termos-de-uso-do-g1.ghtml',
                'robots_url' => 'https://g1.globo.com/robots.txt',
                'verified_at' => '2026-09-29',
            ],
        ]);
    }
}
